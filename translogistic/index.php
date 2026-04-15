<?php
session_start();
require_once 'config/config.php';

// Автозаполнение калькулятора из гет параметров для избранных маршрутов
$prefill_from = isset($_GET['from']) ? htmlspecialchars($_GET['from']) : '';
$prefill_to = isset($_GET['to']) ? htmlspecialchars($_GET['to']) : '';

//данные авторизованного пользователя
$userData = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT name, surname, email, phone FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
}
//последние 3 новости
$newsStmt = $pdo->query("SELECT * FROM news WHERE is_published = 1 ORDER BY published_at DESC LIMIT 3");
$latestNews = $newsStmt->fetchAll();

//услуги для блока
$servicesStmt = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 3");
$mainServices = $servicesStmt->fetchAll();

//настройки сайта
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// обработка трекера (GET track number)
$trackData = null;
if (isset($_GET['track_number']) && !empty($_GET['track_number'])) {
    $trackNum = $_GET['track_number'];
    $trackStmt = $pdo->prepare("SELECT * FROM tracking WHERE tracking_number = ?");
    $trackStmt->execute([$trackNum]);
    $trackData = $trackStmt->fetch();
}

// обработка формы обратной связи
$feedbackSuccess = false;
$feedbackError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_feedback'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = !empty(trim($_POST['phone'])) ? trim($_POST['phone']) : null;
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
    
    // валидация телефона
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
            $stmt->execute([$name, $phone, $email, $message, 'Главная']);
            $feedbackSuccess = true;
            $_POST = [];
        } catch (PDOException $e) {
            $feedbackError = true;
        }
    } else {
        $feedbackError = true;
    }
}

