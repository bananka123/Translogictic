<?php
session_start();
require_once 'config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

//все данные
$reviewsStmt = $pdo->prepare("SELECT * FROM reviews WHERE user_id = ? ORDER BY created_at DESC");
$reviewsStmt->execute([$user_id]);
$user_reviews = $reviewsStmt->fetchAll();

$feedbackStmt = $pdo->prepare("SELECT * FROM feedback WHERE email = ? ORDER BY created_at DESC");
$feedbackStmt->execute([$user['email']]);
$user_feedback = $feedbackStmt->fetchAll();

$ordersStmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$ordersStmt->execute([$user_id]);
$user_orders = $ordersStmt->fetchAll();

$calcStmt = $pdo->prepare("SELECT * FROM calculator_history WHERE user_id = ? ORDER BY created_at DESC");
$calcStmt->execute([$user_id]);
$user_calculations = $calcStmt->fetchAll();

$addressesStmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$addressesStmt->execute([$user_id]);
$user_addresses = $addressesStmt->fetchAll();

$routesStmt = $pdo->prepare("SELECT * FROM favorite_routes WHERE user_id = ? ORDER BY created_at DESC");
$routesStmt->execute([$user_id]);
$user_routes = $routesStmt->fetchAll();

//черновики
$draftsStmt = $pdo->prepare("SELECT * FROM drafts WHERE user_id = ? ORDER BY updated_at DESC");
$draftsStmt->execute([$user_id]);
$user_drafts = $draftsStmt->fetchAll();

// МАССОВЫЕ УДАЛЕНИЯЯ

// Отмена заказа 1
if (isset($_GET['cancel_order'])) {
    $order_id = (int)$_GET['cancel_order'];
    $checkStmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
    $checkStmt->execute([$order_id, $user_id]);
    $order_status = $checkStmt->fetchColumn();
    
    if ($order_status && $order_status != 'delivered' && $order_status != 'cancelled') {
        $updateStmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND user_id = ?");
        $updateStmt->execute([$order_id, $user_id]);
        
        $historyStmt = $pdo->prepare("INSERT INTO order_status_history (order_id, status, comment) VALUES (?, 'cancelled', 'Заказ отменен пользователем')");
        $historyStmt->execute([$order_id]);
    }
    header("Location: cabinet.php?tab=orders");
    exit;
}

// Очистить все адреса
if (isset($_GET['clear_all_addresses'])) {
    $delStmt = $pdo->prepare("DELETE FROM user_addresses WHERE user_id = ?");
    $delStmt->execute([$user_id]);
    header("Location: cabinet.php?tab=addresses");
    exit;
}

// Очистить все маршруты
if (isset($_GET['clear_all_routes'])) {
    $delStmt = $pdo->prepare("DELETE FROM favorite_routes WHERE user_id = ?");
    $delStmt->execute([$user_id]);
    header("Location: cabinet.php?tab=routes");
    exit;
}

// Очистить все черновики
if (isset($_GET['clear_all_drafts'])) {
    $delStmt = $pdo->prepare("DELETE FROM drafts WHERE user_id = ?");
    $delStmt->execute([$user_id]);
    header("Location: cabinet.php?tab=drafts");
    exit;
}

// Очистить все заявки 
if (isset($_GET['clear_all_feedback'])) {
    $delStmt = $pdo->prepare("DELETE FROM feedback WHERE email = ?");
    $delStmt->execute([$user['email']]);
    header("Location: cabinet.php?tab=feedback");
    exit;
}

// Очистить все отзывы
if (isset($_GET['clear_all_reviews'])) {
    $delStmt = $pdo->prepare("DELETE FROM reviews WHERE user_id = ?");
    $delStmt->execute([$user_id]);
    header("Location: cabinet.php?tab=reviews");
    exit;
}

// Одиночные удаления
if (isset($_GET['delete_address'])) {
    $delStmt = $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
    $delStmt->execute([(int)$_GET['delete_address'], $user_id]);
    header("Location: cabinet.php?tab=addresses");
    exit;
}

if (isset($_GET['delete_route'])) {
    $delStmt = $pdo->prepare("DELETE FROM favorite_routes WHERE id = ? AND user_id = ?");
    $delStmt->execute([(int)$_GET['delete_route'], $user_id]);
    header("Location: cabinet.php?tab=routes");
    exit;
}

if (isset($_GET['delete_draft'])) {
    $delStmt = $pdo->prepare("DELETE FROM drafts WHERE id = ? AND user_id = ?");
    $delStmt->execute([(int)$_GET['delete_draft'], $user_id]);
    header("Location: cabinet.php?tab=drafts");
    exit;
}

if (isset($_GET['delete_feedback'])) {
    $delStmt = $pdo->prepare("DELETE FROM feedback WHERE id = ? AND email = ?");
    $delStmt->execute([(int)$_GET['delete_feedback'], $user['email']]);
    header("Location: cabinet.php?tab=feedback");
    exit;
}

if (isset($_GET['delete_review'])) {
    $delStmt = $pdo->prepare("DELETE FROM reviews WHERE id = ? AND user_id = ?");
    $delStmt->execute([(int)$_GET['delete_review'], $user_id]);
    header("Location: cabinet.php?tab=reviews");
    exit;
}

