<?php
// Настройки сессии для корректной работы через HTTPS-прокси
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => $isHttps ? 'None' : 'Lax',
    ]);
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/functions.php';

$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function normalizeItemsList($items) {
    if (!is_array($items)) return [];
    $normalized = [];
    foreach ($items as $item) {
        if (is_array($item)) {
            $item = implode(' | ', array_map('strval', $item));
        }
        $item = trim((string)$item);
        if ($item !== '') $normalized[] = $item;
    }
    return array_values($normalized);
}

function normalizeFeaturesList($features) {
    if (!is_array($features)) return [];
    $normalized = [];
    foreach ($features as $feature) {
        $feature = trim((string)$feature);
        if ($feature !== '') $normalized[] = sanitize($feature);
    }
    return array_values($normalized);
}

function buildProductPayload($input, $existingProduct = null) {
    $items = array_key_exists('items', $input)
        ? normalizeItemsList($input['items'])
        : ($existingProduct['items'] ?? []);

    $isDemo = array_key_exists('is_demo', $input)
        ? (bool)$input['is_demo']
        : (bool)($existingProduct['is_demo'] ?? false);

    $manualQuantity = array_key_exists('quantity', $input)
        ? max(0, (int)$input['quantity'])
        : max(0, (int)($existingProduct['quantity'] ?? 0));

    $calculatedQuantity = (!empty($items) && !$isDemo)
        ? count($items)
        : $manualQuantity;

    return [
        'name' => sanitize($input['name'] ?? ($existingProduct['name'] ?? '')),
        'slug' => sanitize($input['slug'] ?? ($existingProduct['slug'] ?? '')),
        'category' => strtolower(sanitize($input['category'] ?? ($existingProduct['category'] ?? ''))),
        'subcategory' => strtolower(sanitize($input['subcategory'] ?? ($existingProduct['subcategory'] ?? ''))),
        'short_description' => sanitize($input['short_description'] ?? ($existingProduct['short_description'] ?? '')),
        'full_description' => $input['full_description'] ?? ($existingProduct['full_description'] ?? ''),
        'price' => max(0, (float)($input['price'] ?? ($existingProduct['price'] ?? 0))),
        'quantity' => $calculatedQuantity,
        'icon' => sanitize($input['icon'] ?? ($existingProduct['icon'] ?? 'default.svg')),
        'status' => in_array(($input['status'] ?? ($existingProduct['status'] ?? 'active')), ['active', 'inactive'], true)
            ? ($input['status'] ?? ($existingProduct['status'] ?? 'active'))
            : 'active',
        'cookies' => (bool)($input['cookies'] ?? ($existingProduct['cookies'] ?? false)),
        'proxy' => (bool)($input['proxy'] ?? ($existingProduct['proxy'] ?? false)),
        'email_verified' => (bool)($input['email_verified'] ?? ($existingProduct['email_verified'] ?? false)),
        'country' => sanitize($input['country'] ?? ($existingProduct['country'] ?? 'Any')),
        'sex' => sanitize($input['sex'] ?? ($existingProduct['sex'] ?? 'any')),
        'age' => max(0, (int)($input['age'] ?? ($existingProduct['age'] ?? date('Y')))),
        'popular' => (bool)($input['popular'] ?? ($existingProduct['popular'] ?? false)),
        'features' => array_key_exists('features', $input)
            ? normalizeFeaturesList($input['features'])
            : normalizeFeaturesList($existingProduct['features'] ?? []),
        'items' => $items,
        'is_demo' => $isDemo
    ];
}

function getNextEntityId($items) {
    $ids = array_column($items, 'id');
    return ($ids ? max($ids) : 0) + 1;
}

function syncProductsInventory(&$products) {
    foreach ($products as &$product) {
        $product['items'] = normalizeItemsList($product['items'] ?? []);
        $product['is_demo'] = (bool)($product['is_demo'] ?? false);
        $product['quantity'] = (!empty($product['items']) && !$product['is_demo'])
            ? count($product['items'])
            : max(0, (int)($product['quantity'] ?? 0));
        $product['features'] = normalizeFeaturesList($product['features'] ?? []);
        $product['age'] = max(0, (int)($product['age'] ?? date('Y')));
    }
    unset($product);
}

