<?php
session_start();
require_once 'config/config.php';

//данные авторизованного пользователя
$userData = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT name, surname, email, phone FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
}

//настройки сайта
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Получаем статус из сессии для показа сообщения после редиректа
$success = isset($_SESSION['contact_success']) ? true : false;
$error = isset($_SESSION['contact_error']) ? true : false;

// Очищаем сессию после получения
if ($success) unset($_SESSION['contact_success']);
if ($error) unset($_SESSION['contact_error']);

// обработка формы обратной связи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_contact'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $message = trim($_POST['message']);
    
    $errors = [];
    
    if (empty($name) || strlen($name) < 2) {
        $errors[] = 'name';
    }
    
    if (empty($email)) {
        $errors[] = 'email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'email';
    }
    
    if (empty($phone)) {
        $errors[] = 'phone';
    } else {
        $phone_clean = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen($phone_clean) < 10) {
            $errors[] = 'phone';
        }
    }
    
    if (empty($message) || strlen($message) < 10) {
        $errors[] = 'message';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO feedback (name, phone, email, message, page_url) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $email, $message, 'Контакты']);
            $_SESSION['contact_success'] = true;
            header('Location: contacts.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['contact_error'] = true;
            header('Location: contacts.php');
            exit;
        }
    } else {
        $_SESSION['contact_error'] = true;
        // Сохраняем введенные данные для восстановления после редиректа
        $_SESSION['post_data'] = $_POST;
        header('Location: contacts.php');
        exit;
    }
}