//POST
$profile_success = false;
$profile_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $surname = trim($_POST['surname']);
        $phone = trim($_POST['phone']);
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        
        if (empty($name)) {
            $profile_errors['name'] = 'Имя не может быть пустым';
        }
        
        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                $profile_errors['password'] = 'Пароль должен быть не менее 6 символов';
            } elseif ($new_password !== $confirm_password) {
                $profile_errors['password'] = 'Пароли не совпадают';
            }
        }
        
        if (empty($profile_errors)) {
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET name = ?, surname = ?, phone = ?, password = ? WHERE id = ?");
                $updateStmt->execute([$name, $surname, $phone, $hashed_password, $user_id]);
            } else {
                $updateStmt = $pdo->prepare("UPDATE users SET name = ?, surname = ?, phone = ? WHERE id = ?");
                $updateStmt->execute([$name, $surname, $phone, $user_id]);
            }
            $_SESSION['user_name'] = $name;
            $profile_success = true;
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        }
    }
    
    if (isset($_POST['add_address'])) {
        $city = trim($_POST['city']);
        $street = trim($_POST['street']);
        $house = trim($_POST['house']);
        $apartment = trim($_POST['apartment']);
        $address_type = $_POST['address_type'];
        
        if (!empty($city) && !empty($street) && !empty($house)) {
            $addStmt = $pdo->prepare("INSERT INTO user_addresses (user_id, address_type, city, street, house, apartment) VALUES (?, ?, ?, ?, ?, ?)");
            $addStmt->execute([$user_id, $address_type, $city, $street, $house, $apartment]);
            header("Location: cabinet.php?tab=addresses");
            exit;
        }
    }
    
    if (isset($_POST['add_route'])) {
        $from_city = trim($_POST['from_city']);
        $to_city = trim($_POST['to_city']);
        
        if (!empty($from_city) && !empty($to_city)) {
            try {
                $routeStmt = $pdo->prepare("INSERT INTO favorite_routes (user_id, from_city, to_city) VALUES (?, ?, ?)");
                $routeStmt->execute([$user_id, $from_city, $to_city]);
                header("Location: cabinet.php?tab=routes");
                exit;
            } catch (PDOException $e) {
                $route_error = "Такой маршрут уже есть";
            }
        }
    }
}

function formatDate($date) {
    $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $d = new DateTime($date);
    return $d->format('d') . ' ' . $months[$d->format('n') - 1] . ' ' . $d->format('Y');
}

function formatDateTime($date) {
    $d = new DateTime($date);
    return $d->format('d.m.Y H:i');
}

function getOrderStatus($status) {
    $statuses = [
        'new' => ['text' => 'Новый', 'class' => 'status-new'],
        'processing' => ['text' => 'В обработке', 'class' => 'status-processing'],
        'shipped' => ['text' => 'Отправлен', 'class' => 'status-shipped'],
        'delivered' => ['text' => 'Доставлен', 'class' => 'status-delivered'],
        'cancelled' => ['text' => 'Отменён', 'class' => 'status-cancelled']
    ];
    return $statuses[$status] ?? ['text' => $status, 'class' => 'status-default'];
}

function getPaymentStatus($status) {
    $statuses = [
        'pending' => ['text' => 'Ожидает оплаты', 'class' => 'status-pending'],
        'paid' => ['text' => 'Оплачен', 'class' => 'status-paid'],
        'refunded' => ['text' => 'Возврат', 'class' => 'status-refunded']
    ];
    return $statuses[$status] ?? ['text' => $status, 'class' => 'status-default'];
}

function getStars($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= '<span class="star ' . ($i <= $rating ? 'filled' : '') . '">★</span>';
    }
    return $stars;
}