function appendPaymentLog($event, $payload = []) {
    $paymentsFile = __DIR__ . '/../data/payments.json';
    $logs = file_exists($paymentsFile) ? (json_decode(file_get_contents($paymentsFile), true) ?? []) : [];
    $logs[] = [
        'time' => date('Y-m-d H:i:s'),
        'event' => $event,
        'payload' => $payload
    ];
    file_put_contents($paymentsFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function createOrderNumber() {
    $settings = getSettings();
    $siteName = trim($settings['site']['name'] ?? 'shop');
    $prefix = preg_replace('/[^a-z0-9]+/i', '-', $siteName);
    $prefix = trim($prefix, '-');
    if ($prefix === '') {
        $prefix = 'shop';
    }
    return strtoupper($prefix) . '-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function buildAbsoluteUrl($path) {
    // Всегда используем текущий HTTP_HOST для правильной работы на любом домене.
    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
    } else {
        // Фоллбэк для CLI/cron
        $settings = getSettings();
        $baseUrl = rtrim($settings['site']['url'] ?? '', '/');
    }
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

function getOrders() {
    $orders = loadData('orders');
    return is_array($orders) ? $orders : [];
}

function saveOrders($orders) {
    return saveData('orders', array_values($orders));
}

function findOrderIndex($orders, $identifier) {
    foreach ($orders as $index => $order) {
        if (($order['order_number'] ?? '') === $identifier || ($order['label'] ?? '') === $identifier) {
            return $index;
        }
    }
    return -1;
}

function buildPublicPaymentConfig() {
    $payment = getPaymentSettings();
    if (!isset($payment['yoomoney']) || !is_array($payment['yoomoney'])) {
        $payment['yoomoney'] = [];
    }
    unset($payment['yoomoney']['notification_secret'], $payment['yoomoney']['client_secret']);
    $payment['enabled_methods'] = getEnabledPaymentMethods();
    $payment['webhook_url'] = buildAbsoluteUrl('api/?path=payments/yoomoney/webhook');
    $payment['success_url'] = $payment['yoomoney']['success_url'] ?? buildAbsoluteUrl('oplata/?status=success');
    $payment['fail_url'] = $payment['yoomoney']['fail_url'] ?? buildAbsoluteUrl('oplata/?status=fail');
    return $payment;
}

function mergePaymentSettings($existing, $incoming) {
    $payment = is_array($existing) ? $existing : [];
    $payment['methods'] = $payment['methods'] ?? [];
    $payment['yoomoney'] = $payment['yoomoney'] ?? [];
    $payment['crypto'] = $payment['crypto'] ?? [];

    if (isset($incoming['methods']) && is_array($incoming['methods'])) {
        foreach (['yoomoney', 'crypto', 'demo'] as $code) {
            if (isset($incoming['methods'][$code]['enabled'])) {
                $payment['methods'][$code]['enabled'] = (bool)$incoming['methods'][$code]['enabled'];
            }
        }
    }

    if (isset($incoming['yoomoney']) && is_array($incoming['yoomoney'])) {
        $map = [
            'wallet' => 'wallet',
            'receiver' => 'wallet',
            'notification_secret' => 'notification_secret',
            'client_id' => 'client_id',
            'client_secret' => 'client_secret',
            'redirect_uri' => 'redirect_uri',
            'success_url' => 'success_url',
            'fail_url' => 'fail_url',
            'payment_type' => 'payment_type'
        ];
        foreach ($map as $source => $target) {
            if (array_key_exists($source, $incoming['yoomoney'])) {
                $payment['yoomoney'][$target] = sanitize((string)$incoming['yoomoney'][$source]);
            }
        }
    }

    if (isset($incoming['crypto']) && is_array($incoming['crypto'])) {
        foreach (['merchant', 'api_key', 'webhook_url', 'notes'] as $field) {
            if (array_key_exists($field, $incoming['crypto'])) {
                $payment['crypto'][$field] = sanitize((string)$incoming['crypto'][$field]);
            }
        }
    }

    return $payment;
}

function buildPublicOrderData($order) {
    $public = [
        'order_number' => $order['order_number'] ?? '',
        'label' => $order['label'] ?? '',
        'status' => $order['status'] ?? 'pending',
        'payment_status' => $order['payment_status'] ?? 'pending',
        'payment_method' => $order['payment_method'] ?? 'demo',
        'amount' => (float)($order['totals']['amount'] ?? 0),
        'quantity' => (int)($order['totals']['quantity'] ?? 0),
        'created_at' => $order['created_at'] ?? '',
        'updated_at' => $order['updated_at'] ?? '',
        'paid_at' => $order['paid_at'] ?? null,
        'fulfilled_at' => $order['fulfilled_at'] ?? null,
        'is_demo_payment' => !empty($order['is_demo_payment']),
        'items' => array_map(function($item) {
            return [
                'product_id' => (int)($item['product_id'] ?? 0),
                'slug' => $item['slug'] ?? '',
                'name' => $item['name'] ?? '',
                'qty' => (int)($item['qty'] ?? 0),
                'price' => (float)($item['price'] ?? 0)
            ];
        }, $order['items'] ?? [])
    ];

    if (($order['payment_method'] ?? '') === 'crypto' && !empty($order['crypto']) && is_array($order['crypto'])) {
        $public['crypto'] = [
            'token_code' => $order['crypto']['token_code'] ?? '',
            'token_name' => $order['crypto']['token_name'] ?? '',
            'token_symbol' => $order['crypto']['token_symbol'] ?? '',
            'network' => $order['crypto']['network'] ?? '',
            'wallet' => $order['crypto']['wallet'] ?? '',
            'wallet_mask' => $order['crypto']['wallet_mask'] ?? '',
            'expected_amount' => (float)($order['crypto']['expected_amount'] ?? 0),
            'expected_amount_text' => $order['crypto']['expected_amount_text'] ?? '',
            'amount_rub' => (float)($order['crypto']['amount_rub'] ?? 0),
            'amount_usd' => (float)($order['crypto']['amount_usd'] ?? 0),
            'rate_usd' => (float)($order['crypto']['rate_usd'] ?? 0),
            'rate_rub' => (float)($order['crypto']['rate_rub'] ?? 0),
            'confirmations_required' => (int)($order['crypto']['confirmations_required'] ?? 0),
            'payment_uri' => $order['crypto']['payment_uri'] ?? '',
            'qr_value' => $order['crypto']['qr_value'] ?? '',
            'qr_image_url' => $order['crypto']['qr_image_url'] ?? '',
            'invoice_status' => $order['crypto']['invoice_status'] ?? 'awaiting_payment',
            'verification_status' => $order['crypto']['verification_status'] ?? '',
            'verification_source' => $order['crypto']['verification_source'] ?? '',
            'detected_delta' => (float)($order['crypto']['detected_delta'] ?? 0),
            'detected_delta_text' => $order['crypto']['detected_delta_text'] ?? '',
            'last_checked_at' => $order['crypto']['last_checked_at'] ?? '',
            'created_at' => $order['crypto']['created_at'] ?? '',
            'expires_at' => $order['crypto']['expires_at'] ?? ''
        ];
    }

    if (!empty($order['transaction']) && is_array($order['transaction'])) {
        $public['transaction'] = $order['transaction'];
    }

    if (!empty($order['delivered_items']) && is_array($order['delivered_items'])) {
        $public['delivered_items'] = $order['delivered_items'];
    }

    return $public;
}

function formatCryptoAmount($amount, $decimals = 8) {
    $precision = max(0, min(12, (int)$decimals));
    $formatted = number_format((float)$amount, $precision, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');
    return $formatted !== '' ? $formatted : '0';
}

function buildCryptoWalletMask($wallet) {
    $wallet = trim((string)$wallet);
    if ($wallet === '') {
        return '';
    }
    return mb_strlen($wallet) > 14
        ? mb_substr($wallet, 0, 7) . '...' . mb_substr($wallet, -6)
        : $wallet;
}

function buildCryptoPaymentUri($token, $wallet, $amount, $orderNumber = '') {
    $wallet = trim((string)$wallet);
    if ($wallet === '') {
        return '';
    }

    $code = strtoupper((string)($token['code'] ?? ''));
    $scheme = trim((string)($token['uri_scheme'] ?? ''));
    $formattedAmount = formatCryptoAmount($amount, (int)($token['decimals'] ?? 8));

    switch ($code) {
        case 'BTC':
        case 'BCH':
        case 'LTC':
        case 'DOGE':
        case 'DASH':
            $base = ($scheme !== '' ? $scheme . ':' : '') . $wallet;
            return $base . '?amount=' . rawurlencode($formattedAmount);

        case 'XMR':
            $query = 'tx_amount=' . rawurlencode($formattedAmount);
            if ($orderNumber !== '') {
                $query .= '&tx_description=' . rawurlencode('Order ' . $orderNumber);
            }
            return 'monero:' . $wallet . '?' . $query;

        case 'SOL':
            return 'solana:' . $wallet . '?amount=' . rawurlencode($formattedAmount);

        case 'TRX':
        case 'USDT_TRC20':
            return 'tron:' . $wallet . '?amount=' . rawurlencode($formattedAmount);

        case 'TON':
            $nanoAmount = (string)max(0, (int)round(((float)$amount) * 1000000000));
            $uri = 'ton://transfer/' . $wallet . '?amount=' . rawurlencode($nanoAmount);
            if ($orderNumber !== '') {
                $uri .= '&text=' . rawurlencode('Order ' . $orderNumber);
            }
            return $uri;

        default:
            return $wallet;
    }
}


function buildUniqueCryptoAmountSalt($orderNumber, $decimals) {
    $decimals = max(4, min(12, (int)$decimals));
    $baseStep = pow(10, -min($decimals, 6));
    $hash = abs(crc32((string)$orderNumber));
    $steps = ($hash % 899) + 101;
    return round($baseStep * $steps, $decimals);
}

function hexToDecString($hex) {
    $hex = strtolower(trim((string)$hex));
    $hex = preg_replace('/^0x/', '', $hex);
    if ($hex === '' || $hex === '0') {
        return '0';
    }
    $dec = '0';
    for ($i = 0; $i < strlen($hex); $i++) {
        $current = hexdec($hex[$i]);
        $carry = $current;
        $result = '';
        for ($j = strlen($dec) - 1; $j >= 0; $j--) {
            $num = ((int)$dec[$j]) * 16 + $carry;
            $result = ($num % 10) . $result;
            $carry = intdiv($num, 10);
        }
        while ($carry > 0) {
            $result = ($carry % 10) . $result;
            $carry = intdiv($carry, 10);
        }
        $dec = ltrim($result, '0');
        if ($dec === '') {
            $dec = '0';
        }
    }
    return $dec;
}

function decimalStringToFloat($value, $decimals) {
    $value = trim((string)$value);
    if ($value === '') {
        return 0.0;
    }
    $decimals = max(0, min(18, (int)$decimals));
    if ($decimals === 0) {
        return (float)$value;
    }
    $negative = false;
    if ($value[0] === '-') {
        $negative = true;
        $value = substr($value, 1);
    }
    $value = ltrim($value, '0');
    if ($value == '') {
        return 0.0;
    }
    if (strlen($value) <= $decimals) {
        $value = str_pad($value, $decimals + 1, '0', STR_PAD_LEFT);
    }
    $intPart = substr($value, 0, -$decimals);
    $fracPart = substr($value, -$decimals);
    $number = ($intPart === '' ? '0' : $intPart) . '.' . $fracPart;
    $float = (float)$number;
    return $negative ? -$float : $float;
}

function evmRpc($url, $method, $params = []) {
    return httpPostJson($url, [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => $method,
        'params' => $params
    ], [], 20);
}

function detectEvmWalletBalance($rpcUrl, $wallet, $decimals = 18) {
    $response = evmRpc($rpcUrl, 'eth_getBalance', [$wallet, 'latest']);
    $hex = $response['result'] ?? '';
    if (!is_string($hex) || $hex === '') {
        return ['status' => 'unavailable', 'balance' => 0.0, 'source' => $rpcUrl];
    }
    return ['status' => 'ok', 'balance' => decimalStringToFloat(hexToDecString($hex), $decimals), 'source' => $rpcUrl];
}

function detectEvmTokenBalance($rpcUrl, $wallet, $contract, $decimals = 6) {
    $wallet = strtolower(trim((string)$wallet));
    $contract = trim((string)$contract);
    if ($wallet === '' || $contract === '') {
        return ['status' => 'unavailable', 'balance' => 0.0, 'source' => $rpcUrl];
    }
    $data = '0x70a08231' . str_pad(substr($wallet, 2), 64, '0', STR_PAD_LEFT);
    $response = evmRpc($rpcUrl, 'eth_call', [[
        'to' => $contract,
        'data' => $data
    ], 'latest']);
    $hex = $response['result'] ?? '';
    if (!is_string($hex) || $hex === '') {
        return ['status' => 'unavailable', 'balance' => 0.0, 'source' => $rpcUrl];
    }
    return ['status' => 'ok', 'balance' => decimalStringToFloat(hexToDecString($hex), $decimals), 'source' => $rpcUrl];
}

function detectSolanaBalance($wallet) {
    $response = httpPostJson('https://api.mainnet-beta.solana.com', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'getBalance',
        'params' => [$wallet, ['commitment' => 'finalized']]
    ]);
    $value = $response['result']['value'] ?? null;
    if (!is_numeric($value)) {
        return ['status' => 'unavailable', 'balance' => 0.0, 'source' => 'solana_rpc'];
    }
    return ['status' => 'ok', 'balance' => ((float)$value / 1000000000), 'source' => 'solana_rpc'];
}

function detectTonBalance($wallet) {
    $response = httpGetJson('https://toncenter.com/api/v2/getAddressInformation?address=' . rawurlencode($wallet));
    $balance = $response['result']['balance'] ?? null;
    if (!is_numeric($balance) && !is_string($balance)) {
        return ['status' => 'unavailable', 'balance' => 0.0, 'source' => 'toncenter'];
    }
    return ['status' => 'ok', 'balance' => ((float)$balance / 1000000000), 'source' => 'toncenter'];
}

function detectTronBalance($wallet) {
    $response = httpGetJson('https://apilist.tronscanapi.com/api/account?address=' . rawurlencode($wallet));
    $balance = $response['balance'] ?? null;
    if (!is_numeric($balance) && !is_string($balance)) {
        return ['status' => 'unavailable', 'balance' => 0.0, 'source' => 'tronscan'];
    }
    return ['status' => 'ok', 'balance' => ((float)$balance / 1000000), 'source' => 'tronscan'];
}

function detectTrc20Balance($wallet, $contract, $decimals = 6) {
    $response = httpGetJson('https://apilist.tronscanapi.com/api/account/tokens?address=' . rawurlencode($wallet));
    $tokens = $response['data'] ?? [];
    if (!is_array($tokens)) {
        return ['status' => 'unavailable', 'balance' => 0.0, 'source' => 'tronscan_token'];
    }
    $contract = strtoupper(trim((string)$contract));
    foreach ($tokens as $token) {
        $tokenAddress = strtoupper((string)($token['tokenId'] ?? $token['tokenAddress'] ?? ''));
        if ($tokenAddress !== $contract) {
            continue;
        }
        $rawBalance = $token['balance'] ?? $token['amount'] ?? '0';
        $tokenDecimals = (int)($token['tokenDecimal'] ?? $token['tokenDecimalCount'] ?? $decimals);
        return ['status' => 'ok', 'balance' => decimalStringToFloat((string)$rawBalance, $tokenDecimals), 'source' => 'tronscan_token'];
    }
    return ['status' => 'ok', 'balance' => 0.0, 'source' => 'tronscan_token'];
}

function detectBlockchainInfoBalance($wallet) {
    $response = httpGetJson('https://blockchain.info/rawaddr/' . rawurlencode($wallet) . '?limit=0');
    $balance = $response['final_balance'] ?? null;
    if (!is_numeric($balance) && !is_string($balance)) {
        return ['status' => 'unavailable', 'balance' => 0.0, 'source' => 'blockchain_info'];
    }
    return ['status' => 'ok', 'balance' => ((float)$balance / 100000000), 'source' => 'blockchain_info'];
}

function detectBlockchairBalance($chain, $wallet, $decimals = 8) {
    $response = httpGetJson('https://api.blockchair.com/' . rawurlencode($chain) . '/dashboards/address/' . rawurlencode($wallet));
    $addressData = $response['data'][$wallet]['address'] ?? null;
    if (!is_array($addressData)) {
        return ['status' => 'unavailable', 'balance' => 0.0, 'source' => 'blockchair_' . $chain];
    }
    $raw = $addressData['balance'] ?? $addressData['received'] ?? null;
    if (!is_numeric($raw) && !is_string($raw)) {
        return ['status' => 'unavailable', 'balance' => 0.0, 'source' => 'blockchair_' . $chain];
    }
    return ['status' => 'ok', 'balance' => ((float)$raw / pow(10, $decimals)), 'source' => 'blockchair_' . $chain];
}

function takeCryptoBalanceSnapshot($token, $wallet) {
    $code = strtoupper((string)($token['code'] ?? ''));
    $wallet = trim((string)$wallet);
    $contract = trim((string)($token['token_contract'] ?? ''));
    switch ($code) {
        case 'BTC':
            return detectBlockchainInfoBalance($wallet);
        case 'BCH':
            return detectBlockchairBalance('bitcoin-cash', $wallet, 8);
        case 'LTC':
            return detectBlockchairBalance('litecoin', $wallet, 8);
        case 'DOGE':
            return detectBlockchairBalance('dogecoin', $wallet, 8);
        case 'DASH':
            return detectBlockchairBalance('dash', $wallet, 8);
        case 'ETH':
            return detectEvmWalletBalance('https://cloudflare-eth.com', $wallet, 18);
        case 'POL':
            return detectEvmWalletBalance('https://polygon-rpc.com', $wallet, 18);
        case 'USDT_ERC20':
            return detectEvmTokenBalance('https://cloudflare-eth.com', $wallet, $contract, (int)($token['decimals'] ?? 6));
        case 'USDT_POL':
            return detectEvmTokenBalance('https://polygon-rpc.com', $wallet, $contract, (int)($token['decimals'] ?? 6));
        case 'TRX':
            return detectTronBalance($wallet);
        case 'USDT_TRC20':
            return detectTrc20Balance($wallet, $contract, (int)($token['decimals'] ?? 6));
        case 'SOL':
            return detectSolanaBalance($wallet);
        case 'TON':
            return detectTonBalance($wallet);
        case 'BNB':
        case 'USDT_BSC':
            return ['status' => 'manual_review', 'balance' => 0.0, 'source' => 'manual_bnb_beacon'];
        case 'XMR':
            return ['status' => 'manual_review', 'balance' => 0.0, 'source' => 'manual_monero'];
        default:
            return ['status' => 'unavailable', 'balance' => 0.0, 'source' => 'unsupported'];
    }
}

function isCryptoInvoiceExpired($order) {
    $expiresAt = trim((string)($order['crypto']['expires_at'] ?? ''));
    if ($expiresAt === '') {
        return false;
    }
    $expiresTs = strtotime($expiresAt);
    if ($expiresTs === false) {
        return false;
    }
    return time() >= $expiresTs;
}

function tryAutoVerifyCryptoOrder(&$order) {
    if (($order['payment_method'] ?? '') !== 'crypto' || ($order['payment_status'] ?? '') === 'paid') {
        return false;
    }
    if (empty($order['crypto']) || !is_array($order['crypto'])) {
        return false;
    }

    if (isCryptoInvoiceExpired($order)) {
        $order['crypto']['invoice_status'] = 'expired';
        $order['payment_status'] = 'failed';
        $order['updated_at'] = date('Y-m-d H:i:s');
        return false;
    }

    $tokenCode = strtoupper((string)($order['crypto']['token_code'] ?? ''));
    $token = findCryptoTokenByCode($tokenCode);
    if (!$token) {
        $order['crypto']['invoice_status'] = 'verification_unavailable';
        $order['updated_at'] = date('Y-m-d H:i:s');
        return false;
    }

    $snapshot = takeCryptoBalanceSnapshot($token, (string)($order['crypto']['wallet'] ?? ''));
    $expectedAmount = (float)($order['crypto']['expected_amount'] ?? 0);
    $baseline = (float)($order['crypto']['snapshot_balance'] ?? 0);
    $currentBalance = (float)($snapshot['balance'] ?? 0);
    $delta = max(0, $currentBalance - $baseline);
    $decimals = (int)($token['decimals'] ?? 8);
    $tolerance = max(pow(10, -min(max($decimals, 4), 8)) * 3, 0.000001);

    $order['crypto']['last_checked_at'] = date('Y-m-d H:i:s');
    $order['crypto']['last_balance'] = $currentBalance;
    $order['crypto']['last_balance_text'] = formatCryptoAmount($currentBalance, $decimals);
    $order['crypto']['detected_delta'] = $delta;
    $order['crypto']['detected_delta_text'] = formatCryptoAmount($delta, $decimals);
    $order['crypto']['verification_source'] = (string)($snapshot['source'] ?? '');
    $order['crypto']['verification_status'] = (string)($snapshot['status'] ?? 'unavailable');

    if (($snapshot['status'] ?? '') === 'manual_review') {
        $order['crypto']['invoice_status'] = 'manual_review';
        $order['status'] = 'pending';
        $order['updated_at'] = date('Y-m-d H:i:s');
        return false;
    }

    if (($snapshot['status'] ?? '') !== 'ok') {
        $order['crypto']['invoice_status'] = 'verification_unavailable';
        $order['updated_at'] = date('Y-m-d H:i:s');
        return false;
    }

    if ($delta + $tolerance < $expectedAmount) {
        $order['crypto']['invoice_status'] = 'awaiting_payment';
        $order['updated_at'] = date('Y-m-d H:i:s');
        return false;
    }

    $order['status'] = 'paid';
    $order['payment_status'] = 'paid';
    $order['paid_at'] = date('Y-m-d H:i:s');
    $order['updated_at'] = date('Y-m-d H:i:s');
    $order['crypto']['invoice_status'] = 'paid';
    $order['transaction'] = [
        'gateway' => 'crypto_balance_watch',
        'token_code' => $tokenCode,
        'expected_amount' => $expectedAmount,
        'received_delta' => $delta,
        'wallet' => $order['crypto']['wallet'] ?? '',
        'source' => $snapshot['source'] ?? ''
    ];
    $order['history'][] = [
        'time' => date('Y-m-d H:i:s'),
        'status' => 'paid',
        'message' => 'Поступление криптовалюты подтверждено автоматической проверкой баланса'
    ];
    fulfillOrderItems($order);
    return true;
}


function buildCryptoPaymentData(&$order) {
    $selectedTokenCode = strtoupper(trim((string)($order['crypto']['token_code'] ?? '')));
    if ($selectedTokenCode === '') {
        return ['success' => false, 'error' => 'Не выбран токен для криптооплаты'];
    }

    $token = findCryptoTokenByCode($selectedTokenCode);
    if (!$token) {
        return ['success' => false, 'error' => 'Выбранный токен недоступен'];
    }

    $wallet = trim((string)($token['wallet'] ?? ''));
    if ($wallet === '') {
        return ['success' => false, 'error' => 'Для выбранного токена не настроен кошелёк'];
    }

    $ratesCache = getCryptoUsdRates();
    $rates = $ratesCache['rates'] ?? [];
    $tokenRate = $rates[$selectedTokenCode] ?? null;
    if (!is_array($tokenRate) || empty($tokenRate['usd'])) {
        return ['success' => false, 'error' => 'Для выбранного токена сейчас недоступен локальный курс'];
    }

    $rubAmount = round((float)($order['totals']['amount'] ?? 0), 2);
    $usdPerRub = (float)($ratesCache['usd_per_rub'] ?? 0);
    $rubPerUsd = (float)($ratesCache['rub_per_usd'] ?? 0);
    $usdAmount = $usdPerRub > 0 ? ($rubAmount * $usdPerRub) : ($rubPerUsd > 0 ? ($rubAmount / $rubPerUsd) : 0);
    if ($usdAmount <= 0) {
        return ['success' => false, 'error' => 'Не удалось пересчитать стоимость заказа в USD'];
    }

    $decimals = (int)($token['decimals'] ?? 8);
    $expectedAmount = round($usdAmount / (float)$tokenRate['usd'], min(10, max(4, $decimals)));
    $amountSalt = buildUniqueCryptoAmountSalt($order['order_number'] ?? '', $decimals);
    $expectedAmount = round($expectedAmount + $amountSalt, min(10, max(4, $decimals)));
    $paymentUri = buildCryptoPaymentUri($token, $wallet, $expectedAmount, $order['order_number'] ?? '');
    $qrValue = $paymentUri !== '' ? $paymentUri : $wallet;
    $balanceSnapshot = takeCryptoBalanceSnapshot($token, $wallet);

    $invoice = [
        'token_code' => $selectedTokenCode,
        'token_name' => (string)($token['name'] ?? $selectedTokenCode),
        'token_symbol' => (string)($token['usd_symbol'] ?? $selectedTokenCode),
        'network' => (string)($token['network'] ?? ''),
        'wallet' => $wallet,
        'wallet_mask' => buildCryptoWalletMask($wallet),
        'explorer_type' => (string)($token['explorer_type'] ?? ''),
        'token_contract' => (string)($token['token_contract'] ?? ''),
        'expected_amount' => $expectedAmount,
        'expected_amount_text' => formatCryptoAmount($expectedAmount, $decimals),
        'amount_rub' => $rubAmount,
        'amount_usd' => round($usdAmount, 2),
        'rate_usd' => (float)($tokenRate['usd'] ?? 0),
        'rate_rub' => (float)($tokenRate['rub'] ?? 0),
        'confirmations_required' => (int)($token['confirmations_required'] ?? 1),
        'amount_salt' => $amountSalt,
        'snapshot_balance' => (float)($balanceSnapshot['balance'] ?? 0),
        'snapshot_balance_text' => formatCryptoAmount((float)($balanceSnapshot['balance'] ?? 0), $decimals),
        'snapshot_source' => (string)($balanceSnapshot['source'] ?? ''),
        'snapshot_status' => (string)($balanceSnapshot['status'] ?? 'unavailable'),
        'snapshot_taken_at' => date('Y-m-d H:i:s'),
        'payment_uri' => $paymentUri,
        'qr_value' => $qrValue,
        'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . rawurlencode($qrValue),
        'invoice_status' => 'awaiting_payment',
        'created_at' => date('Y-m-d H:i:s'),
        'expires_at' => date('Y-m-d H:i:s', time() + (20 * 60))
    ];

    $order['crypto'] = $invoice;

    return [
        'success' => true,
        'type' => 'crypto_invoice',
        'gateway' => 'crypto',
        'invoice' => $invoice,
        'display' => [
            'title' => 'Оплата криптовалютой',
            'description' => 'Переведите точную сумму на указанный адрес и дождитесь подтверждения сети.'
        ]
    ];
}

function buildYooMoneyPaymentData($order, $paymentSettings) {
    $wallet = trim((string)($paymentSettings['yoomoney']['wallet'] ?? ''));
    if ($wallet === '') {
        return ['success' => false, 'error' => 'В админке не указан кошелёк YooMoney'];
    }

    $paymentType = strtoupper((string)($paymentSettings['yoomoney']['payment_type'] ?? 'AC'));
    if (!in_array($paymentType, ['AC', 'PC'], true)) {
        $paymentType = 'AC';
    }

    $successUrl = trim((string)($paymentSettings['yoomoney']['success_url'] ?? ''));
    if ($successUrl === '') {
        $successUrl = buildAbsoluteUrl('oplata/?status=success&order=' . rawurlencode($order['order_number'] ?? ''));
    } else {
        // Если URL задан, но не содержит номера заказа, добавим его для удобства
        if (strpos($successUrl, 'order=') === false) {
            $sep = (strpos($successUrl, '?') === false) ? '?' : '&';
            $successUrl .= $sep . 'order=' . rawurlencode($order['order_number'] ?? '');
        }
    }

    return [
        'success' => true,
        'type' => 'redirect_form',
        'gateway' => 'yoomoney',
        'action' => 'https://yoomoney.ru/quickpay/confirm',
        'method' => 'POST',
        'fields' => [
            'receiver' => $wallet,
            'quickpay-form' => 'button',
            'paymentType' => $paymentType,
            'targets' => 'Оплата заказа ' . ($order['order_number'] ?? ''),
            'sum' => number_format((float)($order['totals']['amount'] ?? 0), 2, '.', ''),
            'label' => $order['label'] ?? '',
            'successURL' => $successUrl,
            'formcomment' => 'Оплата заказа ' . ($order['order_number'] ?? ''),
            'short-dest' => 'Оплата заказа ' . ($order['order_number'] ?? '')
        ],
        'display' => [
            'title' => 'Оплата через YooMoney',
            'description' => $paymentType === 'AC' ? 'Переход на оплату банковской картой через YooMoney' : 'Переход на оплату через кошелёк YooMoney'
        ]
    ];
}

function verifyYooMoneyNotification($payload, $secret) {
    if (!$secret) return false;
    $parts = [
        $payload['notification_type'] ?? '',
        $payload['operation_id'] ?? '',
        $payload['amount'] ?? '',
        $payload['currency'] ?? '',
        $payload['datetime'] ?? '',
        $payload['sender'] ?? '',
        $payload['codepro'] ?? '',
        $secret,
        $payload['label'] ?? ''
    ];
    $hash = sha1(implode('&', $parts));
    return hash_equals($hash, (string)($payload['sha1_hash'] ?? ''));
}

function fulfillOrderItems(&$order) {
    if (!empty($order['fulfilled_at'])) {
        return;
    }

    $products = getProducts();
    $delivered = [];

    foreach (($order['items'] ?? []) as $item) {
        $matched = false;
        foreach ($products as &$product) {
            if ((int)($product['id'] ?? 0) !== (int)($item['product_id'] ?? 0)) {
                continue;
            }
            $matched = true;
            $qty = max(1, (int)($item['qty'] ?? 1));
            $issuedItems = [];

            if (empty($product['is_demo']) && !empty($product['items']) && is_array($product['items'])) {
                $issuedItems = array_splice($product['items'], 0, min($qty, count($product['items'])));
            } elseif (empty($product['is_demo'])) {
                $product['quantity'] = max(0, (int)($product['quantity'] ?? 0) - $qty);
            }

            $delivered[] = [
                'product_id' => (int)($product['id'] ?? 0),
                'slug' => $product['slug'] ?? '',
                'name' => $product['name'] ?? '',
                'qty' => $qty,
                'issued_items' => $issuedItems,
                'is_demo' => !empty($product['is_demo'])
            ];
            break;
        }
        unset($product);

        if (!$matched) {
            $delivered[] = [
                'product_id' => (int)($item['product_id'] ?? 0),
                'slug' => $item['slug'] ?? '',
                'name' => $item['name'] ?? '',
                'qty' => (int)($item['qty'] ?? 1),
                'issued_items' => [],
                'is_demo' => !empty($item['is_demo']),
                'missing_product' => true
            ];
        }
    }

    syncProductsInventory($products);
    saveProductsWithAutoSplit($products);

    $order['delivered_items'] = $delivered;
    $order['fulfilled_at'] = date('Y-m-d H:i:s');
    $order['history'][] = [
        'time' => date('Y-m-d H:i:s'),
        'status' => 'fulfilled',
        'message' => 'Товарные позиции выданы автоматически'
    ];
}

function buildPaymentTestReport() {
    $payment = getPaymentSettings();
    $checks = [];
    $issues = [];

    $wallet = trim((string)($payment['yoomoney']['wallet'] ?? ''));
    $secret = trim((string)($payment['yoomoney']['notification_secret'] ?? ''));
    $siteUrl = trim((string)(getSettings()['site']['url'] ?? ''));

    $checks[] = ['title' => 'Кошелёк YooMoney', 'ok' => $wallet !== '', 'message' => $wallet !== '' ? 'Кошелёк заполнен' : 'Заполните номер кошелька'];
    $checks[] = ['title' => 'Webhook secret', 'ok' => $secret !== '', 'message' => $secret !== '' ? 'Секрет уведомлений заполнен' : 'Заполните секрет HTTP-уведомлений'];
    $checks[] = ['title' => 'URL сайта', 'ok' => $siteUrl !== '', 'message' => $siteUrl !== '' ? 'URL сайта заполнен' : 'Укажите URL сайта для корректных ссылок возврата'];
    $checks[] = ['title' => 'Способы оплаты', 'ok' => count(getEnabledPaymentMethods()) > 0, 'message' => 'Активные методы: ' . implode(', ', getEnabledPaymentMethods())];

    foreach ($checks as $check) {
        if (empty($check['ok'])) {
            $issues[] = $check['message'];
        }
    }

    return [
        'status' => empty($issues) ? 'ready' : 'warning',
        'checks' => $checks,
        'issues' => $issues,
        'webhook_url' => buildAbsoluteUrl('api/?path=payments/yoomoney/webhook'),
        'success_url' => trim((string)($payment['yoomoney']['success_url'] ?? '')) ?: buildAbsoluteUrl('oplata/?status=success'),
        'fail_url' => trim((string)($payment['yoomoney']['fail_url'] ?? '')) ?: buildAbsoluteUrl('oplata/?status=fail')
    ];
}

function getPaymentSettings() {
    $settings = getSettings();
    $payment = $settings['payment'] ?? [];
    $payment['methods'] = $payment['methods'] ?? [];
    $payment['methods']['yoomoney'] = $payment['methods']['yoomoney'] ?? ['enabled' => true];
    $payment['methods']['crypto'] = $payment['methods']['crypto'] ?? ['enabled' => false];
    $payment['methods']['demo'] = $payment['methods']['demo'] ?? ['enabled' => true];
    return $payment;
}

function getEnabledPaymentMethods() {
    $payment = getPaymentSettings();
    $enabled = [];
    foreach (($payment['methods'] ?? []) as $code => $method) {
        if (!empty($method['enabled'])) $enabled[] = $code;
    }
    if (empty($enabled)) $enabled[] = 'demo';
    return $enabled;
}


function getOrderItemsFromRequest($input) {
    $items = $input['items'] ?? [];
    if (!is_array($items)) return [];

    $products = getProducts();
    $productMapById = [];
    $productMapBySlug = [];
    foreach ($products as $product) {
        $productMapById[(int)$product['id']] = $product;
        $productMapBySlug[$product['slug']] = $product;
    }

    $orderItems = [];
    foreach ($items as $row) {
        if (!is_array($row)) continue;
        $product = null;
        if (isset($row['product_id']) && isset($productMapById[(int)$row['product_id']])) {
            $product = $productMapById[(int)$row['product_id']];
        } elseif (!empty($row['slug']) && isset($productMapBySlug[$row['slug']])) {
            $product = $productMapBySlug[$row['slug']];
        }
        if (!$product) continue;

        $requestedQty = $row['qty'] ?? ($row['quantity'] ?? 1);
        $qty = max(1, (int)$requestedQty);
        if (($product['quantity'] ?? 0) <= 0 && empty($product['is_demo'])) continue;
        if (empty($product['is_demo'])) {
            $qty = min($qty, max(0, (int)$product['quantity']));
            if ($qty <= 0) continue;
        }

        $orderItems[] = [
            'product_id' => (int)$product['id'],
            'slug' => $product['slug'],
            'name' => $product['name'],
            'price' => (float)$product['price'],
            'qty' => $qty,
            'icon' => $product['icon'] ?? 'default.svg',
            'is_demo' => (bool)($product['is_demo'] ?? false)
        ];
    }

    return $orderItems;
}

function calculateOrderTotals($items) {
    $amount = 0;
    $quantity = 0;
    foreach ($items as $item) {
        $amount += ((float)$item['price']) * ((int)$item['qty']);
        $quantity += (int)$item['qty'];
    }
    return [
        'amount' => round($amount, 2),
        'quantity' => $quantity
    ];
}

// Route API requests
switch (true) {
    // GET /api/categories
    case $path === 'categories' && $method === 'GET':
        jsonResponse(['success' => true, 'data' => getCategories()]);

    // GET /api/products
    case $path === 'products' && $method === 'GET':
        $category = $_GET['category'] ?? null;
        $subcategory = $_GET['subcategory'] ?? null;
        $search = $_GET['q'] ?? null;
        $popular = isset($_GET['popular']);

        if ($popular) {
            $products = getPopularProducts(6);
        } elseif ($category) {
            $products = getProductsByCategory($category, $subcategory);
        } else {
            $products = getProducts();
            $products = array_filter($products, fn($p) => $p['status'] === 'active');
            $products = array_values($products);
        }

        if ($search) {
            $products = array_values(array_filter($products, function($p) use ($search) {
                return stripos($p['name'], $search) !== false ||
                       stripos($p['short_description'], $search) !== false;
            }));
        }

        jsonResponse(['success' => true, 'data' => $products, 'total' => count($products)]);

    // GET /api/products/{slug or id}
    case preg_match('/^products\/(.+)$/', $path, $m) && $method === 'GET':
        $identifier = $m[1];
        $product = getProductBySlug($identifier);
        if (!$product && is_numeric($identifier)) {
            $allProducts = getProducts();
            foreach ($allProducts as $p) {
                if ($p['id'] === (int)$identifier) { $product = $p; break; }
            }
        }
        if (!$product) jsonResponse(['success' => false, 'error' => 'Product not found'], 404);
        jsonResponse(['success' => true, 'data' => $product]);

    // GET /api/settings
    case $path === 'settings' && $method === 'GET':
        $settings = getSettings();
        unset($settings['admin']);
        $settings['payment'] = buildPublicPaymentConfig();
        jsonResponse(['success' => true, 'data' => $settings]);

    // POST /api/orders
    case $path === 'orders' && $method === 'POST':
        $email = sanitize($input['email'] ?? '');
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'error' => 'Укажите корректный email'], 400);
        }

        $orderItems = getOrderItemsFromRequest($input);
        if (empty($orderItems)) {
            jsonResponse(['success' => false, 'error' => 'Корзина пуста или товары недоступны'], 400);
        }

        $paymentSettings = getPaymentSettings();
        $enabledMethods = getEnabledPaymentMethods();
        $requestedMethod = sanitize($input['payment_method'] ?? '');
        if ($requestedMethod !== '' && !in_array($requestedMethod, $enabledMethods, true)) {
            jsonResponse(['success' => false, 'error' => 'Выбранный способ оплаты сейчас недоступен'], 400);
        }
        $paymentMethod = $requestedMethod !== '' ? $requestedMethod : $enabledMethods[0];
        $totals = calculateOrderTotals($orderItems);

        $order = [
            'order_number' => createOrderNumber(),
            'label' => createOrderNumber(),
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => $paymentMethod,
            'email' => $email,
            'items' => $orderItems,
            'totals' => $totals,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'history' => [[
                'time' => date('Y-m-d H:i:s'),
                'status' => 'created',
                'message' => 'Заказ создан на сайте'
            ]]
        ];
        $order['label'] = $order['order_number'];

        $paymentData = ['type' => 'none'];
        if ($paymentMethod === 'yoomoney') {
            $paymentData = buildYooMoneyPaymentData($order, $paymentSettings);
            if (empty($paymentData['success'])) {
                jsonResponse(['success' => false, 'error' => $paymentData['error'] ?? 'YooMoney не настроен'], 400);
            }
        } elseif ($paymentMethod === 'crypto') {
            $selectedToken = strtoupper(sanitize($input['selected_token'] ?? ''));
            $order['crypto'] = ['token_code' => $selectedToken];
            $paymentData = buildCryptoPaymentData($order);
            if (empty($paymentData['success'])) {
                jsonResponse(['success' => false, 'error' => $paymentData['error'] ?? 'Криптооплата сейчас недоступна'], 400);
            }
        } else {
            $order['status'] = 'demo-paid';
            $order['payment_status'] = 'demo';
            $order['paid_at'] = date('Y-m-d H:i:s');
            $order['is_demo_payment'] = true;
            $order['history'][] = [
                'time' => date('Y-m-d H:i:s'),
                'status' => 'demo-paid',
                'message' => 'Демо-режим оплаты выполнен без списания средств'
            ];
            // Выдаём товар сразу при демо-оплате
            fulfillOrderItems($order);
            $paymentData = [
                'type' => 'demo',
                'gateway' => 'demo',
                'message' => 'Демонстрационный режим. Товар выдан автоматически.'
            ];
        }

        $orders = getOrders();
        $orders[] = $order;
        saveOrders($orders);
        appendPaymentLog('order_created', ['order_number' => $order['order_number'], 'payment_method' => $paymentMethod, 'amount' => $totals['amount']]);
        if ($paymentMethod === 'crypto' && !empty($order['crypto'])) {
            appendPaymentLog('crypto_invoice_created', [
                'order_number' => $order['order_number'],
                'token_code' => $order['crypto']['token_code'] ?? '',
                'wallet' => $order['crypto']['wallet_mask'] ?? '',
                'amount' => $order['crypto']['expected_amount_text'] ?? ''
            ]);
        }

        jsonResponse([
            'success' => true,
            'data' => [
                'order' => buildPublicOrderData($order),
                'payment' => $paymentData,
                'enabled_methods' => $enabledMethods
            ]
        ]);

    // GET /api/orders/{number}
    case preg_match('/^orders\/(.+)$/', $path, $m) && $method === 'GET':
        $identifier = sanitize(urldecode($m[1]));
        $orders = getOrders();
        $index = findOrderIndex($orders, $identifier);
        if ($index < 0) {
            jsonResponse(['success' => false, 'error' => 'Заказ не найден'], 404);
        }
        if (($orders[$index]['payment_method'] ?? '') === 'crypto' && ($orders[$index]['payment_status'] ?? '') !== 'paid') {
            $wasPaid = tryAutoVerifyCryptoOrder($orders[$index]);
            saveOrders($orders);
            if ($wasPaid) {
                appendPaymentLog('crypto_paid', [
                    'order_number' => $orders[$index]['order_number'] ?? '',
                    'token_code' => $orders[$index]['crypto']['token_code'] ?? '',
                    'source' => $orders[$index]['transaction']['source'] ?? ''
                ]);
            }
        }
        jsonResponse(['success' => true, 'data' => buildPublicOrderData($orders[$index])]);

    // POST /api/payments/yoomoney/webhook
    case $path === 'payments/yoomoney/webhook' && $method === 'POST':
        $payload = $_POST;
        if (empty($payload)) {
            parse_str(file_get_contents('php://input'), $payload);
        }

        appendPaymentLog('yoomoney_webhook_received', $payload);
        http_response_code(200);

        $payment = getPaymentSettings();
        $secret = trim((string)($payment['yoomoney']['notification_secret'] ?? ''));
        if (!$secret || !verifyYooMoneyNotification($payload, $secret)) {
            appendPaymentLog('yoomoney_webhook_invalid', ['label' => $payload['label'] ?? '', 'operation_id' => $payload['operation_id'] ?? '']);
            echo 'OK';
            exit;
        }

        $identifier = trim((string)($payload['label'] ?? ''));
        $orders = getOrders();
        $index = findOrderIndex($orders, $identifier);
        if ($index < 0) {
            appendPaymentLog('yoomoney_webhook_order_not_found', ['label' => $identifier]);
            echo 'OK';
            exit;
        }

        // YooMoney передаёт withdraw_amount (сумма списания) или amount (сумма поступления).
        // Для платёжей через банковскую карту (AC) используем withdraw_amount,
        // для платёжей через кошелёк (PC) - amount.
        $withdrawAmount = (float)($payload['withdraw_amount'] ?? 0);
        $receivedAmount = (float)($payload['amount'] ?? 0);
        // Берём максимальную из двух сумм (withdraw_amount включает комиссию)
        $incomingAmount = round(max($withdrawAmount, $receivedAmount), 2);
        $expectedAmount = round((float)($orders[$index]['totals']['amount'] ?? 0), 2);
        // Допускаем погрешность до 1% или 1 рубль (комиссия банка)
        $tolerance = max(0.01 * $expectedAmount, 1.0);
        if ($incomingAmount < $expectedAmount - $tolerance) {
            appendPaymentLog('yoomoney_webhook_amount_mismatch', [
                'label' => $identifier,
                'expected' => $expectedAmount,
                'received' => $incomingAmount,
                'withdraw_amount' => $withdrawAmount,
                'amount' => $receivedAmount
            ]);
            echo 'OK';
            exit;
        }

        if (($orders[$index]['payment_status'] ?? '') !== 'paid') {
            $orders[$index]['status'] = 'paid';
            $orders[$index]['payment_status'] = 'paid';
            $orders[$index]['payment_method'] = 'yoomoney';
            $orders[$index]['paid_at'] = date('Y-m-d H:i:s');
            $orders[$index]['updated_at'] = date('Y-m-d H:i:s');
            $orders[$index]['transaction'] = [
                'gateway' => 'yoomoney',
                'operation_id' => $payload['operation_id'] ?? '',
                'notification_type' => $payload['notification_type'] ?? '',
                'amount' => $incomingAmount,
                'sender' => $payload['sender'] ?? '',
                'datetime' => $payload['datetime'] ?? ''
            ];
            $orders[$index]['history'][] = [
                'time' => date('Y-m-d H:i:s'),
                'status' => 'paid',
                'message' => 'Платёж подтверждён уведомлением YooMoney'
            ];
            fulfillOrderItems($orders[$index]);
            saveOrders($orders);
            appendPaymentLog('yoomoney_webhook_paid', ['order_number' => $orders[$index]['order_number'], 'operation_id' => $payload['operation_id'] ?? '']);
        }

        echo 'OK';
        exit;

    // POST /api/contact
    case $path === 'contact' && $method === 'POST':
        $email = sanitize($input['email'] ?? '');
        $social = sanitize($input['social'] ?? '');
        $message = sanitize($input['message'] ?? '');
        if (!$email || !$message) {
            jsonResponse(['success' => false, 'error' => 'Email и сообщение обязательны'], 400);
        }
        $log = ['time' => date('Y-m-d H:i:s'), 'email' => $email, 'social' => $social, 'message' => $message];
        $logs = [];
        $logFile = __DIR__ . '/../data/contacts.json';
        if (file_exists($logFile)) {
            $logs = json_decode(file_get_contents($logFile), true) ?? [];
        }
        $logs[] = $log;
        file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        jsonResponse(['success' => true, 'message' => 'Сообщение отправлено. Мы ответим в течение 15 минут.']);

    // GET /api/search
    case $path === 'search' && $method === 'GET':
        $q = sanitize($_GET['q'] ?? '');
        if (strlen($q) < 2) jsonResponse(['success' => true, 'data' => []]);
        $products = getProducts();
        $results = array_values(array_filter($products, function($p) use ($q) {
            return $p['status'] === 'active' && (
                stripos($p['name'], $q) !== false ||
                stripos($p['short_description'], $q) !== false ||
                stripos($p['category'], $q) !== false
            );
        }));
        jsonResponse(['success' => true, 'data' => array_slice($results, 0, 10)]);

    // Admin: POST /api/admin/products
    case $path === 'admin/products' && $method === 'POST':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $products = getProducts();
        $newProduct = buildProductPayload($input);
        $newProduct['id'] = getNextEntityId($products);
        $products[] = $newProduct;
        syncProductsInventory($products);
        saveProductsWithAutoSplit($products);
        jsonResponse(['success' => true, 'data' => $newProduct]);

    // Admin: PUT /api/admin/products/{id}
    case preg_match('/^admin\/products\/(\d+)$/', $path, $m) && $method === 'PUT':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $products = getProducts();
        $id = (int)$m[1];
        foreach ($products as $index => $product) {
            if ($product['id'] === $id) {
                $updatedProduct = buildProductPayload($input, $product);
                $updatedProduct['id'] = $id;
                $products[$index] = $updatedProduct;
                syncProductsInventory($products);
                saveProductsWithAutoSplit($products);
                jsonResponse(['success' => true, 'data' => $updatedProduct]);
            }
        }
        jsonResponse(['success' => false, 'error' => 'Product not found'], 404);

    // Admin: DELETE /api/admin/products/{id}
    case preg_match('/^admin\/products\/(\d+)$/', $path, $m) && $method === 'DELETE':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $products = getProducts();
        $id = (int)$m[1];
        $products = array_values(array_filter($products, fn($p) => $p['id'] !== $id));
        saveProductsWithAutoSplit($products);
        jsonResponse(['success' => true]);

    // Admin: POST /api/admin/categories
    case $path === 'admin/categories' && $method === 'POST':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $categories = getCategories();
        $newCat = [
            'id' => count($categories) + 1,
            'name' => sanitize($input['name'] ?? ''),
            'slug' => sanitize($input['slug'] ?? ''),
            'icon' => sanitize($input['icon'] ?? 'default.svg'),
            'description' => sanitize($input['description'] ?? ''),
            'seo_title' => sanitize($input['seo_title'] ?? ''),
            'seo_description' => sanitize($input['seo_description'] ?? ''),
            'subcategories' => $input['subcategories'] ?? []
        ];
        $categories[] = $newCat;
        saveData('categories', $categories);
        jsonResponse(['success' => true, 'data' => $newCat]);

    // Admin: PUT /api/admin/categories/{id}
    case preg_match('/^admin\/categories\/(\d+)$/', $path, $m) && $method === 'PUT':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $categories = getCategories();
        $id = (int)$m[1];
        foreach ($categories as &$cat) {
            if ($cat['id'] === $id) {
                foreach (['name','slug','icon','description','seo_title','seo_description'] as $field) {
                    if (isset($input[$field])) $cat[$field] = sanitize($input[$field]);
                }
                if (isset($input['subcategories'])) $cat['subcategories'] = $input['subcategories'];
                saveData('categories', $categories);
                jsonResponse(['success' => true, 'data' => $cat]);
            }
        }
        jsonResponse(['success' => false, 'error' => 'Category not found'], 404);

    // Admin: DELETE /api/admin/categories/{id}
    case preg_match('/^admin\/categories\/(\d+)$/', $path, $m) && $method === 'DELETE':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $categories = getCategories();
        $id = (int)$m[1];
        $categories = array_values(array_filter($categories, fn($c) => $c['id'] !== $id));
        saveData('categories', $categories);
        jsonResponse(['success' => true]);

    // Admin: PUT /api/admin/settings
    case $path === 'admin/settings' && $method === 'PUT':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $settings = getSettings();
        if (isset($input['colors'])) {
            foreach ($input['colors'] as $key => $val) {
                $settings['colors'][$key] = sanitize($val);
            }
        }
        if (isset($input['site'])) {
            foreach (['name','tagline','email','theme','logo_text','template','url'] as $field) {
                if (isset($input['site'][$field])) $settings['site'][$field] = sanitize($input['site'][$field]);
            }
            // Специальная обработка template для тем
            if (isset($input['site']['template'])) {
                $validTemplates = ['dark-pro','cyber-neon','accsmarket','light-clean','midnight-gold','noves-shop','dark-shopping'];
                if (in_array($input['site']['template'], $validTemplates)) {
                    $settings['site']['template'] = $input['site']['template'];
                }
            }
        }
        if (isset($input['contacts'])) {
            foreach (['email','telegram','telegram_url'] as $field) {
                if (isset($input['contacts'][$field])) $settings['contacts'][$field] = sanitize($input['contacts'][$field]);
            }
        }
        if (isset($input['seo'])) {
            foreach (['title','description','keywords','oplata_title','oplata_description'] as $field) {
                if (isset($input['seo'][$field])) $settings['seo'][$field] = sanitize($input['seo'][$field]);
            }
        }
        // Save analytics settings (Google Analytics, GTM, Yandex.Metrika)
        if (isset($input['analytics']) && is_array($input['analytics'])) {
            if (!isset($settings['analytics'])) $settings['analytics'] = [];
            foreach (['ga4_id','gtm_id','ym_id','google_verify','yandex_verify'] as $field) {
                if (isset($input['analytics'][$field])) $settings['analytics'][$field] = sanitize($input['analytics'][$field]);
            }
            // Custom code fields - stored as-is (not sanitized to preserve HTML/JS)
            foreach (['custom_head','custom_body'] as $field) {
                if (isset($input['analytics'][$field])) $settings['analytics'][$field] = $input['analytics'][$field];
            }
        }
        // Save pages SEO (FAQ, Rules, Info)
        if (isset($input['pages_seo']) && is_array($input['pages_seo'])) {
            $pagesData = loadData('pages');
            foreach (['faq','rules','info'] as $pageKey) {
                if (isset($input['pages_seo'][$pageKey]) && is_array($input['pages_seo'][$pageKey])) {
                    if (!isset($pagesData[$pageKey])) $pagesData[$pageKey] = [];
                    foreach (['title','description'] as $seoField) {
                        if (isset($input['pages_seo'][$pageKey][$seoField])) {
                            $pagesData[$pageKey][$seoField] = sanitize($input['pages_seo'][$pageKey][$seoField]);
                        }
                    }
                }
            }
            saveData('pages', $pagesData);
        }
        if (isset($input['shop']) && is_array($input['shop'])) {
            $settings['shop'] = $settings['shop'] ?? [];
            if (isset($input['shop']['show_demo_products'])) {
                $settings['shop']['show_demo_products'] = (bool)$input['shop']['show_demo_products'];
            }
        }
        if (isset($input['payment']) && is_array($input['payment'])) {
            $settings['payment'] = mergePaymentSettings($settings['payment'] ?? [], $input['payment']);
        }
        if (isset($input['admin_password']) && $input['admin_password']) {
            $settings['admin']['password_hash'] = md5($input['admin_password']);
        }
        saveData('settings', $settings);
        jsonResponse(['success' => true, 'data' => $settings]);

    // Admin: POST /api/admin/payments/test
    case $path === 'admin/payments/test' && $method === 'POST':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        jsonResponse(['success' => true, 'data' => buildPaymentTestReport()]);

    // Admin: POST /api/admin/import
    case $path === 'admin/import' && $method === 'POST':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        if (!isset($_FILES['csv'])) jsonResponse(['success' => false, 'error' => 'No file uploaded'], 400);
        
        $type = $_POST['type'] ?? 'products'; // 'products' or 'articles'
        $file = $_FILES['csv'];
        if ($file['error'] !== UPLOAD_ERR_OK) jsonResponse(['success' => false, 'error' => 'Upload error'], 400);
        
        $handle = fopen($file['tmp_name'], 'r');
        $headers = fgetcsv($handle);
        if (!$headers) jsonResponse(['success' => false, 'error' => 'Empty CSV'], 400);
        
        $added = 0; $updated = 0; $errors = [];
        
        if ($type === 'articles') {
            $pages = getPages();
            $articles = $pages['info']['articles'] ?? [];
            $maxId = max(array_column($articles, 'id') ?: [0]);
            
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < count($headers)) { $errors[] = 'Неверный формат строки'; continue; }
                $data = array_combine($headers, $row);
                $slug = sanitize($data['slug'] ?? '');
                if (!$slug) { $errors[] = 'Пустой slug'; continue; }
                
                $existing = null;
                foreach ($articles as &$art) {
                    if ($art['slug'] === $slug) { $existing = &$art; break; }
                }
                
                $articleData = [
                    'title' => sanitize($data['title'] ?? ''),
                    'slug' => $slug,
                    'excerpt' => sanitize($data['excerpt'] ?? ''),
                    'content' => $data['content'] ?? '',
                    'image' => sanitize($data['image'] ?? 'blog-default.jpg'),
                    'date' => sanitize($data['date'] ?? date('Y-m-d')),
                    'seo_title' => sanitize($data['seo_title'] ?? $data['title'] ?? ''),
                    'seo_description' => sanitize($data['seo_description'] ?? $data['excerpt'] ?? '')
                ];
                
                if ($existing) {
                    foreach ($articleData as $k => $v) $existing[$k] = $v;
                    $updated++;
                } else {
                    $maxId++;
                    $articleData['id'] = $maxId;
                    $articles[] = $articleData;
                    $added++;
                }
                if ($added + $updated >= 5) break; // Limit for articles as requested
            }
            $pages['info']['articles'] = $articles;
            saveData('pages', $pages);
        } else {
            // Products import (limit 500)
            $products = getProducts();
            $maxId = max(array_column($products, 'id') ?: [0]);
            
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < count($headers)) { $errors[] = 'Неверный формат строки'; continue; }
                $data = array_combine($headers, $row);
                $slug = sanitize($data['slug'] ?? '');
                if (!$slug) { $errors[] = 'Пустой slug'; continue; }
                
                $existing = null;
                $existingName = sanitize($data['name'] ?? '');
                foreach ($products as &$p) {
                    // Match by slug OR by name (to prevent duplicates)
                    if ($p['slug'] === $slug || (!empty($existingName) && strtolower($p['name']) === strtolower($existingName))) {
                        $existing = &$p; break;
                    }
                }
                unset($p);
                
                $productData = [
                    'name' => sanitize($data['name'] ?? ''),
                    'slug' => $slug,
                    'category' => strtolower(sanitize($data['category'] ?? '')),
                    'subcategory' => strtolower(sanitize($data['subcategory'] ?? '')),
                    'short_description' => sanitize($data['short_description'] ?? ''),
                    'full_description' => $data['full_description'] ?? '',
                    'price' => (float)($data['price'] ?? 0),
                    'quantity' => (int)($data['quantity'] ?? 0),
                    'icon' => sanitize($data['icon'] ?? 'default.svg'),
                    'status' => ($data['status'] ?? 'active') === 'active' ? 'active' : 'inactive',
                    'cookies' => strtolower($data['cookies'] ?? 'no') === 'yes',
                    'proxy' => strtolower($data['proxy'] ?? 'no') === 'yes',
                    'email_verified' => strtolower($data['email_verified'] ?? 'no') === 'yes',
                    'country' => sanitize($data['country'] ?? 'Any'),
                    'sex' => sanitize($data['sex'] ?? 'any'),
                    'age' => (int)($data['age'] ?? date('Y')),
                    'popular' => strtolower($data['popular'] ?? 'no') === 'yes',
                    'features' => isset($data['features']) ? explode('|', $data['features']) : []
                ];
                
                if ($existing) {
                    foreach ($productData as $k => $v) $existing[$k] = $v;
                    $updated++;
                } else {
                    $maxId++;
                    $productData['id'] = $maxId;
                    $products[] = $productData;
                    $added++;
                }
                if ($added + $updated >= 500) break; // Limit for products
            }
            syncProductsInventory($products);
            saveProductsWithAutoSplit($products);
        }
        
        jsonResponse(['success' => true, 'added' => $added, 'updated' => $updated, 'errors' => $errors]);

    // Admin: PUT /api/admin/pages
    case $path === 'admin/pages' && $method === 'PUT':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        if (isset($input['pages'])) {
            saveData('pages', $input['pages']);
            jsonResponse(['success' => true]);
        }
        jsonResponse(['success' => false, 'error' => 'No pages data'], 400);

    // Admin: POST /api/admin/upload-image
    case $path === 'admin/upload-image' && $method === 'POST':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        if (!isset($_FILES['image'])) jsonResponse(['success' => false, 'error' => 'No file uploaded'], 400);
        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) jsonResponse(['success' => false, 'error' => 'Upload error'], 400);
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed)) jsonResponse(['success' => false, 'error' => 'Invalid file type'], 400);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('img_') . '.' . strtolower($ext);
        $imgType = $_POST['type'] ?? 'blog';
        $uploadDir = __DIR__ . '/../images/' . ($imgType === 'blog' ? 'blog' : 'products') . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            jsonResponse(['success' => false, 'error' => 'Failed to save file'], 500);
        }
        jsonResponse(['success' => true, 'filename' => $filename, 'url' => '/images/' . ($imgType === 'blog' ? 'blog' : 'products') . '/' . $filename]);

    // Admin: DELETE /api/admin/contacts/{index}
    case preg_match('/^admin\/contacts\/(\d+)$/', $path, $m) && $method === 'DELETE':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $logFile = __DIR__ . '/../data/contacts.json';
        $logs = file_exists($logFile) ? (json_decode(file_get_contents($logFile), true) ?? []) : [];
        $idx = (int)$m[1];
        if (!isset($logs[$idx])) jsonResponse(['success' => false, 'error' => 'Not found'], 404);
        array_splice($logs, $idx, 1);
        file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        jsonResponse(['success' => true]);

    // Admin: DELETE /api/admin/products/all (delete all products)
    case $path === 'admin/products/all' && $method === 'DELETE':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        saveData('products', []);
        jsonResponse(['success' => true, 'message' => 'Все товары удалены']);

    // Admin: DELETE /api/admin/contacts/clear
    case $path === 'admin/contacts/clear' && $method === 'DELETE':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $logFile = __DIR__ . '/../data/contacts.json';
        file_put_contents($logFile, '[]');
        jsonResponse(['success' => true]);

    // Admin: POST /api/admin/login
    case $path === 'admin/login' && $method === 'POST':
        $settings = getSettings();
        $login = $input['login'] ?? '';
        $password = $input['password'] ?? '';
        $adminLogin = $settings['admin']['login'] ?? 'admin';
        $adminHash = $settings['admin']['password_hash'] ?? md5('admin');
        if ($login === $adminLogin && md5($password) === $adminHash) {
            $_SESSION['admin_logged_in'] = true;
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Неверный логин или пароль'], 401);
        }

    // Admin: POST /api/admin/logout
    case $path === 'admin/logout' && $method === 'POST':
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        jsonResponse(['success' => true]);

    // Admin: GET /api/admin/stats
    case $path === 'admin/stats' && $method === 'GET':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $products = getProducts();
        $activeProducts = array_filter($products, fn($p) => $p['status'] === 'active');
        $totalQuantity = array_sum(array_column(array_values($activeProducts), 'quantity'));
        $categories = getCategories();
        jsonResponse([
            'success' => true,
            'data' => [
                'total_products' => count($activeProducts),
                'total_quantity' => $totalQuantity,
                'total_categories' => count($categories),
                'sales_today' => rand(5, 50),
                'views_today' => rand(100, 1000),
                'revenue_today' => rand(5000, 50000)
            ]
        ]);

    // Admin: POST /api/admin/generate-sitemap
    case $path === 'admin/generate-sitemap' && $method === 'POST':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        // Используем динамический домен для правильной работы на любом домене
        $sitemapXml = generateSitemap();
        $sitemapPath = __DIR__ . '/../sitemap.xml';
        if (file_put_contents($sitemapPath, $sitemapXml) !== false) {
            jsonResponse(['success' => true, 'message' => 'Sitemap успешно сгенерирован', 'url' => '/sitemap.xml']);
        } else {
            jsonResponse(['success' => false, 'error' => 'Ошибка записи файла sitemap.xml']);
        }

    // Admin: GET /api/admin/export-products (скачать все товары в CSV)
    case $path === 'admin/export-products' && $method === 'GET':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $products = getProducts();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products_export_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        $output = fopen('php://output', 'w');
        // BOM for Excel UTF-8
        fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, ['name','slug','category','subcategory','short_description','full_description','price','quantity','icon','status','cookies','proxy','email_verified','country','sex','age','popular','features']);
        foreach ($products as $p) {
            fputcsv($output, [
                $p['name'] ?? '',
                $p['slug'] ?? '',
                $p['category'] ?? '',
                $p['subcategory'] ?? '',
                $p['short_description'] ?? '',
                $p['full_description'] ?? '',
                $p['price'] ?? 0,
                $p['quantity'] ?? 0,
                $p['icon'] ?? 'default.svg',
                $p['status'] ?? 'active',
                !empty($p['cookies']) ? 'yes' : 'no',
                !empty($p['proxy']) ? 'yes' : 'no',
                !empty($p['email_verified']) ? 'yes' : 'no',
                $p['country'] ?? 'Any',
                $p['sex'] ?? 'any',
                $p['age'] ?? date('Y'),
                !empty($p['popular']) ? 'yes' : 'no',
                implode('|', $p['features'] ?? [])
            ]);
        }
        fclose($output);
        exit;

    // Admin: GET /api/admin/export-product-items (скачать аккаунты конкретного товара в TXT)
    case $path === 'admin/export-product-items' && $method === 'GET':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
        if (!$productId) {
            jsonResponse(['success' => false, 'error' => 'Missing product_id'], 400);
        }
        $products = getProducts();
        $product = null;
        foreach ($products as $p) {
            if ((int)($p['id'] ?? 0) === $productId) {
                $product = $p;
                break;
            }
        }
        if (!$product) {
            jsonResponse(['success' => false, 'error' => 'Product not found'], 404);
        }
        $items = array_values(array_filter(array_map('trim', $product['items'] ?? []), fn($line) => $line !== ''));
        if (empty($items)) {
            jsonResponse(['success' => false, 'error' => 'Для этого товара нет загруженных аккаунтов'], 404);
        }
        $safeSlug = preg_replace('/[^a-z0-9\-_]+/i', '-', (string)($product['slug'] ?? 'product-' . $productId));
        $safeSlug = trim($safeSlug, '-');
        if ($safeSlug === '') {
            $safeSlug = 'product-' . $productId;
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $safeSlug . '_accounts_' . date('Y-m-d') . '.txt"');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo implode(PHP_EOL, $items) . PHP_EOL;
        exit;

    // Admin: POST /api/admin/upload-items (загрузить товары из TXT для конкретного товара)
    case $path === 'admin/upload-items' && $method === 'POST':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        if (!$productId || !isset($_FILES['items_file'])) {
            jsonResponse(['success' => false, 'error' => 'Missing product_id or file'], 400);
        }
        $file = $_FILES['items_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['success' => false, 'error' => 'Upload error: ' . $file['error']], 400);
        }
        $content = file_get_contents($file['tmp_name']);
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        if (empty($lines)) {
            jsonResponse(['success' => false, 'error' => 'File is empty'], 400);
        }
        $products = getProducts();
        $found = false;
        foreach ($products as &$p) {
            if ($p['id'] === $productId) {
                $p['items'] = array_values($lines);
                $p['quantity'] = count($lines);
                $p['is_demo'] = false;
                $found = true;
                break;
            }
        }
        unset($p);
        if (!$found) {
            jsonResponse(['success' => false, 'error' => 'Product not found'], 404);
        }
        syncProductsInventory($products);
        saveProductsWithAutoSplit($products);
        jsonResponse(['success' => true, 'message' => 'Товары загружены', 'count' => count($lines)]);

    // Admin: POST /api/admin/clear-cache
    case $path === 'admin/clear-cache' && $method === 'POST':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        // Delete cached sitemap if exists
        $sitemapPath = __DIR__ . '/../sitemap.xml';
        if (file_exists($sitemapPath)) @unlink($sitemapPath);
        // Clear PHP opcache
        if (function_exists('opcache_reset')) @opcache_reset();
        // Clear PHP session data
        if (session_status() === PHP_SESSION_NONE) session_start();
        $adminSession = $_SESSION['admin_logged_in'] ?? false;
        session_destroy();
        // Restart session to keep admin logged in
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['admin_logged_in'] = $adminSession;
        // Remove any cached data files (but keep actual data)
        $cacheDir = __DIR__ . '/../cache';
        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir . '/*.cache') as $f) @unlink($f);
        }
        jsonResponse(['success' => true, 'message' => 'Кеш очищен. Следы старого домена удалены.']);

    // Admin: PUT /api/admin/crypto/wallets
    case $path === 'admin/crypto/wallets' && $method === 'PUT':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $wallets = $input['wallets'] ?? [];
        if (!is_array($wallets)) jsonResponse(['success' => false, 'error' => 'Invalid wallets data'], 400);
        $tokens = getCryptoTokens();
        foreach ($tokens as &$token) {
            $code = strtoupper((string)($token['code'] ?? ''));
            if ($code !== '' && array_key_exists($code, $wallets)) {
                $token['wallet'] = trim((string)$wallets[$code]);
            }
        }
        unset($token);
        saveCryptoTokens($tokens);
        jsonResponse(['success' => true, 'message' => 'Адреса кошельков сохранены']);

    // Admin: GET /api/admin/advertising
    case $path === 'admin/advertising' && $method === 'GET':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $adData = getAdvertisingData();
        jsonResponse(['success' => true, 'data' => $adData]);

    // Admin: PUT /api/admin/advertising/spots/{id}
    case preg_match('/^admin\/advertising\/spots\/([\w_]+)$/', $path, $m) && $method === 'PUT':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $spotId = $m[1];
        $adData = getAdvertisingData();
        $found = false;
        foreach ($adData['spots'] as &$spot) {
            if ($spot['id'] === $spotId) {
                if (isset($input['name']))        $spot['name']        = sanitize($input['name']);
                if (isset($input['description'])) $spot['description'] = sanitize($input['description']);
                if (isset($input['location']))    $spot['location']    = sanitize($input['location']);
                if (isset($input['price_week']))  $spot['price_week']  = max(0, (int)$input['price_week']);
                if (isset($input['price_month'])) $spot['price_month'] = max(0, (int)$input['price_month']);
                if (isset($input['max_banners'])) $spot['max_banners'] = max(1, min(10, (int)$input['max_banners']));
                if (isset($input['enabled']))     $spot['enabled']     = (bool)$input['enabled'];
                $found = true;
                break;
            }
        }
        unset($spot);
        if (!$found) jsonResponse(['success' => false, 'error' => 'Spot not found'], 404);
        saveAdvertisingData($adData);
        jsonResponse(['success' => true, 'data' => $adData]);

    // Admin: POST /api/admin/advertising/banners
    case $path === 'admin/advertising/banners' && $method === 'POST':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $adData = getAdvertisingData();
        $spotId = sanitize($input['spot_id'] ?? '');
        // Validate spot exists
        $spotExists = false;
        foreach ($adData['spots'] as $s) { if ($s['id'] === $spotId) { $spotExists = true; break; } }
        if (!$spotExists) jsonResponse(['success' => false, 'error' => 'Рекламное место не найдено'], 400);
        // Count existing banners for this spot
        $existingCount = count(array_filter($adData['banners'], fn($b) => $b['spot_id'] === $spotId && ($b['active'] ?? false)));
        $spot = null;
        foreach ($adData['spots'] as $s) { if ($s['id'] === $spotId) { $spot = $s; break; } }
        if ($existingCount >= ($spot['max_banners'] ?? 10)) {
            jsonResponse(['success' => false, 'error' => 'Достигнут лимит баннеров для этого места (' . ($spot['max_banners'] ?? 10) . ')'], 400);
        }
        $newBanner = [
            'id'          => time() . '_' . rand(1000, 9999),
            'spot_id'     => $spotId,
            'title'       => sanitize($input['title'] ?? ''),
            'advertiser'  => sanitize($input['advertiser'] ?? ''),
            'url'         => sanitize($input['url'] ?? ''),
            'image_url'   => sanitize($input['image_url'] ?? ''),
            'alt_text'    => sanitize($input['alt_text'] ?? ''),
            'type'        => in_array($input['type'] ?? '', ['image', 'text']) ? $input['type'] : 'image',
            'active'      => (bool)($input['active'] ?? true),
            'date_start'  => sanitize($input['date_start'] ?? date('Y-m-d')),
            'date_end'    => sanitize($input['date_end'] ?? ''),
            'created_at'  => date('Y-m-d H:i:s'),
            'notes'       => sanitize($input['notes'] ?? '')
        ];
        $adData['banners'][] = $newBanner;
        saveAdvertisingData($adData);
        jsonResponse(['success' => true, 'data' => $newBanner]);

    // Admin: PUT /api/admin/advertising/banners/{id}
    case preg_match('/^admin\/advertising\/banners\/([\w_]+)$/', $path, $m) && $method === 'PUT':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $bannerId = $m[1];
        $adData = getAdvertisingData();
        $found = false;
        foreach ($adData['banners'] as &$banner) {
            if ($banner['id'] === $bannerId) {
                if (isset($input['title']))      $banner['title']      = sanitize($input['title']);
                if (isset($input['advertiser'])) $banner['advertiser'] = sanitize($input['advertiser']);
                if (isset($input['url']))        $banner['url']        = sanitize($input['url']);
                if (isset($input['image_url']))  $banner['image_url']  = sanitize($input['image_url']);
                if (isset($input['alt_text']))   $banner['alt_text']   = sanitize($input['alt_text']);
                if (isset($input['type']))       $banner['type']       = in_array($input['type'], ['image','text']) ? $input['type'] : 'image';
                if (isset($input['active']))     $banner['active']     = (bool)$input['active'];
                if (isset($input['date_start'])) $banner['date_start'] = sanitize($input['date_start']);
                if (isset($input['date_end']))   $banner['date_end']   = sanitize($input['date_end']);
                if (isset($input['notes']))      $banner['notes']      = sanitize($input['notes']);
                $banner['updated_at'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        unset($banner);
        if (!$found) jsonResponse(['success' => false, 'error' => 'Banner not found'], 404);
        saveAdvertisingData($adData);
        jsonResponse(['success' => true, 'data' => $adData]);

    // Admin: DELETE /api/admin/advertising/banners/{id}
    case preg_match('/^admin\/advertising\/banners\/([\w_]+)$/', $path, $m) && $method === 'DELETE':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $bannerId = $m[1];
        $adData = getAdvertisingData();
        $before = count($adData['banners']);
        $adData['banners'] = array_values(array_filter($adData['banners'], fn($b) => $b['id'] !== $bannerId));
        if (count($adData['banners']) === $before) jsonResponse(['success' => false, 'error' => 'Banner not found'], 404);
        saveAdvertisingData($adData);
        jsonResponse(['success' => true]);

    // Admin: POST /api/admin/advertising/upload-banner (загрузка изображения баннера)
    case $path === 'admin/advertising/upload-banner' && $method === 'POST':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        if (!isset($_FILES['banner_image'])) jsonResponse(['success' => false, 'error' => 'No file'], 400);
        $file = $_FILES['banner_image'];
        if ($file['error'] !== UPLOAD_ERR_OK) jsonResponse(['success' => false, 'error' => 'Upload error'], 400);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mimeType, $allowedTypes)) jsonResponse(['success' => false, 'error' => 'Invalid file type'], 400);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'banner_' . time() . '_' . rand(1000, 9999) . '.' . strtolower($ext);
        $uploadDir = __DIR__ . '/../images/banners/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            jsonResponse(['success' => false, 'error' => 'Failed to save file'], 500);
        }
        jsonResponse(['success' => true, 'url' => '/images/banners/' . $filename]);

    // Inline Editor: POST /api/?path=admin/inline-save
    case $path === 'admin/inline-save' && $method === 'POST':
        if (!isAdminLoggedIn()) jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        $page = $input['page'] ?? '';
        $changes = $input['changes'] ?? [];
        if (!is_array($changes) || empty($changes)) {
            jsonResponse(['success' => false, 'error' => 'No changes provided'], 400);
        }
        $saved = [];
        $errors = [];
        foreach ($changes as $key => $value) {
            $parts = explode(':', $key, 3);
            if (count($parts) < 2) { $errors[] = "Invalid key: $key"; continue; }
            $type = $parts[0];
            $id = $parts[1];
            $field = $parts[2] ?? null;
            $cleanValue = strip_tags($value, '<b><strong><i><em><u><a><br><p><ul><ol><li><h2><h3><h4><span>');
            switch ($type) {
                case 'settings':
                    $s2 = getSettings();
                    if ($field) { if (!isset($s2[$id])) $s2[$id] = []; $s2[$id][$field] = $cleanValue; }
                    else $s2[$id] = $cleanValue;
                    saveData('settings', $s2); $saved[] = $key; break;
                case 'page':
                    $pg = getPages();
                    if (!isset($pg[$id])) $pg[$id] = [];
                    if ($field) $pg[$id][$field] = $cleanValue;
                    saveData('pages', $pg); $saved[] = $key; break;
                case 'article':
                    $pg = getPages();
                    $arts = $pg['info']['articles'] ?? [];
                    $found = false;
                    foreach ($arts as &$art) {
                        if ($art['slug'] === $id) {
                            $art[$field ?? 'content'] = ($field === 'content') ? $value : $cleanValue;
                            $found = true; break;
                        }
                    }
                    unset($art);
                    if ($found) { $pg['info']['articles'] = $arts; saveData('pages', $pg); $saved[] = $key; }
                    else $errors[] = "Article not found: $id";
                    break;
                case 'product':
                    $prods = getProducts();
                    $found = false;
                    foreach ($prods as &$prod) {
                        if ($prod['slug'] === $id && $field) { $prod[$field] = $cleanValue; $found = true; break; }
                    }
                    unset($prod);
                    if ($found) { saveData('products', $prods); $saved[] = $key; }
                    else $errors[] = "Product not found: $id";
                    break;
                default:
                    $errors[] = "Unknown type: $type";
            }
        }
        if (!empty($saved)) {
            jsonResponse(['success' => true, 'saved' => $saved, 'errors' => $errors]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Nothing saved', 'errors' => $errors], 400);
        }

    default:
        jsonResponse(['success' => false, 'error' => 'Not found'], 404);
}
?>