// Восстанавливаем ПОСТ данные после ошибки
if (isset($_SESSION['post_data'])) {
    $_POST = $_SESSION['post_data'];
    unset($_SESSION['post_data']);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Контакты — ТрансЛогистик</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .contact-card {
            background: white;
            border-radius: 24px;
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-border);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 30px -10px rgba(0,0,0,0.15);
        }
        .info-item {
            display: flex;
            gap: 15px;
            align-items: flex-start;
            padding: 20px;
            background: var(--gray-bg);
            border-radius: 20px;
            transition: background 0.3s ease;
        }
        .info-item:hover {
            background: #eef2f6;
        }
        .info-icon {
            width: 48px;
            height: 48px;
            background: var(--primary);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .info-icon img {
            width: 28px;
            height: 28px;
            filter: brightness(0) invert(1);
        }
        .department-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            border: 1px solid var(--gray-border);
            transition: all 0.3s ease;
        }
        .department-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent);
            box-shadow: 0 15px 25px -8px rgba(245,158,11,0.2);
        }
        .department-icon {
            width: 70px;
            height: 70px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            transition: transform 0.3s ease;
        }
        .department-card:hover .department-icon {
            transform: scale(1.1);
            background: var(--accent);
        }
        .department-icon img {
            width: 40px;
            height: 40px;
            filter: brightness(0) invert(1);
        }
        .success-message {
            background: #22c55e;
            color: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            animation: slideUp 0.4s ease;
        }
        .error-message-global {
            background: #ef4444;
            color: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            animation: slideUp 0.4s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .contact-form-header {
            background: linear-gradient(145deg, var(--primary), var(--primary-light));
            padding: 30px;
            color: white;
            border-radius: 24px 24px 0 0;
        }
        
        .contact-form-body {
            padding: 30px;
        }
        
        .contact-form .form-group {
            margin-bottom: 24px;
        }
        
        .contact-form .form-group input,
        .contact-form .form-group textarea {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid var(--gray-border);
            border-radius: 16px;
            font-size: 15px;
            transition: all 0.3s;
            background: white;
            font-family: inherit;
        }
        
        .contact-form .form-group input:focus,
        .contact-form .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(245,158,11,0.1);
        }
        
        .contact-form .form-group input.invalid,
        .contact-form .form-group textarea.invalid {
            border-color: #ef4444;
            background: #fef2f2;
        }
        
        .contact-form .form-group input.valid,
        .contact-form .form-group textarea.valid {
            border-color: #22c55e;
            background: #f0fdf4;
        }
        
        .error-message {
            color: #ef4444;
            font-size: 12px;
            margin-top: 6px;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .welcome-banner .hand-gif {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }
        
        .welcome-banner .text {
            flex: 1;
        }
        
        .welcome-banner .text strong {
            font-size: 18px;
        }
        
        .welcome-banner .text p {
            margin: 5px 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .required-note {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 20px;
        }
        
        .btn-loading {
            opacity: 0.7;
            cursor: wait;
        }
        
        .map-wrapper {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            border: 1px solid var(--gray-border);
        }
        
        @media (max-width: 768px) {
            .contact-form-header h3 {
                font-size: 24px;
            }
            .contact-form-body {
                padding: 20px;
            }
            .welcome-banner {
                padding: 15px 20px;
            }
            .welcome-banner .hand-gif {
                width: 45px;
                height: 45px;
            }
        }

        /* =====ДОП АДАПТАЦИЯ===== */

/* Общие фиксы для двухколоночной сетки */
@media (max-width: 768px) {
    .two-columns {
        grid-template-columns: 1fr !important;
        gap: 30px !important;
    }
    
    /* Левая колонка с картой и адресом */
    .content-block {
        width: 100% !important;
        overflow-x: hidden !important;
    }
    
    /* Карточка контактов */
    .contact-card {
        padding: 20px !important;
    }
    
    /* Заголовки */
    .contact-card h2 {
        font-size: 22px !important;
    }
    
    /* Сетка адрес/режим работы */
    .contact-card > div[style*="display: grid; grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
        gap: 20px !important;
    }
    
    .contact-card > div[style*="display: grid; grid-template-columns: 1fr 1fr"] > div {
        padding: 0 !important;
    }
    
    /* Блок телефоны и email */
    .contact-card > div[style*="margin-top: 30px"] h3 {
        font-size: 16px !important;
    }
    
    .contact-card > div[style*="margin-top: 30px"] > div[style*="display: grid"] {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
    }
    
    /* Карта */
    .map-wrapper iframe {
        height: 250px !important;
    }
    
    /*форма*/
    .sidebar {
        padding: 0 !important;
        overflow: hidden !important;
    }
    
    /* Заголовок формы */
    .contact-form-header {
        padding: 20px !important;
    }
    
    .contact-form-header h3 {
        font-size: 22px !important;
    }
    
    .contact-form-header p {
        font-size: 13px !important;
    }
    
    /* Тело формы */
    .contact-form-body {
        padding: 20px !important;
    }
    
    /* Поля ввода */
    .contact-form .form-group input,
    .contact-form .form-group textarea {
        padding: 12px 14px !important;
        font-size: 14px !important;
    }
    
    /* Кнопка */
    .contact-form .btn {
        padding: 12px !important;
        font-size: 14px !important;
    }
    
    /* Реквизиты */
    .contact-form-body > div[style*="margin-top: 30px; background: var(--gray-bg)"] {
        padding: 15px !important;
    }
    
    .contact-form-body > div[style*="margin-top: 30px"] h4 {
        font-size: 16px !important;
    }
    
    .contact-form-body > div[style*="margin-top: 30px"] > div[style*="display: grid"] {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
    }
}

/* Блок "Наши отделы" 3 колонки в 1 */
@media (max-width: 768px) {
    .container > div[style*="margin: 60px 0"] > div[style*="display: grid"] {
        grid-template-columns: 1fr !important;
        gap: 20px !important;
    }
    
    .container > div[style*="margin: 60px 0"] h2 {
        font-size: 24px !important;
        margin-bottom: 25px !important;
    }
    
    .department-card {
        padding: 20px !important;
    }
    
    .department-icon {
        width: 55px !important;
        height: 55px !important;
    }
    
    .department-icon img {
        width: 30px !important;
        height: 30px !important;
    }
    
    .department-card h4 {
        font-size: 18px !important;
    }
    
    .department-card p {
        font-size: 13px !important;
    }
}

/*320px*/
@media (max-width: 480px) {
    .contact-card {
        padding: 15px !important;
    }
    
    .info-item {
        padding: 15px !important;
    }
    
    .info-icon {
        width: 36px !important;
        height: 36px !important;
    }
    
    .info-icon img {
        width: 20px !important;
        height: 20px !important;
    }
    
    .info-item div p {
        font-size: 13px !important;
    }
    
    .contact-form-body {
        padding: 15px !important;
    }
    
    .welcome-banner {
        padding: 12px !important;
    }
    
    .welcome-banner .hand-gif {
        width: 35px !important;
        height: 35px !important;
    }
    
    .welcome-banner .text strong {
        font-size: 13px !important;
    }
    
    .welcome-banner .text p {
        font-size: 11px !important;
    }
    
    .required-note {
        font-size: 12px !important;
    }
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
                <a href="contacts.php" style="color: var(--accent);">Контакты</a>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="cabinet.php" style="font-weight: 600;">Личный кабинет</a>
                    <a href="logout.php" style="color: #ef4444; font-weight: 600;">
                        Выйти (<?= htmlspecialchars($_SESSION['user_name']) ?>)
                    </a>
                <?php else: ?>
                    <a href="login.php" style="color: var(--accent); font-weight: 600;">
                        Вход / Регистрация
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </div>

    <div class="container">
        <div class="breadcrumbs">
            <a href="index.php">Главная</a> / <span>Контакты</span>
        </div>
        
        <h1 class="page-title">Контакты</h1>
        
        <div class="two-columns">
            <!-- Левая колонка -->
            <div class="content-block">
                <div class="contact-card">
                    <h2 style="color: var(--primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <img src="images/map-icon.png" alt="" style="width: 32px; height: 32px;" onerror="this.style.display='none'">
                        Как нас найти
                    </h2>
                    
                    <div class="map-wrapper">
                        <iframe 
                            src="https://yandex.ru/map-widget/v1/?from=mapframe&ll=39.776799%2C57.698991&mode=search&ol=geo&ouri=ymapsbm1%3A%2F%2Fgeo%3Fdata%3DCgg1NzkwNzA4NxJE0KDQvtGB0YHQuNGPLCDQr9GA0L7RgdC70LDQstC70YwsINGD0LvQuNGG0LAg0KHRgtGA0L7QuNGC0LXQu9C10LksIDUiCg1xGx9CFcTLZkI%2C&source=mapframe&z=16.2" 
                            width="100%" 
                            height="350" 
                            frameborder="0" 
                            allowfullscreen="true" 
                            style="display: block;">
                        </iframe>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin-top: 10px;">
                        <div style="padding-right: 20px;">
                            <div style="display: flex; gap: 15px;">
                                <div class="info-icon">
                                    <img src="images/7.png" alt="">
                                </div>
                                <div>
                                    <h3 style="color: var(--primary); font-size: 18px; margin-bottom: 10px;">Адрес</h3>
                                    <p style="color: var(--text-dark); line-height: 1.6;">
                                        <?= nl2br(htmlspecialchars($settings['company_address'] ?? 'г. Ярославль, ул. Строителей, 5')) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div style="padding-left: 20px;">
                            <div style="display: flex; gap: 15px;">
                                <div class="info-icon">
                                    <img src="images/5.png" alt="">
                                </div>
                                <div>
                                    <h3 style="color: var(--primary); font-size: 18px; margin-bottom: 10px;">Режим работы</h3>
                                    <p style="color: var(--text-dark); line-height: 1.6;">
                                        <?= nl2br(htmlspecialchars($settings['work_hours'] ?? 'Пн-Пт: 9:00 – 20:00<br>Сб: 10:00 – 16:00<br>Вс: выходной')) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; padding-top: 30px; border-top: 1px solid var(--gray-border);">
                        <h3 style="color: var(--primary); font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                            <img src="images/phone-icon.png" alt="" style="width: 24px; height: 24px;" onerror="this.style.display='none'">
                            Телефоны и email
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                            <div class="info-item">
                                <div class="info-icon" style="width: 40px; height: 40px; border-radius: 12px;">
                                    <img src="images/phone.png" alt="">
                                </div>
                                <div>
                                    <p style="font-weight: 600; color: var(--primary); font-size: 14px;">Отдел перевозок</p>
                                    <p style="color: var(--text-dark);"><?= htmlspecialchars($settings['company_phone'] ?? '+7 (4852) 00-00-00') ?></p>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon" style="width: 40px; height: 40px; border-radius: 12px;">
                                    <img src="images/email.png" alt="">
                                </div>
                                <div>
                                    <p style="font-weight: 600; color: var(--primary); font-size: 14px;">Эл. почта</p>
                                    <p style="color: var(--text-dark);"><?= htmlspecialchars($settings['company_email'] ?? 'info@translogistic.ru') ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Правая колонка -->
            <div class="sidebar" style="background: white; border: 1px solid var(--gray-border); border-radius: 24px; overflow: hidden;">
                <div class="contact-form-header">
                    <h3 style="color: white; margin-bottom: 10px; font-size: 28px;">Напишите нам</h3>
                    <p style="color: rgba(255,255,255,0.9);">Заполните форму и мы свяжемся с вами в ближайшее время</p>
                </div>
                
                <div class="contact-form-body">
                    <?php if ($success): ?>
                    <div class="success-message">
                        ✓ Сообщение отправлено! Мы ответим вам в ближайшее время.
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error && !$success): ?>
                    <div class="error-message-global">
                        Ошибка при отправке. Проверьте правильность заполнения полей.
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($userData) && !empty($userData['name'])): ?>
                    <div class="welcome-banner">
                        <img src="images/hand.gif" alt="👋" class="hand-gif">
                        <div class="text">
                            <strong>Здравствуйте, <?= htmlspecialchars($userData['name']) ?>!</strong>
                            <p>Ваши контактные данные автоматически подставлены из личного кабинета</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="contact-form" id="contactForm">
                        <div class="form-group">
                            <input type="text" name="name" id="contactName" placeholder="Ваше имя *" 
                                   value="<?= htmlspecialchars($_POST['name'] ?? ($userData['name'] ?? '')) ?>">
                            <div class="error-message" id="nameError">Введите имя (минимум 2 символа)</div>
                        </div>
                        
                        <div class="form-group">
                            <input type="email" name="email" id="contactEmail" placeholder="E-mail *" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? ($userData['email'] ?? '')) ?>">
                            <div class="error-message" id="emailError">Введите корректный email</div>
                        </div>
                        
                        <div class="form-group">
                            <input type="tel" name="phone" id="contactPhone" placeholder="Телефон *" 
                                   value="<?= htmlspecialchars($_POST['phone'] ?? ($userData['phone'] ?? '')) ?>">
                            <div class="error-message" id="phoneError">Введите корректный телефон (минимум 10 цифр)</div>
                        </div>
                        
                        <div class="form-group">
                            <textarea name="message" id="contactMessage" rows="4" placeholder="Ваше сообщение *"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                            <div class="error-message" id="messageError">Напишите сообщение (минимум 10 символов)</div>
                        </div>
                        
                        <div class="required-note">
                            * — обязательные поля
                        </div>
                        
                        <button type="submit" name="send_contact" class="btn" id="submitBtn" style="width: 100%;" disabled>Отправить сообщение</button>
                    </form>
                    
                    <div style="margin-top: 30px; background: var(--gray-bg); border-radius: 20px; padding: 20px;">
                        <h4 style="color: var(--primary); margin-bottom: 15px;">Реквизиты компании</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 14px;">
                            <div>
                                <p style="color: var(--text-muted); margin-bottom: 5px;">ООО «ТрансЛогистик»</p>
                                <p style="color: var(--text-muted);">ИНН 7604123456</p>
                            </div>
                            <div>
                                <p style="color: var(--text-muted);">КПП 760401001</p>
                                <p style="color: var(--text-muted);">ОГРН 1107604000123</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Блок с контактами отделов -->
        <div style="margin: 60px 0;">
            <h2 style="color: var(--primary); font-size: 32px; text-align: center; margin-bottom: 40px;">Наши отделы</h2>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px;">
                <div class="department-card">
                    <div class="department-icon">
                        <img src="images/8.png" alt="">
                    </div>
                    <h4 style="color: var(--primary); font-size: 22px; margin-bottom: 10px;">Отдел перевозок</h4>
                    <p style="color: var(--text-muted); margin-bottom: 8px;"><?= htmlspecialchars($settings['company_phone'] ?? '+7 (4852) 00-00-00') ?></p>
                    <p style="color: var(--accent); font-weight: 600;">perevozki@translogistic.ru</p>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed var(--gray-border);">
                        <p style="color: var(--text-muted); font-size: 14px;">Консультации по доставке</p>
                    </div>
                </div>
                
                <div class="department-card">
                    <div class="department-icon">
                        <img src="images/4.1.png" alt="">
                    </div>
                    <h4 style="color: var(--primary); font-size: 22px; margin-bottom: 10px;">Экспедирование</h4>
                    <p style="color: var(--text-muted); margin-bottom: 8px;">+7 (4852) 00-00-02</p>
                    <p style="color: var(--accent); font-weight: 600;">exped@translogistic.ru</p>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed var(--gray-border);">
                        <p style="color: var(--text-muted); font-size: 14px;">Сопровождение и документы</p>
                    </div>
                </div>
                
                <div class="department-card">
                    <div class="department-icon">
                        <img src="images/feather.png" alt="">
                    </div>
                    <h4 style="color: var(--primary); font-size: 22px; margin-bottom: 10px;">Бухгалтерия</h4>
                    <p style="color: var(--text-muted); margin-bottom: 8px;">+7 (4852) 00-00-03</p>
                    <p style="color: var(--accent); font-weight: 600;">buh@translogistic.ru</p>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed var(--gray-border);">
                        <p style="color: var(--text-muted); font-size: 14px;">Документооборот и оплаты</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div class="logo" style="color: white; margin-bottom: 16px;">Транс<span style="color: var(--accent);">Логистик</span></div>
                    <p style="color: #cbd5e1;">© 2026 Транспортная компания.<br>Все права защищены.</p>
                </div>
                <div class="footer-links">
                    <h4 style="color: white;">Навигация</h4>
                    <a href="index.php">Главная</a>
                    <a href="services.php">Услуги</a>
                    <a href="about.php">О компании</a>
                    <a href="news.php">Новости</a>
                </div>
                <div class="footer-links">
                    <h4 style="color: white;">Инфо</h4>
                    <a href="reviews.php">Отзывы</a>
                    <a href="contacts.php">Контакты</a>
                    <a href="login.php">Авторизация</a>
                </div>
                <div>
                    <h4 style="color: white;">Контакты</h4>
                    <p style="color: #cbd5e1;"><?= htmlspecialchars($settings['company_address'] ?? 'г. Ярославль, ул. Строителей, 5') ?></p>
                    <p style="color: #cbd5e1;"><?= htmlspecialchars($settings['company_phone'] ?? '+7 (4852) 00-00-00') ?></p>
                    <p style="color: #cbd5e1;"><?= htmlspecialchars($settings['company_email'] ?? 'info@translogistic.ru') ?></p>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Ждём пока вся страница загрузится (DOM Document Object Model те структура страницы)
    document.addEventListener('DOMContentLoaded', function() {

    // все поля по айди
        const nameInput = document.getElementById('contactName');
        const emailInput = document.getElementById('contactEmail');
        const phoneInput = document.getElementById('contactPhone');
        const messageInput = document.getElementById('contactMessage');
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('contactForm');
        // проверка имени
        function validateName() {
            const name = nameInput?.value.trim() || '';
            const errorDiv = document.getElementById('nameError');
            // имя пустое или короче 2 символов
            if (name.length === 0 || name.length < 2) {
                errorDiv.classList.add('show');
                nameInput.classList.add('invalid');
                nameInput.classList.remove('valid');
                return false;
                // проверка левых символов
            } else if (!/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u.test(name)) {
                errorDiv.textContent = 'Имя может содержать только буквы';
                errorDiv.classList.add('show');
                nameInput.classList.add('invalid');
                nameInput.classList.remove('valid');
                return false;
                // имя коррект
            } else {
                errorDiv.textContent = 'Введите имя (минимум 2 символа)';
                errorDiv.classList.remove('show');
                nameInput.classList.remove('invalid');
                nameInput.classList.add('valid');
                return true;
            }
        }
        // проверка маил
        function validateEmail() {
            const email = emailInput?.value.trim() || '';
            // шаблон
            const errorDiv = document.getElementById('emailError');
            const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            // если пустой или не тот шаблон
            if (email.length === 0 || !emailPattern.test(email)) {
                errorDiv.classList.add('show');
                emailInput.classList.add('invalid');
                emailInput.classList.remove('valid');
                return false;
            } else {
                errorDiv.classList.remove('show');
                emailInput.classList.remove('invalid');
                emailInput.classList.add('valid');
                return true;
            }
        }
        // телефон
        function validatePhone() {
            const phone = phoneInput?.value.trim() || '';
            const errorDiv = document.getElementById('phoneError');
            // пустой телефон
            if (phone.length === 0) {
                errorDiv.textContent = 'Введите номер телефона';
                errorDiv.classList.add('show');
                phoneInput.classList.add('invalid');
                phoneInput.classList.remove('valid');
                return false;
            }
            // цифры +
            const phoneClean = phone.replace(/[^0-9+]/g, '');
            if (phoneClean.length < 10) {
                errorDiv.textContent = 'Введите корректный номер (минимум 10 цифр)';
                errorDiv.classList.add('show');
                phoneInput.classList.add('invalid');
                phoneInput.classList.remove('valid');
                return false;
                // если короче 10 символов
            } else {
                errorDiv.classList.remove('show');
                phoneInput.classList.remove('invalid');
                phoneInput.classList.add('valid');
                return true;
            }
        }
        // сообщение
        function validateMessage() {
            const message = messageInput?.value.trim() || '';
            const errorDiv = document.getElementById('messageError');
            // если пустое или короче
            if (message.length === 0 || message.length < 10) {
                errorDiv.classList.add('show');
                messageInput.classList.add('invalid');
                messageInput.classList.remove('valid');
                return false;
            } else {
                errorDiv.classList.remove('show');
                messageInput.classList.remove('invalid');
                messageInput.classList.add('valid');
                return true;
            }
        }
        //проверка формы общ
        // включает выключает отправить
        function checkFormValidity() {
            const isValid = validateName() && validateEmail() && validatePhone() && validateMessage();
            if (submitBtn) submitBtn.disabled = !isValid;
            return isValid;
        }
        // от повторной отправки
        let isSubmitting = false;
        
        form?.addEventListener('submit', function(e) {
            if (!checkFormValidity()) {
                e.preventDefault();
                return false;
            }
            //бллокировка повторной отправки
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            // пошла отправка
            isSubmitting = true;
            // анимка отправка
            submitBtn.textContent = 'Отправка...';
            submitBtn.classList.add('btn-loading');
        });
        // слушатели событий
        // проверяем события в кждом поле
        nameInput?.addEventListener('input', checkFormValidity);
        emailInput?.addEventListener('input', checkFormValidity);
        phoneInput?.addEventListener('input', checkFormValidity);
        messageInput?.addEventListener('input', checkFormValidity);
        // 0,1 проверка после загрузки если уже заполнены
        setTimeout(checkFormValidity, 100);
    });
    </script>
</body>
</html>