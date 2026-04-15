<?php
session_start();
require_once 'config/config.php';

// если уже авторизован, редирект на главную
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$errors = [];
$form_data = [];
$success = '';

// обработка входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $form_data = ['email' => $email];
    
    if (empty($email)) {
        $errors['login_email'] = 'Введите email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['login_email'] = 'Введите корректный email';
    }
    
    if (empty($password)) {
        $errors['login_password'] = 'Введите пароль';
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && $password == $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            
            header('Location: cabinet.php');
            exit;
        } else {
            $errors['login_common'] = 'Неверный email или пароль';
        }
    }
}

// обработка регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $surname = trim($_POST['surname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $agree = isset($_POST['agree']);
    
    $form_data = [
        'name' => $name,
        'surname' => $surname,
        'email' => $email,
        'phone' => $phone
    ];
    
    // валидация имени
    if (empty($name)) {
        $errors['reg_name'] = 'Введите имя';
    } elseif (strlen($name) < 2) {
        $errors['reg_name'] = 'Имя должно содержать не менее 2 символов';
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $name)) {
        $errors['reg_name'] = 'Имя может содержать только буквы';
    }
    
    // маил
    if (empty($email)) {
        $errors['reg_email'] = 'Введите email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['reg_email'] = 'Введите корректный email (пример: name@domain.ru)';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['reg_email'] = 'Этот email уже зарегистрирован';
        }
    }
    
    // телефон
    if (empty($phone)) {
        $errors['reg_phone'] = 'Введите номер телефона';
    } else {
        $phone_clean = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen($phone_clean) < 10) {
            $errors['reg_phone'] = 'Введите корректный номер телефона (минимум 10 цифр)';
        }
    }
    
    // парол
    if (empty($password)) {
        $errors['reg_password'] = 'Введите пароль';
    } elseif (strlen($password) < 6) {
        $errors['reg_password'] = 'Пароль должен быть не менее 6 символов';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        $errors['reg_password'] = 'Пароль должен содержать хотя бы одну заглавную букву, одну строчную и одну цифру';
    }
    
    // парол+
    if ($password !== $password_confirm) {
        $errors['reg_password_confirm'] = 'Пароли не совпадают';
    }
    
    // согласие
    if (!$agree) {
        $errors['reg_agree'] = 'Необходимо согласие на обработку данных';
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO users (email, password, name, surname, phone, role) VALUES (?, ?, ?, ?, ?, 'user')");
        
        if ($stmt->execute([$email, $password, $name, $surname, $phone])) {
            $userId = $pdo->lastInsertId();
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = 'user';
            
            $success = 'Регистрация прошла успешно! Перенаправление...';
            header('refresh:2;url=index.php');
        } else {
            $errors['reg_common'] = 'Ошибка при регистрации. Попробуйте позже.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход / Регистрация — ТрансЛогистик</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-page {
            min-height: calc(100vh - 400px);
            padding: 60px 0;
            background: var(--gray-bg);
        }
        
        .auth-container {
            max-width: 520px;
            margin: 0 auto;
        }
        
        .auth-card {
            background: white;
            border-radius: 28px;
            padding: 40px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-border);
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .auth-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
        }
        
        .auth-header p {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .auth-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
            background: var(--gray-bg);
            padding: 6px;
            border-radius: 60px;
        }
        
        .auth-tab {
            flex: 1;
            padding: 12px 20px;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: 40px;
            transition: all 0.3s ease;
            text-align: center;
            background: transparent;
            border: none;
        }
        
        .auth-tab.active {
            background: var(--primary);
            color: white;
        }
        
        .auth-tab:not(.active):hover {
            background: rgba(11, 59, 92, 0.05);
            color: var(--primary);
        }
        
        .auth-form {
            display: none;
        }
        
        .auth-form.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        
        .label-required::after {
            content: '*';
            color: #ef4444;
            margin-left: 4px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--gray-border);
            border-radius: 16px;
            font-size: 15px;
            transition: all 0.2s;
            background: white;
        }
        
        .input-wrapper input.password-input {
            padding-right: 48px;
        }
        
        .input-wrapper input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(245,158,11,0.1);
        }
        
        .input-wrapper input.valid {
            border-color: #22c55e;
            background: #f0fdf4;
        }
        
        .input-wrapper input.invalid {
            border-color: #ef4444;
            background: #fef2f2;
        }
        
        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            font-size: 20px;
            line-height: 1;
            color: #94a3b8;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .toggle-password:hover {
            color: var(--accent);
        }
        
        .field-error {
            color: #ef4444;
            font-size: 12px;
            margin-top: 6px;
            display: none;
            align-items: center;
            gap: 4px;
        }
        
        .field-error.show {
            display: flex;
        }
        
        .field-success {
            color: #22c55e;
            font-size: 12px;
            margin-top: 6px;
            display: none;
            align-items: center;
            gap: 4px;
        }
        
        .field-success::before {
            content: '✓';
            font-weight: bold;
        }
        
        .field-success.show {
            display: flex;
        }
        
        .field-hint {
            color: #94a3b8;
            font-size: 11px;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            margin: 20px 0;
        }
        
        .checkbox-wrapper input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--accent);
        }
        
        .checkbox-wrapper span {
            color: var(--text-muted);
            font-size: 13px;
        }
        
        .privacy-link {
            color: var(--accent);
            text-decoration: none;
        }
        
        .privacy-link:hover {
            text-decoration: underline;
        }
        
        .btn-auth {
            width: 100%;
            padding: 14px;
            background: var(--accent);
            color: white;
            font-weight: 600;
            font-size: 16px;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-auth:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
        }
        
        .btn-auth:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
        }
        
        .switch-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-border);
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .switch-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        
        .switch-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 14px 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            text-align: center;
            font-size: 14px;
        }
        
        .alert-success {
            background: #22c55e;
            color: white;
        }
        
        .alert-error {
            background: #ef4444;
            color: white;
        }
        
        .password-strength {
            margin-top: 6px;
            font-size: 12px;
        }
        
        .strength-weak { color: #ef4444; display: flex; align-items: center; gap: 4px; }
        .strength-weak::before { content: '🔴'; }
        .strength-medium { color: #f59e0b; display: flex; align-items: center; gap: 4px; }
        .strength-medium::before { content: '🟡'; }
        .strength-strong { color: #22c55e; display: flex; align-items: center; gap: 4px; }
        .strength-strong::before { content: '🟢'; }
        
        @media (max-width: 600px) {
            .auth-card {
                padding: 28px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .auth-page {
                padding: 40px 0;
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
                <a href="contacts.php">Контакты</a>
                <a href="login.php" style="color: var(--accent); font-weight: 600;">Вход</a>
            </div>
        </nav>
    </div>

    <div class="auth-page">
        <div class="container">
            <div class="auth-container">
                <!-- Вывод сообщения об успешной регистрации -->
                <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <!-- Вывод общей ошибки неверный логин/пароль или ошибка регистрации -->
                <?php if (!empty($errors['login_common']) || !empty($errors['reg_common'])): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($errors['login_common'] ?? $errors['reg_common'] ?? '') ?>
                </div>
                <?php endif; ?>
                
                <div class="auth-card">
                    <div class="auth-header">
                        <h1>ТрансЛогистик</h1>
                        <p>Войдите или создайте аккаунт для оформления заказов</p>
                    </div>
                    <!-- Переключатель табов -->
                    <div class="auth-tabs" id="authTabs">
                        <button class="auth-tab active" data-tab="login">Вход</button>
                        <button class="auth-tab" data-tab="register">Регистрация</button>
                    </div>
                    
                    <!-- Форма входа -->
                    <div class="auth-form active" id="loginForm">
                        <form method="POST" id="loginFormElement">
                            <div class="form-group">
                                <label class="label-required">Email</label>
                                <div class="input-wrapper">
                                    <input type="email" name="email" id="loginEmail" placeholder="ivan@example.com" value="<?= htmlspecialchars($form_data['email'] ?? '') ?>">
                                </div>
                                <div class="field-hint">Введите ваш email адрес</div>
                                <div id="loginEmailError" class="field-error <?= isset($errors['login_email']) ? 'show' : '' ?>">
                                    <?= htmlspecialchars($errors['login_email'] ?? '') ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="label-required">Пароль</label>
                                <div class="input-wrapper">
                                    <input type="password" name="password" id="loginPassword" class="password-input" placeholder="••••••••">
                                    <button type="button" class="toggle-password" data-target="loginPassword">
                                        🔒
                                    </button>
                                </div>
                                <div class="field-hint">Введите ваш пароль</div>
                                <div id="loginPasswordError" class="field-error <?= isset($errors['login_password']) ? 'show' : '' ?>">
                                    <?= htmlspecialchars($errors['login_password'] ?? '') ?>
                                </div>
                            </div>
                            
                            <button type="submit" name="login" class="btn-auth">Войти</button>
                        </form>
                        <!-- Ссылка переключения на регистрацию -->
                        <div class="switch-link">
                            Нет аккаунта? 
                            <a href="#" id="switchToRegister">Зарегистрироваться</a>
                        </div>
                    </div>
                    
                    <!-- Форма регистрации -->
                    <div class="auth-form" id="registerForm">
                        <form method="POST" id="registerFormElement">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="label-required">Имя</label>
                                    <div class="input-wrapper">
                                        <input type="text" name="name" id="regName" placeholder="Иван" value="<?= htmlspecialchars($form_data['name'] ?? '') ?>" autocomplete="off">
                                    </div>
                                    <div class="field-hint">Только буквы, от 2 символов</div>
                                    <div id="regNameError" class="field-error <?= isset($errors['reg_name']) ? 'show' : '' ?>">
                                        <?= htmlspecialchars($errors['reg_name'] ?? '') ?>
                                    </div>
                                    <div id="regNameSuccess" class="field-success">Имя заполнено верно</div>
                                </div>
                                <div class="form-group">
                                    <label>Фамилия</label>
                                    <div class="input-wrapper">
                                        <input type="text" name="surname" id="regSurname" placeholder="Иванов" value="<?= htmlspecialchars($form_data['surname'] ?? '') ?>" autocomplete="off">
                                    </div>
                                    <div class="field-hint">Необязательное поле</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="label-required">Email</label>
                                <div class="input-wrapper">
                                    <input type="email" name="email" id="regEmail" placeholder="ivan@example.com" value="<?= htmlspecialchars($form_data['email'] ?? '') ?>" autocomplete="off">
                                </div>
                                <div class="field-hint">Формат: name@domain.ru</div>
                                <div id="regEmailError" class="field-error <?= isset($errors['reg_email']) ? 'show' : '' ?>">
                                    <?= htmlspecialchars($errors['reg_email'] ?? '') ?>
                                </div>
                                <div id="regEmailSuccess" class="field-success">Email доступен для регистрации</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="label-required">Телефон</label>
                                <div class="input-wrapper">
                                    <input type="tel" name="phone" id="regPhone" placeholder="+7 (900) 123-45-67" value="<?= htmlspecialchars($form_data['phone'] ?? '') ?>" autocomplete="off">
                                </div>
                                <div class="field-hint">Обязательно! Для связи по вопросам доставки</div>
                                <div id="regPhoneError" class="field-error <?= isset($errors['reg_phone']) ? 'show' : '' ?>">
                                    <?= htmlspecialchars($errors['reg_phone'] ?? '') ?>
                                </div>
                                <div id="regPhoneSuccess" class="field-success">Телефона введен корректно</div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="label-required">Пароль</label>
                                    <div class="input-wrapper">
                                        <input type="password" name="password" id="regPassword" class="password-input" placeholder="минимум 6 символов">
                                        <button type="button" class="toggle-password" data-target="regPassword">
                                            🔒
                                        </button>
                                    </div>
                                    <div class="field-hint">Заглавная + строчная + цифра</div>
                                    <div id="regPasswordError" class="field-error <?= isset($errors['reg_password']) ? 'show' : '' ?>">
                                        <?= htmlspecialchars($errors['reg_password'] ?? '') ?>
                                    </div>
                                    <div id="passwordStrength" class="password-strength"></div>
                                </div>
                                <div class="form-group">
                                    <label class="label-required">Подтверждение</label>
                                    <div class="input-wrapper">
                                        <input type="password" name="password_confirm" id="regPasswordConfirm" class="password-input" placeholder="повторите пароль">
                                        <button type="button" class="toggle-password" data-target="regPasswordConfirm">
                                            🔒
                                        </button>
                                    </div>
                                    <div class="field-hint">Введите пароль еще раз</div>
                                    <div id="regPasswordConfirmError" class="field-error <?= isset($errors['reg_password_confirm']) ? 'show' : '' ?>">
                                        <?= htmlspecialchars($errors['reg_password_confirm'] ?? '') ?>
                                    </div>
                                    <div id="regPasswordConfirmSuccess" class="field-success">Пароли совпадают</div>
                                </div>
                            </div>
                            
                            <label class="checkbox-wrapper">
                                <input type="checkbox" name="agree" id="regAgree">
                                <span>Я принимаю <a href="#" class="privacy-link" id="showPrivacy">условия обработки данных</a> *</span>
                            </label>
                            <div id="regAgreeError" class="field-error <?= isset($errors['reg_agree']) ? 'show' : '' ?>">
                                <?= htmlspecialchars($errors['reg_agree'] ?? '') ?>
                            </div>
                            
                            <button type="submit" name="register" class="btn-auth" id="registerBtn" disabled>Зарегистрироваться</button>
                        </form>
                        
                        <div class="switch-link">
                            Уже есть аккаунт? 
                            <a href="#" id="switchToLogin">Войти</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно политика конфиденциальности-->
    <div id="privacyModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; max-width: 480px; margin: 20px; border-radius: 24px; padding: 32px; max-height: 80vh; overflow-y: auto;">
            <h3 style="color: var(--primary); margin-bottom: 20px; font-size: 22px;">Политика обработки данных</h3>
            <div style="font-size: 14px; line-height: 1.6; color: var(--text-dark);">
                <p>Нажимая кнопку "Зарегистрироваться", вы даете согласие на обработку своих персональных данных.</p>
                <p style="margin-top: 15px;"><strong>Какие данные мы собираем:</strong> ФИО, телефон, email.</p>
                <p><strong>Для чего:</strong> создание личного кабинета, оформление и отслеживание заказов, обратная связь.</p>
                <p><strong>Как мы защищаем:</strong> данные хранятся на защищенных серверах, не передаются третьим лицам.</p>
                <p style="margin-top: 15px;">Вы можете запросить удаление своих данных, написав на <strong>privacy@translogistic.ru</strong></p>
            </div>
            <button onclick="document.getElementById('privacyModal').style.display='none'" style="width: 100%; margin-top: 24px;" class="btn-auth">Закрыть</button>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div class="footer-logo" style="font-size: 24px; font-weight: 700; margin-bottom: 16px;">Транс<span style="color: var(--accent);">Логистик</span></div>
                    <p style="color: #cbd5e1;">© 2026 Транспортная компания.<br>Все права защищены.</p>
                </div>
                <div class="footer-links">
                    <h4>Навигация</h4>
                    <a href="index.php">Главная</a>
                    <a href="services.php">Услуги</a>
                    <a href="about.php">О компании</a>
                    <a href="news.php">Новости</a>
                </div>
                <div class="footer-links">
                    <h4>Инфо</h4>
                    <a href="reviews.php">Отзывы</a>
                    <a href="contacts.php">Контакты</a>
                    <a href="login.php">Авторизация</a>
                </div>
                <div>
                    <h4>Контакты</h4>
                    <p style="color: #cbd5e1;">г. Ярославль, ул. Строителей, 5</p>
                    <p style="color: #cbd5e1; margin-top: 8px;">+7 (4852) 00-00-00</p>
                    <p style="color: #cbd5e1;">info@translogistic.ru</p>
                </div>
            </div>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Функционал глазка
        const toggleButtons = document.querySelectorAll('.toggle-password');
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                if (input) {
                    if (input.type === 'password') {
                        input.type = 'text';
                        this.textContent = '🔓';
                    } else {
                        input.type = 'password';
                        this.textContent = '🔒';
                    }
                }
            });
        });
        
        // Переключение табов форм
        const tabs = document.querySelectorAll('.auth-tab');
        const forms = document.querySelectorAll('.auth-form');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const target = this.dataset.tab;
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                forms.forEach(form => {
                    form.classList.remove('active');
                    if (form.id === target + 'Form') {
                        form.classList.add('active');
                    }
                });
            });
        });
        
        // Переключение по ссылкам под формой
        document.getElementById('switchToRegister')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector('[data-tab="register"]').click();// имитируем клик по табу
        });
        
        document.getElementById('switchToLogin')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector('[data-tab="login"]').click();
        });
        
        // Модальное окно
        document.getElementById('showPrivacy')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('privacyModal').style.display = 'flex';
        });
        
        document.getElementById('privacyModal')?.addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
        
        // Валидация регистрации
        const regName = document.getElementById('regName');
        const regEmail = document.getElementById('regEmail');
        const regPhone = document.getElementById('regPhone');
        const regPassword = document.getElementById('regPassword');
        const regPasswordConfirm = document.getElementById('regPasswordConfirm');
        const regAgree = document.getElementById('regAgree');
        const registerBtn = document.getElementById('registerBtn');
        
        function validateName() {
            const name = regName?.value.trim() || '';
            const errorDiv = document.getElementById('regNameError');
            const successDiv = document.getElementById('regNameSuccess');
            const namePattern = /^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u;
            // проверка имя 
            if (!name) {
                errorDiv.textContent = 'Введите имя';
                errorDiv.classList.add('show');
                successDiv.classList.remove('show');
                regName?.classList.add('invalid');
                regName?.classList.remove('valid');
                return false;
                //минимум 2 символа не пусто
            } else if (name.length < 2) {
                errorDiv.textContent = 'Имя должно быть не менее 2 символов';
                errorDiv.classList.add('show');
                successDiv.classList.remove('show');
                regName?.classList.add('invalid');
                regName?.classList.remove('valid');
                return false;
                //только русские ьуквы
            } else if (!namePattern.test(name)) {
                errorDiv.textContent = 'Имя может содержать только буквы';
                errorDiv.classList.add('show');
                successDiv.classList.remove('show');
                regName?.classList.add('invalid');
                regName?.classList.remove('valid');
                return false;
                //пробелы и дефисы
            } else {
                errorDiv.classList.remove('show');
                successDiv.classList.add('show');
                regName?.classList.remove('invalid');
                regName?.classList.add('valid');
                return true;
            }
        }
        //имэйл по аналогии на пустоту и маску
        function validateEmail() {
            const email = regEmail?.value.trim() || '';
            const errorDiv = document.getElementById('regEmailError');
            const successDiv = document.getElementById('regEmailSuccess');
            const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            
            if (!email) {
                errorDiv.textContent = 'Введите email';
                errorDiv.classList.add('show');
                successDiv.classList.remove('show');
                regEmail?.classList.add('invalid');
                regEmail?.classList.remove('valid');
                return false;
            } else if (!emailPattern.test(email)) {
                errorDiv.textContent = 'Введите корректный email';
                errorDiv.classList.add('show');
                successDiv.classList.remove('show');
                regEmail?.classList.add('invalid');
                regEmail?.classList.remove('valid');
                return false;
            } else {
                errorDiv.classList.remove('show');
                successDiv.classList.add('show');
                regEmail?.classList.remove('invalid');
                regEmail?.classList.add('valid');
                return true;
            }
        }
        //аналогично с телефоном не пусто и без мусора минимум 10 символов
        function validatePhone() {
            const phone = regPhone?.value.trim() || '';
            const errorDiv = document.getElementById('regPhoneError');
            const successDiv = document.getElementById('regPhoneSuccess');
            
            if (!phone) {
                errorDiv.textContent = 'Введите номер телефона';
                errorDiv.classList.add('show');
                successDiv.classList.remove('show');
                regPhone?.classList.add('invalid');
                regPhone?.classList.remove('valid');
                return false;
            }
            // Удаляем всё кроме цифр и плюса
            const phoneClean = phone.replace(/[^0-9+]/g, '');
            if (phoneClean.length < 10) {
                errorDiv.textContent = 'Введите корректный номер (минимум 10 цифр)';
                errorDiv.classList.add('show');
                successDiv.classList.remove('show');
                regPhone?.classList.add('invalid');
                regPhone?.classList.remove('valid');
                return false;
            } else {
                errorDiv.classList.remove('show');
                successDiv.classList.add('show');
                regPhone?.classList.remove('invalid');
                regPhone?.classList.add('valid');
                return true;
            }
        }
        // проверка пароля 
        function validatePassword() {
            const password = regPassword?.value || '';
            const errorDiv = document.getElementById('regPasswordError');
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (!password) {
                errorDiv.textContent = 'Введите пароль';
                errorDiv.classList.add('show');
                strengthDiv.innerHTML = '';
                regPassword?.classList.add('invalid');
                regPassword?.classList.remove('valid');
                return false;
            } else if (password.length < 6) {
                errorDiv.textContent = 'Пароль должен быть не менее 6 символов';
                errorDiv.classList.add('show');
                strengthDiv.innerHTML = '<span class="strength-weak">Слабый пароль</span>';
                regPassword?.classList.add('invalid');
                regPassword?.classList.remove('valid');
                return false;
            } else if (!/(?=.*[a-z])/.test(password)) {
                errorDiv.textContent = 'Пароль должен содержать строчную букву';
                errorDiv.classList.add('show');
                strengthDiv.innerHTML = '<span class="strength-weak">Слабый пароль</span>';
                regPassword?.classList.add('invalid');
                regPassword?.classList.remove('valid');
                return false;
            } else if (!/(?=.*[A-Z])/.test(password)) {
                errorDiv.textContent = 'Пароль должен содержать заглавную букву';
                errorDiv.classList.add('show');
                strengthDiv.innerHTML = '<span class="strength-medium">Средний пароль</span>';
                regPassword?.classList.add('invalid');
                regPassword?.classList.remove('valid');
                return false;
            } else if (!/(?=.*\d)/.test(password)) {
                errorDiv.textContent = 'Пароль должен содержать цифру';
                errorDiv.classList.add('show');
                strengthDiv.innerHTML = '<span class="strength-medium">Средний пароль</span>';
                regPassword?.classList.add('invalid');
                regPassword?.classList.remove('valid');
                return false;
            } else if (password.length >= 8) {
                errorDiv.classList.remove('show');
                strengthDiv.innerHTML = '<span class="strength-strong">Сильный пароль</span>';
                regPassword?.classList.remove('invalid');
                regPassword?.classList.add('valid');
                return true;
            } else {
                errorDiv.classList.remove('show');
                strengthDiv.innerHTML = '<span class="strength-medium">Средний пароль</span>';
                regPassword?.classList.remove('invalid');
                regPassword?.classList.add('valid');
                return true;
            }
        }
        // проверка пароля+
        function validatePasswordConfirm() {
            const password = regPassword?.value || '';
            const confirm = regPasswordConfirm?.value || '';
            const errorDiv = document.getElementById('regPasswordConfirmError');
            const successDiv = document.getElementById('regPasswordConfirmSuccess');
            
            if (!confirm) {
                errorDiv.textContent = 'Подтвердите пароль';
                errorDiv.classList.add('show');
                successDiv.classList.remove('show');
                regPasswordConfirm?.classList.add('invalid');
                regPasswordConfirm?.classList.remove('valid');
                return false;
            } else if (password !== confirm) {
                errorDiv.textContent = 'Пароли не совпадают';
                errorDiv.classList.add('show');
                successDiv.classList.remove('show');
                regPasswordConfirm?.classList.add('invalid');
                regPasswordConfirm?.classList.remove('valid');
                return false;
            } else {
                errorDiv.classList.remove('show');
                successDiv.classList.add('show');
                regPasswordConfirm?.classList.remove('invalid');
                regPasswordConfirm?.classList.add('valid');
                return true;
            }
        }
        // проверка согласия
        function validateAgree() {
            const errorDiv = document.getElementById('regAgreeError');
            if (!regAgree?.checked) {
                errorDiv.textContent = 'Необходимо согласие на обработку данных';
                errorDiv.classList.add('show');
                return false;
            } else {
                errorDiv.classList.remove('show');
                return true;
            }
        }
        // общая проверка всей формы
        function checkFormValidity() {
            const isValid = validateName() && validateEmail() && validatePhone() && validatePassword() && validatePasswordConfirm() && validateAgree();
            if (registerBtn) registerBtn.disabled = !isValid;
            return isValid;
        }
        // обработчики на все поля
        regName?.addEventListener('input', checkFormValidity);
        regEmail?.addEventListener('input', checkFormValidity);
        regPhone?.addEventListener('input', checkFormValidity);
        regPassword?.addEventListener('input', () => { validatePassword(); validatePasswordConfirm(); checkFormValidity(); });
        regPasswordConfirm?.addEventListener('input', () => { validatePasswordConfirm(); checkFormValidity(); });
        regAgree?.addEventListener('change', checkFormValidity);
        
        // Валидация входа
        const loginEmail = document.getElementById('loginEmail');
        const loginPassword = document.getElementById('loginPassword');
        
        function validateLogin() {
            let isValid = true;
            const email = loginEmail?.value.trim() || '';
            const password = loginPassword?.value || '';
            const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            // проверка имэйл
            if (!email) {
                document.getElementById('loginEmailError').textContent = 'Введите email';
                document.getElementById('loginEmailError').classList.add('show');
                isValid = false;
            } else if (!emailPattern.test(email)) {
                document.getElementById('loginEmailError').textContent = 'Введите корректный email';
                document.getElementById('loginEmailError').classList.add('show');
                isValid = false;
            } else {
                document.getElementById('loginEmailError').classList.remove('show');
            }
            // пароля
            if (!password) {
                document.getElementById('loginPasswordError').textContent = 'Введите пароль';
                document.getElementById('loginPasswordError').classList.add('show');
                isValid = false;
            } else {
                document.getElementById('loginPasswordError').classList.remove('show');
            }
            
            return isValid;
        }
        // валидация при входе
        loginEmail?.addEventListener('input', validateLogin);
        loginPassword?.addEventListener('input', validateLogin);
        // блокировка отправки формы регистрации если есть ошибки в форме
        document.getElementById('registerFormElement')?.addEventListener('submit', function(e) {
            if (!checkFormValidity()) {
                e.preventDefault();
                alert('Пожалуйста, заполните все поля корректно');
            }
        });
    });
    </script>
</body>
</html>