<?php
$currentPage = 'oplata';
$itemSlug = sanitize($_GET['item'] ?? '');
$qty = max(1, (int)($_GET['qty'] ?? 1));
$product = $itemSlug ? getProductBySlug($itemSlug) : null;
$status = sanitize($_GET['status'] ?? '');
$orderNumber = sanitize($_GET['order'] ?? '');
$settings = getSettings();
$paymentSettings = $settings['payment'] ?? [];
$yooEnabled = !empty($paymentSettings['methods']['yoomoney']['enabled']);
$cryptoEnabled = !empty($paymentSettings['methods']['crypto']['enabled']);
$demoEnabled = !empty($paymentSettings['methods']['demo']['enabled']);
$cryptoTokensCatalog = getCryptoTokens();
$cryptoRatesCache = getCryptoUsdRates();
$cryptoRates = $cryptoRatesCache['rates'] ?? [];
$usdPerRub = (float)($cryptoRatesCache['usd_per_rub'] ?? 0);
$rubPerUsd = (float)($cryptoRatesCache['rub_per_usd'] ?? 0);

$cryptoTokensView = [];
foreach ($cryptoTokensCatalog as $token) {
    $code = strtoupper((string)($token['code'] ?? ''));
    if ($code === '') continue;
    $rate = $cryptoRates[$code] ?? [];
    $wallet = trim((string)($token['wallet'] ?? ''));
    $walletMask = $wallet !== '' && mb_strlen($wallet) > 14
        ? mb_substr($wallet, 0, 7) . '...' . mb_substr($wallet, -6)
        : $wallet;
    $cryptoTokensView[] = [
        'code' => $code,
        'name' => (string)($token['name'] ?? $code),
        'network' => (string)($token['network'] ?? ''),
        'wallet' => $wallet,
        'wallet_mask' => $walletMask,
        'symbol' => (string)($token['usd_symbol'] ?? $code),
        'decimals' => (int)($token['decimals'] ?? 8),
        'confirmations_required' => (int)($token['confirmations_required'] ?? 1),
        'rate_usd' => (float)($rate['usd'] ?? 0),
        'rate_rub' => (float)($rate['rub'] ?? 0)
    ];
}
$defaultTokenCode = $cryptoTokensView[0]['code'] ?? '';

$siteName = $settings['site']['name'] ?? 'Магазин аккаунтов';
$oplataTitle = !empty($settings['seo']['oplata_title']) ? $settings['seo']['oplata_title'] : ('Оформление заказа | ' . $siteName);
$oplataDesc = !empty($settings['seo']['oplata_description']) ? $settings['seo']['oplata_description'] : 'Оформите заказ и выберите удобный способ оплаты: YooMoney или криптовалюта.';
$metaTags = metaTags($oplataTitle, $oplataDesc, '', '/oplata/');