function formatDate($date) {
    $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $d = new DateTime($date);
    return $d->format('d') . ' ' . $months[$d->format('n') - 1] . ' ' . $d->format('Y');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5">
    <title>ТрансЛогистик — Главная</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .feedback-section {
            background: var(--gray-bg);
            padding: 60px 0;
        }
        
        .feedback-form {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 28px;
            box-shadow: var(--shadow);
        }
        
        .feedback-form .form-group {
            margin-bottom: 24px;
        }
        
        .feedback-form .form-group input,
        .feedback-form .form-group textarea {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid var(--gray-border);
            border-radius: 16px;
            font-size: 15px;
            transition: all 0.3s;
            background: white;
            font-family: inherit;
        }
        
        .feedback-form .form-group input:focus,
        .feedback-form .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(245,158,11,0.1);
        }
        
        .feedback-form .form-group input.invalid,
        .feedback-form .form-group textarea.invalid {
            border-color: #ef4444;
            background: #fef2f2;
        }
        
        .feedback-form .form-group input.valid,
        .feedback-form .form-group textarea.valid {
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
        
        .track-result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 16px;
            transition: all 0.3s;
        }
        
        .track-result-delivered { background: #dcfce7; }
        .track-result-transit { background: #eff6ff; }
        .track-result-pending { background: #fef9c3; }
        .track-result-error { background: #fee2e2; }
        
        .track-status-delivered { color: #22c55e; }
        .track-status-transit { color: #3b82f6; }
        .track-status-pending { color: #f59e0b; }
        
        @media (max-width: 768px) {
            .feedback-form {
                padding: 25px;
            }
            
            .welcome-banner {
                padding: 15px 20px;
            }
            
            .welcome-banner .hand-gif {
                width: 45px;
                height: 45px;
            }
        }
@media (max-width: 768px) {
    .container div[style*="grid-template-columns: repeat(4, 1fr)"] {
        grid-template-columns: 1fr !important;
        gap: 20px !important;
    }
    
    /* Внутренние отступы карточек */
    .container div[style*="background: white; border-radius: 28px"] {
        padding: 0 !important;
    }
    
    .container div[style*="padding: 28px 24px 32px"] {
        padding: 20px !important;
    }
    
    /* Заголовки карточек */
    .container div[style*="display: flex; align-items: center; gap: 12px"] h3 {
        font-size: 18px !important;
    }
    
    /* Иконки внутри карточек */
    .container div[style*="width: 48px; height: 48px"] {
        width: 40px !important;
        height: 40px !important;
    }
    
    .container div[style*="width: 48px; height: 48px"] img {
        width: 22px !important;
        height: 22px !important;
    }
    
    /* Текст в карточках */
    .container div[style*="background: white; border-radius: 28px"] p {
        font-size: 14px !important;
        line-height: 1.5 !important;
    }
}
@media (max-width: 768px) {
    /* Услуги — 3 карточки в 1 колонку */
    .container div[style*="grid-template-columns: repeat(3, 1fr)"] {
        grid-template-columns: 1fr !important;
        gap: 20px !important;
    }
    
    /* Карточка услуги — не фиксированная высота */
    .service-card {
        height: auto !important;
        min-height: 280px !important;
    }
    
    /* Затемнение фона */
    .service-card div[style*="position: absolute"] {
        filter: brightness(0.5) !important;
    }
    
    /* Заголовок услуги */
    .service-card h3 {
        font-size: 22px !important;
    }
    
    /* Текст услуги */
    .service-card p {
        font-size: 14px !important;
    }
    
    /* Отступы внутри карточки */
    .service-card div[style*="padding: 30px"] {
        padding: 20px !important;
    }
    
    /* Кнопка "Подробнее" */
    .service-link {
        font-size: 14px !important;
    }
}
    </style>
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <a href="index.php" class="logo" style="text-decoration: none;">Транс<span>Логистик</span></a>
            <div class="nav-links">
                <a href="index.php" <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'style="color: var(--accent);"' : '' ?>>Главная</a>
                <a href="services.php" <?= basename($_SERVER['PHP_SELF']) == 'services.php' ? 'style="color: var(--accent);"' : '' ?>>Услуги</a>
                <a href="about.php" <?= basename($_SERVER['PHP_SELF']) == 'about.php' ? 'style="color: var(--accent);"' : '' ?>>О нас</a>
                <a href="news.php" <?= basename($_SERVER['PHP_SELF']) == 'news.php' ? 'style="color: var(--accent);"' : '' ?>>Новости</a>
                <a href="reviews.php" <?= basename($_SERVER['PHP_SELF']) == 'reviews.php' ? 'style="color: var(--accent);"' : '' ?>>Отзывы</a>
                <a href="contacts.php" <?= basename($_SERVER['PHP_SELF']) == 'contacts.php' ? 'style="color: var(--accent);"' : '' ?>>Контакты</a>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="cabinet.php" <?= basename($_SERVER['PHP_SELF']) == 'cabinet.php' ? 'style="color: var(--accent); font-weight: 600;"' : 'style="font-weight: 600;"' ?>>
                        Личный кабинет
                    </a>
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
    
    <section style="padding-top: 20px;">
        <div class="container">
            <div style="max-width: 800px; margin-bottom: 40px;">
                <h1 style="font-size: 44px; font-weight: 800; line-height: 1.2; color: var(--primary);">Надёжная доставка по Ярославлю и России</h1>
                <p style="font-size: 20px; color: var(--text-muted); margin-top: 20px;">Перевозка сборных грузов, экспедирование, ж/д отправки — прозрачно и быстро.</p>
            </div>

            <div class="tools-grid">
                <!--Калькулятор-->
                <div class="tool-card">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 24px;">
                        <img src="images/4.png" alt="Калькулятор" style="width: 48px; height: 48px;">
                        <h3 style="margin: 0;">Калькулятор стоимости</h3>
                    </div>
                    <div class="form-group">
    <label>Откуда</label>
    <select id="fromCity">
        <option value="Ярославль" <?= $prefill_from == 'Ярославль' ? 'selected' : '' ?>>Ярославль</option>
        <option value="Москва" <?= $prefill_from == 'Москва' ? 'selected' : '' ?>>Москва</option>
        <option value="Санкт-Петербург" <?= $prefill_from == 'Санкт-Петербург' ? 'selected' : '' ?>>Санкт-Петербург</option>
        <option value="Кострома" <?= $prefill_from == 'Кострома' ? 'selected' : '' ?>>Кострома</option>
        <option value="Вологда" <?= $prefill_from == 'Вологда' ? 'selected' : '' ?>>Вологда</option>
    </select>
</div>

<div class="form-group">
    <label>Куда</label>
    <select id="toCity">
        <option value="Москва" <?= $prefill_to == 'Москва' ? 'selected' : '' ?>>Москва</option>
        <option value="Ярославль" <?= $prefill_to == 'Ярославль' ? 'selected' : '' ?>>Ярославль</option>
        <option value="Санкт-Петербург" <?= $prefill_to == 'Санкт-Петербург' ? 'selected' : '' ?>>Санкт-Петербург</option>
        <option value="Вологда" <?= $prefill_to == 'Вологда' ? 'selected' : '' ?>>Вологда</option>
        <option value="Кострома" <?= $prefill_to == 'Кострома' ? 'selected' : '' ?>>Кострома</option>
    </select>
</div>

<div class="form-group">
    <label>Вес (кг)</label>
    <input type="number" id="weight" placeholder="Например: 150" value="0">
</div>
                    <div class="form-group">
                        <label>Объём (м³)</label>
                        <input type="number" id="volume" placeholder="0.5" step="0.1" value="0">
                    </div>
                    <div class="form-group">
                        <label>Тип груза</label>
                        <select id="cargoType">
                            <option value="Обычный">Обычный груз</option>
                            <option value="Хрупкий">Хрупкий груз</option>
                            <option value="Опасный">Опасный груз</option>
                        </select>
                    </div>
                    <button class="btn" id="calcAndOrderBtn">Рассчитать и заказать</button>
                    <div id="calcResult" class="calc-result" style="margin-top: 20px;">
                        Примерная стоимость: <strong id="priceValue">0</strong> ₽
                    </div>
                </div>
                
                <!--Трекер-->
                <div class="tool-card">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 24px;">
                        <img src="images/3.png" alt="Трекер" style="width: 40px; height: 40px;">
                        <h3 style="margin: 0;">Трекер груза</h3>
                    </div>
                    <form method="GET" style="display: flex; flex-direction: column; gap: 15px;">
                        <div class="form-group">
                            <label>Номер накладной</label>
                            <input type="text" name="track_number" id="trackNumber" placeholder="TR-2026-001" value="<?= htmlspecialchars($_GET['track_number'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-outline">Отследить</button>
                    </form>
                    
                    <?php if ($trackData): ?>
                        <?php 
                            $statusClass = '';
                            $statusColor = '';
                            $bgClass = '';
                            switch($trackData['status']) {
                                case 'Доставлен': 
                                    $statusColor = 'track-status-delivered';
                                    $bgClass = 'track-result-delivered';
                                    break;
                                case 'В пути': 
                                    $statusColor = 'track-status-transit';
                                    $bgClass = 'track-result-transit';
                                    break;
                                default: 
                                    $statusColor = 'track-status-pending';
                                    $bgClass = 'track-result-pending';
                            }
                        ?>
                        <div class="track-result <?= $bgClass ?>">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div>
                                    <div style="font-size: 12px; color: var(--text-muted);">Статус</div>
                                    <div><strong class="<?= $statusColor ?>"><?= htmlspecialchars($trackData['status']) ?></strong></div>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: var(--text-muted);">Маршрут</div>
                                    <div><?= htmlspecialchars($trackData['from_city']) ?> → <?= htmlspecialchars($trackData['to_city']) ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: var(--text-muted);">Вес/Объем</div>
                                    <div><?= $trackData['weight'] ?> кг / <?= $trackData['volume'] ?> м³</div>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: var(--text-muted);">Плановая дата</div>
                                    <div><?= $trackData['estimated_delivery'] ?? '—' ?></div>
                                </div>
                            </div>
                        </div>
                    <?php elseif (isset($_GET['track_number']) && !empty($_GET['track_number'])): ?>
                        <div class="track-result track-result-error">
                            <div style="text-align: center; color: #dc2626;">
                                Груз с номером «<?= htmlspecialchars($_GET['track_number']) ?>» не найден
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <p style="font-size: 14px; color: var(--text-muted); margin-top: 16px;">
                        Тестовые номера: <strong>TR-2026-001</strong> (доставлен), <strong>TR-2026-015</strong> (в пути), <strong>TR-2026-089</strong> (принят)
                    </p>
                </div>
            </div>
        </div>
    </section>
    
    <section style="padding: 60px 0; background: var(--gray-bg);">
    <div class="container">
        <h2 class="section-title" style="text-align: center;">Как нас найти</h2>
        <iframe src="https://yandex.ru/map-widget/v1/?from=mapframe&ll=39.776799%2C57.698991&mode=search&ol=geo&ouri=ymapsbm1%3A%2F%2Fgeo%3Fdata%3DCgg1NzkwNzA4NxJE0KDQvtGB0YHQuNGPLCDQr9GA0L7RgdC70LDQstC70YwsINGD0LvQuNGG0LAg0KHRgtGA0L7QuNGC0LXQu9C10LksIDUiCg1xGx9CFcTLZkI%2C&source=mapframe&z=16.2" width="100%" height="400" frameborder="0" allowfullscreen="true" style="border-radius: 24px;"></iframe>
        <p style="text-align: center; margin-top: 24px;">г. Ярославль, ул. Строителей, 5</p>
    </div>
</section>
    <section style="background: #ffffff; padding: 80px 0;">
        <div class="container">
            <div style="text-align: center; margin-bottom: 50px;">
                <h2 class="section-title" style="margin-bottom: 16px;">Почему выбирают нас</h2>
                <p style="color: var(--text-muted); font-size: 18px; max-width: 600px; margin: 0 auto;">
                    4 причины работать с нами
                </p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px;">
                <div style="background: white; border-radius: 28px; overflow: hidden; box-shadow: 0 20px 20px -10px rgba(0,0,0,0.05); transition: 0.3s; border: 1px solid #f1f5f9;">
                    <div style="height: 160px; background-image: url('images/1.jpg'); background-size: cover; background-position: center 35%;"></div>
                    <div style="padding: 28px 24px 32px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                            <div style="width: 48px; height: 48px; background: var(--primary); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                                <img src="images/5.png" alt="Доставка" style="width: 28px; height: 28px; filter: brightness(0) invert(1);">
                            </div>
                            <h3 style="font-size: 22px; font-weight: 700; color: var(--primary); margin: 0;">Точно в срок</h3>
                        </div>
                        <p style="color: var(--text-muted); line-height: 1.6; margin: 0; font-size: 16px;">
                            Доставка 95% грузов без опозданий. Собственный автопарк и оптимизация маршрутов.
                        </p>
                    </div>
                </div>
                
                <div style="background: white; border-radius: 28px; overflow: hidden; box-shadow: 0 20px 35px -10px rgba(0,0,0,0.05); transition: 0.3s; border: 1px solid #f1f5f9;">
                    <div style="height: 160px; background-image: url('images/7.jpg'); background-size: cover; background-position: center;"></div>
                    <div style="padding: 28px 24px 32px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                            <div style="width: 48px; height: 48px; background: var(--primary); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                                <img src="images/6.png" alt="Страховка" style="width: 35px; height: 35px; filter: brightness(0) invert(1);">
                            </div>
                            <h3 style="font-size: 22px; font-weight: 700; color: var(--primary); margin: 0;">Страхование</h3>
                        </div>
                        <p style="color: var(--text-muted); line-height: 1.6; margin: 0; font-size: 16px;">
                            Полное покрытие рисков. Страхуем груз на 100% стоимости.
                        </p>
                    </div>
                </div>
                
                <div style="background: white; border-radius: 28px; overflow: hidden; box-shadow: 0 20px 35px -10px rgba(0,0,0,0.05); transition: 0.3s; border: 1px solid #f1f5f9;">
                    <div style="height: 160px; background-image: url('images/1.webp'); background-size: cover; background-position: center 25%;"></div>
                    <div style="padding: 28px 24px 32px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                            <div style="width: 48px; height: 48px; background: var(--primary); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                                <img src="images/7.png" alt="Трекинг" style="width: 28px; height: 28px; filter: brightness(0) invert(1);">
                            </div>
                            <h3 style="font-size: 22px; font-weight: 700; color: var(--primary); margin: 0;">Онлайн-контроль</h3>
                        </div>
                        <p style="color: var(--text-muted); line-height: 1.6; margin: 0; font-size: 16px;">
                            Трекинг 24/7. Знайте, где ваш груз в реальном времени.
                        </p>
                    </div>
                </div>
                
                <div style="background: white; border-radius: 28px; overflow: hidden; box-shadow: 0 20px 35px -10px rgba(0,0,0,0.05); transition: 0.3s; border: 1px solid #f1f5f9;">
                    <div style="height: 160px; background-image: url('images/8.jpg'); background-size: cover; background-position: center;"></div>
                    <div style="padding: 28px 24px 32px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                            <div style="width: 48px; height: 48px; background: var(--primary); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                                <img src="images/8.png" alt="Эконом" style="width: 28px; height: 28px; filter: brightness(0) invert(1);">
                            </div>
                            <h3 style="font-size: 22px; font-weight: 700; color: var(--primary); margin: 0;">Сборные грузы</h3>
                        </div>
                        <p style="color: var(--text-muted); line-height: 1.6; margin: 0; font-size: 16px;">
                            Экономия до 30%. Платите только за место в кузове.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section style="padding: 80px 0;">
        <div class="container">
            <h2 class="section-title" style="text-align: center; margin-bottom: 50px;">Основные услуги</h2>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;">
                <?php foreach ($mainServices as $service): ?>
                <div class="service-card" style="position: relative; border-radius: 24px; overflow: hidden; height: 340px; box-shadow: var(--shadow); cursor: pointer;" onclick="window.location.href='services.php#service-<?= $service['id'] ?>'">
                    <div style="position: absolute; width: 100%; height: 100%; background-image: url('<?= htmlspecialchars($service['image']) ?>'); background-size: cover; background-position: center; filter: brightness(0.4); transition: transform 0.5s;"></div>
                    <div style="position: relative; z-index: 2; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; padding: 30px; color: white;">
                        <h3 style="font-size: 26px; margin-bottom: 8px; color: white;"><?= htmlspecialchars($service['title']) ?></h3>
                        <p style="color: rgba(255,255,255,0.9); margin-bottom: 16px;"><?= htmlspecialchars($service['short_desc']) ?></p>
                        <a href="services.php#service-<?= $service['id'] ?>" class="service-link" style="color: white; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;" onclick="event.stopPropagation()">
                            Подробнее <span style="font-size: 20px;">→</span>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if (!empty($latestNews)): ?>
    <section style="padding: 60px 0; background: var(--gray-bg);">
        <div class="container">
            <h2 class="section-title" style="text-align: center; margin-bottom: 40px;">Последние новости</h2>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;">
                <?php foreach ($latestNews as $news): ?>
                <div style="background: white; border-radius: 24px; overflow: hidden; box-shadow: var(--shadow);">
                    <?php if (!empty($news['image'])): ?>
                    <img src="<?= htmlspecialchars($news['image']) ?>" alt="<?= htmlspecialchars($news['title']) ?>" style="width: 100%; height: 200px; object-fit: cover;">
                    <?php endif; ?>
                    <div style="padding: 25px;">
                        <?php if (!empty($news['category'])): ?>
                        <span style="background: var(--accent); color: white; padding: 3px 12px; border-radius: 30px; font-size: 12px; font-weight: 600; display: inline-block; margin-bottom: 15px;">
                            <?= htmlspecialchars($news['category']) ?>
                        </span>
                        <?php endif; ?>
                        <h3 style="font-size: 20px; color: var(--primary); margin-bottom: 10px;"><?= htmlspecialchars($news['title']) ?></h3>
                        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 15px;"><?= htmlspecialchars($news['announcement']) ?></p>
                        <a href="news.php" style="color: var(--accent); font-weight: 600; text-decoration: none;">Читать далее →</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="text-align: center; margin-top: 30px;">
                <a href="news.php" class="btn btn-outline">Все новости</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Форма обратной связи -->
    <div class="feedback-section">
        <div class="container">
            <h2 class="section-title" style="text-align: center;">Остались вопросы?</h2>
            <p class="section-desc" style="text-align: center; margin-bottom: 40px;">Напишите нам — ответим в течение 2 часов</p>
            
            <?php if ($feedbackSuccess): ?>
            <div style="max-width: 600px; margin: 0 auto 20px; background: #22c55e; color: white; padding: 15px; border-radius: 16px; text-align: center;">
                ✓ Сообщение отправлено! Мы свяжемся с вами.
            </div>
            <?php endif; ?>
            
            <?php if ($feedbackError && !$feedbackSuccess): ?>
            <div style="max-width: 600px; margin: 0 auto 20px; background: #ef4444; color: white; padding: 15px; border-radius: 16px; text-align: center;">
                ⚠️ Ошибка при отправке. Проверьте правильность заполнения полей.
            </div>
            <?php endif; ?>
            
            <div class="feedback-form">
                <?php if (isset($userData) && !empty($userData['name'])): ?>
                <div class="welcome-banner">
                    <img src="images/hand.gif" alt="👋" class="hand-gif">
                    <div class="text">
                        <strong>Здравствуйте, <?= htmlspecialchars($userData['name']) ?>!</strong>
                        <p>Ваши контактные данные автоматически подставлены из личного кабинета</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" id="feedbackForm">
                    <div class="form-group">
                        <input type="text" name="name" id="name" placeholder="Ваше имя *" 
                               value="<?= htmlspecialchars($_POST['name'] ?? ($userData['name'] ?? '')) ?>">
                        <div class="error-message" id="nameError">Введите имя (минимум 2 символа)</div>
                    </div>
                    
                    <div class="form-group">
                        <input type="email" name="email" id="email" placeholder="E-mail *" 
                               value="<?= htmlspecialchars($_POST['email'] ?? ($userData['email'] ?? '')) ?>">
                        <div class="error-message" id="emailError">Введите корректный email</div>
                    </div>
                    
                    <div class="form-group">
                        <input type="tel" name="phone" id="phone" placeholder="Телефон *" 
                               value="<?= htmlspecialchars($_POST['phone'] ?? ($userData['phone'] ?? '')) ?>">
                        <div class="error-message" id="phoneError">Введите корректный телефон (минимум 10 цифр)</div>
                    </div>
                    
                    <div class="form-group">
                        <textarea name="message" id="message" rows="4" placeholder="Сообщение *"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        <div class="error-message" id="messageError">Напишите сообщение (минимум 10 символов)</div>
                    </div>
                    
                    <button type="submit" name="send_feedback" class="btn" id="sendFeedback" style="width: 100%;" disabled>Отправить сообщение</button>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div class="footer-logo">ТрансЛогистик</div>
                    <p style="color: #cbd5e1;">© 2026 Транспортная компания.<br>Все права защищены.</p>
                </div>
                <div class="footer-links">
                    <h4 style="color: white; margin-bottom: 16px;">Навигация</h4>
                    <a href="index.php">Главная</a>
                    <a href="services.php">Услуги</a>
                    <a href="about.php">О компании</a>
                    <a href="news.php">Новости</a>
                </div>
                <div class="footer-links">
                    <h4 style="color: white; margin-bottom: 16px;">Инфо</h4>
                    <a href="reviews.php">Отзывы</a>
                    <a href="contacts.php">Контакты</a>
                    <a href="login.php">Авторизация</a>
                </div>
                <div>
                    <h4 style="color: white; margin-bottom: 16px;">Контакты</h4>
                    <p style="color: #cbd5e1;"><?= htmlspecialchars($settings['company_address'] ?? 'г. Ярославль, ул. Строителей, 5') ?></p>
                    <p style="color: #cbd5e1; margin-top: 8px;"><?= htmlspecialchars($settings['company_phone'] ?? '+7 (4852) 00-00-00') ?></p>
                    <p style="color: #cbd5e1;"><?= htmlspecialchars($settings['company_email'] ?? 'info@translogistic.ru') ?></p>
                </div>
            </div>
        </div>
    </footer>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Запрет отрицательных значений в полях вес и объём
    const weightInput = document.getElementById('weight');
    const volumeInput = document.getElementById('volume');
    
    if (weightInput) {
        weightInput.addEventListener('change', function() {
            if (this.value < 0) this.value = 0;
        });
        weightInput.addEventListener('input', function() {
            if (this.value < 0) this.value = 0;
        });
    }
    
    if (volumeInput) {
        volumeInput.addEventListener('change', function() {
            if (this.value < 0) this.value = 0;
        });
        volumeInput.addEventListener('input', function() {
            if (this.value < 0) this.value = 0;
        });
    }
    
    // Калькулятор
    const calcAndOrderBtn = document.getElementById('calcAndOrderBtn');
    if (calcAndOrderBtn) {
        calcAndOrderBtn.addEventListener('click', function() {
            let weight = parseFloat(document.getElementById('weight').value) || 0;
            let volume = parseFloat(document.getElementById('volume').value) || 0;
            
            // Дополнительная проверка
            if (weight < 0) weight = 0;
            if (volume < 0) volume = 0;
            
            let from = document.getElementById('fromCity').value;
            let to = document.getElementById('toCity').value;
            let cargoType = document.getElementById('cargoType').value;
            
            if (weight <= 0 && volume <= 0) {
                alert('Пожалуйста, укажите вес или объём груза');
                return;
            }
            
            let base = 500;
            let distanceFactor = 1.0;
            let cargoMultiplier = 1.0;
            
            if (cargoType === 'Хрупкий') cargoMultiplier = 1.15;
            if (cargoType === 'Опасный') cargoMultiplier = 1.30;
            
            if ((from === 'Москва' && to === 'Ярославль') || (from === 'Ярославль' && to === 'Москва')) distanceFactor = 1.2;
            if ((from === 'Санкт-Петербург' && to === 'Ярославль') || (from === 'Ярославль' && to === 'Санкт-Петербург')) distanceFactor = 1.5;
            if ((from === 'Москва' && to === 'Вологда') || (from === 'Вологда' && to === 'Москва')) distanceFactor = 1.3;
            if ((from === 'Санкт-Петербург' && to === 'Москва') || (from === 'Москва' && to === 'Санкт-Петербург')) distanceFactor = 1.4;
            
            let price = Math.round((weight * 8 + volume * 400 + base) * distanceFactor * cargoMultiplier);
            
            document.getElementById('priceValue').innerText = price;
            document.getElementById('calcResult').classList.add('show');
            
            window.location.href = `order.php?from_city=${encodeURIComponent(from)}&to_city=${encodeURIComponent(to)}&weight=${weight}&volume=${volume}&cargo_type=${encodeURIComponent(cargoType)}&price=${price}`;
        });
    }
        // Валидация формы обратной связи
        const nameInput = document.getElementById('name');
        const emailInput = document.getElementById('email');
        const phoneInput = document.getElementById('phone');
        const messageInput = document.getElementById('message');
        const sendBtn = document.getElementById('sendFeedback');
        
        function validateName() {
            const name = nameInput?.value.trim() || '';
            const errorDiv = document.getElementById('nameError');
            
            if (name.length === 0 || name.length < 2) {
                errorDiv.classList.add('show');
                nameInput.classList.add('invalid');
                nameInput.classList.remove('valid');
                return false;
            } else if (!/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u.test(name)) {
                errorDiv.textContent = 'Имя может содержать только буквы';
                errorDiv.classList.add('show');
                nameInput.classList.add('invalid');
                nameInput.classList.remove('valid');
                return false;
            } else {
                errorDiv.classList.remove('show');
                nameInput.classList.remove('invalid');
                nameInput.classList.add('valid');
                return true;
            }
        }
        
        function validateEmail() {
            const email = emailInput?.value.trim() || '';
            const errorDiv = document.getElementById('emailError');
            const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            
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
        
        function validatePhone() {
    const phone = phoneInput?.value.trim() || '';
    const errorDiv = document.getElementById('phoneError');
    
    // Телефон теперь обязательный
    if (phone.length === 0) {
        errorDiv.textContent = 'Введите номер телефона';
        errorDiv.classList.add('show');
        phoneInput.classList.add('invalid');
        phoneInput.classList.remove('valid');
        return false;
    }
    
    const phoneClean = phone.replace(/[^0-9+]/g, '');
    if (phoneClean.length < 10) {
        errorDiv.textContent = 'Введите корректный номер (минимум 10 цифр)';
        errorDiv.classList.add('show');
        phoneInput.classList.add('invalid');
        phoneInput.classList.remove('valid');
        return false;
    } else {
        errorDiv.classList.remove('show');
        phoneInput.classList.remove('invalid');
        phoneInput.classList.add('valid');
        return true;
    }
}
        
        function validateMessage() {
            const message = messageInput?.value.trim() || '';
            const errorDiv = document.getElementById('messageError');
            
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
        
        function checkFormValidity() {
            const isValid = validateName() && validateEmail() && validatePhone() && validateMessage();
            if (sendBtn) sendBtn.disabled = !isValid;
            return isValid;
        }
        
        nameInput?.addEventListener('input', checkFormValidity);
        emailInput?.addEventListener('input', checkFormValidity);
        phoneInput?.addEventListener('input', checkFormValidity);
        messageInput?.addEventListener('input', checkFormValidity);
        
        setTimeout(checkFormValidity, 100);
    });
    </script>
</body>
</html>