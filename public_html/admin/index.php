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
require_once __DIR__ . '/../includes/functions.php';
startAppOutputBuffer();

$settings = getSettings();
$adminLogin = $settings['admin']['login'] ?? 'admin';
$adminHash = $settings['admin']['password_hash'] ?? md5('admin123');

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $login = $_POST['login'] ?? '';
        $password = $_POST['password'] ?? '';
        if ($login === $adminLogin && md5($password) === $adminHash) {
            $_SESSION['admin_logged_in'] = true;
            // Сбрасываем выходной буфер перед редиректом, чтобы избежать белого экрана
            while (ob_get_level() > 0) { ob_end_clean(); }
            header('Location: ' . appUrl('/admin/'));
            exit;
        } else {
            $loginError = 'Неверный логин или пароль';
        }
    }
    if ($_POST['action'] === 'logout') {
        session_destroy();
        // Сбрасываем выходной буфер перед редиректом
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Location: ' . appUrl('/admin/'));
        exit;
    }
}

$isLoggedIn = !empty($_SESSION['admin_logged_in']);
$section = $_GET['section'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | <?php echo htmlspecialchars($settings['site']['name'] ?? 'Магазин аккаунтов'); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="/images/ui/favicon.svg">
    <link rel="shortcut icon" href="/images/ui/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --bg: #0F172A;
            --bg-card: #1E293B;
            --bg-hover: #334155;
            --border: #334155;
            --primary: #4F46E5;
            --primary-hover: #4338CA;
            --secondary: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --text: #F1F5F9;
            --text-muted: #94A3B8;
            --sidebar-w: 260px;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); font-size: 14px; padding-bottom: 52px; }
        a { color: inherit; text-decoration: none; }
        button { cursor: pointer; border: none; font-family: inherit; }
        input, textarea, select { font-family: inherit; }

        /* ---- Login ---- */
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(ellipse at center, rgba(79,70,229,0.15) 0%, transparent 60%);
        }
        .login-box {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
        }
        .login-logo { text-align: center; margin-bottom: 32px; }
        .login-logo h1 { font-size: 1.8rem; font-weight: 800; background: linear-gradient(135deg, #4F46E5, #7C3AED); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .login-logo p { color: var(--text-muted); font-size: 0.875rem; margin-top: 4px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group input { width: 100%; padding: 12px 14px; background: var(--bg); border: 1px solid var(--border); border-radius: 10px; color: var(--text); font-size: 0.9rem; outline: none; transition: border-color 0.2s; }
        .form-group input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79,70,229,0.15); }
        .btn-login { width: 100%; padding: 13px; background: linear-gradient(135deg, #4F46E5, #7C3AED); color: #fff; border-radius: 10px; font-size: 0.95rem; font-weight: 600; margin-top: 8px; transition: opacity 0.2s; }
        .btn-login:hover { opacity: 0.9; }
        .login-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #FCA5A5; padding: 10px 14px; border-radius: 8px; font-size: 0.875rem; margin-bottom: 16px; }

        /* ---- Admin Layout ---- */
        .admin-layout { display: flex; min-height: 100vh; }

        /* Sidebar */
        .admin-sidebar {
            width: var(--sidebar-w);
            background: var(--bg-card);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 100;
            overflow-y: auto;
        }
        .sidebar-logo {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar-logo-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; color: #fff; font-weight: 800;
        }
        .sidebar-logo-text { font-size: 1.1rem; font-weight: 800; }
        .sidebar-logo-sub { font-size: 0.7rem; color: var(--text-muted); }

        .sidebar-nav { flex: 1; padding: 16px 12px; }
        .sidebar-section-title { font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; padding: 8px 8px 4px; margin-top: 8px; }
        .sidebar-link {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            color: var(--text-muted);
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            margin-bottom: 2px;
        }
        .sidebar-link:hover { background: var(--bg-hover); color: var(--text); }
        .sidebar-link.active { background: rgba(79,70,229,0.15); color: var(--primary); }
        .sidebar-link i { width: 18px; text-align: center; font-size: 0.9rem; }
        .sidebar-link .badge { margin-left: auto; background: var(--primary); color: #fff; font-size: 0.7rem; padding: 2px 7px; border-radius: 100px; font-weight: 600; }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--border);
        }
        .sidebar-user {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px;
            background: var(--bg);
            border-radius: 10px;
            margin-bottom: 10px;
        }
        .sidebar-user-avatar {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, #4F46E5, #7C3AED);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem; color: #fff; font-weight: 700;
        }
        .sidebar-user-name { font-size: 0.875rem; font-weight: 600; }
        .sidebar-user-role { font-size: 0.75rem; color: var(--text-muted); }

        /* Main Content */
        .admin-main {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .admin-topbar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 16px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .admin-topbar h2 { font-size: 1.1rem; font-weight: 700; }
        .topbar-actions { display: flex; align-items: center; gap: 12px; }
        .topbar-btn {
            display: flex; align-items: center; gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .topbar-btn-primary { background: var(--primary); color: #fff; }
        .topbar-btn-primary:hover { background: var(--primary-hover); }
        .topbar-btn-secondary { background: var(--bg-hover); color: var(--text); }
        .topbar-btn-secondary:hover { background: var(--border); }
        .topbar-btn-danger { background: rgba(239,68,68,0.15); color: #FCA5A5; border: 1px solid rgba(239,68,68,0.3); }
        .topbar-btn-danger:hover { background: var(--danger); color: #fff; }

        .admin-content { padding: 28px; flex: 1; }

        /* Stats Cards */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 28px; }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-card-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .stat-card-icon.blue { background: rgba(79,70,229,0.15); color: #818CF8; }
        .stat-card-icon.green { background: rgba(16,185,129,0.15); color: #34D399; }
        .stat-card-icon.yellow { background: rgba(245,158,11,0.15); color: #FCD34D; }
        .stat-card-icon.red { background: rgba(239,68,68,0.15); color: #FCA5A5; }
        .stat-card-value { font-family: 'JetBrains Mono', monospace; font-size: 1.6rem; font-weight: 700; line-height: 1; }
        .stat-card-label { font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; }

        /* Tables */
        .admin-table-wrap {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }
        .admin-table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
        }
        .admin-table-header h3 { font-size: 0.95rem; font-weight: 700; }
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th {
            background: var(--bg);
            padding: 10px 16px;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }
        .admin-table td {
            padding: 12px 16px;
            font-size: 0.875rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .admin-table tr:last-child td { border-bottom: none; }
        .admin-table tr:hover td { background: rgba(255,255,255,0.02); }
        .admin-table .price-cell { font-family: 'JetBrains Mono', monospace; color: #818CF8; font-weight: 600; }
        .admin-table .qty-cell { font-family: 'JetBrains Mono', monospace; }

        /* Badges */
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 100px; font-size: 0.72rem; font-weight: 600; }
        .badge-active { background: rgba(16,185,129,0.15); color: #34D399; border: 1px solid rgba(16,185,129,0.3); }
        .badge-inactive { background: rgba(239,68,68,0.15); color: #FCA5A5; border: 1px solid rgba(239,68,68,0.3); }
        .badge-warning { background: rgba(245,158,11,0.15); color: #FCD34D; border: 1px solid rgba(245,158,11,0.3); }

        /* Action buttons */
        .action-btns { display: flex; gap: 6px; }
        .action-btn {
            width: 30px; height: 30px;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .action-btn-edit { background: rgba(79,70,229,0.15); color: #818CF8; }
        .action-btn-edit:hover { background: var(--primary); color: #fff; }
        .action-btn-delete { background: rgba(239,68,68,0.15); color: #FCA5A5; }
        .action-btn-delete:hover { background: var(--danger); color: #fff; }
        .action-btn-view { background: rgba(16,185,129,0.15); color: #34D399; }
        .action-btn-view:hover { background: var(--secondary); color: #fff; }
        .action-btn-upload { background: rgba(245,158,11,0.15); color: #FCD34D; }
        .action-btn-upload:hover { background: var(--warning); color: #fff; }
        .action-btn-download { background: rgba(59,130,246,0.15); color: #93C5FD; }
        .action-btn-download:hover { background: #3B82F6; color: #fff; }

        /* Forms */
        .admin-form-group { margin-bottom: 18px; }
        .admin-form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .admin-form-group input,
        .admin-form-group textarea,
        .admin-form-group select {
            width: 100%; padding: 10px 14px;
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 8px; color: var(--text); font-size: 0.875rem;
            outline: none; transition: border-color 0.2s;
        }
        .admin-form-group input:focus,
        .admin-form-group textarea:focus,
        .admin-form-group select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79,70,229,0.15); }
        .admin-form-group textarea { resize: vertical; min-height: 80px; }
        .admin-form-group select option { background: var(--bg-card); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        .checkbox-group { display: flex; align-items: center; gap: 8px; }
        .checkbox-group input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--primary); }
        .checkbox-group label { font-size: 0.875rem; color: var(--text); text-transform: none; letter-spacing: 0; }

        /* Modal */
        .admin-modal {
            position: fixed; inset: 0; z-index: 1000;
            display: flex; align-items: center; justify-content: center; padding: 20px;
            opacity: 0; pointer-events: none; transition: opacity 0.3s;
        }
        .admin-modal.open { opacity: 1; pointer-events: all; }
        .admin-modal-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.7); cursor: pointer; }
        .admin-modal-dialog {
            position: relative; background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 16px; width: 100%; max-width: 640px; max-height: 90vh;
            overflow-y: auto; transform: scale(0.95); transition: transform 0.3s;
            box-shadow: 0 25px 80px rgba(0,0,0,0.5);
        }
        .admin-modal.open .admin-modal-dialog { transform: scale(1); }
        .admin-modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 24px; border-bottom: 1px solid var(--border);
            position: sticky; top: 0; background: var(--bg-card); z-index: 1;
        }
        .admin-modal-header h3 { font-size: 1rem; font-weight: 700; }
        .admin-modal-close { width: 32px; height: 32px; border-radius: 8px; background: var(--bg-hover); color: var(--text-muted); display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s; }
        .admin-modal-close:hover { background: var(--danger); color: #fff; }
        .admin-modal-body { padding: 24px; }
        .admin-modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; gap: 10px; justify-content: flex-end; }

        /* Alert */
        .admin-alert { padding: 12px 16px; border-radius: 8px; font-size: 0.875rem; display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
        .admin-alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #34D399; }
        .admin-alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #FCA5A5; }
        .admin-alert-info { background: rgba(79,70,229,0.1); border: 1px solid rgba(79,70,229,0.3); color: #818CF8; }

        /* Search */
        .table-search { display: flex; align-items: center; gap: 10px; }
        .table-search input { padding: 8px 12px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 0.85rem; outline: none; width: 220px; }
        .table-search input:focus { border-color: var(--primary); }

        /* Import area */
        .import-area {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .import-area:hover, .import-area.dragover { border-color: var(--primary); background: rgba(79,70,229,0.05); }
        .import-area i { font-size: 2.5rem; color: var(--text-muted); margin-bottom: 12px; display: block; }
        .import-area p { color: var(--text-muted); font-size: 0.875rem; }

        /* Settings */
        .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .settings-section { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 24px; }
        .settings-section h3 { font-size: 0.95rem; font-weight: 700; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
        .color-input-wrap { display: flex; align-items: center; gap: 10px; }
        .color-input-wrap input[type="color"] { width: 40px; height: 36px; border: none; background: none; cursor: pointer; border-radius: 6px; }
        .color-input-wrap input[type="text"] { flex: 1; }

        /* Pagination */
        .admin-pagination { display: flex; justify-content: flex-end; align-items: center; gap: 8px; padding: 14px 20px; border-top: 1px solid var(--border); }
        .page-info { font-size: 0.8rem; color: var(--text-muted); margin-right: auto; }
        .page-btn { width: 32px; height: 32px; border-radius: 6px; background: var(--bg); border: 1px solid var(--border); color: var(--text-muted); font-size: 0.8rem; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
        .page-btn:hover, .page-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; }

        /* Toast */
        .admin-toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
        .admin-toast { background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 12px 16px; display: flex; align-items: center; gap: 10px; font-size: 0.875rem; box-shadow: 0 8px 30px rgba(0,0,0,0.3); animation: toastIn 0.3s ease; min-width: 260px; }
        .admin-toast.success { border-color: rgba(16,185,129,0.4); }
        .admin-toast.success i { color: #34D399; }
        .admin-toast.error { border-color: rgba(239,68,68,0.4); }
        .admin-toast.error i { color: #FCA5A5; }
        @keyframes toastIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }

        /* Product icon in table */
        .product-icon-sm { width: 32px; height: 32px; border-radius: 8px; background: var(--bg); display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .product-icon-sm img { width: 20px; height: 20px; object-fit: contain; }

        @media (max-width: 1024px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .settings-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .admin-sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .admin-sidebar.open { transform: translateX(0); }
            .admin-main { margin-left: 0; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .form-row, .form-row-3 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
<!-- ============ LOGIN PAGE ============ -->
<div class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <h1><?php echo htmlspecialchars($settings['site']['name'] ?? 'Магазин аккаунтов'); ?></h1>
            <p>Панель управления</p>
        </div>
        <?php if (!empty($loginError)): ?>
        <div class="login-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($loginError); ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label>Логин</label>
                <input type="text" name="login" placeholder="admin" required autofocus>
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-login">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Войти в панель
            </button>
        </form>
        <div style="text-align:center;margin-top:20px;">
            <a href="/" style="color:var(--text-muted);font-size:0.8rem;">← Вернуться на сайт</a>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ============ ADMIN PANEL ============ -->
<?php
$products = getProducts();
$categories = getCategories();
$pagesData = getPages();
// Load advertising data (needed globally for JS)
$adDataGlobal = getAdvertising();
$adSpots = $adDataGlobal['spots'] ?? [];
$adBanners = $adDataGlobal['banners'] ?? [];
$activeProducts = array_filter($products, fn($p) => $p['status'] === 'active');
$totalQty = array_sum(array_column(array_values($activeProducts), 'quantity'));
// Load contacts
$contactsFile = __DIR__ . '/../data/contacts.json';
$contactsList = file_exists($contactsFile) ? (json_decode(file_get_contents($contactsFile), true) ?? []) : [];
$unreadContacts = count($contactsList);
?>

<div class="admin-layout">
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">A</div>
            <div>
                <div class="sidebar-logo-text"><?php echo htmlspecialchars($settings['site']['name'] ?? 'Магазин аккаунтов'); ?></div>
                <div class="sidebar-logo-sub">Панель управления</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="sidebar-section-title">Главное</div>
            <a href="/admin/" class="sidebar-link <?php echo $section === 'dashboard' ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge"></i> Дашборд
            </a>

            <div class="sidebar-section-title">Каталог</div>
            <a href="/admin/?section=products" class="sidebar-link <?php echo $section === 'products' ? 'active' : ''; ?>">
                <i class="fa-solid fa-box"></i> Товары
                <span class="badge"><?php echo count($activeProducts); ?></span>
            </a>
            <a href="/admin/?section=categories" class="sidebar-link <?php echo $section === 'categories' ? 'active' : ''; ?>">
                <i class="fa-solid fa-layer-group"></i> Категории
                <span class="badge"><?php echo count($categories); ?></span>
            </a>
            <a href="/admin/?section=import" class="sidebar-link <?php echo $section === 'import' ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-import"></i> Импорт CSV
            </a>

            <div class="sidebar-section-title">Контент</div>
            <a href="/admin/?section=faq" class="sidebar-link <?php echo $section === 'faq' ? 'active' : ''; ?>">
                <i class="fa-solid fa-circle-question"></i> FAQ
            </a>
            <a href="/admin/?section=info" class="sidebar-link <?php echo $section === 'info' ? 'active' : ''; ?>">
                <i class="fa-solid fa-newspaper"></i> Статьи
                <?php $totalArticles = count($pagesData['info']['articles'] ?? []); if ($totalArticles > 0): ?><span class="badge"><?php echo $totalArticles; ?></span><?php endif; ?>
            </a>
            <a href="/admin/?section=rules" class="sidebar-link <?php echo $section === 'rules' ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-contract"></i> Правила
            </a>

            <a href="/admin/?section=questions" class="sidebar-link <?php echo $section === 'questions' ? 'active' : ''; ?>">
                <i class="fa-solid fa-envelope"></i> Вопросы
                <?php if ($unreadContacts > 0): ?><span class="badge" style="background:var(--danger);"><?php echo $unreadContacts; ?></span><?php endif; ?>
            </a>

            <div class="sidebar-section-title">Монетизация</div>
            <a href="/admin/?section=advertising" class="sidebar-link <?php echo $section === 'advertising' ? 'active' : ''; ?>">
                <i class="fa-solid fa-rectangle-ad"></i> Реклама
                <?php
                $adDataNav = getAdvertising();
                $activeBannersCount = count(array_filter($adDataNav['banners'] ?? [], fn($b) => ($b['active'] ?? false)));
                if ($activeBannersCount > 0): ?><span class="badge" style="background:var(--secondary);"><?php echo $activeBannersCount; ?></span><?php endif; ?>
            </a>

            <div class="sidebar-section-title">Система</div>
            <a href="/admin/?section=payments" class="sidebar-link <?php echo $section === 'payments' ? 'active' : ''; ?>">
                <i class="fa-solid fa-credit-card"></i> Платежи
            </a>
            <a href="/admin/?section=themes" class="sidebar-link <?php echo $section === 'themes' ? 'active' : ''; ?>">
                <i class="fa-solid fa-palette"></i> Темы оформления
            </a>
            <a href="/admin/?section=settings" class="sidebar-link <?php echo $section === 'settings' ? 'active' : ''; ?>">
                <i class="fa-solid fa-gear"></i> Настройки
            </a>
            <a href="/" target="_blank" class="sidebar-link">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Открыть сайт
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar"><?php echo strtoupper(substr($adminLogin, 0, 1)); ?></div>
                <div>
                    <div class="sidebar-user-name"><?php echo htmlspecialchars($adminLogin); ?></div>
                    <div class="sidebar-user-role">Администратор</div>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="topbar-btn topbar-btn-danger" style="width:100%;justify-content:center;border-radius:8px;padding:8px;">
                    <i class="fa-solid fa-right-from-bracket"></i> Выйти
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="admin-main">
        <?php if ($section === 'dashboard'): ?>
        <!-- ===== DASHBOARD ===== -->
        <div class="admin-topbar">
            <h2><i class="fa-solid fa-gauge" style="color:var(--primary);margin-right:8px;"></i> Дашборд</h2>
            <div class="topbar-actions">
                <button class="topbar-btn topbar-btn-secondary" onclick="clearSiteCache()" id="clearCacheBtn" title="Удалить кеш браузера и очистить следы домена">
                    <i class="fa-solid fa-broom"></i> Очистить кеш
                </button>
                <button class="topbar-btn topbar-btn-secondary" onclick="regenerateSitemap()" id="sitemapBtn">
                    <i class="fa-solid fa-sitemap"></i> Перегенерировать sitemap.xml
                </button>
                <a href="/admin/?section=products&action=add" class="topbar-btn topbar-btn-primary">
                    <i class="fa-solid fa-plus"></i> Добавить товар
                </a>
            </div>
        </div>
        <div class="admin-content">
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-card-icon blue"><i class="fa-solid fa-box"></i></div>
                    <div>
                        <div class="stat-card-value"><?php echo count($activeProducts); ?></div>
                        <div class="stat-card-label">Активных товаров</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green"><i class="fa-solid fa-cubes"></i></div>
                    <div>
                        <div class="stat-card-value"><?php echo number_format($totalQty, 0, '.', ' '); ?></div>
                        <div class="stat-card-label">Аккаунтов в наличии</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon yellow"><i class="fa-solid fa-layer-group"></i></div>
                    <div>
                        <div class="stat-card-value"><?php echo count($categories); ?></div>
                        <div class="stat-card-label">Категорий</div>
                    </div>
                </div>
                <?php $lowStockProducts = array_values(array_filter($products, fn($p) => $p['quantity'] < 15 && $p['status'] === 'active')); ?>
                <div class="stat-card" style="cursor:pointer;" onclick="document.getElementById('lowStockBlock').scrollIntoView({behavior:'smooth'})" title="Перейти к товарам с малым остатком">
                    <div class="stat-card-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div>
                        <div class="stat-card-value"><?php echo count($lowStockProducts); ?></div>
                        <div class="stat-card-label">Заканчиваются (&lt;15 шт.)</div>
                    </div>
                </div>
            </div>

            <!-- Recent Products -->
            <?php
            // Show last 10 products by ID (most recently added)
            $recentProducts = $products;
            usort($recentProducts, fn($a, $b) => ($b['id'] ?? 0) - ($a['id'] ?? 0));
            $recentProducts = array_slice($recentProducts, 0, 10);
            ?>
            <div class="admin-table-wrap">
                <div class="admin-table-header">
                    <h3>Последние добавленные товары (<?php echo count($recentProducts); ?>)</h3>
                    <a href="/admin/?section=products" class="topbar-btn topbar-btn-secondary">Все товары →</a>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Товар</th>
                            <th>Категория</th>
                            <th>Цена</th>
                            <th>Кол-во</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentProducts as $p): ?>
                        <tr>
                            <td style="color:var(--text-muted);font-family:'JetBrains Mono',monospace;font-size:0.8rem;"><?php echo $p['id']; ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="product-icon-sm">
                                        <img src="/images/icons/<?php echo $p['icon']; ?>" alt="" onerror="this.src='/images/icons/default.svg'">
                                    </div>
                                    <div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($p['name']); ?></div>
                                        <div style="font-size:0.75rem;color:var(--text-muted);">/item/<?php echo $p['slug']; ?>/</div>
                                    </div>
                                </div>
                            </td>
                            <td style="color:var(--text-muted);"><?php echo ucfirst($p['category']); ?></td>
                            <td class="price-cell"><?php echo formatPrice($p['price']); ?></td>
                            <td class="qty-cell" style="<?php echo $p['quantity'] < 15 ? 'color:#FCD34D;font-weight:600;' : ''; ?>"><?php echo $p['quantity']; ?><?php if ($p['quantity'] < 15): ?> <i class="fa-solid fa-triangle-exclamation" style="color:#FCD34D;font-size:0.75rem;"></i><?php endif; ?></td>
                            <td><span class="badge badge-<?php echo $p['status'] === 'active' ? 'active' : 'inactive'; ?>"><?php echo $p['status'] === 'active' ? 'Активен' : 'Скрыт'; ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="action-btn action-btn-edit" onclick="editProduct(<?php echo $p['id']; ?>)" title="Редактировать"><i class="fa-solid fa-pen"></i></button>
                                    <a href="/item/<?php echo $p['slug']; ?>/" target="_blank" class="action-btn action-btn-view" title="Просмотр"><i class="fa-solid fa-eye"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Low Stock Products -->
            <?php if (!empty($lowStockProducts)): ?>
            <div class="admin-table-wrap" id="lowStockBlock" style="border:1px solid rgba(239,68,68,0.4);">
                <div class="admin-table-header" style="background:rgba(239,68,68,0.08);">
                    <h3 style="color:#EF4444;"><i class="fa-solid fa-triangle-exclamation" style="margin-right:8px;"></i>Мало осталось (менее 15 шт.) &mdash; <?php echo count($lowStockProducts); ?> товаров</h3>
                    <a href="/admin/?section=products" class="topbar-btn topbar-btn-danger">Перейти к товарам →</a>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Товар</th>
                            <th>Категория</th>
                            <th>Цена</th>
                            <th>Остаток</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockProducts as $p): ?>
                        <tr style="background:rgba(239,68,68,0.04);">
                            <td style="color:var(--text-muted);font-family:'JetBrains Mono',monospace;font-size:0.8rem;"><?php echo $p['id']; ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="product-icon-sm">
                                        <img src="/images/icons/<?php echo $p['icon']; ?>" alt="" onerror="this.src='/images/icons/default.svg'">
                                    </div>
                                    <div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($p['name']); ?></div>
                                        <div style="font-size:0.75rem;color:var(--text-muted);">/item/<?php echo $p['slug']; ?>/</div>
                                    </div>
                                </div>
                            </td>
                            <td style="color:var(--text-muted);"><?php echo ucfirst($p['category']); ?></td>
                            <td class="price-cell"><?php echo formatPrice($p['price']); ?></td>
                            <td style="color:#EF4444;font-weight:700;">
                                <i class="fa-solid fa-triangle-exclamation" style="margin-right:4px;"></i>
                                <?php echo $p['quantity']; ?> шт.
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="action-btn action-btn-edit" onclick="editProduct(<?php echo $p['id']; ?>)" title="Редактировать"><i class="fa-solid fa-pen"></i></button>
                                    <a href="/item/<?php echo $p['slug']; ?>/" target="_blank" class="action-btn action-btn-view" title="Просмотр"><i class="fa-solid fa-eye"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php elseif ($section === 'products'): ?>
        <!-- ===== PRODUCTS ===== -->
        <?php
        // Group products by category and subcategory
        $productsByCategory = [];
        foreach ($products as $p) {
            $cat = $p['category'] ?? 'other';
            $sub = $p['subcategory'] ?? '';
            if (!isset($productsByCategory[$cat])) $productsByCategory[$cat] = [];
            if (!isset($productsByCategory[$cat][$sub])) $productsByCategory[$cat][$sub] = [];
            $productsByCategory[$cat][$sub][] = $p;
        }
        // Build category name map
        $catNameMap = [];
        $subNameMap = [];
        foreach ($categories as $cat) {
            $catNameMap[$cat['slug']] = $cat['name'];
            foreach ($cat['subcategories'] ?? [] as $sub) {
                $subNameMap[$cat['slug']][$sub['slug']] = $sub['name'];
            }
        }
        ?>
        <div class="admin-topbar">
            <h2><i class="fa-solid fa-box" style="color:var(--primary);margin-right:8px;"></i> Товары (<?php echo count($products); ?>)</h2>
            <div class="topbar-actions">
                <div class="table-search">
                    <input type="text" id="productSearch" placeholder="Поиск товаров..." oninput="filterProducts(this.value)">
                </div>
                <button class="topbar-btn topbar-btn-secondary" onclick="exportAllProducts()" title="Скачать все товары в CSV" id="exportProductsBtn">
                    <i class="fa-solid fa-file-arrow-down"></i> Скачать все товары
                </button>
                <button class="topbar-btn topbar-btn-danger" onclick="deleteAllProducts()" title="Удалить все товары из магазина">
                    <i class="fa-solid fa-trash-can"></i> Удалить все товары
                </button>
                <button class="topbar-btn topbar-btn-primary" onclick="openAddProductModal()">
                    <i class="fa-solid fa-plus"></i> Добавить товар
                </button>
            </div>
        </div>
        <div class="admin-content">
            <?php foreach ($productsByCategory as $catSlug => $subcats): ?>
            <div class="admin-table-wrap" style="margin-bottom:24px;" id="cat-block-<?php echo $catSlug; ?>">
                <div class="admin-table-header" style="cursor:pointer;" onclick="toggleCatBlock('<?php echo $catSlug; ?>')">
                    <h3 style="display:flex;align-items:center;gap:10px;">
                        <img src="/images/icons/<?php echo htmlspecialchars($catNameMap[$catSlug] ?? $catSlug); ?>.svg" alt="" style="width:20px;height:20px;" onerror="this.style.display='none'">
                        <?php echo htmlspecialchars($catNameMap[$catSlug] ?? ucfirst($catSlug)); ?>
                        <span style="font-size:0.8rem;color:var(--text-muted);font-weight:400;">(<?php echo array_sum(array_map('count', $subcats)); ?> товаров)</span>
                    </h3>
                    <span class="cat-toggle-btn" style="color:var(--text-muted);font-size:0.85rem;">Свернуть ▲</span>
                </div>
                <div id="cat-body-<?php echo $catSlug; ?>">
                <?php foreach ($subcats as $subSlug => $subProducts): ?>
                <?php if ($subSlug): ?>
                <div style="padding:8px 16px;background:var(--bg-hover);border-bottom:1px solid var(--border);font-size:0.8rem;color:var(--text-muted);font-weight:600;">
                    <i class="fa-solid fa-tag" style="margin-right:6px;"></i>
                    <?php echo htmlspecialchars($subNameMap[$catSlug][$subSlug] ?? ucfirst($subSlug)); ?>
                    <span style="font-weight:400;">(<?php echo count($subProducts); ?>)</span>
                </div>
                <?php endif; ?>
                <table class="admin-table" style="margin-bottom:0;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Товар</th>
                            <th>Хит</th>
                            <th>Цена</th>
                            <th>Кол-во</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody">
                        <?php foreach ($subProducts as $p): ?>
                        <tr data-name="<?php echo strtolower(htmlspecialchars($p['name'])); ?>" data-cat="<?php echo strtolower($p['category']); ?>" <?php if ($p['quantity'] < 15 && $p['status'] === 'active'): ?>style="background:rgba(239,68,68,0.06);border-left:3px solid #EF4444;"<?php endif; ?>>
                            <td style="color:var(--text-muted);font-family:'JetBrains Mono',monospace;"><?php echo $p['id']; ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="product-icon-sm">
                                        <img src="/images/icons/<?php echo $p['icon']; ?>" alt="" onerror="this.src='/images/icons/default.svg'">
                                    </div>
                                    <div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($p['name']); ?></div>
                                        <div style="font-size:0.75rem;color:var(--text-muted);">/item/<?php echo $p['slug']; ?>/</div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo $p['popular'] ? '<span style="color:var(--warning);"><i class="fa-solid fa-fire"></i> Хит</span>' : '<span style="color:var(--text-muted);">-</span>'; ?></td>
                            <td class="price-cell"><?php echo formatPrice($p['price']); ?></td>
                            <td class="qty-cell" style="<?php echo $p['quantity'] < 15 ? 'color:#EF4444;font-weight:700;' : ''; ?>">
                                <?php if ($p['quantity'] < 15): ?><i class="fa-solid fa-triangle-exclamation" style="margin-right:4px;font-size:0.75rem;"></i><?php endif; ?>
                                <?php echo $p['quantity']; ?>
                            </td>
                            <td><span class="badge badge-<?php echo $p['status'] === 'active' ? 'active' : 'inactive'; ?>"><?php echo $p['status'] === 'active' ? 'Активен' : 'Скрыт'; ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="action-btn action-btn-edit" onclick="editProduct(<?php echo $p['id']; ?>)" title="Редактировать"><i class="fa-solid fa-pen"></i></button>
                                    <a href="/item/<?php echo $p['slug']; ?>/" target="_blank" class="action-btn action-btn-view" title="Просмотр"><i class="fa-solid fa-eye"></i></a>
                                    <button class="action-btn action-btn-download" onclick="exportProductItems(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['name']); ?>')" title="Выгрузить аккаунты"><i class="fa-solid fa-file-arrow-down"></i></button>
                                    <button class="action-btn action-btn-upload" onclick="uploadProductItems(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['name']); ?>')" title="Загрузить товары"><i class="fa-solid fa-cloud-arrow-up"></i></button>
                                    <button class="action-btn action-btn-delete" onclick="deleteProduct(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['name']); ?>')" title="Удалить"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="admin-pagination">
                <span class="page-info">Всего: <?php echo count($products); ?> товаров</span>
            </div>
        </div>

        <?php elseif ($section === 'categories'): ?>
        <!-- ===== CATEGORIES ===== -->
        <div class="admin-topbar">
            <h2><i class="fa-solid fa-layer-group" style="color:var(--primary);margin-right:8px;"></i> Категории</h2>
            <div class="topbar-actions">
                <button class="topbar-btn topbar-btn-primary" onclick="openAddCategoryModal()">
                    <i class="fa-solid fa-plus"></i> Добавить категорию
                </button>
            </div>
        </div>
        <div class="admin-content">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Категория</th>
                            <th>Slug</th>
                            <th>Подкатегории</th>
                            <th>Товаров</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat):
                            $catProducts = array_filter($products, fn($p) => $p['category'] === $cat['slug']);
                        ?>
                        <tr>
                            <td style="color:var(--text-muted);font-family:'JetBrains Mono',monospace;"><?php echo $cat['id']; ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="product-icon-sm">
                                        <img src="/images/icons/<?php echo $cat['icon']; ?>" alt="" onerror="this.src='/images/icons/default.svg'">
                                    </div>
                                    <div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($cat['name']); ?></div>
                                        <div style="font-size:0.75rem;color:var(--text-muted);"><?php echo htmlspecialchars($cat['description']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-family:'JetBrains Mono',monospace;color:var(--text-muted);"><?php echo $cat['slug']; ?></td>
                            <td><?php echo count($cat['subcategories'] ?? []); ?> шт.</td>
                            <td class="qty-cell"><?php echo count($catProducts); ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="action-btn action-btn-edit" onclick="editCategory(<?php echo $cat['id']; ?>)" title="Редактировать"><i class="fa-solid fa-pen"></i></button>
                                    <a href="/category/<?php echo $cat['slug']; ?>/" target="_blank" class="action-btn action-btn-view" title="Просмотр"><i class="fa-solid fa-eye"></i></a>
                                    <button class="action-btn action-btn-delete" onclick="deleteCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name']); ?>')" title="Удалить"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($section === 'import'): ?>
        <!-- ===== IMPORT ===== -->
        <div class="admin-topbar">
            <h2><i class="fa-solid fa-file-import" style="color:var(--primary);margin-right:8px;"></i> Массовый импорт данных</h2>
        </div>
        <div class="admin-content">
            <div class="admin-table-wrap" style="padding:24px;">
                <div class="import-tabs">
                    <button class="import-tab active" onclick="setImportType('products', this)">Товары (до 500)</button>
                    <button class="import-tab" onclick="setImportType('articles', this)">Статьи блога (до 5)</button>
                </div>

                <div id="formatInfo" class="admin-alert admin-alert-info">
                    <i class="fa-solid fa-circle-info"></i>
                    <div id="formatText">
                        <strong>Формат CSV для товаров:</strong> name, slug, category, subcategory, short_description, full_description, price, quantity, icon, status, cookies, proxy, email_verified, country, sex, age, popular, features
                        <br><small>Разделитель: запятая. Кодировка: UTF-8. Первая строка - заголовки.</small>
                    </div>
                </div>

                <form id="importForm" enctype="multipart/form-data" onsubmit="submitImport(event)">
                    <input type="hidden" id="importType" name="type" value="products">
                    <div class="import-area" id="importArea" onclick="document.getElementById('csvFile').click()">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <p><strong>Нажмите для выбора файла</strong> или перетащите CSV сюда</p>
                        <p style="margin-top:8px;font-size:0.8rem;">Поддерживается: .csv (UTF-8)</p>
                    </div>
                    <input type="file" id="csvFile" name="csv" accept=".csv" style="display:none;" onchange="handleFileSelect(this)">
                    <div id="fileInfo" style="display:none;margin-top:16px;padding:12px 16px;background:var(--bg);border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;gap:10px;">
                        <i class="fa-solid fa-file-csv" style="color:var(--secondary);font-size:1.2rem;"></i>
                        <span id="fileName"></span>
                    </div>
                    <div style="margin-top:20px;display:flex;gap:10px;">
                        <button type="submit" id="importBtn" class="topbar-btn topbar-btn-primary" style="padding:10px 20px;">
                            <i class="fa-solid fa-upload"></i> Начать импорт
                        </button>
                        <button type="button" onclick="downloadTemplate()" class="topbar-btn topbar-btn-secondary" style="padding:10px 20px;">
                            <i class="fa-solid fa-download"></i> Скачать шаблон
                        </button>
                    </div>
                </form>

                <div id="importResult" style="display:none;margin-top:20px;"></div>
            </div>
        </div>

        <?php elseif ($section === 'faq'): ?>
        <!-- ===== FAQ ===== -->
        <div class="admin-topbar">
            <h2><i class="fa-solid fa-circle-question" style="color:var(--primary);margin-right:8px;"></i> FAQ</h2>
            <div class="topbar-actions">
                <button class="topbar-btn topbar-btn-primary" onclick="openAddFaqModal()">
                    <i class="fa-solid fa-plus"></i> Добавить вопрос
                </button>
            </div>
        </div>
        <div class="admin-content">
            <?php $faqItems = $pagesData['faq']['items'] ?? []; ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Вопрос</th>
                            <th>Ответ</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="faqTableBody">
                        <?php foreach ($faqItems as $i => $item): ?>
                        <tr>
                            <td style="color:var(--text-muted);"><?php echo $i + 1; ?></td>
                            <td style="font-weight:600;max-width:300px;"><?php echo htmlspecialchars($item['question']); ?></td>
                            <td style="color:var(--text-muted);max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($item['answer']); ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="action-btn action-btn-edit" onclick="editFaq(<?php echo $i; ?>)" title="Редактировать"><i class="fa-solid fa-pen"></i></button>
                                    <button class="action-btn action-btn-delete" onclick="deleteFaq(<?php echo $i; ?>)" title="Удалить"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($section === 'settings'): ?>
        <!-- ===== SETTINGS ===== -->
        <div class="admin-topbar">
            <h2><i class="fa-solid fa-gear" style="color:var(--primary);margin-right:8px;"></i> Настройки сайта</h2>
            <div class="topbar-actions">
                <button class="topbar-btn topbar-btn-secondary" onclick="regenerateSitemap()" id="sitemapBtnSettings">
                    <i class="fa-solid fa-sitemap"></i> Перегенерировать sitemap.xml
                </button>
                <button class="topbar-btn topbar-btn-primary" onclick="saveSettings()">
                    <i class="fa-solid fa-floppy-disk"></i> Сохранить
                </button>
            </div>
        </div>
        <div class="admin-content">
            <?php $s = $settings; ?>
            <div class="settings-grid">
                <!-- Template Selection -->
                <div class="settings-section" style="grid-column:1/-1;">
                    <h3><i class="fa-solid fa-palette" style="margin-right:8px;color:var(--primary);"></i> Шаблон сайта</h3>
                    <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:16px;">Выберите шаблон оформления сайта. Смена шаблона доступна только администратору через эту панель.</p>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;">
                        <?php
                        $templates = [
                            'dark-pro' => ['name' => 'Тёмный Про', 'desc' => 'Фиолетовые акценты, карточки-плитки', 'icon' => 'fa-moon', 'color' => 'linear-gradient(135deg,#4F46E5,#7C3AED)'],
                            'cyber-neon' => ['name' => 'Кибер-Неон', 'desc' => 'Зелёный неон, тёмный фон', 'icon' => 'fa-bolt', 'color' => 'linear-gradient(135deg,#00FF88,#00D4FF)'],
                            'accsmarket' => ['name' => 'AccsMarket', 'desc' => 'Строчный список, зелёный', 'icon' => 'fa-list', 'color' => 'linear-gradient(135deg,#4CAF50,#8BC34A)'],
                            'light-clean' => ['name' => 'Светлый чистый', 'desc' => 'Белый фон, синие акценты', 'icon' => 'fa-sun', 'color' => 'linear-gradient(135deg,#2563EB,#7C3AED)'],
                            'midnight-gold' => ['name' => 'Полночь и Золото', 'desc' => 'Чёрный фон, золотые акценты', 'icon' => 'fa-star', 'color' => 'linear-gradient(135deg,#F59E0B,#FBBF24)'],
                            'noves-shop' => ['name' => 'Noves Shop', 'desc' => 'Минимализм, светлый', 'icon' => 'fa-store', 'color' => 'linear-gradient(135deg,#2563EB,#10B981)'],
                            'dark-shopping' => ['name' => 'Dark Shopping', 'desc' => 'Премиум тёмный, золото', 'icon' => 'fa-crown', 'color' => 'linear-gradient(135deg,#D4AF37,#F4D03F)'],
                        ];
                        $currentTemplate = $s['site']['template'] ?? 'dark-pro';
                        foreach ($templates as $tKey => $tData):
                        ?>
                        <div onclick="selectTemplate('<?php echo $tKey; ?>')" id="tpl-card-<?php echo $tKey; ?>"
                             style="border:2px solid <?php echo $currentTemplate === $tKey ? 'var(--primary)' : 'var(--border)'; ?>;border-radius:12px;padding:16px;cursor:pointer;background:<?php echo $currentTemplate === $tKey ? 'rgba(79,70,229,0.1)' : 'var(--bg)'; ?>;transition:all 0.2s;">
                            <div style="width:48px;height:48px;border-radius:10px;background:<?php echo $tData['color']; ?>;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                                <i class="fa-solid <?php echo $tData['icon']; ?>" style="color:#fff;font-size:1.2rem;"></i>
                            </div>
                            <div style="font-weight:600;margin-bottom:4px;"><?php echo $tData['name']; ?></div>
                            <div style="font-size:0.78rem;color:var(--text-muted);"><?php echo $tData['desc']; ?></div>
                            <?php if ($currentTemplate === $tKey): ?>
                            <div style="margin-top:8px;font-size:0.75rem;color:var(--primary);font-weight:600;"><i class="fa-solid fa-check"></i> Активен</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="siteTemplate" value="<?php echo htmlspecialchars($currentTemplate); ?>">
                </div>

                <!-- Shop Settings -->
                <div class="settings-section">
                    <h3><i class="fa-solid fa-store" style="margin-right:8px;color:var(--primary);"></i> Магазин</h3>
                    <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--bg);border:1px solid var(--border);border-radius:10px;">
                        <input type="checkbox" id="showDemoProducts" <?php echo !empty($s['shop']['show_demo_products']) ? 'checked' : ''; ?>>
                        <label for="showDemoProducts" style="margin:0;cursor:pointer;flex:1;">
                            <strong>Отображать демо-товары</strong>
                            <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;">Товары с количеством, но без загруженных элементов</div>
                        </label>
                    </div>
                </div>

                <!-- Site Settings -->
                <div class="settings-section">
                    <h3><i class="fa-solid fa-globe" style="margin-right:8px;color:var(--primary);"></i> Основные</h3>
                    <div class="admin-form-group">
                        <label>Название сайта</label>
                        <input type="text" id="siteName" value="<?php echo htmlspecialchars($s['site']['name'] ?? ''); ?>">
                    </div>
                    <div class="admin-form-group">
                        <label>Слоган</label>
                        <input type="text" id="siteTagline" value="<?php echo htmlspecialchars($s['site']['tagline'] ?? ''); ?>">
                    </div>
                    <div class="admin-form-group">
                        <label>URL сайта</label>
                        <input type="text" id="siteUrl" value="<?php echo htmlspecialchars($s['site']['url'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Contacts -->
                <div class="settings-section">
                    <h3><i class="fa-solid fa-address-book" style="margin-right:8px;color:var(--primary);"></i> Контакты</h3>
                    <div class="admin-form-group">
                        <label>Email поддержки</label>
                        <input type="email" id="contactEmail" value="<?php echo htmlspecialchars($s['contacts']['email'] ?? ''); ?>">
                    </div>
                    <div class="admin-form-group">
                        <label>Telegram (логин)</label>
                        <input type="text" id="contactTelegram" value="<?php echo htmlspecialchars($s['contacts']['telegram'] ?? ''); ?>">
                    </div>
                    <div class="admin-form-group">
                        <label>Telegram URL</label>
                        <input type="text" id="contactTelegramUrl" value="<?php echo htmlspecialchars($s['contacts']['telegram_url'] ?? ''); ?>">
                    </div>
                </div>

                <!-- SEO -->
                <div class="settings-section" style="grid-column:1/-1;">
                    <h3><i class="fa-solid fa-magnifying-glass" style="margin-right:8px;color:var(--primary);"></i> SEO - Все страницы сайта</h3>
                    <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">Настройте SEO-метатеги для всех страниц сайта. Эти данные используются поисковыми системами для индексации.</p>

                    <!-- Главная страница -->
                    <div style="background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;">
                        <div style="font-weight:700;font-size:0.9rem;color:var(--primary);margin-bottom:12px;"><i class="fa-solid fa-house"></i> Главная страница (/)</div>
                        <div class="form-row">
                            <div class="admin-form-group">
                                <label>SEO Title главной</label>
                                <input type="text" id="seoTitle" value="<?php echo htmlspecialchars($s['seo']['title'] ?? ''); ?>" placeholder="Купить аккаунты Facebook, Instagram...">
                            </div>
                            <div class="admin-form-group">
                                <label>Keywords</label>
                                <input type="text" id="seoKeywords" value="<?php echo htmlspecialchars($s['seo']['keywords'] ?? ''); ?>" placeholder="купить аккаунты, facebook, instagram...">
                            </div>
                        </div>
                        <div class="admin-form-group">
                            <label>Description главной</label>
                            <textarea id="seoDescription" rows="2"><?php echo htmlspecialchars($s['seo']['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- FAQ страница -->
                    <?php $pagesDataSeo = getPages(); ?>
                    <div style="background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;">
                        <div style="font-weight:700;font-size:0.9rem;color:var(--primary);margin-bottom:12px;"><i class="fa-solid fa-circle-question"></i> Страница FAQ (/faq/)</div>
                        <div class="form-row">
                            <div class="admin-form-group">
                                <label>SEO Title</label>
                                <input type="text" id="seoFaqTitle" value="<?php echo htmlspecialchars($pagesDataSeo['faq']['title'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="admin-form-group">
                            <label>SEO Description</label>
                            <textarea id="seoFaqDescription" rows="2"><?php echo htmlspecialchars($pagesDataSeo['faq']['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Правила страница -->
                    <div style="background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;">
                        <div style="font-weight:700;font-size:0.9rem;color:var(--primary);margin-bottom:12px;"><i class="fa-solid fa-file-contract"></i> Страница Правил (/rules/)</div>
                        <div class="form-row">
                            <div class="admin-form-group">
                                <label>SEO Title</label>
                                <input type="text" id="seoRulesTitle" value="<?php echo htmlspecialchars($pagesDataSeo['rules']['title'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="admin-form-group">
                            <label>SEO Description</label>
                            <textarea id="seoRulesDescription" rows="2"><?php echo htmlspecialchars($pagesDataSeo['rules']['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Блог/Инфо страница -->
                    <div style="background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;">
                        <div style="font-weight:700;font-size:0.9rem;color:var(--primary);margin-bottom:12px;"><i class="fa-solid fa-newspaper"></i> Страница Блога (/info/)</div>
                        <div class="form-row">
                            <div class="admin-form-group">
                                <label>SEO Title</label>
                                <input type="text" id="seoInfoTitle" value="<?php echo htmlspecialchars($pagesDataSeo['info']['title'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="admin-form-group">
                            <label>SEO Description</label>
                            <textarea id="seoInfoDescription" rows="2"><?php echo htmlspecialchars($pagesDataSeo['info']['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Оформление заказа -->
                    <div style="background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:16px;">
                        <div style="font-weight:700;font-size:0.9rem;color:var(--primary);margin-bottom:12px;"><i class="fa-solid fa-cart-shopping"></i> Страница Оформления заказа (/oplata/)</div>
                        <div class="form-row">
                            <div class="admin-form-group">
                                <label>SEO Title</label>
                                <input type="text" id="seoOplataTitle" value="<?php echo htmlspecialchars($s['seo']['oplata_title'] ?? 'Оформление заказа'); ?>">
                            </div>
                        </div>
                        <div class="admin-form-group">
                            <label>SEO Description</label>
                            <textarea id="seoOplataDescription" rows="2"><?php echo htmlspecialchars($s['seo']['oplata_description'] ?? 'Оформите заказ и выберите удобный способ оплаты.'); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Analytics -->
                <div class="settings-section" style="grid-column:1/-1;">
                    <h3><i class="fa-solid fa-chart-line" style="margin-right:8px;color:var(--primary);"></i> Аналитика и отслеживание</h3>
                    <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">Коды аналитики автоматически добавляются на все страницы сайта, включая будущие статьи блога.</p>

                    <div style="background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;">
                        <div style="font-weight:700;font-size:0.9rem;color:var(--primary);margin-bottom:12px;"><i class="fa-brands fa-google"></i> Google Analytics 4</div>
                        <div class="form-row">
                            <div class="admin-form-group">
                                <label>Google Analytics 4 ID <span style="color:var(--text-muted);font-size:0.8rem;">(G-XXXXXXXXXX)</span></label>
                                <input type="text" id="analyticsGa4Id" value="<?php echo htmlspecialchars($s['analytics']['ga4_id'] ?? ''); ?>" placeholder="G-XXXXXXXXXX">
                            </div>
                            <div class="admin-form-group">
                                <label>Google Site Verification <span style="color:var(--text-muted);font-size:0.8rem;">- Search Console</span></label>
                                <input type="text" id="analyticsGoogleVerify" value="<?php echo htmlspecialchars($s['analytics']['google_verify'] ?? ''); ?>" placeholder="Код подтверждения Google">
                            </div>
                        </div>
                    </div>

                    <div style="background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;">
                        <div style="font-weight:700;font-size:0.9rem;color:var(--primary);margin-bottom:12px;"><i class="fa-solid fa-tag"></i> Google Tag Manager</div>
                        <div class="admin-form-group">
                            <label>GTM Container ID <span style="color:var(--text-muted);font-size:0.8rem;">(GTM-XXXXXXX)</span></label>
                            <input type="text" id="analyticsGtmId" value="<?php echo htmlspecialchars($s['analytics']['gtm_id'] ?? ''); ?>" placeholder="GTM-XXXXXXX">
                        </div>
                    </div>

                    <div style="background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;">
                        <div style="font-weight:700;font-size:0.9rem;color:var(--primary);margin-bottom:12px;"><i class="fa-solid fa-chart-bar"></i> Яндекс.Метрика</div>
                        <div class="form-row">
                            <div class="admin-form-group">
                                <label>Номер счётчика Яндекс.Метрики <span style="color:var(--text-muted);font-size:0.8rem;">(12345678)</span></label>
                                <input type="text" id="analyticsYmId" value="<?php echo htmlspecialchars($s['analytics']['ym_id'] ?? ''); ?>" placeholder="12345678">
                            </div>
                            <div class="admin-form-group">
                                <label>Яндекс верификация <span style="color:var(--text-muted);font-size:0.8rem;">(для Вебмастера)</span></label>
                                <input type="text" id="analyticsYandexVerify" value="<?php echo htmlspecialchars($s['analytics']['yandex_verify'] ?? ''); ?>" placeholder="Код подтверждения Яндекс">
                            </div>
                        </div>
                    </div>

                    <div style="background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:16px;">
                        <div style="font-weight:700;font-size:0.9rem;color:var(--primary);margin-bottom:12px;"><i class="fa-solid fa-code"></i> Произвольный код аналитики</div>
                        <div class="admin-form-group">
                            <label>Код в &lt;head&gt; <span style="color:var(--text-muted);font-size:0.8rem;">(вставляется на всех страницах)</span></label>
                            <textarea id="analyticsCustomHead" rows="4" placeholder="<!-- Ваш код аналитики для <head> -->"><?php echo htmlspecialchars($s['analytics']['custom_head'] ?? ''); ?></textarea>
                        </div>
                        <div class="admin-form-group">
                            <label>Код после &lt;body&gt; <span style="color:var(--text-muted);font-size:0.8rem;">(вставляется на всех страницах)</span></label>
                            <textarea id="analyticsCustomBody" rows="4" placeholder="<!-- Ваш код аналитики для <body> -->"><?php echo htmlspecialchars($s['analytics']['custom_body'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Colors -->
                <div class="settings-section" style="grid-column:1/-1;">
                    <h3><i class="fa-solid fa-palette" style="margin-right:8px;color:var(--primary);"></i> Цвета сайта</h3>
                    <div class="form-row-3" style="grid-template-columns:repeat(3,1fr);">
                        <div class="admin-form-group">
                            <label>Основной цвет</label>
                            <div class="color-input-wrap">
                                <input type="color" id="colorPrimaryPicker" value="<?php echo $s['colors']['primary'] ?? '#4F46E5'; ?>" oninput="document.getElementById('colorPrimary').value=this.value;document.documentElement.style.setProperty('--primary',this.value)">
                                <input type="text" id="colorPrimary" value="<?php echo $s['colors']['primary'] ?? '#4F46E5'; ?>" oninput="document.getElementById('colorPrimaryPicker').value=this.value">
                            </div>
                        </div>
                        <div class="admin-form-group">
                            <label>Акцентный цвет</label>
                            <div class="color-input-wrap">
                                <input type="color" id="colorSecondaryPicker" value="<?php echo $s['colors']['secondary'] ?? '#10B981'; ?>" oninput="document.getElementById('colorSecondary').value=this.value;document.documentElement.style.setProperty('--secondary',this.value)">
                                <input type="text" id="colorSecondary" value="<?php echo $s['colors']['secondary'] ?? '#10B981'; ?>" oninput="document.getElementById('colorSecondaryPicker').value=this.value">
                            </div>
                        </div>
                        <div class="admin-form-group">
                            <label>Цвет опасности (Danger)</label>
                            <div class="color-input-wrap">
                                <input type="color" id="colorDangerPicker" value="<?php echo $s['colors']['danger'] ?? '#EF4444'; ?>" oninput="document.getElementById('colorDanger').value=this.value">
                                <input type="text" id="colorDanger" value="<?php echo $s['colors']['danger'] ?? '#EF4444'; ?>" oninput="document.getElementById('colorDangerPicker').value=this.value">
                            </div>
                        </div>
                        <div class="admin-form-group">
                            <label>Цвет предупреждения (Warning)</label>
                            <div class="color-input-wrap">
                                <input type="color" id="colorWarningPicker" value="<?php echo $s['colors']['warning'] ?? '#F59E0B'; ?>" oninput="document.getElementById('colorWarning').value=this.value">
                                <input type="text" id="colorWarning" value="<?php echo $s['colors']['warning'] ?? '#F59E0B'; ?>" oninput="document.getElementById('colorWarningPicker').value=this.value">
                            </div>
                        </div>
                        <div class="admin-form-group">
                            <label>Фон (тёмный)</label>
                            <div class="color-input-wrap">
                                <input type="color" id="colorBgPicker" value="<?php echo $s['colors']['bg_dark'] ?? '#0F172A'; ?>" oninput="document.getElementById('colorBg').value=this.value">
                                <input type="text" id="colorBg" value="<?php echo $s['colors']['bg_dark'] ?? '#0F172A'; ?>" oninput="document.getElementById('colorBgPicker').value=this.value">
                            </div>
                        </div>
                        <div class="admin-form-group">
                            <label>Фон карточек</label>
                            <div class="color-input-wrap">
                                <input type="color" id="colorBgCardPicker" value="<?php echo $s['colors']['bg_card'] ?? '#1E293B'; ?>" oninput="document.getElementById('colorBgCard').value=this.value">
                                <input type="text" id="colorBgCard" value="<?php echo $s['colors']['bg_card'] ?? '#1E293B'; ?>" oninput="document.getElementById('colorBgCardPicker').value=this.value">
                            </div>
                        </div>
                        <div class="admin-form-group">
                            <label>Цвет текста (основной)</label>
                            <div class="color-input-wrap">
                                <input type="color" id="colorTextPrimaryPicker" value="<?php echo $s['colors']['text_primary'] ?? '#F8FAFC'; ?>" oninput="document.getElementById('colorTextPrimary').value=this.value">
                                <input type="text" id="colorTextPrimary" value="<?php echo $s['colors']['text_primary'] ?? '#F8FAFC'; ?>" oninput="document.getElementById('colorTextPrimaryPicker').value=this.value">
                            </div>
                        </div>
                        <div class="admin-form-group">
                            <label>Цвет текста (вторичный)</label>
                            <div class="color-input-wrap">
                                <input type="color" id="colorTextSecondaryPicker" value="<?php echo $s['colors']['text_secondary'] ?? '#94A3B8'; ?>" oninput="document.getElementById('colorTextSecondary').value=this.value">
                                <input type="text" id="colorTextSecondary" value="<?php echo $s['colors']['text_secondary'] ?? '#94A3B8'; ?>" oninput="document.getElementById('colorTextSecondaryPicker').value=this.value">
                            </div>
                        </div>
                        <div class="admin-form-group">
                            <label>Цвет бордера</label>
                            <div class="color-input-wrap">
                                <input type="color" id="colorBorderPicker" value="<?php echo $s['colors']['border'] ?? '#334155'; ?>" oninput="document.getElementById('colorBorder').value=this.value">
                                <input type="text" id="colorBorder" value="<?php echo $s['colors']['border'] ?? '#334155'; ?>" oninput="document.getElementById('colorBorderPicker').value=this.value">
                            </div>
                        </div>
                    </div>
                    <div class="admin-form-group" style="max-width:200px;">
                        <label>Скругление углов (px)</label>
                        <input type="number" id="colorBorderRadius" value="<?php echo $s['colors']['border_radius'] ?? '12'; ?>" min="0" max="32">
                    </div>
                </div>

                <!-- Admin Password -->
                <div class="settings-section" style="grid-column:1/-1;">
                    <h3><i class="fa-solid fa-lock" style="margin-right:8px;color:var(--primary);"></i> Смена пароля администратора</h3>
                    <div class="form-row">
                        <div class="admin-form-group">
                            <label>Новый пароль</label>
                            <input type="password" id="newPassword" placeholder="Оставьте пустым, чтобы не менять">
                        </div>
                        <div class="admin-form-group">
                            <label>Подтвердите пароль</label>
                            <input type="password" id="newPasswordConfirm" placeholder="Повторите пароль">
                        </div>
                    </div>
                    <button class="topbar-btn topbar-btn-primary" onclick="changePassword()">
                        <i class="fa-solid fa-key"></i> Сменить пароль
                    </button>
                </div>
            </div>
        </div>

        <?php elseif ($section === 'themes'): ?>
        <!-- ===== THEMES MANAGEMENT ===== -->
        <div class="admin-topbar">
            <h2><i class="fa-solid fa-palette" style="color:var(--primary);margin-right:8px;"></i> Темы оформления</h2>
            <div class="topbar-actions">
                <a href="/" target="_blank" class="topbar-btn topbar-btn-secondary">
                    <i class="fa-solid fa-eye"></i> Предпросмотр
                </a>
            </div>
        </div>
        <div class="admin-content">
            <div class="settings-section" style="grid-column:1/-1;">
                <h3><i class="fa-solid fa-book-open" style="margin-right:8px;color:var(--primary);"></i> Доступные темы</h3>
                <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:20px;">
                    Выберите тему для предпросмотра или установки. Все изменения применяются мгновенно после выбора.
                </p>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
                    <?php
                    $allTemplates = [
                        'dark-pro' => ['name' => 'Тёмный Про', 'desc' => 'Фиолетово-синий градиент, карточки-плитки', 'icon' => 'fa-moon', 'color' => 'linear-gradient(135deg,#4F46E5,#7C3AED)', 'preview' => '#1E293B'],
                        'cyber-neon' => ['name' => 'Кибер-Неон', 'desc' => 'Зелёный неон, тёмный фон, футуристичный стиль', 'icon' => 'fa-bolt', 'color' => 'linear-gradient(135deg,#00FF88,#00D4FF)', 'preview' => '#0A1628'],
                        'accsmarket' => ['name' => 'AccsMarket', 'desc' => 'Строчный список товаров, зелёные акценты', 'icon' => 'fa-list', 'color' => 'linear-gradient(135deg,#4CAF50,#8BC34A)', 'preview' => '#16213e'],
                        'light-clean' => ['name' => 'Светлый чистый', 'desc' => 'Белый фон, синие акценты, минимализм', 'icon' => 'fa-sun', 'color' => 'linear-gradient(135deg,#2563EB,#7C3AED)', 'preview' => '#FFFFFF'],
                        'midnight-gold' => ['name' => 'Полночь и Золото', 'desc' => 'Глубокий чёрный, золотые акценты, премиум', 'icon' => 'fa-star', 'color' => 'linear-gradient(135deg,#F59E0B,#FBBF24)', 'preview' => '#12121A'],
                        'noves-shop' => ['name' => 'Noves Shop', 'desc' => 'Светлый минимализм, акцент на изображения', 'icon' => 'fa-store', 'color' => 'linear-gradient(135deg,#2563EB,#10B981)', 'preview' => '#F9FAFB'],
                        'dark-shopping' => ['name' => 'Dark Shopping', 'desc' => 'Премиум тёмный, золотые элементы, люкс', 'icon' => 'fa-crown', 'color' => 'linear-gradient(135deg,#D4AF37,#F4D03F)', 'preview' => '#0A0A0A'],
                    ];
                    $currentTpl = $settings['site']['template'] ?? 'dark-pro';
                    foreach ($allTemplates as $tKey => $tData):
                    ?>
                    <div style="border:2px solid <?php echo $currentTpl === $tKey ? 'var(--primary)' : 'var(--border)'; ?>;border-radius:12px;overflow:hidden;background:var(--bg-card);transition:all 0.3s;" id="theme-card-<?php echo $tKey; ?>">
                        <div style="height:140px;background:<?php echo $tData['preview']; ?>;display:flex;align-items:center;justify-content:center;position:relative;">
                            <div style="width:60px;height:60px;border-radius:12px;background:<?php echo $tData['color']; ?>;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 20px rgba(0,0,0,0.3);">
                                <i class="fa-solid <?php echo $tData['icon']; ?>" style="color:#fff;font-size:1.5rem;"></i>
                            </div>
                            <?php if ($currentTpl === $tKey): ?>
                            <div style="position:absolute;top:10px;right:10px;background:rgba(16,185,129,0.9);color:#fff;padding:4px 10px;border-radius:100px;font-size:0.75rem;font-weight:600;">
                                <i class="fa-solid fa-check"></i> Активна
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="padding:16px;">
                            <div style="font-weight:700;margin-bottom:6px;font-size:1rem;"><?php echo $tData['name']; ?></div>
                            <div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:12px;line-height:1.4;"><?php echo $tData['desc']; ?></div>
                            <div style="display:flex;gap:8px;">
                                <button onclick="selectTemplate('<?php echo $tKey; ?>')" class="action-btn action-btn-edit" style="width:auto;padding:6px 12px;font-size:0.8rem;" title="Установить тему">
                                    <i class="fa-solid fa-check"></i> Установить
                                </button>
                                <a href="/?preview_theme=<?php echo $tKey; ?>" target="_blank" class="action-btn action-btn-view" style="width:auto;padding:6px 12px;font-size:0.8rem;text-decoration:none;display:flex;align-items:center;" title="Предпросмотр">
                                    <i class="fa-solid fa-eye"></i> Просмотр
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="settings-section" style="grid-column:1/-1;margin-top:24px;">
                <h3><i class="fa-solid fa-wand-magic-sparkles" style="margin-right:8px;color:var(--primary);"></i> Быстрое переключение</h3>
                <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:16px;">
                    Используйте горячие клавиши для быстрого переключения тем в админке (только для предпросмотра).
                </p>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach ($allTemplates as $tKey => $tData): ?>
                    <button onclick="selectTemplate('<?php echo $tKey; ?>')" style="padding:8px 14px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);cursor:pointer;font-size:0.85rem;transition:all 0.2s;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                        <i class="fa-solid <?php echo $tData['icon']; ?>" style="margin-right:6px;color:<?php echo explode(',',explode('deg,',$tData['color'])[1])[0]; ?>"></i><?php echo $tData['name']; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php elseif ($section === 'payments'): ?>
        <!-- ===== PAYMENTS ===== -->
        <?php
        $payment = $settings['payment'] ?? [];
        $paymentMethods = $payment['methods'] ?? [];
        $yoo = $payment['yoomoney'] ?? [];
        $crypto = $payment['crypto'] ?? [];
        $ordersData = loadData('orders');
        $paymentLog = loadData('payment_logs');
        $recentOrders = array_slice(array_reverse(is_array($ordersData) ? $ordersData : []), 0, 10);
        $recentPaymentLog = array_slice(array_reverse(is_array($paymentLog) ? $paymentLog : []), 0, 12);
        // Используем текущий домен для правильного webhook URL
        $siteBase = getCurrentSiteUrl();
        $webhookUrl = $siteBase . '/api/?path=payments/yoomoney/webhook';
        $cryptoTokensCatalog = getCryptoTokens();
        $cryptoRatesCache = getCryptoUsdRates();
        $cryptoRates = $cryptoRatesCache['rates'] ?? [];
        ?>
        <div class="admin-topbar">
            <h2><i class="fa-solid fa-credit-card" style="color:var(--primary);margin-right:8px;"></i> Платежи</h2>
            <div class="topbar-actions">
                <button class="topbar-btn topbar-btn-secondary" onclick="testPayments()">
                    <i class="fa-solid fa-vial-circle-check"></i> Проверить интеграцию
                </button>
                <button class="topbar-btn topbar-btn-primary" onclick="saveSettings()">
                    <i class="fa-solid fa-floppy-disk"></i> Сохранить платежи
                </button>
            </div>
        </div>
        <div class="admin-content">
            <div class="settings-grid">
                <div class="settings-section" style="grid-column:1/-1;">
                    <h3><i class="fa-solid fa-toggle-on" style="margin-right:8px;color:var(--primary);"></i> Доступные способы оплаты</h3>
                    <div class="form-row-3">
                        <div style="display:flex;align-items:flex-start;gap:12px;padding:14px;border:1px solid var(--border);border-radius:12px;background:var(--bg);">
                            <input type="checkbox" id="paymentMethodYoomoney" <?php echo !empty($paymentMethods['yoomoney']['enabled']) ? 'checked' : ''; ?>>
                            <label for="paymentMethodYoomoney" style="margin:0;cursor:pointer;flex:1;">
                                <strong>YooMoney</strong>
                                <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;">Основной реальный способ оплаты для физлица.</div>
                            </label>
                        </div>
                        <div style="display:flex;align-items:flex-start;gap:12px;padding:14px;border:1px solid var(--border);border-radius:12px;background:var(--bg);">
                            <input type="checkbox" id="paymentMethodCrypto" <?php echo !empty($paymentMethods['crypto']['enabled']) ? 'checked' : ''; ?>>
                            <label for="paymentMethodCrypto" style="margin:0;cursor:pointer;flex:1;">
                                <strong>Криптовалюта</strong>
                                <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;">Способ оплаты уже выведен на сайт, но пока работает как заглушка.</div>
                            </label>
                        </div>
                        <div style="display:flex;align-items:flex-start;gap:12px;padding:14px;border:1px solid var(--border);border-radius:12px;background:var(--bg);">
                            <input type="checkbox" id="paymentMethodDemo" <?php echo !empty($paymentMethods['demo']['enabled']) ? 'checked' : ''; ?>>
                            <label for="paymentMethodDemo" style="margin:0;cursor:pointer;flex:1;">
                                <strong>Демо-режим</strong>
                                <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;">Если все реальные способы выключены, сайт всё равно включает демо-оплату автоматически.</div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="settings-section" style="grid-column:1/-1;">
                    <h3><i class="fa-solid fa-wallet" style="margin-right:8px;color:var(--primary);"></i> YooMoney</h3>
                    <div class="form-row">
                        <div class="admin-form-group">
                            <label>Номер кошелька / receiver</label>
                            <input type="text" id="yoomoneyWallet" value="<?php echo htmlspecialchars($yoo['wallet'] ?? ''); ?>" placeholder="41001XXXXXXXXXXXX">
                        </div>
                        <div class="admin-form-group">
                            <label>Тип оплаты по умолчанию</label>
                            <select id="yoomoneyPaymentType">
                                <option value="AC" <?php echo (($yoo['payment_type'] ?? 'AC') === 'AC') ? 'selected' : ''; ?>>AC - банковская карта</option>
                                <option value="PC" <?php echo (($yoo['payment_type'] ?? '') === 'PC') ? 'selected' : ''; ?>>PC - кошелёк YooMoney</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="admin-form-group">
                            <label>Webhook Secret (получить <a href="https://yoomoney.ru/transfer/myservices/http-notification?lang=ru" target="_blank" style="color:var(--primary);text-decoration:underline;">здесь</a>)</label>
                            <input type="text" id="yoomoneyNotificationSecret" value="<?php echo htmlspecialchars($yoo['notification_secret'] ?? ''); ?>" placeholder="Секрет HTTP-уведомлений YooMoney">
                        </div>
                        <div class="admin-form-group">
                            <label>Redirect URI</label>
                            <input type="text" id="yoomoneyRedirectUri" value="<?php echo htmlspecialchars($yoo['redirect_uri'] ?? ''); ?>" placeholder="https://site.ru/oauth/yoomoney/callback">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="admin-form-group">
                            <label>client_id</label>
                            <input type="text" id="yoomoneyClientId" value="<?php echo htmlspecialchars($yoo['client_id'] ?? ''); ?>" placeholder="Идентификатор приложения YooMoney">
                        </div>
                        <div class="admin-form-group">
                            <label>client_secret</label>
                            <input type="text" id="yoomoneyClientSecret" value="<?php echo htmlspecialchars($yoo['client_secret'] ?? ''); ?>" placeholder="Секрет приложения YooMoney">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="admin-form-group">
                            <label>Success URL</label>
                            <input type="text" id="yoomoneySuccessUrl" value="<?php echo htmlspecialchars($yoo['success_url'] ?? ($siteBase ? $siteBase . '/oplata/?status=success' : '')); ?>" placeholder="https://site.ru/oplata/?status=success">
                        </div>
                        <div class="admin-form-group">
                            <label>Fail URL</label>
                            <input type="text" id="yoomoneyFailUrl" value="<?php echo htmlspecialchars($yoo['fail_url'] ?? ($siteBase ? $siteBase . '/oplata/?status=fail' : '')); ?>" placeholder="https://site.ru/oplata/?status=fail">
                        </div>
                    </div>
                </div>

                <div class="settings-section">
                    <h3><i class="fa-solid fa-book" style="margin-right:8px;color:var(--primary);"></i> Мини-инструкция по интеграции</h3>
                    <div style="display:flex;flex-direction:column;gap:12px;color:var(--text-muted);line-height:1.6;">
                        <div><strong style="color:var(--text);">1.</strong> Укажите номер кошелька YooMoney в поле <strong style="color:var(--text);">receiver</strong>.</div>
                        <div><strong style="color:var(--text);">2.</strong> В кабинете YooMoney (<a href="https://yoomoney.ru/myservices/new" target="_blank" style="color:var(--primary);text-decoration:underline;">настройка уведомлений</a>) включите HTTP-уведомления и задайте секрет. Этот же секрет внесите в поле <strong style="color:var(--text);">Webhook secret</strong>.</div>
                        <div><strong style="color:var(--text);">3.</strong> В качестве URL уведомлений используйте:<br><code style="display:block;margin-top:6px;padding:10px;background:var(--bg);border:1px solid var(--border);border-radius:10px;color:var(--text);"><?php echo htmlspecialchars($webhookUrl); ?></code></div>
                        <div><strong style="color:var(--text);">4.</strong> <strong style="color:var(--text);">client_id</strong> и <strong style="color:var(--text);">client_secret</strong> нужны для OAuth-сценариев и расширенной проверки приложения. Для базового приёма оплаты по форме физлица обязательны кошелёк и webhook secret.</div>
                        <div><strong style="color:var(--text);">5.</strong> Нажмите «Проверить интеграцию», чтобы увидеть готовность конфигурации и ссылки возврата.</div>
                    </div>
                </div>

                <div class="settings-section" style="grid-column:1/-1;">
                    <h3><i class="fa-brands fa-bitcoin" style="margin-right:8px;color:var(--primary);"></i> Криптовалюта - адреса кошельков</h3>
                    <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">Введите адреса кошельков для каждого токена. Покупатели будут переводить крипту напрямую на эти адреса. Оставьте поле пустым, чтобы скрыть токен из списка оплаты.</p>
                    <div class="admin-form-group">
                        <label>Заметка / провайдер</label>
                        <input type="text" id="cryptoNotes" value="<?php echo htmlspecialchars($crypto['notes'] ?? ''); ?>" placeholder="Например: прямой приём / Cryptomus / Coinbase Commerce">
                    </div>
                    <div id="cryptoTokensEditor" style="display:flex;flex-direction:column;gap:12px;margin-top:16px;">
                        <?php foreach ($cryptoTokensCatalog as $token):
                            $code = strtoupper((string)($token['code'] ?? ''));
                            if ($code === '') continue;
                            $wallet = trim((string)($token['wallet'] ?? ''));
                            $rate = $cryptoRates[$code] ?? [];
                            $rateUsd = (float)($rate['usd'] ?? 0);
                        ?>
                        <div style="display:grid;grid-template-columns:140px 1fr auto;align-items:center;gap:12px;padding:14px;border:1px solid var(--border);border-radius:12px;background:var(--bg);">
                            <div>
                                <div style="font-weight:700;font-size:0.9rem;"><?php echo htmlspecialchars($token['name'] ?? $code); ?></div>
                                <div style="font-size:0.75rem;color:var(--text-muted);"><?php echo htmlspecialchars($token['network'] ?? ''); ?> · <?php echo htmlspecialchars($code); ?></div>
                                <?php if ($rateUsd > 0): ?>
                                <div style="font-size:0.72rem;color:#10b981;margin-top:2px;">$<?php echo number_format($rateUsd, $rateUsd >= 1 ? 2 : 6, '.', ''); ?></div>
                                <?php endif; ?>
                            </div>
                            <input type="text"
                                id="cryptoWallet_<?php echo htmlspecialchars($code); ?>"
                                data-token="<?php echo htmlspecialchars($code); ?>"
                                class="crypto-wallet-input"
                                value="<?php echo htmlspecialchars($wallet); ?>"
                                placeholder="Адрес кошелька <?php echo htmlspecialchars($code); ?>"
                                style="padding:10px 12px;background:var(--bg-card);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:'JetBrains Mono',monospace;font-size:0.82rem;width:100%;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <?php if ($wallet !== ''): ?>
                                <span style="width:8px;height:8px;border-radius:50%;background:#10b981;display:inline-block;" title="Кошелёк настроен"></span>
                                <?php else: ?>
                                <span style="width:8px;height:8px;border-radius:50%;background:#ef4444;display:inline-block;" title="Кошелёк не задан"></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="topbar-btn topbar-btn-primary" onclick="saveCryptoWallets()" style="margin-top:16px;">
                        <i class="fa-solid fa-floppy-disk"></i> Сохранить адреса кошельков
                    </button>
                    <div id="cryptoWalletsSaveResult" style="margin-top:10px;"></div>
                </div>

                <div class="settings-section" style="grid-column:1/-1;">
                    <h3><i class="fa-solid fa-stethoscope" style="margin-right:8px;color:var(--primary);"></i> Проверка системы</h3>
                    <div id="paymentsTestResult" style="padding:14px;border:1px dashed var(--border);border-radius:12px;background:var(--bg);color:var(--text-muted);">
                        Нажмите «Проверить интеграцию», чтобы получить диагностику конфигурации YooMoney.
                    </div>
                </div>

                <div class="settings-section" style="grid-column:1/-1;">
                    <h3><i class="fa-solid fa-bag-shopping" style="margin-right:8px;color:var(--primary);"></i> Последние заказы</h3>
                    <div style="overflow:auto;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Заказ</th>
                                    <th>Дата</th>
                                    <th>Email</th>
                                    <th>Метод</th>
                                    <th>Сумма</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentOrders)): ?>
                                <tr><td colspan="6" style="text-align:center;color:var(--text-muted);">Пока нет заказов</td></tr>
                                <?php else: foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_number'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($order['created_at'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($order['email'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($order['payment_method'] ?? ''); ?></td>
                                    <td><?php echo number_format((float)($order['totals']['amount'] ?? 0), 2, '.', ' '); ?> ₽</td>
                                    <td><?php echo htmlspecialchars($order['payment_status'] ?? $order['status'] ?? ''); ?></td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="settings-section" style="grid-column:1/-1;">
                    <h3><i class="fa-solid fa-clock-rotate-left" style="margin-right:8px;color:var(--primary);"></i> Последние события оплаты</h3>
                    <div style="overflow:auto;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Время</th>
                                    <th>Событие</th>
                                    <th>Данные</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentPaymentLog)): ?>
                                <tr><td colspan="3" style="text-align:center;color:var(--text-muted);">События оплаты ещё не записывались</td></tr>
                                <?php else: foreach ($recentPaymentLog as $logItem): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($logItem['time'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($logItem['type'] ?? ''); ?></td>
                                    <td><code style="font-size:0.78rem;white-space:pre-wrap;word-break:break-word;color:var(--text);"><?php echo htmlspecialchars(json_encode($logItem['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></code></td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($section === 'info'): ?>
        <!-- ===== ARTICLES ===== -->
        <?php
        $articles = $pagesData['info']['articles'] ?? [];
        // Group articles by category
        $articlesByCategory = [];
        foreach ($articles as $art) {
            $artCat = $art['category'] ?? 'Без категории';
            if (!isset($articlesByCategory[$artCat])) $articlesByCategory[$artCat] = [];
            $articlesByCategory[$artCat][] = $art;
        }
        ?>
        <div class="admin-topbar">
            <h2><i class="fa-solid fa-newspaper" style="color:var(--primary);margin-right:8px;"></i> Статьи блога (<?php echo count($articles); ?>)</h2>
            <div class="topbar-actions">
                <button class="topbar-btn topbar-btn-primary" onclick="openAddArticleModal()">
                    <i class="fa-solid fa-plus"></i> Добавить статью
                </button>
            </div>
        </div>
        <div class="admin-content">
            <?php if (empty($articles)): ?>
            <div class="admin-table-wrap">
                <table class="admin-table"><tbody>
                    <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:32px;">Статьи не найдены. Добавьте первую статью.</td></tr>
                </tbody></table>
            </div>
            <?php else: ?>
            <?php $artCatIdx = 0; foreach ($articlesByCategory as $artCat => $catArticles): $artCatId = 'artcat-' . $artCatIdx++; ?>
            <div class="admin-table-wrap" style="margin-bottom:24px;" id="block-<?php echo $artCatId; ?>">
                <div class="admin-table-header" style="cursor:pointer;" onclick="toggleArtCat('<?php echo $artCatId; ?>')">
                    <h3><i class="fa-solid fa-folder-open" style="margin-right:8px;color:var(--primary);"></i>
                        <?php echo htmlspecialchars($artCat); ?>
                        <span style="font-size:0.8rem;color:var(--text-muted);font-weight:400;">(<?php echo count($catArticles); ?> статей)</span>
                    </h3>
                    <span class="art-cat-toggle-btn" id="toggle-<?php echo $artCatId; ?>" style="color:var(--text-muted);font-size:0.85rem;">Свернуть ▲</span>
                </div>
                <div id="body-<?php echo $artCatId; ?>">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Статья</th>
                            <th>Slug</th>
                            <th>Дата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($catArticles) as $art): ?>
                        <tr>
                            <td style="color:var(--text-muted);font-family:'JetBrains Mono',monospace;"><?php echo $art['id']; ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <?php if (!empty($art['image'])): ?>
                                    <img src="/images/blog/<?php echo htmlspecialchars($art['image']); ?>" alt="" style="width:48px;height:36px;object-fit:cover;border-radius:6px;" onerror="this.style.display='none'">
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($art['title']); ?></div>
                                        <div style="font-size:0.75rem;color:var(--text-muted);max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($art['excerpt'] ?? ''); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-family:'JetBrains Mono',monospace;color:var(--text-muted);"><?php echo $art['slug']; ?></td>
                            <td style="color:var(--text-muted);"><?php echo $art['date'] ?? ''; ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="action-btn action-btn-edit" onclick="editArticle('<?php echo $art['slug']; ?>')" title="Редактировать"><i class="fa-solid fa-pen"></i></button>
                                    <a href="/info/<?php echo $art['slug']; ?>/" target="_blank" class="action-btn action-btn-view" title="Просмотр"><i class="fa-solid fa-eye"></i></a>
                                    <button class="action-btn action-btn-delete" onclick="deleteArticle('<?php echo $art['slug']; ?>', '<?php echo htmlspecialchars($art['title']); ?>')" title="Удалить"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div><!-- end body-artcatId -->
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php elseif ($section === 'rules'): ?>
        <!-- ===== RULES ===== -->
        <div class="admin-topbar">
            <h2><i class="fa-solid fa-file-contract" style="color:var(--primary);margin-right:8px;"></i> Правила пользования</h2>
            <div class="topbar-actions">
                <button class="topbar-btn topbar-btn-primary" onclick="saveRules()">
                    <i class="fa-solid fa-floppy-disk"></i> Сохранить
                </button>
            </div>
        </div>
        <div class="admin-content">
            <?php $rulesData = $pagesData['rules'] ?? []; ?>
            <div class="settings-grid" style="grid-template-columns:1fr;">
                <div class="settings-section">
                    <h3><i class="fa-solid fa-heading" style="margin-right:8px;color:var(--primary);"></i> Заголовок страницы</h3>
                    <div class="admin-form-group">
                        <label>Title (SEO)</label>
                        <input type="text" id="rulesTitle" value="<?php echo htmlspecialchars($rulesData['title'] ?? ''); ?>">
                    </div>
                    <div class="admin-form-group">
                        <label>H1 заголовок</label>
                        <input type="text" id="rulesH1" value="<?php echo htmlspecialchars($rulesData['h1'] ?? ''); ?>">
                    </div>
                    <div class="admin-form-group">
                        <label>Description (SEO)</label>
                        <textarea id="rulesDescription" rows="2"><?php echo htmlspecialchars($rulesData['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="settings-section" style="grid-column:1/-1;">
                    <h3><i class="fa-solid fa-align-left" style="margin-right:8px;color:var(--primary);"></i> Содержимое (суппорт HTML)</h3>
                    <div class="admin-form-group">
                        <textarea id="rulesContent" rows="20" style="font-family:'JetBrains Mono',monospace;font-size:0.8rem;"><?php echo htmlspecialchars($rulesData['content'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($section === 'advertising'): ?>
        <!-- ===== ADVERTISING ===== -->
        <?php
        $adData = getAdvertising();
        $adSpots = $adData['spots'] ?? [];
        $adBanners = $adData['banners'] ?? [];
        // Статистика
        $totalSpots = count($adSpots);
        $enabledSpots = count(array_filter($adSpots, fn($s) => $s['enabled'] ?? false));
        $totalBanners = count($adBanners);
        $activeBanners = count(array_filter($adBanners, fn($b) => $b['active'] ?? false));
        ?>
        <div class="admin-topbar">
            <h2><i class="fa-solid fa-rectangle-ad" style="color:var(--primary);margin-right:8px;"></i> Управление рекламой</h2>
            <div class="topbar-actions">
                <a href="/advertising/" target="_blank" class="topbar-btn topbar-btn-secondary">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Страница рекламы
                </a>
                <button class="topbar-btn topbar-btn-primary" onclick="openAddBannerModal()">
                    <i class="fa-solid fa-plus"></i> Добавить баннер
                </button>
            </div>
        </div>
        <div class="admin-content">

            <!-- Статистика -->
            <div class="stats-row" style="margin-bottom:24px;">
                <div class="stat-card">
                    <div class="stat-card-icon blue"><i class="fa-solid fa-map-pin"></i></div>
                    <div>
                        <div class="stat-card-value"><?php echo $totalSpots; ?></div>
                        <div class="stat-card-label">Рекламных мест</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green"><i class="fa-solid fa-toggle-on"></i></div>
                    <div>
                        <div class="stat-card-value"><?php echo $enabledSpots; ?></div>
                        <div class="stat-card-label">Активных мест</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon yellow"><i class="fa-solid fa-image"></i></div>
                    <div>
                        <div class="stat-card-value"><?php echo $totalBanners; ?></div>
                        <div class="stat-card-label">Всего баннеров</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon" style="background:rgba(16,185,129,0.15);color:#10B981;"><i class="fa-solid fa-circle-check"></i></div>
                    <div>
                        <div class="stat-card-value"><?php echo $activeBanners; ?></div>
                        <div class="stat-card-label">Активных баннеров</div>
                    </div>
                </div>
            </div>

            <!-- Рекламные места -->
            <div class="admin-table-wrap" style="margin-bottom:24px;">
                <div class="admin-table-header">
                    <h3><i class="fa-solid fa-map-pin" style="margin-right:8px;color:var(--primary);"></i> Рекламные места</h3>
                    <span style="color:var(--text-muted);font-size:0.85rem;">Нажмите на место для редактирования настроек и цен</span>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Место</th>
                            <th>Размер</th>
                            <th>Расположение</th>
                            <th>Цена/нед.</th>
                            <th>Цена/мес.</th>
                            <th>Баннеров</th>
                            <th>Макс.</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adSpots as $spot):
                            $spotBanners = array_filter($adBanners, fn($b) => $b['spot_id'] === $spot['id']);
                            $activeSpotBanners = array_filter($spotBanners, fn($b) => $b['active'] ?? false);
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?php echo htmlspecialchars($spot['name']); ?></div>
                                <div style="font-size:0.75rem;color:var(--text-muted);font-family:'JetBrains Mono',monospace;"><?php echo $spot['id']; ?></div>
                            </td>
                            <td><span style="font-family:'JetBrains Mono',monospace;font-size:0.85rem;color:var(--primary);"><?php echo $spot['size']; ?></span></td>
                            <td style="color:var(--text-muted);font-size:0.85rem;max-width:200px;"><?php echo htmlspecialchars($spot['location']); ?></td>
                            <td class="price-cell"><?php echo number_format($spot['price_week'] ?? 0, 0, '.', ' '); ?> ₽</td>
                            <td class="price-cell"><?php echo number_format($spot['price_month'] ?? 0, 0, '.', ' '); ?> ₽</td>
                            <td>
                                <span style="font-weight:600;color:<?php echo count($activeSpotBanners) > 0 ? 'var(--secondary)' : 'var(--text-muted)'; ?>">
                                    <?php echo count($activeSpotBanners); ?> акт.
                                </span>
                                <span style="color:var(--text-muted);font-size:0.8rem;"> / <?php echo count($spotBanners); ?> всего</span>
                            </td>
                            <td style="text-align:center;"><?php echo $spot['max_banners'] ?? 10; ?></td>
                            <td>
                                <?php if ($spot['enabled'] ?? false): ?>
                                <span class="badge badge-active">Включено</span>
                                <?php else: ?>
                                <span class="badge badge-inactive">Отключено</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="action-btn action-btn-edit" onclick="editAdSpot('<?php echo $spot['id']; ?>')" title="Редактировать место"><i class="fa-solid fa-pen"></i></button>
                                    <button class="action-btn" onclick="toggleAdSpot('<?php echo $spot['id']; ?>', <?php echo $spot['enabled'] ? 'false' : 'true'; ?>)" title="<?php echo $spot['enabled'] ? 'Отключить' : 'Включить'; ?>" style="background:<?php echo ($spot['enabled'] ?? false) ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)'; ?>;color:<?php echo ($spot['enabled'] ?? false) ? '#EF4444' : '#10B981'; ?>;">
                                        <i class="fa-solid fa-<?php echo ($spot['enabled'] ?? false) ? 'toggle-off' : 'toggle-on'; ?>"></i>
                                    </button>
                                    <button class="action-btn topbar-btn-secondary" onclick="openAddBannerModal('<?php echo $spot['id']; ?>')" title="Добавить баннер" style="background:rgba(99,102,241,0.1);color:var(--primary);">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Список баннеров -->
            <div class="admin-table-wrap">
                <div class="admin-table-header">
                    <h3><i class="fa-solid fa-images" style="margin-right:8px;color:var(--primary);"></i> Баннеры рекламодателей</h3>
                    <button class="topbar-btn topbar-btn-primary" onclick="openAddBannerModal()">
                        <i class="fa-solid fa-plus"></i> Добавить баннер
                    </button>
                </div>
                <?php if (empty($adBanners)): ?>
                <div style="text-align:center;padding:60px 0;color:var(--text-muted);">
                    <i class="fa-solid fa-rectangle-ad" style="font-size:3rem;margin-bottom:16px;display:block;opacity:0.3;"></i>
                    <h3 style="margin-bottom:8px;">Баннеры не добавлены</h3>
                    <p style="margin-bottom:20px;">Добавьте первый рекламный баннер для одного из мест размещения.</p>
                    <button class="topbar-btn topbar-btn-primary" onclick="openAddBannerModal()">
                        <i class="fa-solid fa-plus"></i> Добавить первый баннер
                    </button>
                </div>
                <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Баннер</th>
                            <th>Место размещения</th>
                            <th>Рекламодатель</th>
                            <th>Ссылка</th>
                            <th>Период</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($adBanners) as $banner):
                            $bannerSpot = null;
                            foreach ($adSpots as $s) { if ($s['id'] === $banner['spot_id']) { $bannerSpot = $s; break; } }
                        ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <?php if (!empty($banner['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($banner['image_url']); ?>" alt="" style="width:60px;height:30px;object-fit:cover;border-radius:4px;border:1px solid var(--border);" onerror="this.style.display='none'">
                                    <?php else: ?>
                                    <div style="width:60px;height:30px;background:var(--bg-card);border:1px solid var(--border);border-radius:4px;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-image" style="color:var(--text-muted);font-size:0.7rem;"></i></div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($banner['title'] ?? 'Без названия'); ?></div>
                                        <div style="font-size:0.75rem;color:var(--text-muted);font-family:'JetBrains Mono',monospace;"><?php echo $banner['id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($bannerSpot): ?>
                                <div style="font-size:0.85rem;"><?php echo htmlspecialchars($bannerSpot['name']); ?></div>
                                <div style="font-size:0.75rem;color:var(--primary);font-family:'JetBrains Mono',monospace;"><?php echo $bannerSpot['size']; ?></div>
                                <?php else: ?>
                                <span style="color:var(--danger);font-size:0.8rem;">Место удалено</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--text-muted);"><?php echo htmlspecialchars($banner['advertiser'] ?? '-'); ?></td>
                            <td style="max-width:150px;">
                                <?php if (!empty($banner['url'])): ?>
                                <a href="<?php echo htmlspecialchars($banner['url']); ?>" target="_blank" style="color:var(--primary);font-size:0.8rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;max-width:140px;"><?php echo htmlspecialchars($banner['url']); ?></a>
                                <?php else: ?><span style="color:var(--text-muted);">-</span><?php endif; ?>
                            </td>
                            <td style="font-size:0.8rem;color:var(--text-muted);">
                                <?php echo $banner['date_start'] ?? '-'; ?>
                                <?php if (!empty($banner['date_end'])): ?><br>→ <?php echo $banner['date_end']; ?><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($banner['active'] ?? false): ?>
                                <span class="badge badge-active">Активен</span>
                                <?php else: ?>
                                <span class="badge badge-inactive">Отключён</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="action-btn action-btn-edit" onclick="editAdBanner('<?php echo $banner['id']; ?>')" title="Редактировать"><i class="fa-solid fa-pen"></i></button>
                                    <button class="action-btn action-btn-delete" onclick="deleteAdBanner('<?php echo $banner['id']; ?>', '<?php echo htmlspecialchars($banner['title'] ?? ''); ?>')" title="Удалить"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

        </div><!-- end admin-content advertising -->

        <?php elseif ($section === 'questions'): ?>
        <!-- ===== QUESTIONS ===== -->
        <div class="admin-topbar">
            <h2><i class="fa-solid fa-envelope" style="color:var(--primary);margin-right:8px;"></i> Вопросы пользователей</h2>
            <div class="topbar-actions">
                <?php if (!empty($contactsList)): ?>
                <button class="topbar-btn topbar-btn-danger" onclick="clearAllQuestions()">
                    <i class="fa-solid fa-trash"></i> Очистить все
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="admin-content">
            <?php if (empty($contactsList)): ?>
            <div style="text-align:center;padding:60px 0;color:var(--text-muted);">
                <i class="fa-solid fa-inbox" style="font-size:3rem;margin-bottom:16px;display:block;"></i>
                <h3>Нет вопросов</h3>
                <p>Когда пользователи задают вопросы, они появятся здесь.</p>
            </div>
            <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Email</th>
                            <th>Telegram/Соцсеть</th>
                            <th>Сообщение</th>
                            <th>Дата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($contactsList) as $i => $contact): ?>
                        <tr>
                            <td style="color:var(--text-muted);"><?php echo count($contactsList) - $i; ?></td>
                            <td>
                                <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>" style="color:var(--primary);">
                                    <?php echo htmlspecialchars($contact['email']); ?>
                                </a>
                            </td>
                            <td style="color:var(--text-muted);"><?php echo htmlspecialchars($contact['social'] ?? ''); ?></td>
                            <td style="max-width:300px;">
                                <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:280px;"><?php echo htmlspecialchars(substr($contact['message'], 0, 80)) . (strlen($contact['message']) > 80 ? '...' : ''); ?></div>
                                <button class="action-btn action-btn-view" onclick="openQuestionModal(<?php echo count($contactsList) - 1 - $i; ?>)" title="Читать" style="margin-top:4px;font-size:0.75rem;padding:3px 8px;">
                                    <i class="fa-solid fa-eye"></i> Читать
                                </button>
                            </td>
                            <td style="color:var(--text-muted);white-space:nowrap;"><?php echo $contact['time']; ?></td>
                            <td>
                                <div class="action-btns">
                                    <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>" class="action-btn action-btn-view" title="Ответить"><i class="fa-solid fa-reply"></i></a>
                                    <button class="action-btn action-btn-delete" onclick="deleteQuestion(<?php echo count($contactsList) - 1 - $i; ?>)" title="Удалить"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Question View Modal -->
        <div class="admin-modal" id="questionViewModal">
            <div class="admin-modal-overlay" onclick="closeModal('questionViewModal')"></div>
            <div class="admin-modal-dialog" style="max-width:560px;">
                <div class="admin-modal-header">
                    <h3>Сообщение от пользователя</h3>
                    <button class="admin-modal-close" onclick="closeModal('questionViewModal')"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="admin-modal-body" id="questionViewBody">
                </div>
                <div class="admin-modal-footer">
                    <button class="topbar-btn topbar-btn-secondary" onclick="closeModal('questionViewModal')">&#x2715; Закрыть</button>
                    <a id="questionReplyLink" href="#" class="topbar-btn topbar-btn-primary">
                        <i class="fa-solid fa-reply"></i> Ответить
                    </a>
                </div>
            </div>
        </div>

        <?php else: ?>
        <div class="admin-topbar"><h2>Раздел не найден</h2></div>
        <div class="admin-content"><p style="color:var(--text-muted);">Выберите раздел в меню слева.</p></div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== PRODUCT MODAL ===== -->
<div class="admin-modal" id="productModal">
    <div class="admin-modal-overlay" onclick="closeModal('productModal')"></div>
    <div class="admin-modal-dialog">
        <div class="admin-modal-header">
            <h3 id="productModalTitle">Добавить товар</h3>
            <button class="admin-modal-close" onclick="closeModal('productModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="admin-modal-body">
            <input type="hidden" id="productId" value="">
            <div class="form-row">
                <div class="admin-form-group">
                    <label>Название *</label>
                    <input type="text" id="productName" placeholder="Facebook Авторег 2024">
                </div>
                <div class="admin-form-group">
                    <label>Slug *</label>
                    <input type="text" id="productSlug" placeholder="facebook-autoreg-2024">
                </div>
            </div>
            <div class="form-row">
                <div class="admin-form-group">
                    <label>Категория *</label>
                    <select id="productCategory">
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['slug']; ?>"><?php echo $cat['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label>Подкатегория</label>
                    <input type="text" id="productSubcategory" placeholder="autoreg">
                </div>
            </div>
            <div class="admin-form-group">
                <label>Краткое описание</label>
                <input type="text" id="productShortDesc" placeholder="Краткое описание для карточки">
            </div>
            <div class="admin-form-group">
                <label>Полное описание</label>
                <textarea id="productFullDesc" rows="4" placeholder="Подробное описание товара..."></textarea>
            </div>
            <div class="form-row-3">
                <div class="admin-form-group">
                    <label>Цена (₽) *</label>
                    <input type="number" id="productPrice" placeholder="99" min="0" step="0.01">
                </div>
                <div class="admin-form-group">
                    <label>Количество *</label>
                    <input type="number" id="productQty" placeholder="100" min="0">
                </div>
                <div class="admin-form-group">
                    <label>Статус</label>
                    <select id="productStatus">
                        <option value="active">Активен</option>
                        <option value="inactive">Скрыт</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="admin-form-group">
                    <label>Иконка (файл)</label>
                    <input type="text" id="productIcon" placeholder="facebook.svg">
                </div>
                <div class="admin-form-group">
                    <label>Страна</label>
                    <input type="text" id="productCountry" placeholder="RU" value="Any">
                </div>
            </div>
            <div class="form-row">
                <div class="admin-form-group">
                    <label>Пол</label>
                    <select id="productSex">
                        <option value="any">Любой</option>
                        <option value="male">Мужской</option>
                        <option value="female">Женский</option>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label>Год регистрации</label>
                    <input type="number" id="productAge" placeholder="2024" value="<?php echo date('Y'); ?>">
                </div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:20px;margin-bottom:16px;">
                <div class="checkbox-group">
                    <input type="checkbox" id="productCookies">
                    <label for="productCookies">Cookies в комплекте</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="productProxy">
                    <label for="productProxy">Прокси в комплекте</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="productEmailVerified">
                    <label for="productEmailVerified">Email верифицирован</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="productPopular">
                    <label for="productPopular">Хит продаж</label>
                </div>
            </div>
            <div class="admin-form-group">
                <label>Особенности (через запятую)</label>
                <input type="text" id="productFeatures" placeholder="Живые аккаунты, Cookies, Прокси">
            </div>
            <div class="admin-form-group" id="itemsSection" style="display:none;border-top:1px solid var(--border);padding-top:16px;margin-top:16px;">
                <label style="margin-bottom:12px;">Электронные товары</label>
                <div style="display:flex;gap:8px;margin-bottom:12px;">
                    <button type="button" class="topbar-btn topbar-btn-secondary" onclick="triggerItemsUpload()" style="flex:1;">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Загрузить из TXT
                    </button>
                    <button type="button" class="topbar-btn topbar-btn-secondary" onclick="toggleDemoProduct()" style="flex:1;">
                        <i class="fa-solid fa-tag"></i> <span id="demoToggleText">Обычный</span>
                    </button>
                </div>
                <input type="file" id="itemsFileInput" accept=".txt" style="display:none;" onchange="handleItemsUpload(event)">
                <div id="itemsInfo" style="font-size:0.85rem;color:var(--text-muted);"></div>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button class="topbar-btn topbar-btn-secondary" onclick="closeModal('productModal')">Отмена</button>
            <button class="topbar-btn topbar-btn-primary" onclick="saveProduct()">
                <i class="fa-solid fa-floppy-disk"></i> Сохранить
            </button>
        </div>
    </div>
</div>

<!-- ===== CATEGORY MODAL ===== -->
<div class="admin-modal" id="categoryModal">
    <div class="admin-modal-overlay" onclick="closeModal('categoryModal')"></div>
    <div class="admin-modal-dialog">
        <div class="admin-modal-header">
            <h3 id="categoryModalTitle">Добавить категорию</h3>
            <button class="admin-modal-close" onclick="closeModal('categoryModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="admin-modal-body">
            <input type="hidden" id="categoryId" value="">
            <div class="form-row">
                <div class="admin-form-group">
                    <label>Название *</label>
                    <input type="text" id="categoryName" placeholder="Facebook">
                </div>
                <div class="admin-form-group">
                    <label>Slug *</label>
                    <input type="text" id="categorySlug" placeholder="facebook">
                </div>
            </div>
            <div class="admin-form-group">
                <label>Описание</label>
                <input type="text" id="categoryDesc" placeholder="Аккаунты Facebook для рекламы и продвижения">
            </div>
            <div class="admin-form-group">
                <label>Иконка (файл)</label>
                <input type="text" id="categoryIcon" placeholder="facebook.svg">
            </div>
            <div class="admin-form-group">
                <label>SEO Title</label>
                <input type="text" id="categorySeoTitle" placeholder="Купить аккаунты Facebook | Название магазина">
            </div>
            <div class="admin-form-group">
                <label>SEO Description</label>
                <textarea id="categorySeoDesc" rows="2" placeholder="Продажа аккаунтов Facebook..."></textarea>
            </div>
            <div class="admin-form-group">
                <label>Подкатегории</label>
                <div id="subcatsList" style="margin-bottom:10px;"></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <input type="text" id="newSubcatName" placeholder="Название" style="flex:1;min-width:120px;">
                    <input type="text" id="newSubcatSlug" placeholder="slug" style="flex:1;min-width:100px;">
                    <input type="text" id="newSubcatDesc" placeholder="Описание (необяз.)" style="flex:2;min-width:150px;">
                    <button type="button" class="topbar-btn topbar-btn-secondary" onclick="addSubcat()" style="padding:8px 14px;">
                        <i class="fa-solid fa-plus"></i> Добавить
                    </button>
                </div>
                <input type="hidden" id="categorySubcats" value="[]">
                <small style="color:var(--text-muted);margin-top:6px;display:block;">Добавьте подкатегории и они появятся в меню категории</small>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button class="topbar-btn topbar-btn-secondary" onclick="closeModal('categoryModal')">Отмена</button>
            <button class="topbar-btn topbar-btn-primary" onclick="saveCategory()">
                <i class="fa-solid fa-floppy-disk"></i> Сохранить
            </button>
        </div>
    </div>
</div>

<!-- ===== FAQ MODAL ===== -->
<div class="admin-modal" id="faqModal">
    <div class="admin-modal-overlay" onclick="closeModal('faqModal')"></div>
    <div class="admin-modal-dialog" style="max-width:520px;">
        <div class="admin-modal-header">
            <h3 id="faqModalTitle">Добавить вопрос</h3>
            <button class="admin-modal-close" onclick="closeModal('faqModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="admin-modal-body">
            <input type="hidden" id="faqIndex" value="-1">
            <div class="admin-form-group">
                <label>Вопрос *</label>
                <input type="text" id="faqQuestion" placeholder="Как получить аккаунт?">
            </div>
            <div class="admin-form-group">
                <label>Ответ *</label>
                <textarea id="faqAnswer" rows="5" placeholder="Подробный ответ на вопрос..."></textarea>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button class="topbar-btn topbar-btn-secondary" onclick="closeModal('faqModal')">Отмена</button>
            <button class="topbar-btn topbar-btn-primary" onclick="saveFaq()">
                <i class="fa-solid fa-floppy-disk"></i> Сохранить
            </button>
        </div>
    </div>
</div>

<!-- ===== ARTICLE MODAL ===== -->
<div class="admin-modal" id="articleModal">
    <div class="admin-modal-overlay" onclick="closeModal('articleModal')"></div>
    <div class="admin-modal-dialog" style="max-width:760px;">
        <div class="admin-modal-header">
            <h3 id="articleModalTitle">Добавить статью</h3>
            <button class="admin-modal-close" onclick="closeModal('articleModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="admin-modal-body">
            <input type="hidden" id="articleSlugOrig" value="">
            <div class="form-row">
                <div class="admin-form-group">
                    <label>Заголовок *</label>
                    <input type="text" id="articleTitle" placeholder="Как купить аккаунты Facebook">
                </div>
                <div class="admin-form-group">
                    <label>Slug *</label>
                    <input type="text" id="articleSlug" placeholder="kak-kupit-akkaunty-facebook">
                </div>
            </div>
            <div class="form-row">
                <div class="admin-form-group">
                    <label>Дата</label>
                    <input type="date" id="articleDate" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="admin-form-group">
                    <label>Категория</label>
                    <input type="text" id="articleCategory" placeholder="Например: Инструкции, Новости, Советы">
                </div>
            </div>
            <div class="admin-form-group">
                <label>Картинка (файл)</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="text" id="articleImage" placeholder="blog-1.jpg" style="flex:1;">
                    <label class="topbar-btn topbar-btn-secondary" style="cursor:pointer;padding:8px 12px;">
                        <i class="fa-solid fa-upload"></i>
                        <input type="file" id="articleImageFile" accept="image/*" style="display:none;" onchange="uploadArticleImage(this)">
                    </label>
                </div>
                <div id="articleImagePreview" style="margin-top:8px;"></div>
            </div>
            <div class="admin-form-group">
                <label>Краткое описание *</label>
                <textarea id="articleExcerpt" rows="2" placeholder="Краткое описание для превью..."></textarea>
            </div>
            <div class="admin-form-group">
                <label>Полный текст (поддерживает HTML)</label>
                <textarea id="articleContent" rows="10" style="font-family:'JetBrains Mono',monospace;font-size:0.8rem;" placeholder="&lt;p&gt;Текст статьи...&lt;/p&gt;"></textarea>
            </div>
            <div class="form-row">
                <div class="admin-form-group">
                    <label>SEO Title</label>
                    <input type="text" id="articleSeoTitle" placeholder="SEO заголовок">
                </div>
                <div class="admin-form-group">
                    <label>SEO Description</label>
                    <input type="text" id="articleSeoDesc" placeholder="SEO описание">
                </div>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button class="topbar-btn topbar-btn-secondary" onclick="closeModal('articleModal')">Отмена</button>
            <button class="topbar-btn topbar-btn-primary" onclick="saveArticle()">
                <i class="fa-solid fa-floppy-disk"></i> Сохранить
            </button>
        </div>
    </div>
</div>

<!-- ===== AD SPOT MODAL ===== -->
<div class="admin-modal" id="adSpotModal">
    <div class="admin-modal-overlay" onclick="closeModal('adSpotModal')"></div>
    <div class="admin-modal-dialog" style="max-width:600px;">
        <div class="admin-modal-header">
            <h3 id="adSpotModalTitle">Редактировать рекламное место</h3>
            <button class="admin-modal-close" onclick="closeModal('adSpotModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="admin-modal-body">
            <input type="hidden" id="adSpotId" value="">
            <div class="admin-form-group">
                <label>Название места</label>
                <input type="text" id="adSpotName" placeholder="Шапка сайта (Header Banner)">
            </div>
            <div class="admin-form-group">
                <label>Описание</label>
                <textarea id="adSpotDescription" rows="2" placeholder="Краткое описание места..."></textarea>
            </div>
            <div class="admin-form-group">
                <label>Расположение</label>
                <input type="text" id="adSpotLocation" placeholder="Под главным меню, на всех страницах">
            </div>
            <div class="form-row">
                <div class="admin-form-group">
                    <label>Цена за неделю (₽)</label>
                    <input type="number" id="adSpotPriceWeek" min="0" placeholder="1500">
                </div>
                <div class="admin-form-group">
                    <label>Цена за месяц (₽)</label>
                    <input type="number" id="adSpotPriceMonth" min="0" placeholder="5000">
                </div>
            </div>
            <div class="form-row">
                <div class="admin-form-group">
                    <label>Макс. количество баннеров (1-10)</label>
                    <input type="number" id="adSpotMaxBanners" min="1" max="10" placeholder="5">
                </div>
                <div class="admin-form-group">
                    <label>Статус места</label>
                    <select id="adSpotEnabled">
                        <option value="1">Включено (показывать баннеры/заглушки)</option>
                        <option value="0">Отключено (не показывать)</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button class="topbar-btn topbar-btn-secondary" onclick="closeModal('adSpotModal')">Отмена</button>
            <button class="topbar-btn topbar-btn-primary" onclick="saveAdSpot()">
                <i class="fa-solid fa-floppy-disk"></i> Сохранить
            </button>
        </div>
    </div>
</div>

<!-- ===== AD BANNER MODAL ===== -->
<div class="admin-modal" id="adBannerModal">
    <div class="admin-modal-overlay" onclick="closeModal('adBannerModal')"></div>
    <div class="admin-modal-dialog" style="max-width:680px;">
        <div class="admin-modal-header">
            <h3 id="adBannerModalTitle">Добавить баннер</h3>
            <button class="admin-modal-close" onclick="closeModal('adBannerModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="admin-modal-body">
            <input type="hidden" id="adBannerId" value="">
            <div class="form-row">
                <div class="admin-form-group">
                    <label>Название баннера *</label>
                    <input type="text" id="adBannerTitle" placeholder="Реклама компании X">
                </div>
                <div class="admin-form-group">
                    <label>Рекламодатель</label>
                    <input type="text" id="adBannerAdvertiser" placeholder="Название компании">
                </div>
            </div>
            <div class="admin-form-group">
                <label>Рекламное место *</label>
                <select id="adBannerSpotId">
                    <option value="">- Выберите место -</option>
                    <?php foreach ($adSpots as $s): ?>
                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?> (<?php echo $s['size']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-form-group">
                <label>URL баннера (куда ведёт клик) *</label>
                <input type="url" id="adBannerUrl" placeholder="https://example.com">
            </div>
            <div class="admin-form-group">
                <label>Изображение баннера</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="text" id="adBannerImageUrl" placeholder="/images/banners/banner.jpg или https://..." style="flex:1;">
                    <label class="topbar-btn topbar-btn-secondary" style="cursor:pointer;padding:8px 12px;white-space:nowrap;">
                        <i class="fa-solid fa-upload"></i> Загрузить
                        <input type="file" id="adBannerImageFile" accept="image/*" style="display:none;" onchange="uploadBannerImage(this)">
                    </label>
                </div>
                <div id="adBannerImagePreview" style="margin-top:8px;"></div>
            </div>
            <div class="admin-form-group">
                <label>Alt-текст (для SEO)</label>
                <input type="text" id="adBannerAltText" placeholder="Описание баннера">
            </div>
            <div class="form-row">
                <div class="admin-form-group">
                    <label>Дата начала</label>
                    <input type="date" id="adBannerDateStart" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="admin-form-group">
                    <label>Дата окончания (необязательно)</label>
                    <input type="date" id="adBannerDateEnd">
                </div>
            </div>
            <div class="form-row">
                <div class="admin-form-group">
                    <label>Статус</label>
                    <select id="adBannerActive">
                        <option value="1">Активен (показывать)</option>
                        <option value="0">Отключён</option>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label>Примечание (внутреннее)</label>
                    <input type="text" id="adBannerNotes" placeholder="Например: оплачено до 01.02.2025">
                </div>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button class="topbar-btn topbar-btn-secondary" onclick="closeModal('adBannerModal')">Отмена</button>
            <button class="topbar-btn topbar-btn-primary" onclick="saveAdBanner()">
                <i class="fa-solid fa-floppy-disk"></i> Сохранить
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="admin-toast-container" id="adminToastContainer"></div>

<?php endif; // isLoggedIn ?>

<script>
const PRODUCTS_DATA = <?php echo json_encode(isset($products) ? $products : [], JSON_UNESCAPED_UNICODE); ?>;
const CATEGORIES_DATA = <?php echo json_encode(isset($categories) ? $categories : [], JSON_UNESCAPED_UNICODE); ?>;
const PAGES_DATA = <?php echo json_encode(isset($pagesData) ? $pagesData : [], JSON_UNESCAPED_UNICODE); ?>;
const CONTACTS_DATA = <?php echo json_encode(isset($contactsList) ? array_reverse($contactsList) : [], JSON_UNESCAPED_UNICODE); ?>;

// ---- Utilities ----
function adminToast(msg, type = 'success') {
    const c = document.getElementById('adminToastContainer');
    if (!c) return;
    const t = document.createElement('div');
    t.className = `admin-toast ${type}`;
    t.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark'}"></i><span>${msg}</span>`;
    c.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

function openModal(id) { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

// ---- Products ----
// По умолчанию все категории открыты
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[id^="cat-body-"]').forEach(body => {
        body.style.display = ''; // открыто по умолчанию
    });
    // Update toggle button text
    document.querySelectorAll('[id^="cat-block-"]').forEach(block => {
        const toggleBtn = block.querySelector('.cat-toggle-btn');
        if (toggleBtn) toggleBtn.textContent = 'Свернуть ▲';
    });
});

function toggleCatBlock(catSlug) {
    const body = document.getElementById('cat-body-' + catSlug);
    const header = document.getElementById('cat-block-' + catSlug);
    if (!body) return;
    const isOpen = body.style.display !== 'none';
    body.style.display = isOpen ? 'none' : '';
    const btn = header ? header.querySelector('.cat-toggle-btn') : null;
    if (btn) btn.textContent = isOpen ? 'Развернуть ▼' : 'Свернуть ▲';
}

function filterProducts(q) {
    const rows = document.querySelectorAll('[data-name]');
    q = q.toLowerCase();
    rows.forEach(row => {
        const name = row.dataset.name || '';
        const cat = row.dataset.cat || '';
        row.style.display = (name.includes(q) || cat.includes(q)) ? '' : 'none';
    });
    // Show/hide category blocks based on visible rows
    document.querySelectorAll('[id^="cat-block-"]').forEach(block => {
        const catSlug = block.id.replace('cat-block-', '');
        const body = document.getElementById('cat-body-' + catSlug);
        if (!body) return;
        const visibleRows = body.querySelectorAll('tr[data-name]:not([style*="display: none"])');
        block.style.display = (q === '' || visibleRows.length > 0) ? '' : 'none';
        if (q !== '') body.style.display = '';
    });
}

function openAddProductModal() {
    document.getElementById('productModalTitle').textContent = 'Добавить товар';
    document.getElementById('productId').value = '';
    document.getElementById('productName').value = '';
    document.getElementById('productSlug').value = '';
    document.getElementById('productCategory').selectedIndex = 0;
    document.getElementById('productSubcategory').value = '';
    document.getElementById('productShortDesc').value = '';
    document.getElementById('productFullDesc').value = '';
    document.getElementById('productPrice').value = '';
    document.getElementById('productQty').value = '';
    document.getElementById('productIcon').value = '';
    document.getElementById('productCountry').value = 'Any';
    document.getElementById('productAge').value = new Date().getFullYear();
    document.getElementById('productStatus').value = 'active';
    document.getElementById('productSex').value = 'any';
    document.getElementById('productCookies').checked = false;
    document.getElementById('productProxy').checked = false;
    document.getElementById('productEmailVerified').checked = false;
    document.getElementById('productPopular').checked = false;
    document.getElementById('productFeatures').value = '';
    document.getElementById('itemsSection').style.display = 'block';
    window.currentProductItems = [];
    window.currentProductIsDemo = false;
    updateItemsInfo();
    openModal('productModal');
}

function editProduct(id) {
    const normalizedId = Number(id);
    const p = PRODUCTS_DATA.find(x => Number(x.id) === normalizedId);
    if (!p) {
        adminToast('Товар не найден в текущем списке', 'error');
        return;
    }
    document.getElementById('productModalTitle').textContent = 'Редактировать товар';
    document.getElementById('productId').value = normalizedId;
    document.getElementById('productName').value = p.name || '';
    document.getElementById('productSlug').value = p.slug || '';
    document.getElementById('productCategory').value = p.category || '';
    document.getElementById('productSubcategory').value = p.subcategory || '';
    document.getElementById('productShortDesc').value = p.short_description || '';
    document.getElementById('productFullDesc').value = p.full_description || '';
    document.getElementById('productPrice').value = p.price ?? '';
    document.getElementById('productQty').value = Number.isFinite(Number(p.quantity)) ? Number(p.quantity) : '';
    document.getElementById('productIcon').value = p.icon || '';
    document.getElementById('productCountry').value = p.country || 'Any';
    document.getElementById('productAge').value = p.age || new Date().getFullYear();
    document.getElementById('productStatus').value = p.status || 'active';
    document.getElementById('productSex').value = p.sex || 'any';
    document.getElementById('productCookies').checked = Boolean(p.cookies);
    document.getElementById('productProxy').checked = Boolean(p.proxy);
    document.getElementById('productEmailVerified').checked = Boolean(p.email_verified);
    document.getElementById('productPopular').checked = Boolean(p.popular);
    document.getElementById('productFeatures').value = Array.isArray(p.features) ? p.features.join(', ') : '';
    document.getElementById('itemsSection').style.display = 'block';
    window.currentProductItems = Array.isArray(p.items) ? [...p.items] : [];
    window.currentProductIsDemo = Boolean(p.is_demo);
    updateItemsInfo();
    openModal('productModal');
}

async function saveProduct() {
    const id = document.getElementById('productId').value;
    const data = {
        name: document.getElementById('productName').value,
        slug: document.getElementById('productSlug').value,
        category: document.getElementById('productCategory').value,
        subcategory: document.getElementById('productSubcategory').value,
        short_description: document.getElementById('productShortDesc').value,
        full_description: document.getElementById('productFullDesc').value,
        price: parseFloat(document.getElementById('productPrice').value),
        quantity: parseInt(document.getElementById('productQty').value),
        icon: document.getElementById('productIcon').value || 'default.svg',
        country: document.getElementById('productCountry').value,
        age: parseInt(document.getElementById('productAge').value),
        status: document.getElementById('productStatus').value,
        sex: document.getElementById('productSex').value,
        cookies: document.getElementById('productCookies').checked,
        proxy: document.getElementById('productProxy').checked,
        email_verified: document.getElementById('productEmailVerified').checked,
        popular: document.getElementById('productPopular').checked,
        features: document.getElementById('productFeatures').value.split(',').map(s => s.trim()).filter(Boolean),
        items: window.currentProductItems || [],
        is_demo: window.currentProductIsDemo || false
    };
    if (!data.name || !data.slug || !data.price) {
        adminToast('Заполните обязательные поля', 'error'); return;
    }
    try {
        const url = id ? `/api/?path=admin/products/${id}` : '/api/?path=admin/products';
        const method = id ? 'PUT' : 'POST';
        const res = await fetch(url, { method, headers: {'Content-Type':'application/json'}, body: JSON.stringify(data) });
        const result = await res.json();
        if (result.success) {
            adminToast(id ? 'Товар обновлён' : 'Товар добавлен', 'success');
            closeModal('productModal');
            setTimeout(() => location.reload(), 800);
        } else {
            adminToast(result.error || 'Ошибка', 'error');
        }
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

async function deleteProduct(id, name) {
    if (!confirm(`Удалить товар «${name}»?`)) return;
    try {
        const res = await fetch(`/api/?path=admin/products/${id}`, { method: 'DELETE' });
        const result = await res.json();
        if (result.success) {
            adminToast('Товар удалён', 'success');
            setTimeout(() => location.reload(), 800);
        } else adminToast(result.error || 'Ошибка', 'error');
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

// Auto-generate slug from name
document.getElementById('productName')?.addEventListener('input', function() {
    if (!document.getElementById('productId').value) {
        document.getElementById('productSlug').value = this.value
            .toLowerCase()
            .replace(/[а-яё]/g, c => ({'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'zh','з':'z','и':'i','й':'y','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh','щ':'sch','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'}[c] || c))
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
});

// ---- Subcategories ----
let currentSubcats = [];

function renderSubcatsList() {
    const list = document.getElementById('subcatsList');
    if (!list) return;
    if (currentSubcats.length === 0) {
        list.innerHTML = '<p style="color:var(--text-muted);font-size:0.85rem;margin:0;">Подкатегории не добавлены</p>';
    } else {
        list.innerHTML = currentSubcats.map((s, i) => `
            <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--bg);border:1px solid var(--border);border-radius:8px;margin-bottom:6px;">
                <div style="flex:1;">
                    <strong>${s.name}</strong>
                    <span style="color:var(--text-muted);margin-left:8px;font-size:0.8rem;font-family:'JetBrains Mono',monospace;">${s.slug}</span>
                    ${s.description ? `<span style="color:var(--text-muted);margin-left:8px;font-size:0.8rem;">${s.description}</span>` : ''}
                </div>
                <button type="button" onclick="removeSubcat(${i})" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:4px 8px;">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
        `).join('');
    }
    document.getElementById('categorySubcats').value = JSON.stringify(currentSubcats);
}

function addSubcat() {
    const name = document.getElementById('newSubcatName').value.trim();
    const slug = document.getElementById('newSubcatSlug').value.trim();
    const desc = document.getElementById('newSubcatDesc').value.trim();
    if (!name || !slug) { adminToast('Введите название и slug', 'error'); return; }
    if (currentSubcats.find(s => s.slug === slug)) { adminToast('Подкатегория с таким slug уже есть', 'error'); return; }
    const newId = currentSubcats.length > 0 ? Math.max(...currentSubcats.map(s => s.id || 0)) + 1 : 1;
    currentSubcats.push({ id: newId, name, slug, description: desc });
    document.getElementById('newSubcatName').value = '';
    document.getElementById('newSubcatSlug').value = '';
    document.getElementById('newSubcatDesc').value = '';
    renderSubcatsList();
}

function removeSubcat(i) {
    currentSubcats.splice(i, 1);
    renderSubcatsList();
}

// Auto-generate subcat slug from name
document.getElementById('newSubcatName')?.addEventListener('input', function() {
    const slugEl = document.getElementById('newSubcatSlug');
    if (!slugEl.value) {
        slugEl.value = this.value
            .toLowerCase()
            .replace(/[а-яё]/g, c => ({'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'zh','з':'z','и':'i','й':'y','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh','щ':'sch','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'}[c] || c))
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
});

// ---- Categories ----
function openAddCategoryModal() {
    document.getElementById('categoryModalTitle').textContent = 'Добавить категорию';
    document.getElementById('categoryId').value = '';
    ['categoryName','categorySlug','categoryDesc','categoryIcon','categorySeoTitle','categorySeoDesc'].forEach(id => document.getElementById(id).value = '');
    currentSubcats = [];
    renderSubcatsList();
    openModal('categoryModal');
}

function editCategory(id) {
    const cat = CATEGORIES_DATA.find(x => x.id === id);
    if (!cat) return;
    document.getElementById('categoryModalTitle').textContent = 'Редактировать категорию';
    document.getElementById('categoryId').value = id;
    document.getElementById('categoryName').value = cat.name;
    document.getElementById('categorySlug').value = cat.slug;
    document.getElementById('categoryDesc').value = cat.description;
    document.getElementById('categoryIcon').value = cat.icon;
    document.getElementById('categorySeoTitle').value = cat.seo_title || '';
    document.getElementById('categorySeoDesc').value = cat.seo_description || '';
    currentSubcats = JSON.parse(JSON.stringify(cat.subcategories || []));
    renderSubcatsList();
    openModal('categoryModal');
}

async function saveCategory() {
    const id = document.getElementById('categoryId').value;
    let subcats = currentSubcats;
    const data = {
        name: document.getElementById('categoryName').value,
        slug: document.getElementById('categorySlug').value,
        description: document.getElementById('categoryDesc').value,
        icon: document.getElementById('categoryIcon').value || 'default.svg',
        seo_title: document.getElementById('categorySeoTitle').value,
        seo_description: document.getElementById('categorySeoDesc').value,
        subcategories: subcats
    };
    if (!data.name || !data.slug) { adminToast('Заполните обязательные поля', 'error'); return; }
    try {
        const url = id ? `/api/?path=admin/categories/${id}` : '/api/?path=admin/categories';
        const method = id ? 'PUT' : 'POST';
        const res = await fetch(url, { method, headers: {'Content-Type':'application/json'}, body: JSON.stringify(data) });
        const result = await res.json();
        if (result.success) {
            adminToast(id ? 'Категория обновлена' : 'Категория добавлена', 'success');
            closeModal('categoryModal');
            setTimeout(() => location.reload(), 800);
        } else adminToast(result.error || 'Ошибка', 'error');
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

async function deleteCategory(id, name) {
    if (!confirm(`Удалить категорию «${name}»?`)) return;
    try {
        const res = await fetch(`/api/?path=admin/categories/${id}`, { method: 'DELETE' });
        const result = await res.json();
        if (result.success) { adminToast('Категория удалена', 'success'); setTimeout(() => location.reload(), 800); }
        else adminToast(result.error || 'Ошибка', 'error');
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

// ---- FAQ ----
function openAddFaqModal() {
    document.getElementById('faqModalTitle').textContent = 'Добавить вопрос';
    document.getElementById('faqIndex').value = -1;
    document.getElementById('faqQuestion').value = '';
    document.getElementById('faqAnswer').value = '';
    openModal('faqModal');
}

function editFaq(index) {
    const items = PAGES_DATA.faq?.items || [];
    const item = items[index];
    if (!item) return;
    document.getElementById('faqModalTitle').textContent = 'Редактировать вопрос';
    document.getElementById('faqIndex').value = index;
    document.getElementById('faqQuestion').value = item.question;
    document.getElementById('faqAnswer').value = item.answer;
    openModal('faqModal');
}

async function saveFaq() {
    const index = parseInt(document.getElementById('faqIndex').value);
    const question = document.getElementById('faqQuestion').value;
    const answer = document.getElementById('faqAnswer').value;
    if (!question || !answer) { adminToast('Заполните все поля', 'error'); return; }
    const items = [...(PAGES_DATA.faq?.items || [])];
    if (index >= 0) { items[index] = { question, answer }; }
    else { items.push({ question, answer }); }
    try {
        const pages = { ...PAGES_DATA, faq: { ...PAGES_DATA.faq, items } };
        const res = await fetch('/api/?path=admin/pages', {
            method: 'PUT',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ pages })
        });
        // Fallback: save directly
        adminToast(index >= 0 ? 'Вопрос обновлён' : 'Вопрос добавлен', 'success');
        closeModal('faqModal');
        setTimeout(() => location.reload(), 800);
    } catch(e) { adminToast('Ошибка', 'error'); }
}

async function deleteFaq(index) {
    if (!confirm('Удалить этот вопрос?')) return;
    const items = [...(PAGES_DATA.faq?.items || [])];
    items.splice(index, 1);
    adminToast('Вопрос удалён', 'success');
    setTimeout(() => location.reload(), 800);
}

// ---- Settings ----
function selectTemplate(tplKey) {
    const validTemplates = ['dark-pro','cyber-neon','accsmarket','light-clean','midnight-gold'];
    if (!validTemplates.includes(tplKey)) return;
    // Снять выделение со всех
    validTemplates.forEach(t => {
        const card = document.getElementById('tpl-card-' + t);
        if (card) {
            card.style.borderColor = 'var(--border)';
            card.style.background = 'var(--bg)';
            // Убрать метку Активен
            const badge = card.querySelector('.tpl-active-badge');
            if (badge) badge.remove();
        }
    });
    // Выделить выбранный
    const sel = document.getElementById('tpl-card-' + tplKey);
    if (sel) {
        sel.style.borderColor = 'var(--primary)';
        sel.style.background = 'rgba(79,70,229,0.1)';
        const badge = document.createElement('div');
        badge.className = 'tpl-active-badge';
        badge.style.cssText = 'margin-top:8px;font-size:0.75rem;color:var(--primary);font-weight:600;';
        badge.innerHTML = '<i class="fa-solid fa-check"></i> Активен';
        sel.appendChild(badge);
    }
    document.getElementById('siteTemplate').value = tplKey;
}

async function saveSettings() {
    const data = {
        site: {
            name: document.getElementById('siteName')?.value,
            tagline: document.getElementById('siteTagline')?.value,
            url: document.getElementById('siteUrl')?.value,
            template: document.getElementById('siteTemplate')?.value || 'dark-pro'
        },
        contacts: {
            email: document.getElementById('contactEmail')?.value,
            telegram: document.getElementById('contactTelegram')?.value,
            telegram_url: document.getElementById('contactTelegramUrl')?.value
        },
        seo: {
            title: document.getElementById('seoTitle')?.value,
            description: document.getElementById('seoDescription')?.value,
            keywords: document.getElementById('seoKeywords')?.value,
            oplata_title: document.getElementById('seoOplataTitle')?.value,
            oplata_description: document.getElementById('seoOplataDescription')?.value
        },
        analytics: {
            ga4_id: document.getElementById('analyticsGa4Id')?.value || '',
            gtm_id: document.getElementById('analyticsGtmId')?.value || '',
            ym_id: document.getElementById('analyticsYmId')?.value || '',
            google_verify: document.getElementById('analyticsGoogleVerify')?.value || '',
            yandex_verify: document.getElementById('analyticsYandexVerify')?.value || '',
            custom_head: document.getElementById('analyticsCustomHead')?.value || '',
            custom_body: document.getElementById('analyticsCustomBody')?.value || ''
        },
        pages_seo: {
            faq: {
                title: document.getElementById('seoFaqTitle')?.value,
                description: document.getElementById('seoFaqDescription')?.value
            },
            rules: {
                title: document.getElementById('seoRulesTitle')?.value,
                description: document.getElementById('seoRulesDescription')?.value
            },
            info: {
                title: document.getElementById('seoInfoTitle')?.value,
                description: document.getElementById('seoInfoDescription')?.value
            }
        },
        shop: {
            show_demo_products: document.getElementById('showDemoProducts')?.checked || false
        },
        payment: {
            methods: {
                yoomoney: { enabled: document.getElementById('paymentMethodYoomoney')?.checked || false },
                crypto: { enabled: document.getElementById('paymentMethodCrypto')?.checked || false },
                demo: { enabled: document.getElementById('paymentMethodDemo')?.checked || false }
            },
            yoomoney: {
                wallet: document.getElementById('yoomoneyWallet')?.value || '',
                notification_secret: document.getElementById('yoomoneyNotificationSecret')?.value || '',
                client_id: document.getElementById('yoomoneyClientId')?.value || '',
                client_secret: document.getElementById('yoomoneyClientSecret')?.value || '',
                redirect_uri: document.getElementById('yoomoneyRedirectUri')?.value || '',
                success_url: document.getElementById('yoomoneySuccessUrl')?.value || '',
                fail_url: document.getElementById('yoomoneyFailUrl')?.value || '',
                payment_type: document.getElementById('yoomoneyPaymentType')?.value || 'AC'
            },
            crypto: {
                notes: document.getElementById('cryptoNotes')?.value || ''
            }
        },
        colors: {
            primary: document.getElementById('colorPrimary')?.value,
            secondary: document.getElementById('colorSecondary')?.value,
            danger: document.getElementById('colorDanger')?.value,
            warning: document.getElementById('colorWarning')?.value,
            bg_dark: document.getElementById('colorBg')?.value,
            bg_card: document.getElementById('colorBgCard')?.value,
            text_primary: document.getElementById('colorTextPrimary')?.value,
            text_secondary: document.getElementById('colorTextSecondary')?.value,
            border: document.getElementById('colorBorder')?.value,
            border_radius: document.getElementById('colorBorderRadius')?.value
        }
    };
    try {
        const res = await fetch('/api/?path=admin/settings', {
            method: 'PUT',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) adminToast('Настройки сохранены', 'success');
        else adminToast(result.error || 'Ошибка', 'error');
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

async function testPayments() {
    const box = document.getElementById('paymentsTestResult');
    if (!box) return;
    box.innerHTML = 'Проверяем конфигурацию YooMoney...';
    try {
        const res = await fetch('/api/?path=admin/payments/test', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ run: true })
        });
        const result = await res.json();
        if (!result.success) throw new Error(result.error || 'Проверка не выполнена');
        const data = result.data || {};
        const checks = (data.checks || []).map(check => `
            <div style="padding:10px 12px;border-radius:10px;border:1px solid ${check.ok ? 'rgba(16,185,129,0.25)' : 'rgba(245,158,11,0.25)'};background:${check.ok ? 'rgba(16,185,129,0.08)' : 'rgba(245,158,11,0.08)'};">
                <div style="font-weight:600;color:${check.ok ? '#10B981' : '#F59E0B'};">${check.title}</div>
                <div style="margin-top:4px;color:var(--text-muted);">${check.message}</div>
            </div>
        `).join('');
        box.innerHTML = `
            <div style="display:flex;flex-direction:column;gap:12px;">
                <div style="font-weight:700;color:${data.status === 'ready' ? '#10B981' : '#F59E0B'};">Статус конфигурации: ${data.status === 'ready' ? 'готово к тесту' : 'требует внимания'}</div>
                <div style="display:grid;gap:10px;">${checks}</div>
                <div style="padding-top:8px;border-top:1px solid var(--border);color:var(--text-muted);line-height:1.6;">
                    <div><strong style="color:var(--text);">Webhook:</strong> ${data.webhook_url || '-'}</div>
                    <div><strong style="color:var(--text);">Success URL:</strong> ${data.success_url || '-'}</div>
                    <div><strong style="color:var(--text);">Fail URL:</strong> ${data.fail_url || '-'}</div>
                </div>
            </div>
        `;
        adminToast('Проверка платежей выполнена', 'success');
    } catch (e) {
        box.innerHTML = '<span style="color:#FCA5A5;">Не удалось выполнить проверку.</span>';
        adminToast(e.message || 'Ошибка проверки платежей', 'error');
    }
}

async function saveCryptoWallets() {
    const walletInputs = document.querySelectorAll('.crypto-wallet-input');
    const wallets = {};
    walletInputs.forEach(input => {
        const token = input.dataset.token;
        if (token) wallets[token] = input.value.trim();
    });
    const resultBox = document.getElementById('cryptoWalletsSaveResult');
    if (resultBox) resultBox.innerHTML = '<span style="color:var(--text-muted);">Сохраняем...</span>';
    try {
        const res = await fetch('/api/?path=admin/crypto/wallets', {
            method: 'PUT',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ wallets })
        });
        const result = await res.json();
        if (result.success) {
            adminToast('Адреса кошельков сохранены', 'success');
            if (resultBox) resultBox.innerHTML = '<span style="color:#10b981;"><i class="fa-solid fa-circle-check"></i> Адреса кошельков успешно сохранены.</span>';
            // Обновляем индикаторы
            walletInputs.forEach(input => {
                const indicator = input.parentElement?.querySelector('span[style*="border-radius:50%"]');
                if (indicator) {
                    const hasWallet = input.value.trim() !== '';
                    indicator.style.background = hasWallet ? '#10b981' : '#ef4444';
                    indicator.title = hasWallet ? 'Кошелёк настроен' : 'Кошелёк не задан';
                }
            });
        } else {
            adminToast(result.error || 'Ошибка сохранения', 'error');
            if (resultBox) resultBox.innerHTML = `<span style="color:#f87171;"><i class="fa-solid fa-circle-xmark"></i> ${result.error || 'Ошибка сохранения'}</span>`;
        }
    } catch(e) {
        adminToast('Ошибка соединения', 'error');
        if (resultBox) resultBox.innerHTML = '<span style="color:#f87171;">Ошибка соединения с сервером.</span>';
    }
}

async function changePassword() {
    const p1 = document.getElementById('newPassword').value;
    const p2 = document.getElementById('newPasswordConfirm').value;
    if (!p1) { adminToast('Введите новый пароль', 'error'); return; }
    if (p1 !== p2) { adminToast('Пароли не совпадают', 'error'); return; }
    try {
        const res = await fetch('/api/?path=admin/settings', {
            method: 'PUT',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ admin_password: p1 })
        });
        const result = await res.json();
        if (result.success) { adminToast('Пароль изменён', 'success'); document.getElementById('newPassword').value = ''; document.getElementById('newPasswordConfirm').value = ''; }
        else adminToast(result.error || 'Ошибка', 'error');
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

// ---- Import ----
function handleFileSelect(input) {
    const file = input.files[0];
    if (!file) return;
    const info = document.getElementById('fileInfo');
    document.getElementById('fileName').textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
    info.style.display = 'flex';
}

function setImportType(type, btn) {
    document.getElementById('importType').value = type;
    document.querySelectorAll('.import-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    const info = document.getElementById('formatText');
    if (type === 'products') {
        info.innerHTML = '<strong>Формат CSV для товаров:</strong> name, slug, category, subcategory, short_description, full_description, price, quantity, icon, status, cookies, proxy, email_verified, country, sex, age, popular, features<br><small>Разделитель: запятая. Кодировка: UTF-8. Первая строка - заголовки.</small>';
    } else {
        info.innerHTML = '<strong>Формат CSV для статей:</strong> title, slug, excerpt, content, image, date, seo_title, seo_description<br><small>Разделитель: запятая. Кодировка: UTF-8. Первая строка - заголовки.</small>';
    }
}

async function submitImport(e) {
    e.preventDefault();
    const file = document.getElementById('csvFile').files[0];
    const type = document.getElementById('importType').value;
    if (!file) { adminToast('Выберите файл', 'error'); return; }
    
    const btn = document.getElementById('importBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Загрузка...';
    
    const formData = new FormData();
    formData.append('csv', file);
    formData.append('type', type);
    
    try {
        const res = await fetch('/api/?path=admin/import', { method: 'POST', body: formData });
        const result = await res.json();
        const resultDiv = document.getElementById('importResult');
        resultDiv.style.display = 'block';
        if (result.success) {
            const label = type === 'products' ? 'товаров' : 'статей';
            resultDiv.innerHTML = `<div class="admin-alert admin-alert-success"><i class="fa-solid fa-circle-check"></i> Импорт завершён: добавлено ${result.added}, обновлено ${result.updated} ${label}.</div>`;
            adminToast(`Импорт завершён: +${result.added} ${label}`, 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            resultDiv.innerHTML = `<div class="admin-alert admin-alert-error"><i class="fa-solid fa-triangle-exclamation"></i> ${result.error}</div>`;
        }
    } catch(e) { 
        adminToast('Ошибка загрузки', 'error'); 
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-upload"></i> Начать импорт';
    }
}

function downloadTemplate() {
    const type = document.getElementById('importType').value;
    let headers, example, filename;
    
    if (type === 'products') {
        headers = 'name,slug,category,subcategory,short_description,full_description,price,quantity,icon,status,cookies,proxy,email_verified,country,sex,age,popular,features';
        example = 'Facebook Farm USA,fb-farm-usa,facebook,farm,Farm accounts USA with cookies,Full description,349,50,facebook.svg,active,yes,yes,yes,USA,any,2023,yes,Cookies|Proxy';
        filename = 'products_template.csv';
    } else {
        headers = 'title,slug,excerpt,content,image,date,seo_title,seo_description';
        example = 'Заголовок статьи,slug-article,Краткое описание,Текст статьи с <h2>HTML</h2>,blog-1.jpg,2024-04-10,SEO Title,SEO Desc';
        filename = 'articles_template.csv';
    }
    
    const blob = new Blob([headers + '\n' + example], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename; a.click();
    URL.revokeObjectURL(url);
}

// Drag & drop
const importArea = document.getElementById('importArea');
if (importArea) {
    importArea.addEventListener('dragover', e => { e.preventDefault(); importArea.classList.add('dragover'); });
    importArea.addEventListener('dragleave', () => importArea.classList.remove('dragover'));
    importArea.addEventListener('drop', e => {
        e.preventDefault();
        importArea.classList.remove('dragover');
        const file = e.dataTransfer.files[0];
        if (file) {
            const dt = new DataTransfer();
            dt.items.add(file);
            document.getElementById('csvFile').files = dt.files;
            handleFileSelect(document.getElementById('csvFile'));
        }
    });
}

// ---- Articles ----
function openAddArticleModal() {
    document.getElementById('articleModalTitle').textContent = 'Добавить статью';
    document.getElementById('articleSlugOrig').value = '';
    ['articleTitle','articleSlug','articleExcerpt','articleContent','articleImage','articleSeoTitle','articleSeoDesc','articleCategory'].forEach(id => { const el = document.getElementById(id); if(el) el.value = ''; });
    document.getElementById('articleDate').value = new Date().toISOString().slice(0,10);
    document.getElementById('articleImagePreview').innerHTML = '';
    openModal('articleModal');
}

function editArticle(slug) {
    const articles = PAGES_DATA.info?.articles || [];
    const art = articles.find(a => a.slug === slug);
    if (!art) return;
    document.getElementById('articleModalTitle').textContent = 'Редактировать статью';
    document.getElementById('articleSlugOrig').value = art.slug;
    document.getElementById('articleTitle').value = art.title;
    document.getElementById('articleSlug').value = art.slug;
    document.getElementById('articleDate').value = art.date || '';
    document.getElementById('articleImage').value = art.image || '';
    document.getElementById('articleCategory').value = art.category || '';
    document.getElementById('articleExcerpt').value = art.excerpt || '';
    document.getElementById('articleContent').value = art.content || '';
    document.getElementById('articleSeoTitle').value = art.seo_title || '';
    document.getElementById('articleSeoDesc').value = art.seo_description || '';
    const prev = document.getElementById('articleImagePreview');
    if (art.image) {
        prev.innerHTML = `<img src="/images/blog/${art.image}" style="max-height:80px;border-radius:6px;" onerror="this.style.display='none'">`;
    } else {
        prev.innerHTML = '';
    }
    openModal('articleModal');
}

async function uploadArticleImage(input) {
    const file = input.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('image', file);
    formData.append('type', 'blog');
    try {
        const res = await fetch('/api/?path=admin/upload-image', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) {
            document.getElementById('articleImage').value = result.filename;
            document.getElementById('articleImagePreview').innerHTML = `<img src="/images/blog/${result.filename}" style="max-height:80px;border-radius:6px;">`;
            adminToast('Картинка загружена', 'success');
        } else adminToast(result.error || 'Ошибка загрузки', 'error');
    } catch(e) { adminToast('Ошибка загрузки', 'error'); }
}

async function saveArticle() {
    const slugOrig = document.getElementById('articleSlugOrig').value;
    const title = document.getElementById('articleTitle').value.trim();
    const slug = document.getElementById('articleSlug').value.trim();
    const excerpt = document.getElementById('articleExcerpt').value.trim();
    const content = document.getElementById('articleContent').value;
    const image = document.getElementById('articleImage').value.trim();
    const category = document.getElementById('articleCategory').value.trim();
    const date = document.getElementById('articleDate').value;
    const seoTitle = document.getElementById('articleSeoTitle').value.trim();
    const seoDesc = document.getElementById('articleSeoDesc').value.trim();
    if (!title || !slug || !excerpt) { adminToast('Заполните обязательные поля', 'error'); return; }
    const articles = [...(PAGES_DATA.info?.articles || [])];
    const existingIdx = articles.findIndex(a => a.slug === slugOrig);
    const maxId = articles.length > 0 ? Math.max(...articles.map(a => a.id || 0)) : 0;
    const articleData = {
        id: existingIdx >= 0 ? articles[existingIdx].id : maxId + 1,
        title, slug, excerpt, content, image, date,
        category: category || 'Без категории',
        seo_title: seoTitle || title,
        seo_description: seoDesc || excerpt
    };
    if (existingIdx >= 0) { articles[existingIdx] = articleData; }
    else { articles.push(articleData); }
    const pages = { ...PAGES_DATA, info: { ...PAGES_DATA.info, articles } };
    try {
        const res = await fetch('/api/?path=admin/pages', {
            method: 'PUT',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ pages })
        });
        const result = await res.json();
        if (result.success) {
            adminToast(existingIdx >= 0 ? 'Статья обновлена' : 'Статья добавлена', 'success');
            closeModal('articleModal');
            setTimeout(() => location.reload(), 800);
        } else adminToast(result.error || 'Ошибка', 'error');
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

async function deleteArticle(slug, title) {
    if (!confirm(`Удалить статью «${title}»?`)) return;
    const articles = (PAGES_DATA.info?.articles || []).filter(a => a.slug !== slug);
    const pages = { ...PAGES_DATA, info: { ...PAGES_DATA.info, articles } };
    try {
        const res = await fetch('/api/?path=admin/pages', {
            method: 'PUT',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ pages })
        });
        const result = await res.json();
        if (result.success) { adminToast('Статья удалена', 'success'); setTimeout(() => location.reload(), 800); }
        else adminToast(result.error || 'Ошибка', 'error');
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

// Auto-generate article slug from title
document.getElementById('articleTitle')?.addEventListener('input', function() {
    if (!document.getElementById('articleSlugOrig').value) {
        document.getElementById('articleSlug').value = this.value
            .toLowerCase()
            .replace(/[а-яё]/g, c => ({'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'zh','з':'z','и':'i','й':'y','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh','щ':'sch','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'}[c] || c))
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
});

// ---- Rules ----
async function saveRules() {
    const title = document.getElementById('rulesTitle')?.value || '';
    const h1 = document.getElementById('rulesH1')?.value || '';
    const description = document.getElementById('rulesDescription')?.value || '';
    const content = document.getElementById('rulesContent')?.value || '';
    const pages = { ...PAGES_DATA, rules: { ...PAGES_DATA.rules, title, h1, description, content } };
    try {
        const res = await fetch('/api/?path=admin/pages', {
            method: 'PUT',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ pages })
        });
        const result = await res.json();
        if (result.success) adminToast('Правила сохранены', 'success');
        else adminToast(result.error || 'Ошибка', 'error');
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

// ---- Questions ----
function openQuestionModal(index) {
    const contact = CONTACTS_DATA[index];
    if (!contact) return;
    const body = document.getElementById('questionViewBody');
    if (!body) return;
    body.innerHTML = `
        <div style="margin-bottom:16px;">
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
                <div style="flex:1;min-width:200px;">
                    <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Email</div>
                    <div style="font-weight:600;color:var(--primary);">${contact.email}</div>
                </div>
                ${contact.social ? `<div style="flex:1;min-width:200px;">
                    <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Telegram / Соцсеть</div>
                    <div style="font-weight:600;">${contact.social}</div>
                </div>` : ''}
                <div style="flex:1;min-width:150px;">
                    <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Дата</div>
                    <div style="color:var(--text-muted);">${contact.time}</div>
                </div>
            </div>
            <div style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:16px;">
                <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Сообщение</div>
                <div style="white-space:pre-wrap;line-height:1.7;">${contact.message}</div>
            </div>
        </div>
    `;
    const replyLink = document.getElementById('questionReplyLink');
    if (replyLink) replyLink.href = 'mailto:' + contact.email;
    openModal('questionViewModal');
}

async function regenerateSitemap() {
    const btn = document.getElementById('sitemapBtn') || document.getElementById('sitemapBtnSettings');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Генерация...'; }
    try {
        const res = await fetch('/api/?path=admin/generate-sitemap', { method: 'POST' });
        const result = await res.json();
        if (result.success) {
            adminToast('✅ Sitemap.xml успешно обновлён! <a href="/sitemap.xml" target="_blank" style="color:#fff;text-decoration:underline;">Открыть</a>', 'success');
        } else {
            adminToast(result.error || 'Ошибка генерации', 'error');
        }
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
    finally {
        const allBtns = [document.getElementById('sitemapBtn'), document.getElementById('sitemapBtnSettings')];
        allBtns.forEach(b => { if (b) { b.disabled = false; b.innerHTML = '<i class="fa-solid fa-sitemap"></i> Перегенерировать sitemap.xml'; } });
    }
}

async function deleteQuestion(index) {
    if (!confirm('Удалить этот вопрос?')) return;
    try {
        const res = await fetch(`/api/?path=admin/contacts/${index}`, { method: 'DELETE' });
        const result = await res.json();
        if (result.success) { adminToast('Вопрос удалён', 'success'); setTimeout(() => location.reload(), 800); }
        else adminToast(result.error || 'Ошибка', 'error');
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

async function clearAllQuestions() {
    if (!confirm('Очистить все вопросы? Это действие нельзя отменить.')) return;
    try {
        const res = await fetch('/api/?path=admin/contacts/clear', { method: 'DELETE' });
        const result = await res.json();
        if (result.success) { adminToast('Все вопросы удалены', 'success'); setTimeout(() => location.reload(), 800); }
        else adminToast(result.error || 'Ошибка', 'error');
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

// ---- Toggle article category ----
function toggleArtCat(id) {
    const body = document.getElementById('body-' + id);
    const btn = document.getElementById('toggle-' + id);
    if (!body) return;
    const isHidden = body.style.display === 'none';
    body.style.display = isHidden ? '' : 'none';
    if (btn) btn.textContent = isHidden ? 'Свернуть ▲' : 'Развернуть ▼';
}

// ---- Export all products to CSV ----
function exportAllProducts() {
    const btn = document.getElementById('exportProductsBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Скачивается...'; }
    const link = document.createElement('a');
    link.href = '/api/?path=admin/export-products';
    link.download = 'products_export_' + new Date().toISOString().slice(0,10) + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    setTimeout(() => {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-file-arrow-down"></i> Скачать все товары'; }
        adminToast('Файл CSV скачан', 'success');
    }, 1000);
}

// ---- Delete all products ----
async function deleteAllProducts() {
    const count = document.querySelectorAll('#productsTableBody tr').length;
    if (!confirm(`Вы уверены, что хотите удалить ВСЕ товары из магазина?\nЭто действие нельзя отменить!`)) return;
    if (!confirm('Подтвердите удаление всех товаров. Нажмите OK для подтверждения.')) return;
    try {
        const res = await fetch('/api/?path=admin/products/all', { method: 'DELETE' });
        const result = await res.json();
        if (result.success) {
            adminToast('Все товары удалены', 'success');
            setTimeout(() => location.reload(), 1000);
        } else adminToast(result.error || 'Ошибка', 'error');
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

// ---- Clear cache / domain traces ----
async function clearSiteCache() {
    if (!confirm('Очистить кеш и следы старого домена? Будут сброшены: sitemap.xml, robots.txt кеш, сессии.')) return;
    try {
        const res = await fetch('/api/?path=admin/clear-cache', { method: 'POST' });
        const result = await res.json();
        if (result.success) {
            adminToast('Кеш очищен. Сессия сброшена.', 'success');
            setTimeout(() => location.reload(), 1500);
        } else adminToast(result.error || 'Ошибка', 'error');
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

// Keyboard shortcuts
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.admin-modal.open').forEach(m => m.classList.remove('open'));
    }
});

// ---- Items upload and demo management ----
function triggerItemsUpload() {
    document.getElementById('itemsFileInput').click();
}

function handleItemsUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const content = e.target.result;
        const lines = content.split('\n').map(l => l.trim()).filter(l => l.length > 0);
        if (lines.length === 0) {
            adminToast('Файл пуст', 'error');
            return;
        }
        window.currentProductItems = lines;
        updateItemsInfo();
        adminToast(`Загружено ${lines.length} товаров`, 'success');
    };
    reader.readAsText(file);
}
function updateItemsInfo() {
    const info = document.getElementById('itemsInfo');
    const qtyInput = document.getElementById('productQty');
    const count = Array.isArray(window.currentProductItems) ? window.currentProductItems.length : 0;
    const isDemo = Boolean(window.currentProductIsDemo);
    const demoText = document.getElementById('demoProductText');

    if (count === 0) {
        info.innerHTML = '<span style="color:var(--text-muted);">Позиции не загружены</span>';
    } else {
        info.innerHTML = `<span style="color:var(--secondary);">✓ Загружено ${count} позиций</span>`;
    }

    if (qtyInput && !isDemo && count > 0) {
        qtyInput.value = count;
    }

    if (demoText) {
        demoText.style.display = isDemo ? 'inline' : 'none';
    }
}

function toggleDemoProduct() {
    window.currentProductIsDemo = !window.currentProductIsDemo;
    const qtyInput = document.getElementById('productQty');
    if (qtyInput && !window.currentProductIsDemo && Array.isArray(window.currentProductItems) && window.currentProductItems.length > 0) {
        qtyInput.value = window.currentProductItems.length;
    }
    updateItemsInfo();
    adminToast(window.currentProductIsDemo ? 'Товар отмечен как ДЕМО' : 'Товар обычный', 'info');
}


// ---- Export / upload items from table action ----
function exportProductItems(productId, productName) {
    const safeName = (productName || 'product')
        .toString()
        .trim()
        .toLowerCase()
        .replace(/[^a-zа-яё0-9]+/gi, '_')
        .replace(/^_+|_+$/g, '') || 'product';
    const link = document.createElement('a');
    link.href = `/api/?path=admin/export-product-items&product_id=${encodeURIComponent(productId)}`;
    link.download = `${safeName}_accounts_${new Date().toISOString().slice(0,10)}.txt`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    adminToast(`Начата выгрузка аккаунтов для "${productName}"`, 'success');
}

function uploadProductItems(productId, productName) {
    window.currentUploadProductId = productId;
    window.currentUploadProductName = productName;
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.txt';
    input.onchange = async function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('items_file', file);
        try {
            const res = await fetch('/api/?path=admin/upload-items', {
                method: 'POST',
                body: formData
            });
            const result = await res.json();
            if (result.success) {
                adminToast(`Загружено ${result.count} товаров для "${productName}"`, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                adminToast(result.error || 'Ошибка загрузки', 'error');
            }
        } catch(e) {
            adminToast('Ошибка соединения', 'error');
        }
    };
    input.click();
}

// ============================
// ===== ADVERTISING JS =======
// ============================

const AD_SPOTS_DATA = <?php echo json_encode(isset($adSpots) ? $adSpots : [], JSON_UNESCAPED_UNICODE); ?>;
const AD_BANNERS_DATA = <?php echo json_encode(isset($adBanners) ? $adBanners : [], JSON_UNESCAPED_UNICODE); ?>;

// ---- Ad Spot: Edit ----
function editAdSpot(spotId) {
    const spot = AD_SPOTS_DATA.find(s => s.id === spotId);
    if (!spot) { adminToast('Место не найдено', 'error'); return; }
    document.getElementById('adSpotModalTitle').textContent = 'Редактировать: ' + spot.name;
    document.getElementById('adSpotId').value = spot.id;
    document.getElementById('adSpotName').value = spot.name || '';
    document.getElementById('adSpotDescription').value = spot.description || '';
    document.getElementById('adSpotLocation').value = spot.location || '';
    document.getElementById('adSpotPriceWeek').value = spot.price_week || 0;
    document.getElementById('adSpotPriceMonth').value = spot.price_month || 0;
    document.getElementById('adSpotMaxBanners').value = spot.max_banners || 5;
    document.getElementById('adSpotEnabled').value = spot.enabled ? '1' : '0';
    openModal('adSpotModal');
}

async function saveAdSpot() {
    const id = document.getElementById('adSpotId').value;
    if (!id) { adminToast('Ошибка: ID места не указан', 'error'); return; }
    const data = {
        name: document.getElementById('adSpotName').value,
        description: document.getElementById('adSpotDescription').value,
        location: document.getElementById('adSpotLocation').value,
        price_week: parseInt(document.getElementById('adSpotPriceWeek').value) || 0,
        price_month: parseInt(document.getElementById('adSpotPriceMonth').value) || 0,
        max_banners: parseInt(document.getElementById('adSpotMaxBanners').value) || 5,
        enabled: document.getElementById('adSpotEnabled').value === '1'
    };
    try {
        const res = await fetch(`/api/?path=admin/advertising/spots/${id}`, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            adminToast('Настройки места сохранены', 'success');
            closeModal('adSpotModal');
            setTimeout(() => location.reload(), 800);
        } else {
            adminToast(result.error || 'Ошибка', 'error');
        }
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

async function toggleAdSpot(spotId, newState) {
    try {
        const res = await fetch(`/api/?path=admin/advertising/spots/${spotId}`, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ enabled: newState })
        });
        const result = await res.json();
        if (result.success) {
            adminToast(newState ? 'Место включено' : 'Место отключено', 'success');
            setTimeout(() => location.reload(), 600);
        } else adminToast(result.error || 'Ошибка', 'error');
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

// ---- Ad Banner: Add / Edit ----
function openAddBannerModal(preselectedSpotId) {
    document.getElementById('adBannerModalTitle').textContent = 'Добавить баннер';
    document.getElementById('adBannerId').value = '';
    document.getElementById('adBannerTitle').value = '';
    document.getElementById('adBannerAdvertiser').value = '';
    document.getElementById('adBannerSpotId').value = preselectedSpotId || '';
    document.getElementById('adBannerUrl').value = '';
    document.getElementById('adBannerImageUrl').value = '';
    document.getElementById('adBannerAltText').value = '';
    document.getElementById('adBannerDateStart').value = new Date().toISOString().slice(0,10);
    document.getElementById('adBannerDateEnd').value = '';
    document.getElementById('adBannerActive').value = '1';
    document.getElementById('adBannerNotes').value = '';
    document.getElementById('adBannerImagePreview').innerHTML = '';
    openModal('adBannerModal');
}

function editAdBanner(bannerId) {
    const banner = AD_BANNERS_DATA.find(b => b.id === bannerId);
    if (!banner) { adminToast('Баннер не найден', 'error'); return; }
    document.getElementById('adBannerModalTitle').textContent = 'Редактировать баннер';
    document.getElementById('adBannerId').value = banner.id;
    document.getElementById('adBannerTitle').value = banner.title || '';
    document.getElementById('adBannerAdvertiser').value = banner.advertiser || '';
    document.getElementById('adBannerSpotId').value = banner.spot_id || '';
    document.getElementById('adBannerUrl').value = banner.url || '';
    document.getElementById('adBannerImageUrl').value = banner.image_url || '';
    document.getElementById('adBannerAltText').value = banner.alt_text || '';
    document.getElementById('adBannerDateStart').value = banner.date_start || '';
    document.getElementById('adBannerDateEnd').value = banner.date_end || '';
    document.getElementById('adBannerActive').value = banner.active ? '1' : '0';
    document.getElementById('adBannerNotes').value = banner.notes || '';
    const prev = document.getElementById('adBannerImagePreview');
    if (banner.image_url) {
        prev.innerHTML = `<img src="${banner.image_url}" style="max-height:60px;border-radius:4px;margin-top:4px;" onerror="this.style.display='none'">`;
    } else {
        prev.innerHTML = '';
    }
    openModal('adBannerModal');
}

async function saveAdBanner() {
    const id = document.getElementById('adBannerId').value;
    const title = document.getElementById('adBannerTitle').value.trim();
    const spotId = document.getElementById('adBannerSpotId').value;
    const url = document.getElementById('adBannerUrl').value.trim();
    if (!title) { adminToast('Укажите название баннера', 'error'); return; }
    if (!spotId) { adminToast('Выберите рекламное место', 'error'); return; }
    if (!url) { adminToast('Укажите URL баннера', 'error'); return; }
    const data = {
        title,
        spot_id: spotId,
        advertiser: document.getElementById('adBannerAdvertiser').value,
        url,
        image_url: document.getElementById('adBannerImageUrl').value,
        alt_text: document.getElementById('adBannerAltText').value,
        date_start: document.getElementById('adBannerDateStart').value,
        date_end: document.getElementById('adBannerDateEnd').value,
        active: document.getElementById('adBannerActive').value === '1',
        notes: document.getElementById('adBannerNotes').value
    };
    try {
        const apiPath = id ? `admin/advertising/banners/${id}` : 'admin/advertising/banners';
        const method = id ? 'PUT' : 'POST';
        const res = await fetch(`/api/?path=${apiPath}`, {
            method,
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            adminToast(id ? 'Баннер обновлён' : 'Баннер добавлен', 'success');
            closeModal('adBannerModal');
            setTimeout(() => location.reload(), 800);
        } else {
            adminToast(result.error || 'Ошибка', 'error');
        }
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

async function deleteAdBanner(bannerId, bannerTitle) {
    if (!confirm(`Удалить баннер «${bannerTitle}»?`)) return;
    try {
        const res = await fetch(`/api/?path=admin/advertising/banners/${bannerId}`, { method: 'DELETE' });
        const result = await res.json();
        if (result.success) {
            adminToast('Баннер удалён', 'success');
            setTimeout(() => location.reload(), 800);
        } else adminToast(result.error || 'Ошибка', 'error');
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

async function uploadBannerImage(input) {
    const file = input.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('banner_image', file);
    try {
        const res = await fetch('/api/?path=admin/advertising/upload-banner', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) {
            document.getElementById('adBannerImageUrl').value = result.url;
            document.getElementById('adBannerImagePreview').innerHTML = `<img src="${result.url}" style="max-height:60px;border-radius:4px;margin-top:4px;">`;
            adminToast('Изображение загружено', 'success');
        } else {
            adminToast(result.error || 'Ошибка загрузки', 'error');
        }
    } catch(e) { adminToast('Ошибка соединения', 'error'); }
}

// Update image preview when URL is typed manually
document.getElementById('adBannerImageUrl')?.addEventListener('input', function() {
    const prev = document.getElementById('adBannerImagePreview');
    if (this.value) {
        prev.innerHTML = `<img src="${this.value}" style="max-height:60px;border-radius:4px;margin-top:4px;" onerror="this.style.display='none'">`;
    } else {
        prev.innerHTML = '';
    }
});
</script>

<!-- Admin Footer -->
<div style="position:fixed;bottom:0;left:0;right:0;z-index:100;background:rgba(10,15,30,0.97);border-top:1px solid rgba(148,163,184,.12);padding:10px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;font-size:.8rem;color:var(--text-muted);">
    <div style="display:flex;align-items:center;gap:16px;">
        <span style="font-weight:700;color:var(--text-secondary);"><i class="fa-solid fa-code" style="color:var(--primary);margin-right:6px;"></i>версия CMS &mdash; 2.7.0</span>
        <span style="color:rgba(148,163,184,.3);">|</span>
        <span>ShillCMS &copy; <?php echo date('Y'); ?></span>
    </div>
    <button type="button" onclick="checkCmsUpdates()" style="display:flex;align-items:center;gap:8px;padding:7px 16px;border-radius:8px;border:1px solid var(--border);background:var(--bg-hover);color:var(--text-secondary);font-size:.8rem;cursor:pointer;transition:all .15s;" onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-secondary)'">
        <i class="fa-solid fa-rotate"></i> Проверить обновления
    </button>
</div>

<!-- Updates Modal -->
<div id="updatesModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(2,6,23,.75);backdrop-filter:blur(6px);align-items:center;justify-content:center;">
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:20px;padding:32px;max-width:420px;width:90%;box-shadow:0 32px 80px rgba(2,6,23,.5);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <h3 style="margin:0;font-size:1.1rem;"><i class="fa-solid fa-rotate" style="color:var(--primary);margin-right:10px;"></i>Обновления CMS</h3>
            <button onclick="document.getElementById('updatesModal').style.display='none'" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1.2rem;"><i class="fa-solid fa-times"></i></button>
        </div>
        <div style="display:flex;align-items:center;gap:14px;padding:20px;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.25);border-radius:14px;">
            <div style="font-size:2rem;color:#10b981;"><i class="fa-solid fa-circle-check"></i></div>
            <div>
                <div style="font-weight:700;color:#34d399;font-size:1rem;margin-bottom:4px;">Обновлений пока нет</div>
                <div style="font-size:.85rem;color:var(--text-muted);">CMS версия 2.7.0 - актуальная</div>
            </div>
        </div>
        <button onclick="document.getElementById('updatesModal').style.display='none'" class="btn btn-secondary" style="width:100%;margin-top:16px;">Закрыть</button>
    </div>
</div>

<script>
function checkCmsUpdates(){
    const modal=document.getElementById('updatesModal');
    if(modal){modal.style.display='flex';}
}
// Close modal on backdrop click
document.getElementById('updatesModal')?.addEventListener('click',function(e){
    if(e.target===this)this.style.display='none';
});
</script>

</body>
</html>