require __DIR__ . '/../includes/header.php';
?>
<style>
.checkout-flow{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.85fr);gap:24px}
.checkout-card,.checkout-sidebar-card,.payment-status-card{background:linear-gradient(180deg,rgba(15,23,42,.92),rgba(15,23,42,.84));border:1px solid rgba(148,163,184,.18);border-radius:24px;box-shadow:0 24px 60px rgba(2,6,23,.28)}
.checkout-card{padding:32px}.checkout-sidebar-card{padding:28px;position:sticky;top:110px}.payment-status-card{margin-bottom:24px;padding:20px 24px}
.step-label{display:inline-flex;align-items:center;gap:10px;padding:8px 12px;border-radius:999px;background:rgba(79,70,229,.14);color:#a5b4fc;font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:18px}
.payment-methods-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:20px 0}
.payment-method-card{position:relative;border:2px solid rgba(148,163,184,.18);border-radius:18px;padding:20px 16px;background:rgba(15,23,42,.54);cursor:pointer;transition:all .2s ease;text-align:center}
.payment-method-card:hover{border-color:rgba(99,102,241,.5);box-shadow:0 12px 24px rgba(2,6,23,.2);transform:translateY(-2px)}
.payment-method-card.selected{border-color:rgba(99,102,241,.85);background:rgba(79,70,229,.16);box-shadow:0 16px 32px rgba(79,70,229,.2)}
.payment-method-card.selected::after{content:'';position:absolute;inset:6px;border-radius:14px;border:1px solid rgba(129,140,248,.32);pointer-events:none}
.payment-method-icon{width:52px;height:52px;border-radius:14px;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem}
.payment-method-icon.yoomoney{background:rgba(255,80,0,.12);color:#ff5000}.payment-method-icon.crypto{background:rgba(245,158,11,.12);color:#f59e0b}.payment-method-icon.demo{background:rgba(16,185,129,.12);color:#10b981}
.payment-method-name{font-weight:700;font-size:1rem;margin-bottom:4px}.payment-method-desc{font-size:.8rem;color:var(--text-muted)}
.payment-method-check{position:absolute;top:12px;right:12px;width:22px;height:22px;border-radius:50%;background:var(--primary);color:#fff;display:none;align-items:center;justify-content:center;font-size:.7rem}
.payment-method-card.selected .payment-method-check{display:flex}
.crypto-token-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:16px}
.crypto-token-card{position:relative;border:1px solid rgba(148,163,184,.18);border-radius:16px;padding:14px;background:rgba(15,23,42,.54);cursor:pointer;transition:all .18s ease}
.crypto-token-card:hover{transform:translateY(-2px);border-color:rgba(99,102,241,.5)}.crypto-token-card.selected{border-color:rgba(99,102,241,.8);background:rgba(79,70,229,.16);box-shadow:0 16px 30px rgba(79,70,229,.18)}
.crypto-token-head{display:flex;align-items:center;justify-content:space-between;gap:8px}.crypto-token-title{font-weight:700;font-size:.9rem;margin-bottom:2px}.crypto-token-meta{font-size:.75rem;color:var(--text-muted)}.crypto-token-code{font-weight:800;font-size:.82rem;color:#fff;background:rgba(79,70,229,.35);border:1px solid rgba(129,140,248,.4);padding:3px 8px;border-radius:6px;white-space:nowrap;letter-spacing:.04em}.crypto-token-amount{margin-top:8px;font-size:.82rem;color:var(--text-secondary)}.crypto-token-wallet{margin-top:4px;font-size:.72rem;color:var(--text-muted);font-family:monospace}
.payment-summary-box{background:rgba(15,23,42,.6);border:1px solid rgba(148,163,184,.12);border-radius:16px;padding:20px;margin-top:24px}
.payment-summary-box h3{margin-bottom:14px;font-size:1rem}
.summary-stack{display:flex;flex-direction:column;gap:10px}.summary-row{display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:.9rem}.summary-row--accent strong{color:var(--primary);font-size:1.05rem}.summary-hint{font-size:.78rem;color:var(--text-muted);margin-top:4px}.rate-footnote{font-size:.75rem;color:var(--text-muted);margin-top:12px;line-height:1.5}
.quantity-line{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px;background:rgba(15,23,42,.5);border:1px solid rgba(148,163,184,.12);border-radius:14px;margin:20px 0}
.quantity-selector{display:flex;align-items:center;gap:8px}
.qty-btn{width:36px;height:36px;border-radius:10px;border:1px solid var(--border);background:var(--bg-hover);color:var(--text-primary);font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s}
.qty-btn:hover{background:var(--primary);border-color:var(--primary);color:#fff}
.qty-input{width:64px;height:36px;text-align:center;border-radius:10px;border:1px solid var(--border);background:var(--bg);color:var(--text-primary);font-size:1rem;font-weight:700}
.sidebar-stack{display:flex;flex-direction:column;gap:10px}.sidebar-row{display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:.88rem}.sidebar-row--accent strong{color:var(--primary);font-size:1rem}.sidebar-muted{font-size:.78rem;color:var(--text-muted)}
.order-lines{display:flex;flex-direction:column;gap:10px;margin:14px 0}.order-line{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px;background:rgba(15,23,42,.5);border:1px solid rgba(148,163,184,.1);border-radius:12px}.order-line-price{font-weight:700;font-size:.9rem;color:var(--primary);white-space:nowrap}.order-total-line{display:flex;justify-content:space-between;padding:14px 0 0;border-top:1px solid rgba(148,163,184,.12);margin-top:8px;font-weight:700}
.payment-status-top{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}.payment-status-badge{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;background:rgba(245,158,11,.14);color:#fbbf24;font-weight:700;transition:all .2s ease}.payment-status-card.status-fail{border-color:rgba(239,68,68,.45);box-shadow:0 24px 60px rgba(127,29,29,.28)}.payment-status-card.status-fail #paymentReturnText{color:#fecaca;font-size:1.02rem;font-weight:600}.payment-status-badge.status-fail{padding:16px 24px;font-size:1.25rem;font-weight:900;letter-spacing:.04em;text-transform:uppercase;background:rgba(239,68,68,.18);color:#ef4444;border:2px solid rgba(239,68,68,.5)}.payment-status-badge.status-fail span{line-height:1.2}
.crypto-payment-layout{display:grid;grid-template-columns:1fr auto;gap:24px;margin-top:20px}
.crypto-payment-amount-box{background:rgba(15,23,42,.6);border:1px solid rgba(148,163,184,.12);border-radius:16px;padding:20px;margin-bottom:16px}
.crypto-payment-amount-label{font-size:.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px}.crypto-payment-amount-value{font-size:1.8rem;font-weight:800;color:var(--text-primary)}.crypto-payment-amount-subline{font-size:.85rem;color:var(--text-muted);margin-top:4px}
.crypto-payment-details{display:flex;flex-direction:column;gap:10px}.crypto-payment-detail-row{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;font-size:.85rem;padding:8px 0;border-bottom:1px solid rgba(148,163,184,.08)}.crypto-payment-detail-row code{font-family:monospace;font-size:.78rem;word-break:break-all;color:var(--text-secondary)}
.crypto-payment-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}.btn-crypto-secondary{display:flex;align-items:center;gap:8px;padding:10px 16px;border-radius:10px;border:1px solid var(--border);background:var(--bg-hover);color:var(--text-primary);font-size:.82rem;cursor:pointer;transition:all .15s}.btn-crypto-secondary:hover{border-color:var(--primary);color:var(--primary)}
.crypto-payment-qr-panel{display:flex;flex-direction:column;align-items:center;gap:10px}.crypto-qr-box{width:200px;height:200px;border-radius:16px;overflow:hidden;background:#fff;padding:8px}.crypto-qr-box img{width:100%;height:100%;object-fit:contain}.crypto-payment-qr-caption{font-size:.8rem;color:var(--text-muted);text-align:center}.crypto-payment-hint{font-size:.75rem;color:var(--text-muted);text-align:center;max-width:200px}
.payment-status-inline{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:12px;background:rgba(245,158,11,.14);color:#fbbf24;font-weight:600;margin-bottom:16px}
.payment-wait-spinner{display:inline-block;width:16px;height:16px;border:2px solid currentColor;border-top-color:transparent;border-radius:50%;animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.yoomoney-step{text-align:center;padding:20px 0}.yoomoney-step .yoo-logo{font-size:3rem;margin-bottom:16px;color:#ff5000}
.flow-note{font-size:.78rem;color:var(--text-muted);margin-top:14px;line-height:1.5}
.form-group{margin-bottom:16px}.form-group label{display:block;font-size:.82rem;font-weight:600;color:var(--text-muted);margin-bottom:6px}
.form-group input[type="email"],.form-group input[type="text"]{width:100%;padding:12px 14px;background:rgba(15,23,42,.7);border:1px solid var(--border);border-radius:12px;color:var(--text-primary);font-size:.9rem;outline:none;transition:border-color .2s}
.form-group input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(79,70,229,.15)}
.checkout-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-top:24px}
.payment-rate-box{background:rgba(15,23,42,.5);border:1px solid rgba(148,163,184,.1);border-radius:14px;padding:16px;margin-top:16px}.payment-rate-box h3{font-size:.9rem;margin-bottom:12px}
.sidebar-trust-list{display:flex;flex-direction:column;gap:12px;margin-top:20px}.sidebar-trust-item{display:flex;align-items:flex-start;gap:12px;font-size:.82rem;color:var(--text-muted)}.sidebar-trust-item i{color:var(--primary);margin-top:2px;flex-shrink:0}
.btn-lg{padding:16px 28px;font-size:1rem}.btn-pulse{animation:pulse-glow 2s ease-in-out infinite}
@keyframes pulse-glow{0%,100%{box-shadow:0 0 0 0 rgba(79,70,229,.4)}50%{box-shadow:0 0 0 12px rgba(79,70,229,0)}}
.toast-container{position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px}
.toast{display:flex;align-items:center;gap:10px;padding:12px 18px;border-radius:12px;font-size:.875rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.3);animation:slideIn .3s ease}
.toast.success{background:rgba(16,185,129,.9);color:#fff}.toast.error{background:rgba(239,68,68,.9);color:#fff}.toast.hide{animation:slideOut .3s ease forwards}
@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes slideOut{from{transform:translateX(0);opacity:1}to{transform:translateX(100%);opacity:0}}
@media(max-width:900px){.checkout-flow{grid-template-columns:1fr}.checkout-sidebar-card{position:static}.crypto-token-grid{grid-template-columns:1fr}.crypto-payment-layout{grid-template-columns:1fr}.checkout-form-grid{grid-template-columns:1fr}}
@media(max-width:600px){.payment-methods-grid{grid-template-columns:1fr}.checkout-card{padding:20px}}
</style>

<div class="page-hero">
    <div class="container">
        <?php echo breadcrumbs([['name' => 'Главная', 'url' => '/'], ['name' => 'Оформление заказа']]); ?>
        <h1>Оформление заказа</h1>
        <p>Введите email, выберите удобный способ оплаты и завершите покупку.</p>
    </div>
</div>

<section class="section">
    <div class="container">

        <div class="payment-status-card" id="paymentReturnCard" <?php echo (!$status && !$orderNumber) ? 'hidden' : ''; ?>>
            <div class="payment-status-top">
                <div>
                    <h3 style="margin-bottom:6px;">Статус заказа <?php echo $orderNumber ? htmlspecialchars($orderNumber) : ''; ?></h3>
                    <p class="section-text" id="paymentReturnText">
                        <?php if ($status === 'success'): ?>Проверяем подтверждение оплаты и статус выдачи товара.<?php elseif ($status === 'fail'): ?>Платёж не был завершён. Вы можете вернуться и повторить попытку.<?php else: ?>Заказ создан. Ожидаем обновление статуса оплаты.<?php endif; ?>
                    </p>
                </div>
                <div class="payment-status-badge" id="paymentReturnBadge">
                    <i class="fa-solid fa-clock"></i>
                    <span><?php echo $orderNumber ? 'Заказ ' . htmlspecialchars($orderNumber) : 'Ожидает оплаты'; ?></span>
                </div>
            </div>
        </div>

        <div class="checkout-flow">
            <div>

                <!-- ШАГ 1: Выбор способа оплаты -->
                <div class="checkout-card" id="checkoutStep1">
                    <div class="step-label">
                        <i class="fa-solid fa-credit-card"></i>
                        Шаг 1 · Способ оплаты
                    </div>
                    <h2>Выберите способ оплаты</h2>
                    <p class="section-text">Введите email для получения товара и выберите удобный способ оплаты.</p>

                    <div class="checkout-form-grid">
                        <div class="form-group">
                            <label for="payEmail">Email для получения товара *</label>
                            <input type="email" id="payEmail" name="email" placeholder="your@email.com" required>
                        </div>
                        <div class="form-group">
                            <label for="payEmailConfirm">Подтвердите email *</label>
                            <input type="email" id="payEmailConfirm" name="email_confirm" placeholder="your@email.com" required>
                        </div>
                    </div>

                    <?php if ($product): ?>
                    <div class="quantity-line">
                        <div>
                            <div style="font-weight:700;margin-bottom:4px;">Количество товара</div>
                            <div class="summary-hint">Изменение количества мгновенно пересчитывает итоговую сумму.</div>
                        </div>
                        <div class="quantity-selector">
                            <button type="button" class="qty-btn" onclick="changePayQty(-1)">−</button>
                            <input type="number" class="qty-input" id="payQty" value="<?php echo $qty; ?>" min="1" max="<?php echo max(1, (int)$product['quantity']); ?>">
                            <button type="button" class="qty-btn" onclick="changePayQty(1)">+</button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div style="margin-top:24px;">
                        <div style="font-weight:700;font-size:1rem;margin-bottom:4px;">Способ оплаты</div>
                        <div class="summary-hint" style="margin-bottom:0;">Выберите один из доступных способов оплаты.</div>
                    </div>

                    <div class="payment-methods-grid" id="paymentMethodsGrid">
                        <?php if ($yooEnabled): ?>
                        <div class="payment-method-card selected" data-method="yoomoney" onclick="selectPaymentMethod('yoomoney')">
                            <div class="payment-method-check"><i class="fa-solid fa-check"></i></div>
                            <div class="payment-method-icon yoomoney"><i class="fa-solid fa-wallet"></i></div>
                            <div class="payment-method-name">YooMoney</div>
                            <div class="payment-method-desc">Банковская карта или кошелёк YooMoney</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($cryptoEnabled): ?>
                        <div class="payment-method-card <?php echo !$yooEnabled ? 'selected' : ''; ?>" data-method="crypto" onclick="selectPaymentMethod('crypto')">
                            <div class="payment-method-check"><i class="fa-solid fa-check"></i></div>
                            <div class="payment-method-icon crypto"><i class="fa-brands fa-bitcoin"></i></div>
                            <div class="payment-method-name">Криптовалюта</div>
                            <div class="payment-method-desc">BTC, ETH, USDT и другие токены</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($demoEnabled): ?>
                        <div class="payment-method-card <?php echo !$yooEnabled && !$cryptoEnabled ? 'selected' : ''; ?>" data-method="demo" onclick="selectPaymentMethod('demo')">
                            <div class="payment-method-check"><i class="fa-solid fa-check"></i></div>
                            <div class="payment-method-icon demo"><i class="fa-solid fa-flask"></i></div>
                            <div class="payment-method-name">Демо-режим</div>
                            <div class="payment-method-desc">Тестовая оплата без списания средств</div>
                        </div>
                        <?php endif; ?>
                        <?php if (!$yooEnabled && !$cryptoEnabled && !$demoEnabled): ?>
                        <div class="alert alert-warning" style="grid-column:1/-1;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>Все способы оплаты временно отключены. Обратитесь к администратору.</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Блок выбора токена (показывается только при crypto) -->
                    <div id="cryptoTokenSection" style="display:none;margin-top:24px;">
                        <div style="font-weight:700;font-size:1rem;margin-bottom:4px;">Выберите токен</div>
                        <div class="summary-hint" style="margin-bottom:0;">Выберите криптовалюту для оплаты. Сумма пересчитывается автоматически.</div>
                        <div class="crypto-token-grid" id="cryptoTokenGrid">
                            <?php foreach ($cryptoTokensView as $index => $token): ?>
                            <button type="button" class="crypto-token-card <?php echo $index === 0 ? 'selected' : ''; ?>"
                                data-token="<?php echo htmlspecialchars($token['code']); ?>"
                                onclick="selectCryptoToken('<?php echo htmlspecialchars($token['code']); ?>')">
                                <div class="crypto-token-head">
                                    <div>
                                        <div class="crypto-token-title"><?php echo htmlspecialchars($token['name']); ?></div>
                                        <div class="crypto-token-meta"><?php echo htmlspecialchars($token['network']); ?></div>
                                    </div>
                                    <div class="crypto-token-code"><?php echo htmlspecialchars($token['code']); ?></div>
                                </div>
                                <div class="crypto-token-amount" data-role="token-amount">-</div>
                                <div class="crypto-token-wallet"><?php echo htmlspecialchars($token['wallet_mask']); ?></div>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Итоговая сумма -->
                    <div class="payment-summary-box">
                        <h3>Сумма заказа</h3>
                        <div class="summary-stack">
                            <div class="summary-row">
                                <span>Стоимость заказа</span>
                                <strong id="orderTotalRub">0 ₽</strong>
                            </div>
                            <div class="summary-row" id="usdRow" style="display:none;">
                                <span>Эквивалент в USD</span>
                                <strong id="orderTotalUsd">$0.00</strong>
                            </div>
                            <div class="summary-row summary-row--accent" id="tokenRow" style="display:none;">
                                <span id="selectedTokenLabel">Сумма в токене</span>
                                <strong id="selectedTokenAmount">-</strong>
                            </div>
                        </div>
                        <div class="rate-footnote" id="ratesFootnote"></div>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:12px;margin-top:20px;">
	                        <button type="button" class="btn btn-primary btn-lg btn-pulse" id="proceedToPayBtn" onclick="proceedToPayment()" style="width:100%;">
	                            <i class="fa-solid fa-credit-card"></i> Оплатить заказ <span id="payBtnTotal"></span>
	                        </button>
	                        <a href="/" class="btn btn-secondary btn-lg" style="width:100%;text-align:center;display:flex;align-items:center;justify-content:center;gap:8px;">
	                            <i class="fa-solid fa-basket-shopping"></i> Продолжить покупки
	                        </a>
	                    </div>
	                    <div class="flow-note">
	                        После нажатия система создаст заказ и либо перенаправит вас к выбранному способу оплаты, либо сразу покажет результат демо-покупки с выдачей товара.
	                    </div>
                </div>

                <!-- ШАГ 2: Крипто-оплата -->
                <div class="checkout-card" id="checkoutStep2Crypto" hidden>
                    <div class="step-label">
                        <i class="fa-solid fa-qrcode"></i>
                        Шаг 2 · Оплата криптовалютой
                    </div>
                    <div class="payment-status-inline" id="cryptoInlineStatus">
                        <span class="payment-wait-spinner" aria-hidden="true"></span>
                        <span id="cryptoInlineStatusText">Ожидает оплату</span>
                    </div>
                    <h3 style="margin:0 0 10px;">Оплатите заказ и дождитесь подтверждения сети</h3>
                    <p class="section-text" id="cryptoPaymentSubtitle">Переведите точную сумму на указанный адрес.</p>

                    <div class="crypto-payment-layout">
                        <div>
                            <div class="crypto-payment-amount-box">
                                <div class="crypto-payment-amount-label">К оплате по заказу</div>
                                <div class="crypto-payment-amount-value" id="cryptoPaymentAmount">-</div>
                                <div class="crypto-payment-amount-subline" id="cryptoPaymentAmountSubline">-</div>
                                <div style="margin-top:10px;font-size:.8rem;color:var(--text-muted);" id="cryptoPaymentRateNote">-</div>
                            </div>
                            <div class="crypto-payment-details">
                                <div class="crypto-payment-detail-row"><span>Номер заказа</span><strong id="cryptoOrderNumber">-</strong></div>
                                <div class="crypto-payment-detail-row"><span>Токен и сеть</span><strong id="cryptoTokenNetwork">-</strong></div>
                                <div class="crypto-payment-detail-row"><span>Адрес кошелька</span><code id="cryptoWalletAddress">-</code></div>
                                <div class="crypto-payment-detail-row"><span>Подтверждений нужно</span><strong id="cryptoConfirmations">-</strong></div>
                                <div class="crypto-payment-detail-row"><span>Счёт активен ещё</span><strong id="cryptoExpiresAt" style="color:#EF4444;font-size:1.1em;">-</strong></div>
                                <div style="margin-top:10px;font-size:.8rem;color:var(--text-muted);" id="cryptoExpiresAtMeta">Счёт создаётся автоматически после оформления заказа.</div>
                            </div>
                            <div class="crypto-payment-actions">
                                <button type="button" class="btn-crypto-secondary" id="copyCryptoAmountBtn"><i class="fa-regular fa-copy"></i> Скопировать сумму</button>
                                <button type="button" class="btn-crypto-secondary" id="copyCryptoWalletBtn"><i class="fa-regular fa-copy"></i> Скопировать кошелёк</button>
                                <button type="button" class="btn-crypto-secondary" id="copyCryptoUriBtn"><i class="fa-solid fa-link"></i> Платёжная ссылка</button>
                            </div>
                        </div>
                        <div class="crypto-payment-qr-panel">
                            <div class="crypto-qr-box"><img id="cryptoQrImage" src="" alt="QR-код для оплаты"></div>
                            <div class="crypto-payment-qr-caption" id="cryptoQrCaption">QR-код оплаты</div>
                            <div class="crypto-payment-hint" id="cryptoQrHint">QR-код содержит адрес и параметры платежа.</div>
                        </div>
                    </div>
                </div>

                <!-- ШАГ 2: YooMoney -->
                <div class="checkout-card yoomoney-step" id="checkoutStep2Yoomoney" hidden>
                    <div class="step-label">
                        <i class="fa-solid fa-wallet"></i>
                        Шаг 2 · Оплата YooMoney
                    </div>
                    <div class="yoo-logo"><i class="fa-solid fa-wallet"></i></div>
                    <h3>Переход на страницу оплаты YooMoney</h3>
                    <p>Заказ создан. Нажмите кнопку ниже для перехода на защищённую страницу оплаты YooMoney.</p>
                    <div id="yoomoneyFormContainer"></div>
                    <div class="flow-note" style="margin-top:16px;">После успешной оплаты вы будете автоматически перенаправлены обратно на сайт, а товар будет выдан на указанный email.</div>
                </div>

                <!-- ШАГ 2: Демо-оплата -->
                <div class="checkout-card" id="checkoutStep2Demo" hidden>
                    <div class="step-label">
                        <i class="fa-solid fa-flask"></i>
                        Шаг 2 · Демо-оплата
                    </div>
                    <div style="text-align:center;padding:20px 0;">
                        <div style="font-size:3rem;color:#10b981;margin-bottom:16px;"><i class="fa-solid fa-circle-check"></i></div>
                        <h3 style="margin-bottom:8px;">Демо-заказ оформлен!</h3>
                        <p style="color:var(--text-muted);margin-bottom:20px;">Это тестовый режим. Реальных списаний нет. Товар выдан автоматически.</p>
                        <div id="demoDeliveredItems" style="text-align:left;"></div>
                    </div>
                </div>

            </div>

            <!-- Сайдбар -->
            <aside class="checkout-sidebar-card">
                <h3>Ваш заказ</h3>
                <div class="order-lines" id="sidebarOrderItems">
                    <?php if ($product): ?>
                    <div class="order-line">
                        <div>
                            <div style="font-size:.9rem;font-weight:700;"><?php echo htmlspecialchars($product['name']); ?></div>
                            <div class="sidebar-muted" id="singleProductQtyText"><?php echo $qty; ?> шт.</div>
                        </div>
                        <div class="order-line-price" id="singleProductTotalText"><?php echo number_format($product['price'] * $qty, 0, '.', ' '); ?> ₽</div>
                    </div>
                    <?php else: ?>
                    <div id="cartOrderItems"></div>
                    <?php endif; ?>
                </div>
                <div class="order-total-line">
                    <span>Итого</span>
                    <strong id="sidebarOrderTotalRub">0 ₽</strong>
                </div>
                <div class="sidebar-stack" style="margin-top:12px;">
                    <div class="sidebar-row" id="sidebarUsdRow" style="display:none;">
                        <span>Итого в USD</span>
                        <strong id="sidebarOrderTotalUsd">$0.00</strong>
                    </div>
                    <div class="sidebar-row sidebar-row--accent" id="sidebarTokenRow" style="display:none;">
                        <span>К оплате сейчас</span>
                        <strong id="sidebarTokenAmount">-</strong>
                    </div>
                </div>

                <div class="payment-rate-box" id="sidebarRateBox" style="display:none;">
                    <h3>Курсы</h3>
                    <div class="sidebar-stack">
                        <div class="sidebar-row">
                            <span>USD/RUB</span>
                            <strong id="sidebarUsdRubRate"><?php echo $rubPerUsd > 0 ? number_format($rubPerUsd, 4, '.', ' ') . ' ₽' : '-'; ?></strong>
                        </div>
                        <div class="sidebar-row">
                            <span>Выбранный токен</span>
                            <strong id="sidebarSelectedRate">-</strong>
                        </div>
                    </div>
                    <div class="rate-footnote" id="sidebarRatesInfo">
                        <?php if (!empty($cryptoRatesCache['updated_at'])): ?>
                            Курсы обновлены: <?php echo htmlspecialchars($cryptoRatesCache['updated_at']); ?>
                        <?php else: ?>
                            Курсы будут загружены при первом обновлении.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sidebar-trust-list">
                    <div class="sidebar-trust-item"><i class="fa-solid fa-shield-halved"></i><div>Безопасная оплата через проверенные платёжные системы</div></div>
                    <div class="sidebar-trust-item"><i class="fa-solid fa-bolt"></i><div>Товар выдаётся автоматически сразу после подтверждения оплаты</div></div>
                    <div class="sidebar-trust-item"><i class="fa-solid fa-envelope"></i><div>Данные заказа отправляются на указанный email</div></div>
                </div>

                <?php
                $_adCheckoutPrimary = renderAdSpot('checkout_banner');
                $_adCheckoutSecondary = renderAdSpot('sidebar_top');
                if (!empty($_adCheckoutPrimary) || !empty($_adCheckoutSecondary)):
                ?>
                <div style="display:flex;flex-direction:column;gap:12px;margin-top:18px;align-items:center;">
                    <?php if (!empty($_adCheckoutPrimary)) echo $_adCheckoutPrimary; ?>
                    <?php if (!empty($_adCheckoutSecondary)) echo $_adCheckoutSecondary; ?>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<div class="toast-container" id="toastContainer"></div>

<script>
(() => {
const singleProduct = <?php echo json_encode($product ? [
    'id' => (int)$product['id'],
    'slug' => $product['slug'],
    'name' => $product['name'],
    'price' => (float)$product['price'],
    'quantity' => (int)$product['quantity']
] : null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

const orderStatusConfig = <?php echo json_encode([
    'status' => $status,
    'orderNumber' => $orderNumber
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

const cryptoRatesMeta = <?php echo json_encode([
    'updated_at' => $cryptoRatesCache['updated_at'] ?? '',
    'usd_per_rub' => (float)$usdPerRub,
    'rub_per_usd' => (float)$rubPerUsd
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

const cryptoTokens = <?php echo json_encode($cryptoTokensView, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

const enabledMethods = {
    yoomoney: <?php echo $yooEnabled ? 'true' : 'false'; ?>,
    crypto: <?php echo $cryptoEnabled ? 'true' : 'false'; ?>,
    demo: <?php echo $demoEnabled ? 'true' : 'false'; ?>
};

let selectedPaymentMethod = <?php echo $yooEnabled ? "'yoomoney'" : ($cryptoEnabled ? "'crypto'" : "'demo'"); ?>;
let selectedCryptoToken = '<?php echo htmlspecialchars($defaultTokenCode); ?>';
let currentCryptoOrder = null;
let currentCryptoInvoice = null;
let orderStatusPoller = null;
let cryptoCountdownTimer = null;
let checkoutPageInitialized = false;
window._lastOrder = null; // Хранит последний заказ для скачивания

function formatRub(v){return`${(Number(v)||0).toLocaleString('ru-RU',{maximumFractionDigits:2})} ₽`}
function formatUsd(v){return`$${(Number(v)||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}`}
function formatTokenAmount(v,d=8){const a=Number(v)||0;const p=a>=1?Math.min(d,6):Math.min(d,8);return a.toLocaleString('en-US',{minimumFractionDigits:0,maximumFractionDigits:p})}
function formatDateTime(v){if(!v)return'-';const p=new Date(String(v).replace(' ','T'));return isNaN(p.getTime())?v:p.toLocaleString('ru-RU')}
function escHtml(s){if(!s)return'';return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}

const CART_STORAGE_KEY='shillcms_cart';
const CHECKOUT_CART_SNAPSHOT_KEY='shillcms_checkout_cart';
const CART_SESSION_KEY='shillcms_cart_session';

function getCartItems(){
    try{
        // 1. Try primary localStorage key
        const primaryRaw = localStorage.getItem(CART_STORAGE_KEY);
        const primary = JSON.parse(primaryRaw || '[]');
        if(Array.isArray(primary) && primary.length > 0) return primary;
        
        // 2. Try checkout snapshot key
        const snapshotRaw = localStorage.getItem(CHECKOUT_CART_SNAPSHOT_KEY);
        const snapshot = JSON.parse(snapshotRaw || '[]');
        if(Array.isArray(snapshot) && snapshot.length > 0) return snapshot;
        
        // 3. Try sessionStorage backup (most reliable across page navigation)
        const sessionRaw = sessionStorage.getItem(CART_SESSION_KEY);
        const sessionCart = JSON.parse(sessionRaw || '[]');
        if(Array.isArray(sessionCart) && sessionCart.length > 0){
            // Restore to localStorage for consistency
            try{ localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(sessionCart)); }catch(e){}
            return sessionCart;
        }
        
        return [];
    }catch(e){
        return [];
    }
}

function syncCheckoutCart(cart){
    try{localStorage.setItem(CART_STORAGE_KEY,JSON.stringify(cart||[]))}catch(e){}
    try{localStorage.setItem(CHECKOUT_CART_SNAPSHOT_KEY,JSON.stringify(cart||[]))}catch(e){}
    try{sessionStorage.setItem(CART_SESSION_KEY,JSON.stringify(cart||[]))}catch(e){}
}

function clearCheckoutCart(){
    try{localStorage.removeItem(CART_STORAGE_KEY)}catch(e){}
    try{localStorage.removeItem(CHECKOUT_CART_SNAPSHOT_KEY)}catch(e){}
    try{sessionStorage.removeItem(CART_SESSION_KEY)}catch(e){}
}

function calculateCurrentTotalRub(){
    if(singleProduct){const q=parseInt(document.getElementById('payQty')?.value||'1',10)||1;return(singleProduct.price||0)*q}
    return getCartItems().reduce((s,i)=>s+((i.price||0)*(i.qty||1)),0)
}
function calculateCurrentTotalUsd(){
    const r=calculateCurrentTotalRub();
    const u=Number(cryptoRatesMeta.usd_per_rub)||0;
    if(u>0)return r*u;
    const ru=Number(cryptoRatesMeta.rub_per_usd)||0;
    return ru>0?r/ru:0
}
function getSelectedToken(){return cryptoTokens.find(t=>t.code===selectedCryptoToken)||cryptoTokens[0]||null}

function selectPaymentMethod(method){
    selectedPaymentMethod=method;
    document.querySelectorAll('.payment-method-card').forEach(c=>c.classList.toggle('selected',c.dataset.method===method));
    const cs=document.getElementById('cryptoTokenSection');
    const usdRow=document.getElementById('usdRow');
    const tokenRow=document.getElementById('tokenRow');
    const sidebarUsdRow=document.getElementById('sidebarUsdRow');
    const sidebarTokenRow=document.getElementById('sidebarTokenRow');
    const sidebarRateBox=document.getElementById('sidebarRateBox');
    const isCrypto=method==='crypto';
    if(cs)cs.style.display=isCrypto?'block':'none';
    if(usdRow)usdRow.style.display=isCrypto?'flex':'none';
    if(tokenRow)tokenRow.style.display=isCrypto?'flex':'none';
    if(sidebarUsdRow)sidebarUsdRow.style.display=isCrypto?'flex':'none';
    if(sidebarTokenRow)sidebarTokenRow.style.display=isCrypto?'flex':'none';
    if(sidebarRateBox)sidebarRateBox.style.display=isCrypto?'block':'none';
    updatePaymentSummary()
}

function selectCryptoToken(code){
    selectedCryptoToken=code;
    document.querySelectorAll('.crypto-token-card').forEach(c=>c.classList.toggle('selected',c.dataset.token===code));
    updatePaymentSummary()
}

function changePayQty(delta){
    const input=document.getElementById('payQty');
    if(!input)return;
    const min=parseInt(input.min||'1',10)||1;
    const max=parseInt(input.max||'999',10)||999;
    let next=(parseInt(input.value,10)||1)+delta;
    next=Math.max(min,Math.min(max,next));
    input.value=next;
    updatePaymentSummary()
}

function updatePaymentSummary(){
    const totalRub=calculateCurrentTotalRub();
    const totalUsd=calculateCurrentTotalUsd();
    const token=getSelectedToken();
    const tokenAmount=token&&token.rate_usd?totalUsd/token.rate_usd:0;

    const el=id=>document.getElementById(id);
    if(el('orderTotalRub'))el('orderTotalRub').textContent=formatRub(totalRub);
    if(el('orderTotalUsd'))el('orderTotalUsd').textContent=formatUsd(totalUsd);
    if(el('sidebarOrderTotalRub'))el('sidebarOrderTotalRub').textContent=formatRub(totalRub);
    if(el('sidebarOrderTotalUsd'))el('sidebarOrderTotalUsd').textContent=formatUsd(totalUsd);
    if(el('sidebarUsdRubRate'))el('sidebarUsdRubRate').textContent=cryptoRatesMeta.rub_per_usd?`${Number(cryptoRatesMeta.rub_per_usd).toLocaleString('ru-RU',{maximumFractionDigits:4})} ₽`:'-';

    document.querySelectorAll('.crypto-token-card').forEach(card=>{
        const t=cryptoTokens.find(x=>x.code===card.dataset.token);
        const node=card.querySelector('[data-role="token-amount"]');
        if(!t||!node)return;
        if(!t.rate_usd){node.textContent='Курс недоступен';return}
        const ta=totalUsd/t.rate_usd;
        node.textContent=`${formatTokenAmount(ta,t.decimals)} ${t.symbol}`
    });

    if(token&&selectedPaymentMethod==='crypto'){
        const amtTxt=token.rate_usd?`${formatTokenAmount(tokenAmount,token.decimals)} ${token.symbol}`:'Курс недоступен';
        const rateTxt=token.rate_usd?`1 ${token.symbol} = ${formatUsd(token.rate_usd)} · ${formatRub(token.rate_rub)}`:'Курс временно недоступен';
        if(el('selectedTokenLabel'))el('selectedTokenLabel').textContent=`Сумма в ${token.symbol}`;
        if(el('selectedTokenAmount'))el('selectedTokenAmount').textContent=amtTxt;
        if(el('sidebarTokenAmount'))el('sidebarTokenAmount').textContent=amtTxt;
        if(el('sidebarSelectedRate'))el('sidebarSelectedRate').textContent=rateTxt;
        if(el('payBtnTotal'))el('payBtnTotal').textContent=token.rate_usd?`- ${amtTxt}`:'';
    } else {
        if(el('payBtnTotal'))el('payBtnTotal').textContent='';
    }

    if(el('ratesFootnote'))el('ratesFootnote').textContent=cryptoRatesMeta.updated_at?`Курсы обновлены ${cryptoRatesMeta.updated_at}. Пересчёт выполняется локально.`:'Сумма рассчитывается по последнему доступному кэшу.';

    renderCartSummary();

    if(singleProduct){
        const qty=parseInt(el('payQty')?.value||'1',10)||1;
        if(el('singleProductQtyText'))el('singleProductQtyText').textContent=`${qty} шт.`;
        if(el('singleProductTotalText'))el('singleProductTotalText').textContent=formatRub(totalRub);
    }
}

function changeCartItemQty(slug,delta){
    let cart=getCartItems();
    const idx=cart.findIndex(i=>i.slug===slug);
    if(idx===-1)return;
    const maxQty=cart[idx].maxQty||999;
    let newQty=(cart[idx].qty||1)+delta;
    newQty=Math.max(1,Math.min(maxQty,newQty));
    cart[idx].qty=newQty;
    syncCheckoutCart(cart);
    renderCartSummary();
    updatePaymentSummary();
}

function renderCartSummary(){
    if(singleProduct)return;
    const cart=getCartItems();
    const wrapper=document.getElementById('cartOrderItems');
    if(!wrapper)return;
    if(!cart.length){wrapper.innerHTML='<div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i><span>Корзина пуста.</span></div>';return}
    wrapper.innerHTML=cart.map(item=>{
        const t=(item.price||0)*(item.qty||1);
        const maxQty=item.maxQty||999;
        return`<div class="order-line" style="flex-direction:column;align-items:stretch;gap:8px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                <div style="font-size:.9rem;font-weight:700;flex:1;">${escHtml(item.name||item.slug)}</div>
                <div class="order-line-price">${formatRub(t)}</div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                <div class="sidebar-muted">Цена: ${formatRub(item.price||0)} / шт.</div>
                <div class="quantity-selector" style="gap:6px;">
                    <button type="button" class="qty-btn" style="width:28px;height:28px;font-size:.9rem;" onclick="changeCartItemQty('${escHtml(item.slug)}',-1)">−</button>
                    <span style="min-width:28px;text-align:center;font-weight:700;font-size:.9rem;">${item.qty||1}</span>
                    <button type="button" class="qty-btn" style="width:28px;height:28px;font-size:.9rem;" onclick="changeCartItemQty('${escHtml(item.slug)}',1)">+</button>
                </div>
            </div>
        </div>`
    }).join('')
}

function buildOrderItemsPayload(){
    if(singleProduct)return[{slug:singleProduct.slug,qty:parseInt(document.getElementById('payQty')?.value||'1',10)||1}];
    return getCartItems().map(i=>({slug:i.slug,qty:i.qty}))
}

async function proceedToPayment(){
    const email=document.getElementById('payEmail')?.value.trim()||'';
    const emailConfirm=document.getElementById('payEmailConfirm')?.value.trim()||'';
    if(!email||!emailConfirm||email!==emailConfirm){showToast('Email адреса должны совпадать','error');return}
    if(!selectedPaymentMethod){showToast('Выберите способ оплаты','error');return}
    if(selectedPaymentMethod==='crypto'&&!selectedCryptoToken){showToast('Выберите токен для оплаты','error');return}
    const items=buildOrderItemsPayload();
    if(!items.length){showToast('Корзина пуста. Добавьте товар перед оплатой.','error');return}

    const btn=document.getElementById('proceedToPayBtn');
    const orig=btn.innerHTML;
    btn.disabled=true;
    btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Создаём заказ...';

    try{
        const payload={email,payment_method:selectedPaymentMethod,items};
        if(selectedPaymentMethod==='crypto')payload.selected_token=selectedCryptoToken;

        const resp=await fetch((window.appUrl?window.appUrl('/api/?path=orders'):'/api/?path=orders'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        const result=await resp.json();
        if(!result.success)throw new Error(result.error||'Не удалось создать заказ');

        const order=result.data.order||{};
        const payment=result.data.payment||{};
        orderStatusConfig.orderNumber=order.order_number||'';
        updateOrderUrl(orderStatusConfig.orderNumber);
        document.getElementById('checkoutStep1').hidden=true;

        if(selectedPaymentMethod==='yoomoney'){
            showYooMoneyStep(payment);
        } else if(selectedPaymentMethod==='crypto'){
            renderCryptoPaymentStep(order,payment.invoice||order.crypto||null);
            startOrderStatusPolling();
        } else {
            showDemoStep(order);
        }
        showToast('Заказ создан успешно!','success');
        // Очищаем корзину если оплата из корзины
        if(!singleProduct){clearCheckoutCart()}
    } catch(err){
        showToast(err.message||'Ошибка при создании заказа','error');
    } finally{
        btn.disabled=false;
        btn.innerHTML=orig;
    }
}

function showYooMoneyStep(payment){
    const step=document.getElementById('checkoutStep2Yoomoney');
    if(step)step.hidden=false;
    const container=document.getElementById('yoomoneyFormContainer');
    if(!container)return;
    if(payment.type==='redirect_form'&&payment.action&&payment.fields){
        const fieldsHtml=Object.entries(payment.fields).map(([n,v])=>`<input type="hidden" name="${escHtml(n)}" value="${escHtml(String(v))}">`).join('');
        container.innerHTML=`<form id="yoomoneyRedirectForm" method="POST" action="${escHtml(payment.action)}">${fieldsHtml}<button type="submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:16px;"><i class="fa-solid fa-wallet"></i> Оплатить через YooMoney</button></form>`;
        setTimeout(()=>{const f=document.getElementById('yoomoneyRedirectForm');if(f)f.submit()},1500);
    } else {
        container.innerHTML='<div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i><span>Ошибка конфигурации YooMoney. Обратитесь к администратору.</span></div>';
    }
}

function downloadOrderItems(orderArg) {
    // Принимаем объект заказа либо из аргумента, либо из глобального хранилища
    const order = orderArg || window._lastOrder;
    if (!order || !order.delivered_items) {
        showToast('Данные заказа недоступны для скачивания', 'error');
        return;
    }
    let content = `Заказ №${order.order_number || '---'}\n`;
    content += `Дата: ${new Date().toLocaleString()}\n`;
    content += `------------------------------------------\n\n`;
    
    order.delivered_items.forEach(item => {
        content += `Товар: ${item.name} (Кол-во: ${item.qty})\n`;
        if (item.issued_items && item.issued_items.length > 0) {
            item.issued_items.forEach((line, idx) => {
                content += `${idx + 1}. ${line}\n`;
            });
        } else {
            content += `Данные товара отсутствуют или будут отправлены на email.\n`;
        }
        content += `\n------------------------------------------\n\n`;
    });

    const blob = new Blob([content], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.style.display = 'none';
    a.href = url;
    a.download = `order_${order.order_number || 'items'}.txt`;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);
}

function showDemoStep(order){
    const step=document.getElementById('checkoutStep2Demo');
    if(step)step.hidden=false;
    // Сохраняем объект заказа в глобальную переменную для кнопки скачивания
    window._lastOrder = order;
    renderOrderStatus(order.order_number||'', 'success', 'Демо-оплата успешно выполнена. Списаний нет, товар выдан автоматически.');
    const container=document.getElementById('demoDeliveredItems');
    if(!container)return;
    const delivered=order.delivered_items||[];
    if(!delivered.length){
        container.innerHTML='<p style="color:var(--text-muted);">Заказ создан в демо-режиме. Если для товара нет реального контента, будет показана тестовая выдача.</p>';
        return;
    }
    
    let html = '';
    delivered.forEach(item => {
        html += `<div style="margin-bottom:16px; padding:16px; background:rgba(15,23,42,.4); border:1px solid rgba(148,163,184,.1); border-radius:12px;">
            <div style="font-weight:700; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
                <span>${escHtml(item.name)} × ${item.qty}</span>
                <span style="font-size:0.75rem; color:var(--primary); background:rgba(79,70,229,0.1); padding:2px 8px; border-radius:4px;">Готов к выдаче</span>
            </div>
            <button type="button" class="btn btn-primary btn-sm" style="width:100%; justify-content:center;" onclick="downloadOrderItems()">
                <i class="fa-solid fa-download"></i> Скачать товар (.txt)
            </button>
        </div>`;
    });
    container.innerHTML = html;
}

function formatCountdown(totalSeconds){
    const safe=Math.max(0,parseInt(totalSeconds,10)||0);
    const minutes=Math.floor(safe/60);
    const seconds=safe%60;
    return `${String(minutes).padStart(2,'0')}:${String(seconds).padStart(2,'0')}`;
}

function stopCryptoCountdown(){
    if(cryptoCountdownTimer){clearInterval(cryptoCountdownTimer);cryptoCountdownTimer=null}
}

function startCryptoCountdown(invoice){
    stopCryptoCountdown();
    const countdownNode=document.getElementById('cryptoExpiresAt');
    const metaNode=document.getElementById('cryptoExpiresAtMeta');
    const inlineNode=document.getElementById('cryptoInlineStatus');
    if(!countdownNode||!invoice)return;

    const expiresRaw=invoice.expires_at||'';
    const createdRaw=invoice.created_at||'';
    const expiresTs=expiresRaw?new Date(String(expiresRaw).replace(' ','T')).getTime():NaN;
    const createdText=createdRaw?formatDateTime(createdRaw):'';
    const expiresText=expiresRaw?formatDateTime(expiresRaw):'';

    const tick=()=>{
        const isPaid=currentCryptoOrder&&(currentCryptoOrder.payment_status==='paid'||currentCryptoOrder.status==='paid');
        const invoiceStatus=String(currentCryptoInvoice?.invoice_status||'').toLowerCase();
        if(isPaid){
            countdownNode.textContent='Оплачено';
            if(metaNode)metaNode.textContent=expiresText?`Счёт был действителен до ${expiresText}.`:'Оплата подтверждена.';
            stopCryptoCountdown();
            return;
        }
        if(Number.isNaN(expiresTs)){
            countdownNode.textContent='-';
            if(metaNode)metaNode.textContent=createdText?`Счёт создан ${createdText}.`:'Не удалось определить срок действия счёта.';
            return;
        }
        const leftSeconds=Math.max(0,Math.floor((expiresTs-Date.now())/1000));
        const expired=leftSeconds<=0||invoiceStatus==='expired';
        if(expired){
            countdownNode.textContent='00:00';
            if(metaNode)metaNode.textContent=expiresText?`Срок действия счёта истёк в ${expiresText}.`:'Срок действия счёта истёк.';
            if(inlineNode){
                inlineNode.style.background='rgba(239,68,68,.14)';
                inlineNode.style.color='#f87171';
                inlineNode.innerHTML='<i class="fa-solid fa-hourglass-end"></i><span>Счёт просрочен</span>';
            }
            renderOrderStatus(currentCryptoOrder?.order_number||'', 'fail', 'Время на оплату счёта истекло. Статус - не оплачено. Сформируйте новый заказ для повторной попытки.');
            stopCryptoCountdown();
            return;
        }
        countdownNode.textContent=formatCountdown(leftSeconds);
        if(metaNode)metaNode.textContent=`Счёт создан ${createdText||'только что'} и действует до ${expiresText}.`;
    };

    tick();
    cryptoCountdownTimer=setInterval(tick,1000);
}

function renderCryptoPaymentStep(order,invoice){
    const o=order||{};
    const inv=invoice||o.crypto||{};
    if(!inv.wallet)return;
    currentCryptoOrder=o;
    currentCryptoInvoice=inv;

    const step=document.getElementById('checkoutStep2Crypto');
    if(step)step.hidden=false;

    const el=id=>document.getElementById(id);
    if(el('cryptoPaymentAmount'))el('cryptoPaymentAmount').textContent=`${inv.expected_amount_text||formatTokenAmount(inv.expected_amount,8)} ${inv.token_symbol||inv.token_code||''}`.trim();
    if(el('cryptoPaymentAmountSubline'))el('cryptoPaymentAmountSubline').textContent=`${formatRub(inv.amount_rub||o.amount||0)} · ${formatUsd(inv.amount_usd||0)}`;
    if(el('cryptoPaymentRateNote'))el('cryptoPaymentRateNote').textContent=inv.rate_usd?`Курс: 1 ${inv.token_symbol||inv.token_code} = ${formatUsd(inv.rate_usd)} · ${formatRub(inv.rate_rub||0)}`:'Сумма зафиксирована по локальному кэшу.';
    if(el('cryptoOrderNumber'))el('cryptoOrderNumber').textContent=o.order_number||'-';
    if(el('cryptoTokenNetwork'))el('cryptoTokenNetwork').textContent=`${inv.token_name||inv.token_code||'-'}${inv.network?' · '+inv.network:''}`;
    if(el('cryptoWalletAddress'))el('cryptoWalletAddress').textContent=inv.wallet||'-';
    if(el('cryptoConfirmations'))el('cryptoConfirmations').textContent=inv.confirmations_required?`${inv.confirmations_required}`:'-';
    startCryptoCountdown(inv);
    if(el('cryptoQrCaption'))el('cryptoQrCaption').textContent=`QR-ко
 для ${inv.token_symbol||inv.token_code||'оплаты'}`;
    if(el('cryptoQrHint'))el('cryptoQrHint').textContent=inv.payment_uri&&inv.payment_uri!==inv.wallet?'QR-код содержит адрес и платёжные параметры.':'QR-код содержит адрес кошелька.';

    const qrImg=el('cryptoQrImage');
    const qrSrc=inv.qr_image_url||`https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=${encodeURIComponent(inv.qr_value||inv.payment_uri||inv.wallet||'')}`;
    if(qrImg){qrImg.src=qrSrc;qrImg.alt=`QR-код для оплаты заказа ${o.order_number||''}`}

    renderInlinePaymentStatus(o);
    renderOrderStatus(o.order_number||'',(o.payment_status==='paid'||o.status==='paid')?'paid':'pending',(o.payment_status==='paid'||o.status==='paid')?'Оплата подтверждена.':'Заказ создан. Ожидаем оплату.');
    step.scrollIntoView({behavior:'smooth',block:'start'});
}

function renderInlinePaymentStatus(order){
    const container=document.getElementById('cryptoInlineStatus');
    const subtitle=document.getElementById('cryptoPaymentSubtitle');
    if(!container)return;
    const isPaid=order&&(order.payment_status==='paid'||order.status==='paid');
    const invoiceStatus=String(order?.crypto?.invoice_status||currentCryptoInvoice?.invoice_status||'').toLowerCase();
    if(isPaid){
        container.style.background='rgba(16,185,129,.14)';container.style.color='#34d399';
        container.innerHTML='<i class="fa-solid fa-circle-check"></i><span>Оплата подтверждена</span>';
        // Сохраняем объект заказа в глобальную переменную для кнопки скачивания
        window._lastOrder = order;
        if(subtitle) {
            subtitle.innerHTML = `Транзакция подтверждена. Товар выдан автоматически.<br><br>
            <button type="button" class="btn btn-primary btn-sm" style="margin-top:10px;" onclick="downloadOrderItems()">
                <i class="fa-solid fa-download"></i> Скачать товар (.txt)
            </button>`;
        }
    } else if(invoiceStatus==='expired') {
        container.style.background='rgba(239,68,68,.14)';container.style.color='#f87171';
        container.innerHTML='<i class="fa-solid fa-hourglass-end"></i><span>Счёт просрочен</span>';
        if(subtitle)subtitle.textContent='Время на оплату истекло. Для повторной попытки создайте новый заказ.';
    } else {
        container.style.background='rgba(245,158,11,.14)';container.style.color='#fbbf24';
        container.innerHTML='<span class="payment-wait-spinner" aria-hidden="true"></span><span>Ожидает оплату</span>';
        if(subtitle)subtitle.textContent='Переведите точную сумму на указанный адрес. После подтверждения сети товар будет выдан автоматически.';
    }
}

function renderOrderStatus(orderNumber,status,text){
    const card=document.getElementById('paymentReturnCard');
    const textNode=document.getElementById('paymentReturnText');
    const badge=document.getElementById('paymentReturnBadge');
    if(!card||!textNode||!badge)return;
    const variants={
        paid:{bg:'rgba(16,185,129,.14)',color:'#34d399',icon:'fa-circle-check',label:'Оплачен',spinner:false},
        success:{bg:'rgba(16,185,129,.14)',color:'#34d399',icon:'fa-circle-check',label:'Успех',spinner:false},
        pending:{bg:'rgba(245,158,11,.14)',color:'#fbbf24',icon:'fa-clock',label:'Ожидает оплату',spinner:true},
        fail:{bg:'rgba(239,68,68,.18)',color:'#ef4444',icon:'fa-circle-xmark',label:'Статус - не оплачено',spinner:false}
    };
    const v=variants[status]||variants.pending;
    card.hidden=false;
    card.classList.remove('status-paid','status-success','status-pending','status-fail');
    card.classList.add(`status-${status in variants ? status : 'pending'}`);
    textNode.textContent=text;
    badge.className=`payment-status-badge status-${status in variants ? status : 'pending'}`;
    badge.style.background=v.bg;
    badge.style.color=v.color;
    badge.innerHTML=v.spinner?`<span class="payment-wait-spinner" aria-hidden="true"></span><span>${v.label}${orderNumber?' · '+orderNumber:''}</span>`:`<i class="fa-solid ${v.icon}"></i><span>${v.label}${orderNumber?' · '+orderNumber:''}</span>`;
    if(status==='fail'){
        card.scrollIntoView({behavior:'smooth',block:'start'});
    }
}

function updateOrderUrl(orderNumber){
    if(!orderNumber)return;
    const url=new URL(window.location.href);
    url.searchParams.set('order',orderNumber);
    url.searchParams.delete('status');
    window.history.replaceState({},'',url.toString());
}

function startOrderStatusPolling(){
    if(orderStatusPoller)clearInterval(orderStatusPoller);
    if(!orderStatusConfig.orderNumber)return;
    orderStatusPoller=setInterval(refreshOrderStatus,15000);
}

async function refreshOrderStatus(){
    if(!orderStatusConfig.orderNumber)return;
    try{
        const baseUrl=window.appUrl?window.appUrl('/api/?path=orders/'):'/api/?path=orders/';
        const resp=await fetch(`${baseUrl}${encodeURIComponent(orderStatusConfig.orderNumber)}`);
        const result=await resp.json();
        if(!result.success)return;
        const order=result.data||{};
        currentCryptoOrder=order;
        if(order.payment_method==='crypto'&&order.crypto)renderCryptoPaymentStep(order,order.crypto);
        if(order.status==='demo-paid'||order.payment_method==='demo'){
            // Демо-заказ: показываем шаг демо-оплаты с выдачей товара
            document.getElementById('checkoutStep1')&&(document.getElementById('checkoutStep1').hidden=true);
            showDemoStep(order);
            if(orderStatusPoller){clearInterval(orderStatusPoller);orderStatusPoller=null}
        } else if(order.payment_status==='paid'||order.status==='paid'){
            renderOrderStatus(order.order_number,'paid','Оплата подтверждена. Товар выдан.');
            renderInlinePaymentStatus(order);
            if(orderStatusPoller){clearInterval(orderStatusPoller);orderStatusPoller=null}
        } else if(String(order?.crypto?.invoice_status||'').toLowerCase()==='expired' || String(order.payment_status||'').toLowerCase()==='failed') {
            renderOrderStatus(order.order_number,'fail','Время на оплату счёта истекло. Статус - не оплачено. Сформируйте новый заказ для повторной попытки.');
            renderInlinePaymentStatus(order);
            if(orderStatusPoller){clearInterval(orderStatusPoller);orderStatusPoller=null}
        }
    } catch(e){console.error(e)}
}

async function copyTextValue(text,msg){
    if(!text){showToast('Нет данных для копирования','error');return}
    try{await navigator.clipboard.writeText(text);showToast(msg,'success')}
    catch{showToast('Не удалось скопировать','error')}
}

function bindCryptoCopyButtons(){
    document.getElementById('copyCryptoAmountBtn')?.addEventListener('click',()=>copyTextValue(currentCryptoInvoice?.expected_amount_text||'','Сумма скопирована'));
    document.getElementById('copyCryptoWalletBtn')?.addEventListener('click',()=>copyTextValue(currentCryptoInvoice?.wallet||'','Адрес кошелька скопирован'));
    document.getElementById('copyCryptoUriBtn')?.addEventListener('click',()=>copyTextValue(currentCryptoInvoice?.payment_uri||currentCryptoInvoice?.wallet||'','Платёжная ссылка скопирована'));
}

function showToast(message,type='success'){
    const container=document.getElementById('toastContainer');
    if(!container)return;
    const toast=document.createElement('div');
    toast.className=`toast ${type}`;
    const icon=type==='success'?'fa-circle-check':'fa-circle-xmark';
    toast.innerHTML=`<i class="fa-solid ${icon}"></i><span>${escHtml(message)}</span>`;
    container.appendChild(toast);
    setTimeout(()=>{toast.classList.add('hide');setTimeout(()=>toast.remove(),300)},3500);
}

function initCheckoutPage() {
    if (checkoutPageInitialized) return;
    checkoutPageInitialized = true;
    console.log('Checkout: Initializing page...');
    const qtyInput=document.getElementById('payQty');
    if(qtyInput){
        qtyInput.addEventListener('input', updatePaymentSummary);
        qtyInput.addEventListener('change', updatePaymentSummary);
    }
    selectPaymentMethod(selectedPaymentMethod);
    renderCartSummary();
    updatePaymentSummary();
    bindCryptoCopyButtons();
    
    if(orderStatusConfig.orderNumber){
        refreshOrderStatus();
        startOrderStatusPolling();
    }
    
    if(orderStatusConfig.status==='success') {
        renderOrderStatus(orderStatusConfig.orderNumber,'success','Оплата успешно завершена. Проверяем выдачу товара.');
    } else if(orderStatusConfig.status==='fail'){
        renderOrderStatus(orderStatusConfig.orderNumber,'fail','Платёж не был завершён. Статус - не оплачено. Вы можете снова выбрать способ оплаты и повторить попытку.');
        // Show big red fail notice
        const failBanner=document.createElement('div');
        failBanner.id='yooFailBanner';
        failBanner.style.cssText='background:rgba(239,68,68,.15);border:2px solid rgba(239,68,68,.6);border-radius:18px;padding:28px 32px;margin-bottom:20px;text-align:center;';
        failBanner.innerHTML=`
            <div style="font-size:3rem;margin-bottom:12px;"><i class="fa-solid fa-circle-xmark" style="color:#ef4444;"></i></div>
            <div style="font-size:1.8rem;font-weight:900;color:#ef4444;letter-spacing:.04em;text-transform:uppercase;margin-bottom:10px;">СТАТУС - НЕ ОПЛАЧЕНО</div>
            <div style="font-size:1rem;color:#fca5a5;margin-bottom:16px;">Платёж не был завершён или был отменён. Вы можете повторить попытку ниже.</div>
            <a href="${window.appUrl?window.appUrl('/oplata/'):'/oplata/'}" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:8px;"><i class="fa-solid fa-rotate-left"></i> Повторить оплату</a>
        `;
        const checkoutFlow=document.querySelector('.checkout-flow');
        if(checkoutFlow)checkoutFlow.parentNode.insertBefore(failBanner,checkoutFlow);
        setTimeout(()=>failBanner.scrollIntoView({behavior:'smooth',block:'start'}),100);
    }
}

window.selectPaymentMethod = selectPaymentMethod;
window.selectCryptoToken = selectCryptoToken;
window.changePayQty = changePayQty;
window.changeCartItemQty = changeCartItemQty;
window.proceedToPayment = proceedToPayment;
window.downloadOrderItems = downloadOrderItems;

// Robust initialization: run after DOM is ready
// Uses multiple strategies to ensure cart data is available
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCheckoutPage);
} else {
    // DOM already ready (interactive or complete)
    initCheckoutPage();
}
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
