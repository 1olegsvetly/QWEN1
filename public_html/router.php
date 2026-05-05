<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$root = __DIR__;
$file = realpath($root . $uri);

if ($uri !== '/' && $file && str_starts_with($file, $root) && is_file($file)) {
    return false;
}

// Admin panel route — must be handled before the API and index routes
if ($uri === '/admin' || $uri === '/admin/' || preg_match('#^/admin(/.*)?$#', $uri)) {
    $_SERVER['SCRIPT_NAME'] = '/admin/index.php';
    $_SERVER['PHP_SELF'] = '/admin/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $root . '/admin/index.php';
    require $root . '/admin/index.php';
    return true;
}

if ($uri === '/api' || $uri === '/api/' || preg_match('#^/api/(.+)$#', $uri, $m)) {
    if (!isset($_GET['path']) && !empty($m[1])) {
        $_GET['path'] = $m[1];
    }
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    $_SERVER['PHP_SELF'] = '/api/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $root . '/api/index.php';
    require $root . '/api/index.php';
    return true;
}

if (preg_match('#^/category/([^/]+)/([^/]+)/?$#', $uri, $m)) {
    $_GET['page'] = 'category';
    $_GET['slug'] = $m[1];
    $_GET['sub'] = $m[2];
} elseif (preg_match('#^/category/([^/]+)/?$#', $uri, $m)) {
    $_GET['page'] = 'category';
    $_GET['slug'] = $m[1];
} elseif (preg_match('#^/item/([^/]+)/?$#', $uri, $m)) {
    $_GET['page'] = 'item';
    $_GET['slug'] = $m[1];
} elseif ($uri === '/oplata' || $uri === '/oplata/') {
    $_GET['page'] = 'oplata';
} elseif ($uri === '/faq' || $uri === '/faq/') {
    $_GET['page'] = 'faq';
} elseif ($uri === '/rules' || $uri === '/rules/') {
    $_GET['page'] = 'rules';
} elseif ($uri === '/info' || $uri === '/info/') {
    $_GET['page'] = $_GET['page'] ?? 'info';
} elseif (preg_match('#^/info/([^/]+)/?$#', $uri, $m)) {
    $_GET['page'] = 'info';
    $_GET['article'] = $m[1];
} elseif ($uri === '/advertising' || $uri === '/advertising/') {
    $_GET['page'] = 'advertising';
} elseif ($uri === '/catalog' || $uri === '/catalog/') {
    $_GET['page'] = 'catalog';
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';

require $root . '/index.php';
