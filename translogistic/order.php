<?php
session_start();
require_once 'config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=order');
    exit;
}

$user_id = $_SESSION['user_id'];

//данные пользователя
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// последний заказ пользователя 
$lastOrderStmt = $pdo->prepare("
    SELECT receiver_name, receiver_phone, delivery_address, receiver_email 
    FROM orders 
    WHERE user_id = ? AND receiver_name IS NOT NULL 
    ORDER BY created_at DESC 
    LIMIT 1
");
$lastOrderStmt->execute([$user_id]);
$lastOrder = $lastOrderStmt->fetch();

if (isset($_GET['load_draft'])) {
    $draft_id = (int)$_GET['load_draft'];
    $loadStmt = $pdo->prepare("SELECT * FROM drafts WHERE id = ? AND user_id = ?");
    $loadStmt->execute([$draft_id, $user_id]);
    $draft = $loadStmt->fetch();
    
    if ($draft) {
        $draftData = json_decode($draft['draft_data'], true);
        
        // Берем базовую цену (без доп услуг)
        $base_price = (float)($draftData['base_price'] ?? $draft['base_price'] ?? $draft['price'] ?? 0);
        
        $newOrderData = [
            'from_city' => $draftData['from_city'] ?? $draft['from_city'],
            'to_city' => $draftData['to_city'] ?? $draft['to_city'],
            'weight' => (float)($draftData['weight'] ?? $draft['weight']),
            'volume' => (float)($draftData['volume'] ?? $draft['volume']),
            'cargo_type' => $draftData['cargo_type'] ?? $draft['cargo_type'],
            'price' => $base_price,
            'base_price' => $base_price
        ];
        
        $_SESSION['order_data_cache'] = $newOrderData;
        $_SESSION['draft_post_data'] = $draftData;
        
        header("Location: order.php");
        exit;
    }
}

// удаление черновика
if (isset($_GET['delete_draft'])) {
    $draft_id = (int)$_GET['delete_draft'];
    $deleteStmt = $pdo->prepare("DELETE FROM drafts WHERE id = ? AND user_id = ?");
    $deleteStmt->execute([$draft_id, $user_id]);
    header("Location: order.php");
    exit;
}

// восстанавливаем пост данные из сессии после загрузки черновика
if (isset($_SESSION['draft_post_data'])) {
    $_POST = $_SESSION['draft_post_data'];
    unset($_SESSION['draft_post_data']);
}

//черновики пользователя
$draftsStmt = $pdo->prepare("SELECT * FROM drafts WHERE user_id = ? ORDER BY updated_at DESC");
$draftsStmt->execute([$user_id]);
$user_drafts = $draftsStmt->fetchAll();

//данные из сессии или гет параметров
if (isset($_GET['from_city'])) {
    $orderData = [
        'from_city' => $_GET['from_city'],
        'to_city' => $_GET['to_city'],
        'weight' => (float)$_GET['weight'],
        'volume' => (float)$_GET['volume'],
        'cargo_type' => $_GET['cargo_type'] ?? 'Обычный',
        'price' => (float)$_GET['price']
    ];
} elseif (isset($_SESSION['order_data'])) {
    $orderData = $_SESSION['order_data'];
    unset($_SESSION['order_data']);
} elseif (isset($_SESSION['order_data_cache'])) {
    $orderData = $_SESSION['order_data_cache'];
} else {
    header('Location: index.php');
    exit;
}

$_SESSION['order_data_cache'] = $orderData;

function calculateDistance($from, $to) {
    $distances = [
        'Москва-Ярославль' => 260,
        'Ярославль-Москва' => 260,
        'Ярославль-Санкт-Петербург' => 760,
        'Санкт-Петербург-Ярославль' => 760,
        'Ярославль-Казань' => 680,
        'Казань-Ярославль' => 680,
        'Москва-Казань' => 820,
        'Казань-Москва' => 820,
        'Ярославль-Вологда' => 200,
        'Вологда-Ярославль' => 200,
    ];
    $key = $from . '-' . $to;
    return $distances[$key] ?? 300;
}

$distance = calculateDistance($orderData['from_city'], $orderData['to_city']);
$order_success = null;
$error = null;
$draft_saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Сохранение черновика
if (isset($_POST['save_draft_db'])) {
    $draft_name = trim($_POST['draft_name'] ?? 'Черновик');
    
    // Вычисляем итоговую цену с учетом упаковки и страховки
    $final_price = $orderData['price'];
    if (isset($_POST['packaging'])) {
        $final_price += 500;
    }
    if (isset($_POST['insurance']) && isset($_POST['declared_value']) && $_POST['declared_value'] > 0) {
        $final_price += (float)$_POST['declared_value'] * 0.01;
    }
    
    $draft_data = [
        'from_city' => $_POST['from_city'] ?? $orderData['from_city'],
        'to_city' => $_POST['to_city'] ?? $orderData['to_city'],
        'weight' => (float)($_POST['weight'] ?? $orderData['weight']),
        'volume' => (float)($_POST['volume'] ?? $orderData['volume']),
        'cargo_type' => $_POST['cargo_type'] ?? $orderData['cargo_type'],
        'price' => $final_price, 
        'base_price' => $orderData['price'],  
        'sender_name' => $_POST['sender_name'] ?? '',
        'sender_phone' => $_POST['sender_phone'] ?? '',
        'sender_address' => $_POST['sender_address'] ?? '',
        'receiver_name' => $_POST['receiver_name'] ?? '',
        'receiver_phone' => $_POST['receiver_phone'] ?? '',
        'receiver_address' => $_POST['receiver_address'] ?? '',
        'receiver_email' => $_POST['receiver_email'] ?? '',
        'receiver_comment' => $_POST['receiver_comment'] ?? '',
        'cargo_description' => $_POST['cargo_description'] ?? '',
        'comment' => $_POST['comment'] ?? '',
        'packaging' => isset($_POST['packaging']) ? 'on' : '',
        'insurance' => isset($_POST['insurance']) ? 'on' : '',
        'declared_value' => $_POST['declared_value'] ?? 0,
        'pickup_date' => $_POST['pickup_date'] ?? '',
        'payment_method' => $_POST['payment_method'] ?? 'card',
        'who_pays' => $_POST['who_pays'] ?? 'sender',
        'payer_name' => $_POST['payer_name'] ?? '',
        'payer_inn' => $_POST['payer_inn'] ?? '',
        'payer_email' => $_POST['payer_email'] ?? '',
        'agree_terms' => isset($_POST['agree_terms']) ? 'on' : '',
        'agree_offers' => isset($_POST['agree_offers']) ? 'on' : ''
    ];
        
        $draft_json = json_encode($draft_data, JSON_UNESCAPED_UNICODE);
        
        $insertStmt = $pdo->prepare("INSERT INTO drafts (user_id, draft_name, draft_data, from_city, to_city, weight, volume, cargo_type, price, base_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insertStmt->execute([
            $user_id, 
            $draft_name, 
            $draft_json, 
            $draft_data['from_city'], 
            $draft_data['to_city'], 
            (float)$draft_data['weight'], 
            (float)$draft_data['volume'], 
            $draft_data['cargo_type'], 
            (float)$draft_data['price'],
            (float)$draft_data['base_price']
        ]);
        
        $draft_saved = true;
        $draftsStmt = $pdo->prepare("SELECT * FROM drafts WHERE user_id = ? ORDER BY updated_at DESC");
        $draftsStmt->execute([$user_id]);
        $user_drafts = $draftsStmt->fetchAll();
    }
    
    // оформление заказа
    if (isset($_POST['submit_order'])) {
        
        // Валидация полей
        $validation_errors = [];
        
        // Валидация имени отправителя
        $sender_name = trim($_POST['sender_name'] ?? '');
        if (empty($sender_name)) {
            $validation_errors['sender_name'] = 'Укажите ФИО отправителя';
        } elseif (strlen($sender_name) < 2) {
            $validation_errors['sender_name'] = 'Имя отправителя должно быть не менее 2 символов';
        } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $sender_name)) {
            $validation_errors['sender_name'] = 'Имя отправителя может содержать только буквы';
        }
        
        // Валидация телефона отправителя
        $sender_phone = trim($_POST['sender_phone'] ?? '');
        if (!empty($sender_phone)) {
            $phone_clean = preg_replace('/[^0-9+]/', '', $sender_phone);
            if (strlen($phone_clean) < 10) {
                $validation_errors['sender_phone'] = 'Введите корректный номер телефона (минимум 10 цифр)';
            }
        }
        
        // Валидация адреса отправления
        $sender_address = trim($_POST['sender_address'] ?? '');
        if (empty($sender_address)) {
            $validation_errors['sender_address'] = 'Укажите адрес отправления';
        } elseif (strlen($sender_address) < 5) {
            $validation_errors['sender_address'] = 'Адрес должен быть не менее 5 символов';
        }
        
        // Валидация имени получателя
        $receiver_name = trim($_POST['receiver_name'] ?? '');
        if (empty($receiver_name)) {
            $validation_errors['receiver_name'] = 'Укажите ФИО получателя';
        } elseif (strlen($receiver_name) < 2) {
            $validation_errors['receiver_name'] = 'Имя получателя должно быть не менее 2 символов';
        } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $receiver_name)) {
            $validation_errors['receiver_name'] = 'Имя получателя может содержать только буквы';
        }
        
        // Валидация телефона получателя
        $receiver_phone = trim($_POST['receiver_phone'] ?? '');
        if (empty($receiver_phone)) {
            $validation_errors['receiver_phone'] = 'Укажите телефон получателя';
        } else {
            $phone_clean = preg_replace('/[^0-9+]/', '', $receiver_phone);
            if (strlen($phone_clean) < 10) {
                $validation_errors['receiver_phone'] = 'Введите корректный номер телефона (минимум 10 цифр)';
            }
        }
        
        // Валидация адреса доставки
        $receiver_address = trim($_POST['receiver_address'] ?? '');
        if (empty($receiver_address)) {
            $validation_errors['receiver_address'] = 'Укажите адрес доставки';
        } elseif (strlen($receiver_address) < 5) {
            $validation_errors['receiver_address'] = 'Адрес должен быть не менее 5 символов';
        }
        
        // Валидация имэйд получателя необязательно но если заполнен проверяем
        $receiver_email = trim($_POST['receiver_email'] ?? '');
        if (!empty($receiver_email) && !filter_var($receiver_email, FILTER_VALIDATE_EMAIL)) {
            $validation_errors['receiver_email'] = 'Введите корректный email получателя';
        }
        
        // Валидация даты забора
        $pickup_date = $_POST['pickup_date'] ?? null;
        if ($pickup_date && $pickup_date < date('Y-m-d')) {
            $validation_errors['pickup_date'] = 'Дата забора груза не может быть в прошлом';
        }
        
        // Валидация согласия с условиями
        $agree_terms = isset($_POST['agree_terms']) ? 1 : 0;
        if (!$agree_terms) {
            $validation_errors['agree_terms'] = 'Необходимо согласиться с условиями оказания услуг';
        }
        
        // Валидация плательщика 
        $who_pays = $_POST['who_pays'] ?? 'sender';
        if ($who_pays !== 'sender') {
            $payer_name = trim($_POST['payer_name'] ?? '');
            if (empty($payer_name)) {
                $validation_errors['payer_name'] = 'Укажите название или ФИО плательщика';
            }
            if ($who_pays === 'legal') {
                $payer_inn = trim($_POST['payer_inn'] ?? '');
                if (empty($payer_inn)) {
                    $validation_errors['payer_inn'] = 'Укажите ИНН плательщика';
                } elseif (!preg_match('/^\d{10}$|^\d{12}$/', $payer_inn)) {
                    $validation_errors['payer_inn'] = 'ИНН должен содержать 10 или 12 цифр';
                }
            }
        }
        
        // если есть ошибки валидации
        if (!empty($validation_errors)) {
            $error = implode("<br>", $validation_errors);
        } else {
            $from_city = $_POST['from_city'];
            $to_city = $_POST['to_city'];
            $weight = (float)$_POST['weight'];
            $volume = (float)$_POST['volume'];
            $cargo_type = $_POST['cargo_type'];
            $base_price = (float)$orderData['price'];
            $declared_value = (float)($_POST['declared_value'] ?? 0);
            $cargo_description = $_POST['cargo_description'] ?? '';
            
            $packaging = isset($_POST['packaging']) ? 500 : 0;
            $insurance_enabled = isset($_POST['insurance']);
            $insurance = ($insurance_enabled && $declared_value > 0) ? ($declared_value * 0.01) : 0;
            
            $payment_method = $_POST['payment_method'] ?? 'card';
            $payer_name = $_POST['payer_name'] ?? '';
            $payer_inn = $_POST['payer_inn'] ?? '';
            $payer_email = $_POST['payer_email'] ?? '';
            $comment = $_POST['comment'] ?? '';
            $agree_offers = isset($_POST['agree_offers']) ? 1 : 0;
            $receiver_comment = $_POST['receiver_comment'] ?? '';
            
            $order_number = 'ORD-' . date('Ymd') . '-' . rand(1000, 9999);
            $tracking_number = 'TR-' . date('Ymd') . '-' . rand(100, 999);
            $total_price = $base_price + $packaging + $insurance;
            
            try {
                $payment_status = 'pending';
                
                $orderStmt = $pdo->prepare("INSERT INTO orders (
                    user_id, order_number, from_city, to_city, weight, volume, cargo_type, 
                    cargo_description, price, tracking_number, status, payment_status,
                    delivery_address, receiver_name, receiver_phone, comment,
                    receiver_email, insurance_amount, pickup_date, payment_method,
                    payer_name, payer_inn, payer_type
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $orderStmt->execute([
                    $user_id, $order_number, $from_city, $to_city, $weight, $volume, $cargo_type,
                    $cargo_description, $total_price, $tracking_number, $payment_status,
                    $receiver_address, $receiver_name, $receiver_phone, $comment . "\n" . $receiver_comment,
                    $receiver_email, $insurance, $pickup_date, $payment_method,
                    $payer_name, $payer_inn, $who_pays
                ]);
                
                $order_id = $pdo->lastInsertId();
                
                $trackStmt = $pdo->prepare("INSERT INTO tracking (
                    tracking_number, status, from_city, to_city, 
                    weight, volume, sender_name, receiver_name, estimated_delivery
                ) VALUES (?, 'Принят', ?, ?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 3 DAY))");
                
                $trackStmt->execute([
                    $tracking_number, $from_city, $to_city, $weight, $volume, $sender_name, $receiver_name
                ]);
                
                $calcStmt = $pdo->prepare("INSERT INTO calculator_history (user_id, from_city, to_city, weight, volume, price) VALUES (?, ?, ?, ?, ?, ?)");
                $calcStmt->execute([$user_id, $from_city, $to_city, $weight, $volume, $total_price]);
                
                $notifyStmt = $pdo->prepare("INSERT INTO notifications (user_id, order_id, title, message, is_read) VALUES (?, ?, 'Заказ создан', ?, 0)");
                $notifyStmt->execute([$user_id, $order_id, "Ваш заказ №{$order_number} успешно создан. Трек-номер: {$tracking_number}. Сумма: " . number_format($total_price, 0, ',', ' ') . " ₽"]);
                
                $order_success = [
                    'order_number' => $order_number,
                    'tracking_number' => $tracking_number,
                    'price' => $total_price,
                    'from_city' => $from_city,
                    'to_city' => $to_city
                ];
                
                $_POST = [];
                
                echo '<script>localStorage.removeItem("orderDraft");</script>';
                
            } catch (PDOException $e) {
                $error = "Ошибка при оформлении заказа: " . $e->getMessage();
            }
        }
    }
}

$addressesStmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC");
$addressesStmt->execute([$user_id]);
$user_addresses = $addressesStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оформление заказа — ТрансЛогистик</title>
    <link rel="stylesheet" href="style.css">
    <style>
    .order-page {
        max-width: 1000px;
        margin: 0 auto;
        padding-bottom: 60px;
    }
    .order-section {
        background: white;
        border-radius: 24px;
        padding: 28px 32px;
        margin-bottom: 24px;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
    }
    .order-section h2 {
        font-size: 20px;
        font-weight: 700;
        color: #0b3b5c;
        margin-bottom: 24px;
        padding-bottom: 12px;
        border-bottom: 2px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .order-section h2 img {
        width: 28px;
        height: 28px;
        object-fit: contain;
    }
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
    .form-group-full {
        grid-column: span 2;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #0f172a;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .label-required::after {
        content: '*';
        color: #ef4444;
        margin-left: 4px;
    }
    .form-control {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 15px;
        transition: all 0.2s;
        font-family: inherit;
    }
    .form-control:focus {
        outline: none;
        border-color: #f59e0b;
        box-shadow: 0 0 0 3px rgba(245,158,11,0.1);
    }
    .form-control.error {
        border-color: #ef4444;
        background: #fef2f2;
    }
    .form-control.valid {
        border-color: #22c55e;
        background: #f0fdf4;
    }
    .field-error {
        color: #ef4444;
        font-size: 12px;
        margin-top: 5px;
        display: none;
    }
    .field-error.show {
        display: block;
    }
    .hint-text {
        font-size: 11px;
        color: #475569;
        margin-top: 6px;
        display: block;
    }
    .address-link {
        font-size: 12px;
        color: #f59e0b;
        margin-top: 8px;
        display: inline-block;
        cursor: pointer;
        text-decoration: none;
    }
    .address-link:hover {
        text-decoration: underline;
    }
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 16px 0;
        padding: 8px 0;
    }
    .checkbox-group input {
        width: 18px;
        height: 18px;
        cursor: pointer;
        margin: 0;
        flex-shrink: 0;
    }
    .checkbox-group label {
        margin: 0;
        cursor: pointer;
        font-weight: normal;
        text-transform: none;
        font-size: 14px;
    }
    .price-block {
        background: linear-gradient(135deg, #0b3b5c 0%, #1e5570 100%);
        color: white;
        border-radius: 24px;
        padding: 28px 32px;
        margin-bottom: 24px;
        text-align: center;
    }
    .price-block .label {
        font-size: 14px;
        opacity: 0.85;
        letter-spacing: 1px;
    }
    .price-block .value {
        font-size: 42px;
        font-weight: 800;
        margin-top: 12px;
    }
    .price-block .note {
        font-size: 12px;
        opacity: 0.7;
        margin-top: 12px;
    }
    .action-buttons {
        display: flex;
        gap: 16px;
        margin-top: 16px;
    }
    .btn {
        padding: 14px 28px;
        border: none;
        border-radius: 40px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        font-family: inherit;
        text-align: center;
        text-decoration: none;
        display: inline-block;
    }
    .btn-primary {
        background: #f59e0b;
        color: white;
        flex: 2;
    }
    .btn-primary:hover {
        background: #d97706;
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(245,158,11,0.2);
    }
    .btn-draft {
        background: #f8fafc;
        color: #0b3b5c;
        border: 2px solid #e2e8f0;
        flex: 1;
    }
    .btn-draft:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
    }
    .radio-group {
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        gap: 24px;
        margin-top: 8px;
        align-items: center;
    }
    .radio-group label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: normal;
        cursor: pointer;
        text-transform: none;
        font-size: 14px;
        margin: 0;
    }
    .radio-group input[type="radio"] {
        width: 18px;
        height: 18px;
        margin: 0;
        cursor: pointer;
        flex-shrink: 0;
    }
    .distance-info {
        background: #f1f5f9;
        border-radius: 12px;
        padding: 12px 16px;
        margin-top: 16px;
        font-size: 13px;
        color: #475569;
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    .error-alert {
        background: #fee2e2;
        border: 1px solid #ef4444;
        color: #dc2626;
        padding: 16px 20px;
        border-radius: 16px;
        margin-bottom: 24px;
        font-size: 14px;
    }
    .success-container {
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        color: white;
        border-radius: 24px;
        padding: 48px 32px;
        margin-bottom: 30px;
        text-align: center;
        animation: slideDown 0.5s ease;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .success-icon {
        width: 80px;
        height: 80px;
        background: white;
        color: #22c55e;
        font-size: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }
    .success-container .order-number {
        font-size: 28px;
        font-weight: 800;
        margin: 15px 0;
    }
    .success-container .tracking-number {
        background: rgba(255,255,255,0.2);
        padding: 10px 20px;
        border-radius: 40px;
        font-family: monospace;
        font-size: 18px;
        margin: 15px auto;
        display: inline-block;
    }
    .new-order-btn {
        background: white;
        color: #f59e0b;
        margin-top: 20px;
    }
    .payer-info {
        background: #f8fafc;
        border-radius: 16px;
        padding: 16px;
        margin-top: 16px;
    }
    .drafts-section {
        background: #fef9e6;
        border-color: #f59e0b;
    }
    .draft-item {
        background: white;
        border-radius: 12px;
        padding: 12px 15px;
        border: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .draft-info strong {
        display: block;
        color: #0b3b5c;
    }
    .draft-info small {
        font-size: 12px;
        color: #64748b;
    }
    .draft-actions {
        display: flex;
        gap: 8px;
    }
    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
        border-radius: 20px;
    }
    .btn-danger {
        background: #ef4444;
        color: white;
    }
    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr; }
        .form-group-full { grid-column: span 1; }
        .action-buttons { flex-direction: column; }
        .radio-group { flex-direction: column; gap: 12px; align-items: flex-start; }
        .order-section { padding: 20px; }
        .price-block .value { font-size: 28px; }
        .draft-item { flex-direction: column; text-align: center; }
        .checkbox-group { align-items: flex-start; }
        .checkbox-group input { margin-top: 2px; }
        .order-page { padding-bottom: 40px !important; }
        .page-title { font-size: 24px !important; margin-bottom: 20px !important; }
        .order-section { padding: 20px !important; margin-bottom: 20px !important; }
        .order-section h2 { font-size: 18px !important; }
        .price-block { padding: 20px !important; }
        .form-grid { grid-template-columns: 1fr !important; gap: 15px !important; }
        .form-control { padding: 10px 14px !important; font-size: 14px !important; }
        .btn { padding: 12px 20px !important; font-size: 14px !important; width: 100% !important; }
        .success-container { padding: 30px 20px !important; }
        .success-icon { width: 60px !important; height: 60px !important; font-size: 32px !important; }
    }
    @media (max-width: 480px) {
        .order-section { padding: 15px !important; }
        .price-block .value { font-size: 24px !important; }
        .form-control { font-size: 13px !important; padding: 8px 12px !important; }
        .btn { font-size: 13px !important; padding: 10px 16px !important; }
    }
    .radio-group {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: wrap !important;
        gap: 20px !important;
        align-items: center !important;
        margin-top: 8px !important;
    }
    .radio-group label {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        cursor: pointer !important;
        font-weight: normal !important;
        font-size: 14px !important;
        margin: 0 !important;
        white-space: nowrap !important;
    }
    .radio-group input[type="radio"] {
        width: 18px !important;
        height: 18px !important;
        margin: 0 !important;
        padding: 0 !important;
        cursor: pointer !important;
        flex-shrink: 0 !important;
    }
    @media (max-width: 768px) {
        .radio-group {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 12px !important;
        }
        .radio-group label {
            white-space: normal !important;
        }
    }
    .logo, .logo:hover, .logo:focus, .logo:active {
        text-decoration: none !important;
        border-bottom: none !important;
        outline: none !important;
    }
    </style>
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <a href="index.php" class="logo" style="text-decoration: none;">Транс<span>Логистик</span></a>
            <div class="nav-links">
                <a href="index.php">Главная</a>
                <a href="services.php">Услуги</a>
                <a href="about.php">О нас</a>
                <a href="news.php">Новости</a>
                <a href="reviews.php">Отзывы</a>
                <a href="contacts.php">Контакты</a>
                <a href="cabinet.php">Личный кабинет</a>
                <a href="logout.php" style="color: #ef4444;">Выйти</a>
            </div>
        </nav>
    </div>

    <div class="container order-page">
        <div class="breadcrumbs">
            <a href="index.php">Главная</a> / <a href="index.php#calculator">Калькулятор</a> / <span>Оформление заказа</span>
        </div>
        
        <h1 class="page-title">Оформление заказа</h1>
        
        <?php if ($draft_saved): ?>
        <div style="background: #22c55e; color: white; padding: 15px; border-radius: 16px; margin-bottom: 20px; text-align: center;">
            Черновик сохранён!
        </div>
        <?php endif; ?>
        
       <?php if ($order_success): ?>