$is_admin = ($user['role'] === 'admin');
if ($is_admin) {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalFeedback = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
    $totalReviews = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
    $pendingReviews = $pdo->query("SELECT COUNT(*) FROM reviews WHERE is_published = 0")->fetchColumn();
    $totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет — ТрансЛогистик</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .cabinet-wrapper {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            margin: 40px 0 60px;
        }
        .cabinet-sidebar {
            background: white;
            border-radius: 28px;
            padding: 30px 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-border);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        .user-card {
            text-align: center;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--gray-border);
            margin-bottom: 25px;
        }
        .user-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 42px;
            font-weight: 700;
            color: white;
            box-shadow: 0 10px 25px -5px rgba(11,59,92,0.3);
        }
        .user-name-sidebar {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }
        .user-email-sidebar {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        .user-role-badge {
            display: inline-block;
            padding: 4px 12px;
            background: var(--accent);
            color: white;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }
        .cabinet-nav {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .nav-item {
            padding: 12px 18px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s;
            color: var(--text-dark);
            font-weight: 500;
        }
        .nav-item:hover {
            background: var(--gray-bg);
            color: var(--accent);
        }
        .nav-item.active {
            background: var(--primary);
            color: white;
        }
        .nav-icon {
            width: 22px;
            height: 22px;
            object-fit: contain;
        }
        .nav-item.active .nav-icon {
            filter: brightness(0) invert(1);
        }
        .cabinet-main {
            background: white;
            border-radius: 28px;
            padding: 35px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-border);
        }
        .tab-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gray-border);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: space-between;
        }
        .tab-title .title-icon {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }
        .clear-all-btn {
            background: #ef4444;
            color: white;
            padding: 8px 20px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }
        .clear-all-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        .tab-content.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .profile-form {
            max-width: 500px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
        }
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-wrapper .form-control {
            flex: 1;
            padding-right: 45px;
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            transition: color 0.3s;
        }
        .toggle-password:hover {
            color: var(--accent);
        }
        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--gray-border);
            border-radius: 16px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(245,158,11,0.1);
        }
        .form-control[disabled] {
            background: var(--gray-bg);
            cursor: not-allowed;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 28px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--accent);
            transform: translateY(-2px);
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }
        .btn-sm {
            padding: 6px 16px;
            font-size: 12px;
        }
        .status-new { background: #06b6d4; color: white; }
        .status-processing { background: #f59e0b; color: white; }
        .status-shipped { background: #3b82f6; color: white; }
        .status-delivered { background: #22c55e; color: white; }
        .status-cancelled { background: #ef4444; color: white; }
        .status-pending { background: #f59e0b; color: white; }
        .status-paid { background: #22c55e; color: white; }
        .status-refunded { background: #64748b; color: white; }
        .status-default { background: #64748b; color: white; }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }
        .order-card, .draft-card {
            background: var(--gray-bg);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
            border: 1px solid transparent;
        }
        .order-card:hover, .draft-card:hover {
            border-color: var(--accent);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-border);
        }
        .order-number, .draft-name {
            font-weight: 700;
            color: var(--primary);
            font-size: 18px;
        }
        .order-tracking {
            font-family: monospace;
            color: var(--accent);
            font-size: 14px;
        }
        .order-details, .draft-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .order-detail-label {
            color: var(--text-muted);
            font-size: 12px;
            margin-bottom: 5px;
        }
        .order-price {
            font-weight: 700;
            color: var(--primary);
            font-size: 20px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
            padding: 12px 15px;
            background: var(--gray-bg);
            color: var(--primary);
            font-weight: 600;
            font-size: 14px;
        }
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid var(--gray-border);
        }
        .data-table tr:hover td {
            background: #f9fafb;
        }
        .address-card, .route-card {
            background: var(--gray-bg);
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            transition: all 0.3s;
        }
        .address-card:hover, .route-card:hover {
            background: #eef2f6;
        }
        .address-info h4, .route-info h4 {
            color: var(--primary);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .address-badge {
            background: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            margin-left: 10px;
        }
        .add-form {
            background: var(--gray-bg);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .add-form h3 {
            color: var(--primary);
            margin-bottom: 15px;
            font-size: 18px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: var(--gray-bg);
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            background: var(--primary);
            color: white;
        }
        .stat-card:hover .stat-number,
        .stat-card:hover .stat-label {
            color: white;
        }
        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 8px;
        }
        .stat-label {
            color: var(--text-muted);
            font-size: 14px;
        }
        .star {
            color: #cbd5e1;
            font-size: 16px;
        }
        .logo, .logo:hover, .logo:focus, .logo:active {
            text-decoration: none !important;
            border-bottom: none !important;
            outline: none !important;
        }
        .star.filled {
            color: var(--accent);
        }
        .alert-success {
            background: #22c55e;
            color: white;
            padding: 15px;
            border-radius: 16px;
            margin-bottom: 20px;
            text-align: center;
        }
        .empty-state {
            text-align: center;
            padding: 50px;
            color: var(--text-muted);
        }
        @media (max-width: 768px) {
            .cabinet-wrapper { grid-template-columns: 1fr; }
            .cabinet-sidebar { position: static; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .cabinet-main { padding: 25px; }
            .order-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .cabinet-main { padding: 20px !important; }
            .tab-title { font-size: 22px !important; margin-bottom: 20px !important; padding-bottom: 10px !important; flex-direction: column !important; align-items: flex-start !important; gap: 15px !important; }
            .tab-title .title-icon { width: 24px !important; height: 24px !important; }
            .profile-form { max-width: 100% !important; }
            .form-control { padding: 10px 14px !important; font-size: 14px !important; }
            .form-group label { font-size: 13px !important; }
            .order-card, .draft-card { padding: 15px !important; }
            .order-number, .draft-name { font-size: 16px !important; }
            .order-tracking { font-size: 12px !important; }
            .order-details, .draft-details { grid-template-columns: 1fr 1fr !important; gap: 10px !important; }
            .order-detail-label { font-size: 11px !important; }
            .order-details div div:last-child, .draft-details div div:last-child { font-size: 13px !important; }
            .order-price { font-size: 16px !important; }
            .badge { font-size: 10px !important; padding: 3px 8px !important; }
            .btn-sm { padding: 5px 12px !important; font-size: 11px !important; }
            .data-table th, .data-table td { padding: 8px 10px !important; font-size: 12px !important; }
            .table-responsive { overflow-x: auto !important; }
            .add-form { padding: 15px !important; }
            .add-form h3 { font-size: 16px !important; margin-bottom: 12px !important; }
            .form-grid { grid-template-columns: 1fr !important; gap: 10px !important; }
            .address-card, .route-card { padding: 12px 15px !important; flex-direction: column !important; text-align: center !important; }
            .address-info h4, .route-info h4 { font-size: 14px !important; flex-wrap: wrap !important; justify-content: center !important; }
            .address-badge { font-size: 9px !important; margin-left: 5px !important; }
            .empty-state { padding: 30px !important; font-size: 14px !important; }
            .stats-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 15px !important; }
            .stat-card { padding: 15px !important; }
            .stat-number { font-size: 28px !important; }
            .stat-label { font-size: 12px !important; }
            .cabinet-main > div[style*="margin-top: 30px"] h3 { font-size: 18px !important; }
            .cabinet-main > div[style*="margin-top: 30px"] .btn { font-size: 13px !important; padding: 8px 16px !important; }
        }
        @media (max-width: 480px) {
            .cabinet-main { padding: 15px !important; }
            .tab-title { font-size: 18px !important; }
            .order-details, .draft-details { grid-template-columns: 1fr !important; }
            .order-header { gap: 8px !important; }
            .order-header > div:first-child { display: flex !important; flex-direction: column !important; align-items: flex-start !important; gap: 5px !important; }
            .stats-grid { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <a href="index.php" class="logo">Транс<span>Логистик</span></a>
            <div class="nav-links">
                <a href="index.php">Главная</a>
                <a href="services.php">Услуги</a>
                <a href="about.php">О нас</a>
                <a href="news.php">Новости</a>
                <a href="reviews.php">Отзывы</a>
                <a href="contacts.php">Контакты</a>
                <a href="cabinet.php" style="color: var(--accent); font-weight: 600;">Личный кабинет</a>
                <a href="logout.php" style="color: #ef4444;">Выйти (<?= htmlspecialchars($_SESSION['user_name']) ?>)</a>
            </div>
        </nav>
    </div>

    <div class="container">
        <div class="breadcrumbs">
            <a href="index.php">Главная</a> / <span>Личный кабинет</span>
        </div>
        
        <div class="cabinet-wrapper">
            <!-- Сайдбар -->
            <div class="cabinet-sidebar">
                <div class="user-card">
                    <div class="user-avatar"><?= mb_substr($user['name'], 0, 1) ?></div>
                    <div class="user-name-sidebar"><?= htmlspecialchars($user['name'] . ' ' . $user['surname']) ?></div>
                    <div class="user-email-sidebar"><?= htmlspecialchars($user['email']) ?></div>
                    <span class="user-role-badge"><?= $user['role'] == 'admin' ? 'Администратор' : 'Клиент' ?></span>
                </div>
                
                <div class="cabinet-nav">
                    <div class="nav-item <?= $active_tab == 'profile' ? 'active' : '' ?>" data-tab="profile"><img src="images/human.png" class="nav-icon" onerror="this.style.display='none'"> Мой профиль</div>
                    <div class="nav-item <?= $active_tab == 'orders' ? 'active' : '' ?>" data-tab="orders"><img src="images/korobka.png" class="nav-icon" onerror="this.style.display='none'"> Мои заказы</div>
                    <div class="nav-item <?= $active_tab == 'drafts' ? 'active' : '' ?>" data-tab="drafts"><img src="images/arm.png" class="nav-icon" onerror="this.style.display='none'"> Черновики</div>
                    <div class="nav-item <?= $active_tab == 'calculations' ? 'active' : '' ?>" data-tab="calculations"><img src="images/feather1.png" class="nav-icon" onerror="this.style.display='none'"> История расчетов</div>
                    <div class="nav-item <?= $active_tab == 'addresses' ? 'active' : '' ?>" data-tab="addresses"><img src="images/punkt.png" class="nav-icon" onerror="this.style.display='none'"> Мои адреса</div>
                    <div class="nav-item <?= $active_tab == 'routes' ? 'active' : '' ?>" data-tab="routes"><img src="images/fav.png" class="nav-icon" onerror="this.style.display='none'"> Избранные маршруты</div>
                    <div class="nav-item <?= $active_tab == 'reviews' ? 'active' : '' ?>" data-tab="reviews"><img src="images/comment.png" class="nav-icon" onerror="this.style.display='none'"> Мои отзывы</div>
                    <div class="nav-item <?= $active_tab == 'feedback' ? 'active' : '' ?>" data-tab="feedback"><img src="images/envelope.png" class="nav-icon" onerror="this.style.display='none'"> Мои заявки</div>
                    <?php if ($is_admin): ?>
                    <div class="nav-item <?= $active_tab == 'admin' ? 'active' : '' ?>" data-tab="admin" style="margin-top: 10px; border-top: 1px solid var(--gray-border); padding-top: 15px;"><img src="images/settings.png" class="nav-icon" onerror="this.style.display='none'"> Панель администратора</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Основной контент -->
            <div class="cabinet-main">
                <!-- Профиль -->
                <div id="tab-profile" class="tab-content <?= $active_tab == 'profile' ? 'active' : '' ?>">
                    <div class="tab-title"><img src="images/human.png" class="title-icon" onerror="this.style.display='none'"> Мой профиль</div>
                    <?php if ($profile_success): ?>
                    <div class="alert-success">✓ Профиль успешно обновлен!</div>
                    <?php endif; ?>
                    <form method="POST" class="profile-form">
                        <div class="form-group"><label>Email</label><input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled></div>
                        <div class="form-group"><label>Имя *</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required></div>
                        <div class="form-group"><label>Фамилия</label><input type="text" name="surname" class="form-control" value="<?= htmlspecialchars($user['surname'] ?? '') ?>"></div>
                        <div class="form-group"><label>Телефон</label><input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>"></div>
                        <div class="form-group">
                            <label>Новый пароль (оставьте пустым, если не хотите менять)</label>
                            <div class="password-wrapper">
                                <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Новый пароль">
                                <button type="button" class="toggle-password" data-target="new_password">🔒</button>
                            </div>
                            <div id="passwordStrength" style="font-size: 12px; margin-top: 5px;"></div>
                        </div>
                        <div class="form-group">
                            <label>Подтверждение пароля</label>
                            <div class="password-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Повторите пароль">
                                <button type="button" class="toggle-password" data-target="confirm_password">🔒</button>
                            </div>
                            <div id="passwordMatch" style="font-size: 12px; margin-top: 5px;"></div>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">Сохранить изменения</button>
                    </form>
                </div>
                
                <!-- Заказы -->
                <div id="tab-orders" class="tab-content <?= $active_tab == 'orders' ? 'active' : '' ?>">
                    <div class="tab-title"><img src="images/korobka.png" class="title-icon" onerror="this.style.display='none'"> Мои заказы</div>
                    <?php if (empty($user_orders)): ?>
                    <div class="empty-state"><p>У вас пока нет заказов</p><a href="index.php" class="btn btn-primary" style="margin-top: 15px;">Рассчитать стоимость</a></div>
                    <?php else: ?>
                        <?php foreach ($user_orders as $order): 
                            $historyStmt = $pdo->prepare("SELECT th.* FROM tracking_history th JOIN tracking t ON t.id = th.tracking_id WHERE t.tracking_number = ? ORDER BY th.created_at DESC");
                            $historyStmt->execute([$order['tracking_number']]);
                            $trackHistory = $historyStmt->fetchAll();
                        ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div><span class="order-number">Заказ №<?= htmlspecialchars($order['order_number']) ?></span>
                                <?php if ($order['tracking_number']): ?>
                                <span class="order-tracking">| Трек: <a href="index.php?track_number=<?= urlencode($order['tracking_number']) ?>" style="color: var(--accent); text-decoration: underline;"><?= htmlspecialchars($order['tracking_number']) ?></a></span>
                                <?php endif; ?>
                                </div>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <span class="badge <?= getOrderStatus($order['status'])['class'] ?>"><?= getOrderStatus($order['status'])['text'] ?></span>
                                    <span class="badge <?= getPaymentStatus($order['payment_status'])['class'] ?>"><?= getPaymentStatus($order['payment_status'])['text'] ?></span>
                                </div>
                            </div>
                            <div class="order-details">
                                <div><div class="order-detail-label">Маршрут</div><div><?= htmlspecialchars($order['from_city']) ?> → <?= htmlspecialchars($order['to_city']) ?></div></div>
                                <div><div class="order-detail-label">Груз</div><div><?= number_format($order['weight'], 0, ',', ' ') ?> кг / <?= number_format($order['volume'], 1, ',', ' ') ?> м³</div></div>
                                <div><div class="order-detail-label">Дата заказа</div><div><?= formatDate($order['created_at']) ?></div></div>
                                <div><div class="order-detail-label">Стоимость</div><div class="order-price"><?= number_format($order['price'], 0, ',', ' ') ?> ₽</div></div>
                            </div>
                            <?php if (!empty($trackHistory)): ?>
                            <div style="margin-top: 15px; padding-top: 12px; border-top: 1px dashed var(--gray-border);">
                                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 10px;">История перемещения:</div>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <?php foreach ($trackHistory as $history): ?>
                                    <div style="font-size: 13px; display: flex; gap: 15px; flex-wrap: wrap;"><span style="color: var(--text-muted); min-width: 100px;"><?= formatDate($history['created_at']) ?></span><span style="font-weight: 600;"><?= htmlspecialchars($history['status']) ?></span><?php if ($history['location']): ?><span>📍 <?= htmlspecialchars($history['location']) ?></span><?php endif; ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div style="margin-top: 15px; display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid var(--gray-border); padding-top: 12px;">
                                <a href="order.php?from_city=<?= urlencode($order['from_city']) ?>&to_city=<?= urlencode($order['to_city']) ?>&weight=<?= $order['weight'] ?>&volume=<?= $order['volume'] ?>&cargo_type=<?= urlencode($order['cargo_type'] ?? 'Обычный') ?>&price=<?= $order['price'] ?>" class="btn btn-primary btn-sm" style="background: var(--accent);">🔄 Повторить заказ</a>
                                <?php if ($order['status'] != 'cancelled' && $order['status'] != 'delivered'): ?>
                                <a href="?tab=orders&cancel_order=<?= $order['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Вы уверены, что хотите отменить заказ №<?= htmlspecialchars($order['order_number']) ?>?')">❌ Отменить заказ</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Черновики ура наконец исправлена логика цены -->
                <div id="tab-drafts" class="tab-content <?= $active_tab == 'drafts' ? 'active' : '' ?>">
                    <div class="tab-title">
                        <div><img src="images/arm.png" class="title-icon" onerror="this.style.display='none'"> Мои черновики</div>
                        <?php if (!empty($user_drafts)): ?>
                        <a href="?clear_all_drafts=1" class="clear-all-btn" onclick="return confirm('Удалить ВСЕ черновики? Это действие нельзя отменить.')">Очистить все</a>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($user_drafts)): ?>
                    <div class="empty-state"><p>У вас нет сохранённых черновиков</p><a href="order.php" class="btn btn-primary" style="margin-top: 15px;">Создать заказ</a></div>
                    <?php else: ?>
                        <?php foreach ($user_drafts as $draft): 
                            $draft_price_cabinet = (float)$draft['price'];
                        ?>
                        <div class="draft-card">
                            <div class="order-header">
                                <div><span class="draft-name">📄 <?= htmlspecialchars($draft['draft_name']) ?></span></div>
                                <div style="display: flex; gap: 8px;"><span class="badge status-pending">Черновик</span></div>
                            </div>
                            <div class="draft-details">
                                <div><div class="order-detail-label">Маршрут</div><div><?= htmlspecialchars($draft['from_city']) ?> → <?= htmlspecialchars($draft['to_city']) ?></div></div>
                                <div><div class="order-detail-label">Груз</div><div><?= number_format($draft['weight'], 0, ',', ' ') ?> кг / <?= number_format($draft['volume'], 1, ',', ' ') ?> м³</div></div>
                                <div><div class="order-detail-label">Тип груза</div><div><?= htmlspecialchars($draft['cargo_type'] ?? 'Обычный') ?></div></div>
                                <div><div class="order-detail-label">Стоимость</div><div class="order-price"><?= number_format($draft_price_cabinet, 0, ',', ' ') ?> ₽</div></div>
                            </div>
                            <div style="margin-top: 15px; font-size: 12px; color: var(--text-muted);">Обновлён: <?= formatDateTime($draft['updated_at'] ?? $draft['created_at']) ?></div>
                            <div style="margin-top: 15px; display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid var(--gray-border); padding-top: 12px;">
                                <a href="order.php?load_draft=<?= $draft['id'] ?>" class="btn btn-primary btn-sm" style="background: var(--accent);">Продолжить оформление</a>
                                <a href="?tab=drafts&delete_draft=<?= $draft['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить черновик?')">Удалить</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- История расчетов -->
                <div id="tab-calculations" class="tab-content <?= $active_tab == 'calculations' ? 'active' : '' ?>">
                    <div class="tab-title"><img src="images/feather1.png" class="title-icon" onerror="this.style.display='none'"> История расчетов</div>
                    <?php if (empty($user_calculations)): ?>
                    <div class="empty-state"><p>Вы еще не делали расчетов</p><a href="index.php" class="btn btn-primary" style="margin-top: 15px;">Рассчитать стоимость</a></div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead><tr><th>Дата</th><th>Маршрут</th><th>Вес</th><th>Объем</th><th>Стоимость</th><th>Комментарий</th></tr></thead>
                            <tbody>
                                <?php foreach ($user_calculations as $calc): ?>
                                <tr><td><?= formatDate($calc['created_at']) ?></td>
                                <td><?= htmlspecialchars($calc['from_city']) ?> → <?= htmlspecialchars($calc['to_city']) ?></td>
                                <td><?= number_format($calc['weight'], 0, ',', ' ') ?> кг</td>
                                <td><?= number_format($calc['volume'], 1, ',', ' ') ?> м³</td>
                                <td><strong><?= number_format($calc['price'], 0, ',', ' ') ?> ₽</strong></td>
                                <td style="max-width: 200px;"><?= htmlspecialchars($calc['comment'] ?? '—') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Адреса -->
                <div id="tab-addresses" class="tab-content <?= $active_tab == 'addresses' ? 'active' : '' ?>">
                    <div class="tab-title">
                        <div><img src="images/punkt.png" class="title-icon" onerror="this.style.display='none'"> Мои адреса</div>
                        <?php if (!empty($user_addresses)): ?>
                        <a href="?clear_all_addresses=1" class="clear-all-btn" onclick="return confirm('Удалить ВСЕ адреса? Это действие нельзя отменить.')">Очистить все</a>
                        <?php endif; ?>
                    </div>
                    <div class="add-form"><h3>+ Добавить новый адрес</h3>
                        <form method="POST"><div class="form-grid"><input type="text" name="city" placeholder="Город *" class="form-control" required>
                        <input type="text" name="street" placeholder="Улица *" class="form-control" required><input type="text" name="house" placeholder="Дом *" class="form-control" required>
                        <input type="text" name="apartment" placeholder="Квартира" class="form-control"><select name="address_type" class="form-control"><option value="home">Домашний</option>
                        <option value="work">Рабочий</option><option value="other">Другой</option></select></div>
                        <button type="submit" name="add_address" class="btn btn-primary" style="margin-top: 15px;">+ Добавить адрес</button></form>
                    </div>
                    <?php if (empty($user_addresses)): ?>
                    <div class="empty-state">У вас нет сохраненных адресов</div>
                    <?php else: ?>
                        <?php foreach ($user_addresses as $address): ?>
                        <div class="address-card"><div class="address-info"><h4><?= htmlspecialchars($address['city']) ?>, <?= htmlspecialchars($address['street']) ?>, д. <?= htmlspecialchars($address['house']) ?><?= $address['apartment'] ? ", кв. {$address['apartment']}" : '' ?><span class="address-badge"><?= $address['address_type'] == 'home' ? 'Домашний' : ($address['address_type'] == 'work' ? 'Рабочий' : 'Другой') ?></span></h4></div><a href="?tab=addresses&delete_address=<?= $address['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить этот адрес?')">🗑 Удалить</a></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Маршруты -->
                <div id="tab-routes" class="tab-content <?= $active_tab == 'routes' ? 'active' : '' ?>">
                    <div class="tab-title">
                        <div><img src="images/fav.png" class="title-icon" onerror="this.style.display='none'"> Избранные маршруты</div>
                        <?php if (!empty($user_routes)): ?>
                        <a href="?clear_all_routes=1" class="clear-all-btn" onclick="return confirm('Удалить ВСЕ маршруты? Это действие нельзя отменить.')">Очистить все</a>
                        <?php endif; ?>
                    </div>
                    <div class="add-form"><h3>+ Добавить маршрут</h3>
                        <form method="POST"><div class="form-grid"><input type="text" name="from_city" placeholder="Откуда *" class="form-control" required><input type="text" name="to_city" placeholder="Куда *" class="form-control" required><button type="submit" name="add_route" class="btn btn-primary">+ Добавить</button></div></form>
                    </div>
                    <?php if (empty($user_routes)): ?>
                    <div class="empty-state">У вас нет избранных маршрутов</div>
                    <?php else: ?>
                        <?php foreach ($user_routes as $route): ?>
                        <div class="route-card"><div class="route-info"><h4>📍 <?= htmlspecialchars($route['from_city']) ?> → <?= htmlspecialchars($route['to_city']) ?></h4><p style="color: var(--text-muted); font-size: 12px;">Добавлен: <?= formatDate($route['created_at']) ?></p></div><div><a href="index.php?from=<?= urlencode($route['from_city']) ?>&to=<?= urlencode($route['to_city']) ?>" class="btn btn-primary btn-sm">Рассчитать</a><a href="?tab=routes&delete_route=<?= $route['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить маршрут?')">Удалить</a></div></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Отзывы -->
                <div id="tab-reviews" class="tab-content <?= $active_tab == 'reviews' ? 'active' : '' ?>">
                    <div class="tab-title">
                        <div><img src="images/comment.png" class="title-icon" onerror="this.style.display='none'"> Мои отзывы</div>
                        <?php if (!empty($user_reviews)): ?>
                        <a href="?clear_all_reviews=1" class="clear-all-btn" onclick="return confirm('Удалить ВСЕ отзывы? Это действие нельзя отменить.')">Очистить все</a>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($user_reviews)): ?>
                    <div class="empty-state"><p>Вы еще не оставляли отзывов</p><a href="reviews.php" class="btn btn-primary" style="margin-top: 15px;">Оставить отзыв</a></div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead><tr><th>Дата</th><th>Оценка</th><th>Отзыв</th><th>Статус</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($user_reviews as $review): ?>
                                <tr><td><?= formatDate($review['created_at']) ?></td>
                                <td><?= getStars($review['rating']) ?></td>
                                <td><?= htmlspecialchars(mb_substr($review['text'], 0, 50)) ?>...</td>
                                <td><span class="badge <?= $review['is_published'] ? 'status-paid' : 'status-pending' ?>"><?= $review['is_published'] ? '✓ Опубликован' : 'На модерации' ?></span></td>
                                <td><a href="?tab=reviews&delete_review=<?= $review['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить отзыв?')">Удалить</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Заявки -->
                <div id="tab-feedback" class="tab-content <?= $active_tab == 'feedback' ? 'active' : '' ?>">
                    <div class="tab-title">
                        <div><img src="images/envelope.png" class="title-icon" onerror="this.style.display='none'"> Мои заявки</div>
                        <?php if (!empty($user_feedback)): ?>
                        <a href="?clear_all_feedback=1" class="clear-all-btn" onclick="return confirm('Удалить ВСЕ заявки? Это действие нельзя отменить.')">Очистить все</a>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($user_feedback)): ?>
                    <div class="empty-state"><p>У вас нет заявок</p><a href="contacts.php" class="btn btn-primary" style="margin-top: 15px;">Связаться с нами</a></div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead><tr><th>Дата</th><th>Имя</th><th>Телефон</th><th>Сообщение</th><th>Статус</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($user_feedback as $fb): ?>
                                <tr><td><?= formatDate($fb['created_at']) ?></td>
                                <td><?= htmlspecialchars($fb['name']) ?></td>
                                <td><?= htmlspecialchars($fb['phone'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(mb_substr($fb['message'], 0, 40)) ?>...</td>
                                <td><span class="badge <?= $fb['is_processed'] ? 'status-paid' : 'status-pending' ?>"><?= $fb['is_processed'] ? '✓ Обработано' : 'Ожидает' ?></span></td>
                                <td><a href="?tab=feedback&delete_feedback=<?= $fb['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить заявку?')">Удалить</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Админ панель на всякий-->
                <?php if ($is_admin): ?>
                <div id="tab-admin" class="tab-content <?= $active_tab == 'admin' ? 'active' : '' ?>">
                    <div class="tab-title"><img src="images/settings.png" class="title-icon" onerror="this.style.display='none'"> Панель администратора</div>
                    <div class="stats-grid">
                        <div class="stat-card"><div class="stat-number"><?= $totalUsers ?></div><div class="stat-label">Пользователей</div></div>
                        <div class="stat-card"><div class="stat-number"><?= $totalOrders ?></div><div class="stat-label">Заказов</div></div>
                        <div class="stat-card"><div class="stat-number"><?= $totalFeedback ?></div><div class="stat-label">Заявок</div></div>
                        <div class="stat-card"><div class="stat-number"><?= $pendingReviews ?></div><div class="stat-label">На модерации</div></div>
                    </div>
                    <div style="margin-top: 30px;"><h3 style="color: var(--primary); margin-bottom: 20px;">Быстрые действия</h3><div style="display: flex; gap: 15px; flex-wrap: wrap;"><a href="admin/feedback.php" class="btn btn-primary">Управление заявками</a><a href="admin/reviews.php" class="btn btn-primary">Модерация отзывов</a><a href="admin/users.php" class="btn btn-primary">Управление пользователями</a><a href="admin/orders.php" class="btn btn-primary">Управление заказами</a></div></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div><div class="footer-logo" style="font-size: 24px; font-weight: 700; margin-bottom: 16px;">Транс<span style="color: var(--accent);">Логистик</span></div><p style="color: #cbd5e1;">© 2026 Транспортная компания.<br>Все права защищены.</p></div>
                <div class="footer-links"><h4>Навигация</h4><a href="index.php">Главная</a><a href="services.php">Услуги</a><a href="about.php">О компании</a></div>
                <div class="footer-links"><h4>Инфо</h4><a href="reviews.php">Отзывы</a><a href="contacts.php">Контакты</a><a href="cabinet.php">Личный кабинет</a></div>
                <div><h4>Контакты</h4><p style="color: #cbd5e1;">г. Ярославль, ул. Строителей, 5</p><p style="color: #cbd5e1;">+7 (4852) 00-00-00</p></div>
            </div>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const navItems = document.querySelectorAll('.nav-item');
        const tabs = document.querySelectorAll('.tab-content');
        
        function switchTab(tabId) {
            tabs.forEach(tab => tab.classList.remove('active'));
            const activeTab = document.getElementById('tab-' + tabId);
            if (activeTab) activeTab.classList.add('active');
            navItems.forEach(item => {
                if (item.getAttribute('data-tab') === tabId) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);
        }
        
        navItems.forEach(item => {
            item.addEventListener('click', function(e) {
                const tabId = this.getAttribute('data-tab');
                if (tabId) {
                    e.preventDefault();
                    switchTab(tabId);
                }
            });
        });
        
        window.addEventListener('popstate', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabId = urlParams.get('tab') || 'profile';
            switchTab(tabId);
        });
        
        // Функция для переключения видимости пароля
        const toggleButtons = document.querySelectorAll('.toggle-password');
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                if (input) {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.textContent = type === 'password' ? '🔒' : '🔓';
                }
            });
        });
        
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordStrength = document.getElementById('passwordStrength');
        const passwordMatch = document.getElementById('passwordMatch');
        
        if (newPassword) {
            newPassword.addEventListener('input', function() {
                const pwd = this.value;
                if (pwd.length === 0) {
                    passwordStrength.innerHTML = '';
                } else if (pwd.length < 6) {
                    passwordStrength.innerHTML = '<span style="color:#ef4444;">🔴 Слабый (минимум 6 символов)</span>';
                } else if (!/(?=.*[a-z])/.test(pwd)) {
                    passwordStrength.innerHTML = '<span style="color:#ef4444;">🔴 Добавьте строчную букву</span>';
                } else if (!/(?=.*[A-Z])/.test(pwd)) {
                    passwordStrength.innerHTML = '<span style="color:#f59e0b;">🟡 Добавьте заглавную букву</span>';
                } else if (!/(?=.*\d)/.test(pwd)) {
                    passwordStrength.innerHTML = '<span style="color:#f59e0b;">🟡 Добавьте цифру</span>';
                } else if (pwd.length >= 8) {
                    passwordStrength.innerHTML = '<span style="color:#22c55e;">🟢 Сильный пароль</span>';
                } else {
                    passwordStrength.innerHTML = '<span style="color:#22c55e;">🟢 Хороший пароль</span>';
                }
                checkPasswordMatch();
            });
            confirmPassword?.addEventListener('input', checkPasswordMatch);
        }
        
        function checkPasswordMatch() {
            if (!confirmPassword) return;
            const pwd = newPassword?.value || '';
            const confirm = confirmPassword.value;
            if (confirm.length === 0) {
                passwordMatch.innerHTML = '';
            } else if (pwd === confirm) {
                passwordMatch.innerHTML = '<span style="color:#22c55e;">✓ Пароли совпадают</span>';
            } else {
                passwordMatch.innerHTML = '<span style="color:#ef4444;">✗ Пароли не совпадают</span>';
            }
        }
    });
    </script>
</body>
</html>