<div class="success-container">
    <div class="success-icon">✓</div>
    <h2 style="color: white;">Заказ успешно оформлен!</h2>
    <div class="order-number">№ <?= htmlspecialchars($order_success['order_number']) ?></div>
    <div class="tracking-number">
        Трек-номер: 
        <a href="index.php?track_number=<?= urlencode($order_success['tracking_number']) ?>" 
           style="color: white; text-decoration: underline; font-weight: bold;">
            <?= htmlspecialchars($order_success['tracking_number']) ?>
        </a>
    </div>
    <div style="margin: 15px 0;">
        <?= htmlspecialchars($order_success['from_city']) ?> → <?= htmlspecialchars($order_success['to_city']) ?><br>
        <?= number_format($order_success['price'], 0, ',', ' ') ?> ₽
    </div>
    <p style="opacity: 0.9; font-size: 14px;">
        Уведомление отправлено в <a href="cabinet.php" style="color: white; text-decoration: underline;">личный кабинет</a>.<br>
        Отслеживать статус можно там же или по <strong>ссылке выше</strong>.
    </p>
    <a href="index.php?<?= http_build_query($orderData) ?>" class="btn btn-primary new-order-btn" style="background: white; color: #f59e0b;">➕ Оформить новый заказ</a>
</div>
<?php endif; ?>
        
        <?php if (!$order_success): ?>
        
        <?php if ($error): ?>
        <div class="error-alert">⚠️ <?= $error ?></div>
        <?php endif; ?>
        
      <?php if (!empty($user_drafts)): ?>
<div class="order-section drafts-section">
    <h2><img src="images/feather1.png" alt="Черновики"> Мои черновики</h2>
    <div style="display: flex; flex-direction: column; gap: 10px;">
        <?php foreach ($user_drafts as $draft): 
            //полная цена с учётом всех услуг
            $draft_price_total = (float)$draft['price'];
        ?>
        <div class="draft-item">
            <div class="draft-info">
                <strong><?= htmlspecialchars($draft['draft_name']) ?></strong>
                <small><?= htmlspecialchars($draft['from_city']) ?> → <?= htmlspecialchars($draft['to_city']) ?> | <?= number_format($draft_price_total, 0, ',', ' ') ?> ₽</small>
            </div>
            <div class="draft-actions">
                <a href="?load_draft=<?= $draft['id'] ?>" class="btn btn-primary btn-sm" style="background: #f59e0b;">Загрузить</a>
                <a href="?delete_draft=<?= $draft['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить черновик?')">Удалить</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
        
        <form method="POST" id="orderForm">
            <input type="hidden" id="draft_name_input" name="draft_name" value="Черновик">
            
            <div class="price-block">
                <div class="label">ИТОГОВАЯ СТОИМОСТЬ ПЕРЕВОЗКИ</div>
                <div class="value">
                    <span id="totalPriceDisplay"><?= number_format($orderData['price'] ?? 0, 0, ',', ' ') ?></span> ₽
                </div>
                <input type="hidden" id="priceInput" name="price" value="<?= number_format($orderData['price'] ?? 0, 0, ',', ' ') ?> ₽">
                <input type="hidden" id="basePriceValue" value="<?= $orderData['price'] ?? 0 ?>">
                <div class="note">* Окончательная стоимость может измениться после проверки груза</div>
            </div>
            
            <div class="order-section">
                <h2><img src="images/4.png" alt="Маршрут"> Маршрут и груз</h2>
                <div class="form-grid">
                    <div class="form-group"><label class="label-required">Откуда</label><input type="text" name="from_city" class="form-control" value="<?= htmlspecialchars($orderData['from_city'] ?? '') ?>" readonly></div>
                    <div class="form-group"><label class="label-required">Куда</label><input type="text" name="to_city" class="form-control" value="<?= htmlspecialchars($orderData['to_city'] ?? '') ?>" readonly></div>
                    <div class="form-group"><label class="label-required">Вес (кг)</label><input type="number" name="weight" class="form-control" value="<?= htmlspecialchars($orderData['weight'] ?? 0) ?>" readonly></div>
                    <div class="form-group"><label class="label-required">Объём (м³)</label><input type="number" name="volume" class="form-control" value="<?= htmlspecialchars($orderData['volume'] ?? 0) ?>" readonly></div>
                    <div class="form-group"><label class="label-required">Тип груза</label><input type="text" name="cargo_type" class="form-control" value="<?= htmlspecialchars($orderData['cargo_type'] ?? 'Обычный') ?>" readonly></div>
                    <div class="form-group-full"><label>Описание груза</label><textarea name="cargo_description" class="form-control" rows="2" placeholder="Что перевозите? Габариты, особенности..."><?= htmlspecialchars($_POST['cargo_description'] ?? '') ?></textarea></div>
                </div>
                <div class="distance-info"><span>📏 Расстояние: <?= $distance ?> км</span><span>⏱ Время в пути: ~<?= round($distance / 60) ?> часов</span></div>
            </div>
            
            <div class="order-section">
                <h2><img src="images/korobka.png" alt="Услуги"> Дополнительные услуги</h2>
                <div class="checkbox-group">
                    <input type="checkbox" name="packaging" id="packaging" <?= (isset($_POST['packaging']) && $_POST['packaging'] == 'on') ? 'checked' : '' ?>>
                    <label for="packaging">Заказать упаковку и паллетирование (+500 ₽)</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="insurance" id="insurance" <?= (isset($_POST['insurance']) && $_POST['insurance'] == 'on') ? 'checked' : '' ?>>
                    <label for="insurance">Застраховать груз (1% от объявленной стоимости)</label>
                </div>
                <div id="insuranceBlock" style="display: <?= (isset($_POST['insurance']) && $_POST['insurance'] == 'on') ? 'block' : 'none' ?>; margin-top: 16px; padding: 16px; background: #f8fafc; border-radius: 16px;">
                    <div class="form-group">
                        <label>Объявленная стоимость (₽)</label>
                        <input type="number" name="declared_value" id="declared_value" class="form-control" placeholder="Например: 50000" value="<?= htmlspecialchars($_POST['declared_value'] ?? 0) ?>">
                        <span class="hint-text">Страховка составит 1% от этой суммы</span>
                    </div>
                </div>
            </div>
            
            <div class="order-section">
                <h2><img src="images/human.png" alt="Отправитель"> Отправитель</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="label-required">ФИО или наименование</label>
                        <input type="text" name="sender_name" id="sender_name" class="form-control" value="<?= htmlspecialchars($_POST['sender_name'] ?? trim($user['name'] . ' ' . ($user['surname'] ?? ''))) ?>" required>
                        <div class="field-error" id="sender_name_error"></div>
                    </div>
                    <div class="form-group">
                        <label>Телефон</label>
                        <input type="tel" name="sender_phone" id="sender_phone" class="form-control" value="<?= htmlspecialchars($_POST['sender_phone'] ?? $user['phone'] ?? '') ?>" placeholder="+7 (___) ___-__-__">
                        <span class="hint-text">Для связи по вопросам забора груза</span>
                        <div class="field-error" id="sender_phone_error"></div>
                    </div>
                    <div class="form-group-full">
                        <label class="label-required">Адрес отправления</label>
                        <input type="text" name="sender_address" id="sender_address" class="form-control" value="<?= htmlspecialchars($_POST['sender_address'] ?? '') ?>" placeholder="г. Ярославль, ул. Свободы, д. 15" required>
                        <div class="field-error" id="sender_address_error"></div>
                        <?php if (!empty($user_addresses)): ?><a href="#" class="address-link" onclick="showAddressModal('sender_address'); return false;">Выбрать из сохраненных адресов</a><?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="order-section">
                <h2><img src="images/human2.png" alt="Получатель"> Получатель</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="label-required">ФИО или наименование</label>
                        <input type="text" name="receiver_name" id="receiver_name" class="form-control" value="<?= htmlspecialchars($_POST['receiver_name'] ?? ($lastOrder['receiver_name'] ?? '')) ?>" required>
                        <div class="field-error" id="receiver_name_error"></div>
                    </div>
                    <div class="form-group">
                        <label class="label-required">Телефон получателя</label>
                        <input type="tel" name="receiver_phone" id="receiver_phone" class="form-control" value="<?= htmlspecialchars($_POST['receiver_phone'] ?? ($lastOrder['receiver_phone'] ?? '')) ?>" required placeholder="+7 (___) ___-__-__">
                        <span class="hint-text">Обязательно! Для связи курьера при доставке</span>
                        <div class="field-error" id="receiver_phone_error"></div>
                    </div>
                    <div class="form-group-full">
                        <label class="label-required">Адрес доставки</label>
                        <input type="text" name="receiver_address" id="receiver_address" class="form-control" value="<?= htmlspecialchars($_POST['receiver_address'] ?? ($lastOrder['delivery_address'] ?? '')) ?>" placeholder="г. Ярославль, ул. Свободы, д. 15" required>
                        <div class="field-error" id="receiver_address_error"></div>
                        <?php if (!empty($user_addresses)): ?><a href="#" class="address-link" onclick="showAddressModal('receiver_address'); return false;">Выбрать из сохраненных адресов</a><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Email получателя</label>
                        <input type="email" name="receiver_email" id="receiver_email" class="form-control" value="<?= htmlspecialchars($_POST['receiver_email'] ?? ($lastOrder['receiver_email'] ?? '')) ?>" placeholder="email@example.com">
                        <span class="hint-text">Необязательно. Для уведомлений о статусе</span>
                        <div class="field-error" id="receiver_email_error"></div>
                    </div>
                    <div class="form-group">
                        <label>Комментарий для курьера</label>
                        <input type="text" name="receiver_comment" class="form-control" value="<?= htmlspecialchars($_POST['receiver_comment'] ?? '') ?>" placeholder="Код домофона, этаж, ориентиры...">
                    </div>
                </div>
            </div>
            
            <div class="order-section">
                <h2><img src="images/calendar.png" alt="Дата"> Дата и оплата</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Желаемая дата забора груза</label>
                        <input type="date" name="pickup_date" id="pickup_date" class="form-control" value="<?= htmlspecialchars($_POST['pickup_date'] ?? '') ?>" min="<?= date('Y-m-d') ?>">
                        <div class="field-error" id="pickup_date_error"></div>
                    </div>
                    <div class="form-group">
                        <label>Способ оплаты</label>
                        <div class="radio-group">
                            <label><input type="radio" name="payment_method" value="card" <?= ($_POST['payment_method'] ?? 'card') == 'card' ? 'checked' : '' ?>> Карта онлайн</label>
                            <label><input type="radio" name="payment_method" value="cash" <?= ($_POST['payment_method'] ?? '') == 'cash' ? 'checked' : '' ?>> Наличные курьеру</label>
                            <label><input type="radio" name="payment_method" value="bank" <?= ($_POST['payment_method'] ?? '') == 'bank' ? 'checked' : '' ?>> Безналичный расчет</label>
                        </div>
                    </div>
                </div>
                
                <h2 style="margin-top: 24px;"><img src="images/card.png" alt="Плательщик"> Кто платит?</h2>
                <div class="radio-group">
                    <label><input type="radio" name="who_pays" value="sender" <?= ($_POST['who_pays'] ?? 'sender') == 'sender' ? 'checked' : '' ?> class="who-pays-radio"> Отправитель (я)</label>
                    <label><input type="radio" name="who_pays" value="receiver" <?= ($_POST['who_pays'] ?? '') == 'receiver' ? 'checked' : '' ?> class="who-pays-radio"> Получатель</label>
                    <label><input type="radio" name="who_pays" value="legal" <?= ($_POST['who_pays'] ?? '') == 'legal' ? 'checked' : '' ?> class="who-pays-radio"> Юридическое лицо / компания</label>
                </div>
                
                <div id="payerBlock" style="display: <?= ($_POST['who_pays'] ?? 'sender') != 'sender' ? 'block' : 'none' ?>;" class="payer-info">
                    <div class="form-group">
                        <label>Название или ФИО плательщика</label>
                        <input type="text" name="payer_name" id="payer_name" class="form-control" value="<?= htmlspecialchars($_POST['payer_name'] ?? '') ?>" placeholder="ООО Ромашка или Иванов Иван">
                        <div class="field-error" id="payer_name_error"></div>
                    </div>
                    <div class="form-group" id="innField" style="display: <?= ($_POST['who_pays'] ?? '') == 'legal' ? 'block' : 'none' ?>;">
                        <label>ИНН плательщика</label>
                        <input type="text" name="payer_inn" id="payer_inn" class="form-control" value="<?= htmlspecialchars($_POST['payer_inn'] ?? '') ?>" placeholder="ИНН организации">
                        <div class="field-error" id="payer_inn_error"></div>
                    </div>
                    <div class="form-group">
                        <label>Email для отправки чека</label>
                        <input type="email" name="payer_email" class="form-control" value="<?= htmlspecialchars($_POST['payer_email'] ?? '') ?>" placeholder="finance@company.ru">
                    </div>
                </div>
            </div>
            
            <div class="order-section">
                <h2><img src="images/feather1.png" alt="Комментарий"> Комментарий к заказу</h2>
                <div class="form-group-full"><textarea name="comment" class="form-control" rows="3" placeholder="Любая дополнительная информация..."><?= htmlspecialchars($_POST['comment'] ?? '') ?></textarea></div>
            </div>
            
            <div class="order-section">
                <div class="checkbox-group">
                    <input type="checkbox" name="agree_terms" id="agree_terms" <?= isset($_POST['agree_terms']) ? 'checked' : '' ?> required>
                    <label for="agree_terms">Я согласен с <a href="#" style="color: #f59e0b;">условиями оказания услуг</a> и <a href="#" style="color: #f59e0b;">политикой обработки персональных данных</a> *</label>
                </div>
                <div class="field-error" id="agree_terms_error"></div>
                <div class="checkbox-group">
                    <input type="checkbox" name="agree_offers" id="agree_offers" <?= isset($_POST['agree_offers']) ? 'checked' : '' ?>>
                    <label for="agree_offers">📧 Получать новости и специальные акции</label>
                </div>
            </div>
            
            <div class="action-buttons">
                <button type="submit" name="submit_order" class="btn btn-primary"> Оформить заказ</button>
                <button type="button" class="btn btn-draft" onclick="showSaveDraftModal()"> Сохранить черновик</button>
            </div>
        </form>
        
        <?php endif; ?>
    </div>
    
    <div id="saveDraftModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1001; align-items: center; justify-content: center;">
        <div style="background: white; max-width: 400px; width: 90%; border-radius: 24px; padding: 25px;">
            <h3 style="color: #0b3b5c; margin-bottom: 15px;"> Сохранить черновик</h3>
            <div class="form-group">
                <label>Название черновика</label>
                <input type="text" id="modal_draft_name" class="form-control" placeholder="Например: Доставка мебели" value="Черновик">
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button onclick="saveDraftToDB()" class="btn btn-primary" style="flex: 1;">Сохранить</button>
                <button onclick="closeSaveDraftModal()" class="btn btn-draft" style="flex: 1;">Отмена</button>
            </div>
        </div>
    </div>
    
    <div id="addressModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; max-width: 500px; width: 90%; border-radius: 24px; padding: 25px;">
            <h3 style="color: #0b3b5c; margin-bottom: 15px;">Выберите адрес</h3>
            <div id="addressList" style="max-height: 400px; overflow-y: auto;">
                <?php foreach ($user_addresses as $addr): ?>
                <div style="padding: 12px; border-bottom: 1px solid #e2e8f0; cursor: pointer;" onclick="selectAddress('<?= htmlspecialchars($addr['city'] . ', ' . $addr['street'] . ', д. ' . $addr['house'] . ($addr['apartment'] ? ', кв. ' . $addr['apartment'] : '')) ?>')">
                    <?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['street']) ?>, д. <?= htmlspecialchars($addr['house']) ?>
                    <?= $addr['apartment'] ? ", кв. {$addr['apartment']}" : '' ?>
                    <?php if ($addr['is_default']): ?> <span style="color: #f59e0b; font-size: 11px;">(основной)</span><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <button onclick="closeModal()" class="btn btn-primary" style="width: 100%; margin-top: 15px;">Закрыть</button>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div><div class="footer-logo" style="font-size: 24px; font-weight: 700; margin-bottom: 16px;">Транс<span style="color: #f59e0b;">Логистик</span></div><p style="color: #cbd5e1;">© 2026 Транспортная компания.<br>Все права защищены.</p></div>
                <div class="footer-links"><h4>Навигация</h4><a href="index.php">Главная</a><a href="services.php">Услуги</a><a href="about.php">О компании</a><a href="news.php">Новости</a></div>
                <div class="footer-links"><h4>Инфо</h4><a href="reviews.php">Отзывы</a><a href="contacts.php">Контакты</a><a href="cabinet.php">Личный кабинет</a></div>
                <div><h4>Контакты</h4><p style="color: #cbd5e1;">г. Ярославль, ул. Строителей, 5</p><p style="color: #cbd5e1;">+7 (4852) 00-00-00</p><p style="color: #cbd5e1;">info@translogistic.ru</p></div>
            </div>
        </div>
    </footer>

 <script>
    let currentField = null;
    
    function showAddressModal(fieldId) { currentField = fieldId; document.getElementById('addressModal').style.display = 'flex'; }
    function selectAddress(address) { if (currentField) { document.getElementById(currentField).value = address; validateAddress(currentField); } closeModal(); }
    function closeModal() { document.getElementById('addressModal').style.display = 'none'; currentField = null; }
    function validateAddress(fieldId) { const input = document.getElementById(fieldId); const address = input.value.trim(); if (address.length > 10 && /[а-яА-Я0-9]/.test(address)) { input.classList.remove('error'); input.classList.add('valid'); return true; } else { input.classList.remove('valid'); input.classList.add('error'); return false; } }
    
    // Базовая цена из скрытого поля без учета доп услуг
    let basePrice = parseFloat(document.getElementById('basePriceValue').value) || 0;

    function recalcTotalPrice() {
        let packaging = document.getElementById('packaging')?.checked ? 500 : 0;
        let insurance = 0;
        if (document.getElementById('insurance')?.checked) {
            let declaredValue = parseFloat(document.getElementById('declared_value')?.value) || 0;
            insurance = declaredValue * 0.01;
        }
        let total = basePrice + packaging + insurance;
        document.getElementById('totalPriceDisplay').innerText = total.toLocaleString('ru-RU');
        document.getElementById('priceInput').value = total.toLocaleString('ru-RU') + ' ₽';
    }
    
    function showSaveDraftModal() {
        document.getElementById('saveDraftModal').style.display = 'flex';
    }
    
    function closeSaveDraftModal() {
        document.getElementById('saveDraftModal').style.display = 'none';
    }
    
    function saveDraftToDB() {
        recalcTotalPrice();
        
        const draftName = document.getElementById('modal_draft_name').value.trim();
        if (!draftName) {
            alert('Введите название черновика');
            return;
        }
        
        const formData = new FormData(document.getElementById('orderForm'));
        formData.append('save_draft_db', '1');
        formData.append('draft_name', draftName);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        }).then(response => {
            if (response.ok) {
                alert(' Черновик "' + draftName + '" сохранён!');
                closeSaveDraftModal();
                location.reload();
            } else {
                alert(' Ошибка при сохранении');
            }
        }).catch(() => {
            alert(' Ошибка при сохранении');
        });
    }
    
    const insuranceCheckbox = document.getElementById('insurance');
    const insuranceBlock = document.getElementById('insuranceBlock');
    const declaredValueInput = document.getElementById('declared_value');
    
    function toggleInsuranceBlock() {
        if (insuranceCheckbox && insuranceCheckbox.checked) {
            insuranceBlock.style.display = 'block';
        } else {
            insuranceBlock.style.display = 'none';
            if (declaredValueInput) declaredValueInput.value = 0;
        }
        recalcTotalPrice();
    }
    
    if (insuranceCheckbox) {
        insuranceCheckbox.addEventListener('change', toggleInsuranceBlock);
        insuranceCheckbox.addEventListener('change', recalcTotalPrice);
    }
    if (declaredValueInput) declaredValueInput.addEventListener('input', recalcTotalPrice);
    
    const packagingCheckbox = document.getElementById('packaging');
    if (packagingCheckbox) packagingCheckbox.addEventListener('change', recalcTotalPrice);
    
    const whoPaysRadios = document.querySelectorAll('.who-pays-radio');
    const payerBlock = document.getElementById('payerBlock');
    const innField = document.getElementById('innField');
    
    function togglePayerBlock() {
        const selected = document.querySelector('input[name="who_pays"]:checked')?.value;
        const payerNameInput = document.getElementById('payer_name');
        const receiverNameInput = document.getElementById('receiver_name');
        const receiverEmailInput = document.getElementById('receiver_email');
        const payerEmailInput = document.querySelector('input[name="payer_email"]');
        
        if (selected && selected !== 'sender') {
            payerBlock.style.display = 'block';
            innField.style.display = selected === 'legal' ? 'block' : 'none';
            
            // Автозаполнение при выборе получатель
            if (selected === 'receiver') {
                if (receiverNameInput && receiverNameInput.value.trim()) {
                    payerNameInput.value = receiverNameInput.value.trim();
                }
                if (receiverEmailInput && receiverEmailInput.value.trim()) {
                    payerEmailInput.value = receiverEmailInput.value.trim();
                }
            }
            
            // Для юридического лица подставляем название из получателя
            if (selected === 'legal') {
                if (receiverNameInput && receiverNameInput.value.trim()) {
                    payerNameInput.value = receiverNameInput.value.trim();
                }
            }
        } else {
            payerBlock.style.display = 'none';
        }
    }
    
    // Добавляем слушалки для радиокнопок
    if (whoPaysRadios.length) {
        whoPaysRadios.forEach(radio => radio.addEventListener('change', togglePayerBlock));
        togglePayerBlock(); // Вызываем для установки начального состояния
    }
    
    // Обновление полей плательщика при изменении данных получателя
    const receiverNameInput = document.getElementById('receiver_name');
    const receiverEmailInput = document.getElementById('receiver_email');
    
    function updatePayerFromReceiver() {
        const selected = document.querySelector('input[name="who_pays"]:checked')?.value;
        if (selected === 'receiver') {
            const payerNameInput = document.getElementById('payer_name');
            const payerEmailInput = document.querySelector('input[name="payer_email"]');
            if (receiverNameInput && receiverNameInput.value.trim()) {
                payerNameInput.value = receiverNameInput.value.trim();
            }
            if (receiverEmailInput && receiverEmailInput.value.trim()) {
                payerEmailInput.value = receiverEmailInput.value.trim();
            }
        }
    }
    
    if (receiverNameInput) {
        receiverNameInput.addEventListener('input', updatePayerFromReceiver);
    }
    if (receiverEmailInput) {
        receiverEmailInput.addEventListener('input', updatePayerFromReceiver);
    }
    
    const pickupDateInput = document.getElementById('pickup_date');
    if (pickupDateInput) {
        pickupDateInput.addEventListener('change', function() {
            const selectedDate = this.value;
            const today = new Date().toISOString().split('T')[0];
            if (selectedDate && selectedDate < today) {
                alert('Дата забора груза не может быть в прошлом');
                this.value = today;
            }
        });
    }
    
    // Клиентская валидация перед отправкой
    document.getElementById('orderForm')?.addEventListener('submit', function(e) {
        let isValid = true;
        
        // Валидация имени отправителя
        const senderName = document.getElementById('sender_name')?.value.trim() || '';
        const senderNameError = document.getElementById('sender_name_error');
        if (!senderName || senderName.length < 2) {
            senderNameError.textContent = 'Укажите ФИО отправителя (минимум 2 символа)';
            senderNameError.classList.add('show');
            isValid = false;
        } else if (!/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u.test(senderName)) {
            senderNameError.textContent = 'Имя может содержать только буквы';
            senderNameError.classList.add('show');
            isValid = false;
        } else {
            senderNameError.classList.remove('show');
        }
        
        // Валидация телефона отправителя если заполнен
        const senderPhone = document.getElementById('sender_phone')?.value.trim() || '';
        const senderPhoneError = document.getElementById('sender_phone_error');
        if (senderPhone) {
            const phoneClean = senderPhone.replace(/[^0-9+]/g, '');
            if (phoneClean.length < 10) {
                senderPhoneError.textContent = 'Введите корректный номер (минимум 10 цифр)';
                senderPhoneError.classList.add('show');
                isValid = false;
            } else {
                senderPhoneError.classList.remove('show');
            }
        } else {
            senderPhoneError.classList.remove('show');
        }
        
        // Валидация адреса отправления
        const senderAddress = document.getElementById('sender_address')?.value.trim() || '';
        const senderAddressError = document.getElementById('sender_address_error');
        if (!senderAddress || senderAddress.length < 5) {
            senderAddressError.textContent = 'Укажите адрес отправления (минимум 5 символов)';
            senderAddressError.classList.add('show');
            isValid = false;
        } else {
            senderAddressError.classList.remove('show');
        }
        
        // Валидация имени получателя
        const receiverName = document.getElementById('receiver_name')?.value.trim() || '';
        const receiverNameError = document.getElementById('receiver_name_error');
        if (!receiverName || receiverName.length < 2) {
            receiverNameError.textContent = 'Укажите ФИО получателя (минимум 2 символа)';
            receiverNameError.classList.add('show');
            isValid = false;
        } else if (!/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u.test(receiverName)) {
            receiverNameError.textContent = 'Имя может содержать только буквы';
            receiverNameError.classList.add('show');
            isValid = false;
        } else {
            receiverNameError.classList.remove('show');
        }
        
        // Валидация телефона получателя
        const receiverPhone = document.getElementById('receiver_phone')?.value.trim() || '';
        const receiverPhoneError = document.getElementById('receiver_phone_error');
        if (!receiverPhone) {
            receiverPhoneError.textContent = 'Укажите телефон получателя';
            receiverPhoneError.classList.add('show');
            isValid = false;
        } else {
            const phoneClean = receiverPhone.replace(/[^0-9+]/g, '');
            if (phoneClean.length < 10) {
                receiverPhoneError.textContent = 'Введите корректный номер (минимум 10 цифр)';
                receiverPhoneError.classList.add('show');
                isValid = false;
            } else {
                receiverPhoneError.classList.remove('show');
            }
        }
        
        // Валидация адреса доставки
        const receiverAddress = document.getElementById('receiver_address')?.value.trim() || '';
        const receiverAddressError = document.getElementById('receiver_address_error');
        if (!receiverAddress || receiverAddress.length < 5) {
            receiverAddressError.textContent = 'Укажите адрес доставки (минимум 5 символов)';
            receiverAddressError.classList.add('show');
            isValid = false;
        } else {
            receiverAddressError.classList.remove('show');
        }
        
        // Валидация имэйл получателя если заполн
        const receiverEmail = document.getElementById('receiver_email')?.value.trim() || '';
        const receiverEmailError = document.getElementById('receiver_email_error');
        if (receiverEmail && !/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(receiverEmail)) {
            receiverEmailError.textContent = 'Введите корректный email';
            receiverEmailError.classList.add('show');
            isValid = false;
        } else {
            receiverEmailError.classList.remove('show');
        }
        
        // Валидация даты забора
        const pickupDate = document.getElementById('pickup_date')?.value;
        const pickupDateError = document.getElementById('pickup_date_error');
        if (pickupDate && pickupDate < new Date().toISOString().split('T')[0]) {
            pickupDateError.textContent = 'Дата не может быть в прошлом';
            pickupDateError.classList.add('show');
            isValid = false;
        } else {
            pickupDateError.classList.remove('show');
        }
        
        // Валидация согласия с условиями
        const agreeTerms = document.getElementById('agree_terms')?.checked;
        const agreeTermsError = document.getElementById('agree_terms_error');
        if (!agreeTerms) {
            agreeTermsError.textContent = 'Необходимо согласиться с условиями оказания услуг';
            agreeTermsError.classList.add('show');
            isValid = false;
        } else {
            agreeTermsError.classList.remove('show');
        }
        
        // Валидация плательщика если не отправитель
        const whoPays = document.querySelector('input[name="who_pays"]:checked')?.value;
        const payerName = document.getElementById('payer_name')?.value.trim() || '';
        const payerNameError = document.getElementById('payer_name_error');
        const payerInn = document.getElementById('payer_inn')?.value.trim() || '';
        const payerInnError = document.getElementById('payer_inn_error');
        
        if (whoPays && whoPays !== 'sender') {
            if (!payerName) {
                payerNameError.textContent = 'Укажите название или ФИО плательщика';
                payerNameError.classList.add('show');
                isValid = false;
            } else {
                payerNameError.classList.remove('show');
            }
            if (whoPays === 'legal') {
                if (!payerInn) {
                    payerInnError.textContent = 'Укажите ИНН плательщика';
                    payerInnError.classList.add('show');
                    isValid = false;
                } else if (!/^\d{10}$|^\d{12}$/.test(payerInn)) {
                    payerInnError.textContent = 'ИНН должен содержать 10 или 12 цифр';
                    payerInnError.classList.add('show');
                    isValid = false;
                } else {
                    payerInnError.classList.remove('show');
                }
            }
        } else {
            if (payerNameError) payerNameError.classList.remove('show');
            if (payerInnError) payerInnError.classList.remove('show');
        }
        
        if (!isValid) {
            e.preventDefault();
            alert('Пожалуйста, исправьте ошибки в форме');
        }
    });
    
    function setupAddressAutocomplete(fieldId) {
        const input = document.getElementById(fieldId);
        if (!input) return;
        const streets = ['ул. Свободы', 'пр-т Ленина', 'ул. Кирова', 'ул. Советская', 'ул. Республиканская', 'ул. Некрасова', 'ул. Чайковского', 'ул. Б. Октябрьская', 'ул. Свердлова', 'ул. Трефолева', 'Московский пр-т', 'Ленинградский пр-т', 'ул. Полушкина роща'];
        let suggestionBox = null;
        input.addEventListener('input', function() {
            const value = this.value.toLowerCase();
            if (suggestionBox && suggestionBox.remove) suggestionBox.remove();
            if (value.length < 2) return;
            const suggestions = streets.filter(street => street.toLowerCase().includes(value));
            if (!suggestions.length) return;
            suggestionBox = document.createElement('div');
            suggestionBox.className = 'suggestion-box';
            suggestionBox.style.cssText = 'position: absolute; background: white; border: 1px solid #e2e8f0; border-radius: 12px; max-height: 200px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.1);';
            suggestions.forEach(street => {
                const item = document.createElement('div');
                item.textContent = street;
                item.style.cssText = 'padding: 10px 15px; cursor: pointer; border-bottom: 1px solid #e2e8f0;';
                item.addEventListener('click', function() { input.value = 'г. Ярославль, ' + street; validateAddress(input.id); if (suggestionBox && suggestionBox.remove) suggestionBox.remove(); suggestionBox = null; });
                suggestionBox.appendChild(item);
            });
            const rect = input.getBoundingClientRect();
            suggestionBox.style.top = rect.bottom + window.scrollY + 'px';
            suggestionBox.style.left = rect.left + window.scrollX + 'px';
            suggestionBox.style.width = rect.width + 'px';
            document.body.appendChild(suggestionBox);
        });
        input.addEventListener('blur', function() { setTimeout(() => { if (suggestionBox && suggestionBox.remove) suggestionBox.remove(); suggestionBox = null; }, 200); });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        basePrice = parseFloat(document.getElementById('basePriceValue').value) || 0;
        recalcTotalPrice();
        setupAddressAutocomplete('sender_address');
        setupAddressAutocomplete('receiver_address');
        
        // при загрузке страницы если выбран получатель заполняем поля
        if (document.querySelector('input[name="who_pays"]:checked')?.value === 'receiver') {
            const payerNameInput = document.getElementById('payer_name');
            const receiverNameInputEl = document.getElementById('receiver_name');
            const receiverEmailInputEl = document.getElementById('receiver_email');
            const payerEmailInput = document.querySelector('input[name="payer_email"]');
            
            if (receiverNameInputEl && receiverNameInputEl.value.trim()) {
                payerNameInput.value = receiverNameInputEl.value.trim();
            }
            if (receiverEmailInputEl && receiverEmailInputEl.value.trim()) {
                payerEmailInput.value = receiverEmailInputEl.value.trim();
            }
        }
    });
</script>
</body>
</html>