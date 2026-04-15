<?php
session_start();
require_once 'config/config.php';

//авторизация для отправки отзыва
$is_logged_in = isset($_SESSION['user_id']);

// данные авторизованного пользователя
$userData = null;
if ($is_logged_in) {
    $stmt = $pdo->prepare("SELECT id, name, surname, email, phone FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
}

// пагинация
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 3;
$offset = ($page - 1) * $limit;

// сортировка
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$orderBy = ($sort == 'rating') ? 'rating DESC, created_at DESC' : 'created_at DESC';

//общее колво опубликованных отзывов
$totalStmt = $pdo->query("SELECT COUNT(*) FROM reviews WHERE is_published = 1");
$totalReviews = $totalStmt->fetchColumn();
$totalPages = ceil($totalReviews / $limit);

//отзывы для текущей страницы
$stmt = $pdo->prepare("SELECT * FROM reviews WHERE is_published = 1 ORDER BY $orderBy LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$reviews = $stmt->fetchAll();

//статистика рейтингов
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        AVG(rating) as avg_rating,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
    FROM reviews 
    WHERE is_published = 1
");
$stats = $statsStmt->fetch();

//форматирование даты
function formatDate($date) {
    $months = [
        'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
        'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'
    ];
    $d = new DateTime($date);
    return $d->format('d') . ' ' . $months[$d->format('n') - 1] . ' ' . $d->format('Y');
}

//генер звезд
function getStars($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= '<span class="star ' . ($i <= $rating ? 'filled' : '') . '">★</span>';
    }
    return $stars;
}

// обработка отправки формы !!только для авторизованных!!
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    // проверка авторизации
    if (!$is_logged_in) {
        header('Location: login.php?redirect=reviews');
        exit;
    }
    
    $name = trim($_POST['name']);
    $city = trim($_POST['city']);
    $rating = (int)$_POST['rating'];
    $text = trim($_POST['text']);
    $cargo = trim($_POST['cargo']);
    
    if (empty($name) || strlen($name) < 2) {
        $errors['name'] = 'Введите имя (минимум 2 символа)';
    }
    
    if (empty($text) || strlen($text) < 10) {
        $errors['text'] = 'Напишите отзыв (минимум 10 символов)';
    }
    
    if ($rating < 1 || $rating > 5) {
        $errors['rating'] = 'Поставьте оценку';
    }
    
    if (empty($errors)) {
        try {
            $user_id = $_SESSION['user_id'];
            $stmt = $pdo->prepare("INSERT INTO reviews (user_id, name, city, rating, text, cargo_info, is_published) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$user_id, $name, $city, $rating, $text, $cargo]);
            $success = true;
            $_POST = [];
            
            // Обновляем статистику
            header("Location: reviews.php?sort=$sort&page=$page&success=1");
            exit;
        } catch (PDOException $e) {
            $errors['db'] = 'Ошибка при сохранении отзыва';
        }
    }
}

//сообщение об успехе после редиректа
if (isset($_GET['success'])) {
    $success = true;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отзывы — ТрансЛогистик</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .reviews-list {
            display: flex;
            flex-direction: column;
            gap: 25px;
            margin-bottom: 50px;
        }

        .review-card {
            background: white;
            border: 1px solid var(--gray-border);
            border-radius: 24px;
            padding: 30px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            animation: fadeIn 0.5s ease;
        }

        .review-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 30px -10px rgba(0,0,0,0.15);
            border-color: var(--accent);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .review-author {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .review-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 24px;
            flex-shrink: 0;
        }

        .review-author-info h3 {
            font-size: 20px;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .review-rating {
            display: flex;
            gap: 3px;
        }

        .star {
            color: var(--text-muted);
            font-size: 18px;
            cursor: default;
        }

        .star.filled {
            color: var(--accent);
        }

        .review-date {
            color: var(--text-muted);
            font-size: 14px;
        }

        .review-text {
            color: var(--text-dark);
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .review-cargo {
            color: var(--primary);
            font-weight: 500;
            display: inline-block;
            background: var(--gray-bg);
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 14px;
        }

        .sort-bar {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-bottom: 30px;
            align-items: center;
            flex-wrap: wrap;
        }

        .sort-label {
            color: var(--text-muted);
        }

        .sort-link {
            color: var(--text-muted);
            text-decoration: none;
            padding-bottom: 3px;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }

        .sort-link.active {
            color: var(--primary);
            font-weight: 600;
            border-bottom-color: var(--accent);
        }

        .sort-link:hover {
            color: var(--accent);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 40px 0 30px;
            flex-wrap: wrap;
        }

        .pagination-item {
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: 1px solid transparent;
        }

        .pagination-item.active {
            background: var(--primary);
            color: white;
        }

        .pagination-item:not(.active):not(.dots) {
            background: var(--gray-bg);
            color: var(--primary);
        }

        .pagination-item:not(.active):not(.dots):hover {
            background: var(--accent);
            color: white;
            transform: translateY(-2px);
        }

        .rating-input {
            margin-bottom: 25px;
            text-align: center;
        }

        .rating-label {
            color: var(--text-muted);
            margin-right: 15px;
            display: inline-block;
        }

        .rating-stars {
            display: inline-flex;
            gap: 8px;
            vertical-align: middle;
        }

        .rating-star {
            font-size: 32px;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-muted);
        }

        .rating-star:hover,
        .rating-star.hover,
        .rating-star.selected {
            color: var(--accent);
            transform: scale(1.1);
        }

        .review-form-section {
            background: var(--gray-bg);
            border-radius: 32px;
            padding: 40px;
            margin-top: 30px;
        }

        .review-form-title {
            color: var(--primary);
            font-size: 28px;
            margin-bottom: 20px;
            text-align: center;
        }

        .review-form {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 28px;
            box-shadow: var(--shadow);
        }

        .review-form .form-group {
            margin-bottom: 24px;
        }

        .review-form .form-group input,
        .review-form .form-group textarea {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid var(--gray-border);
            border-radius: 16px;
            font-size: 15px;
            transition: all 0.3s;
            background: white;
            font-family: inherit;
        }

        .review-form .form-group input:focus,
        .review-form .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(245,158,11,0.1);
        }

        .review-form .form-group input.invalid,
        .review-form .form-group textarea.invalid {
            border-color: #ef4444;
            background: #fef2f2;
        }

        .review-form .form-group input.valid,
        .review-form .form-group textarea.valid {
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

        .auth-required-banner {
            background: #fef9e6;
            border: 1px solid var(--accent);
            border-radius: 24px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
        }

        .auth-required-banner h3 {
            color: var(--primary);
            margin-bottom: 15px;
        }

        .auth-required-banner p {
            color: var(--text-muted);
            margin-bottom: 20px;
        }

        .alert-success {
            background: #22c55e;
            color: white;
            padding: 15px;
            border-radius: 16px;
            margin-bottom: 20px;
            text-align: center;
            animation: slideUp 0.4s ease;
        }

        .alert-error {
            background: #ef4444;
            color: white;
            padding: 15px;
            border-radius: 16px;
            margin-bottom: 20px;
            text-align: center;
            animation: slideUp 0.4s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .rating-summary {
            background: var(--primary);
            color: white;
            border-radius: 24px;
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
        }

        .average-rating {
            text-align: center;
        }

        .average-number {
            font-size: 48px;
            font-weight: 800;
            line-height: 1;
        }

        .average-stars {
            margin: 10px 0;
        }

        .total-reviews {
            font-size: 14px;
            opacity: 0.9;
        }

        .rating-bars {
            flex: 1;
            min-width: 200px;
        }

        .rating-bar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .rating-bar-label {
            width: 30px;
            color: rgba(255,255,255,0.9);
        }

        .rating-bar-bg {
            flex: 1;
            height: 8px;
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
            overflow: hidden;
        }

        .rating-bar-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 4px;
        }

        .rating-bar-count {
            width: 30px;
            font-size: 14px;
            color: rgba(255,255,255,0.9);
        }

        @media (max-width: 768px) {
            .review-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .rating-summary {
                flex-direction: column;
                text-align: center;
            }
            
            .review-form {
                padding: 25px;
            }
            
            .rating-star {
                font-size: 28px;
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

/* Карточки отзывов */
@media (max-width: 768px) {
    .review-card {
        padding: 20px !important;
    }
    
    .review-author {
        gap: 10px !important;
    }
    
    .review-avatar {
        width: 45px !important;
        height: 45px !important;
        font-size: 18px !important;
    }
    
    .review-author-info h3 {
        font-size: 16px !important;
    }
    
    .review-rating .star {
        font-size: 14px !important;
    }
    
    .review-date {
        font-size: 12px !important;
    }
    
    .review-text {
        font-size: 14px !important;
        margin-top: 10px !important;
    }
    
    .review-cargo {
        font-size: 12px !important;
        padding: 3px 12px !important;
    }
}

/* Панель сортировки */
@media (max-width: 768px) {
    .sort-bar {
        justify-content: center !important;
        gap: 10px !important;
    }
    
    .sort-label {
        font-size: 13px !important;
    }
    
    .sort-link {
        font-size: 13px !important;
    }
}

/* Пагинация */
@media (max-width: 768px) {
    .pagination {
        gap: 6px !important;
    }
    
    .pagination-item {
        min-width: 35px !important;
        height: 35px !important;
        font-size: 13px !important;
    }
}

/* Блок статистики рейтингов */
@media (max-width: 768px) {
    .rating-summary {
        padding: 20px !important;
    }
    
    .average-number {
        font-size: 36px !important;
    }
    
    .average-stars .star {
        font-size: 16px !important;
    }
    
    .rating-bar-label {
        font-size: 12px !important;
        width: 25px !important;
    }
    
    .rating-bar-count {
        font-size: 12px !important;
        width: 25px !important;
    }
}

/* Секция формы отзыва */
@media (max-width: 768px) {
    .review-form-section {
        padding: 25px 15px !important;
    }
    
    .review-form-title {
        font-size: 22px !important;
    }
    
    .review-form {
        padding: 20px !important;
    }
    
    .welcome-banner {
        padding: 12px 15px !important;
        gap: 12px !important;
    }
    
    .welcome-banner .hand-gif {
        width: 40px !important;
        height: 40px !important;
    }
    
    .welcome-banner .text strong {
        font-size: 15px !important;
    }
    
    .welcome-banner .text p {
        font-size: 12px !important;
    }
    
    /* Рейтинг в форме */
    .rating-input {
        margin-bottom: 20px !important;
    }
    
    .rating-label {
        font-size: 14px !important;
        display: block !important;
        margin-bottom: 8px !important;
    }
    
    .rating-star {
        font-size: 24px !important;
    }
    
    /* Поля формы */
    .review-form .form-group input,
    .review-form .form-group textarea {
        padding: 10px 14px !important;
        font-size: 14px !important;
    }
    
    /* Кнопка отправки */
    .review-form .btn {
        padding: 12px !important;
        font-size: 14px !important;
    }
}

/*320px*/
@media (max-width: 480px) {
    .review-card {
        padding: 15px !important;
    }
    
    .review-author {
        flex-wrap: wrap !important;
    }
    
    .review-avatar {
        width: 40px !important;
        height: 40px !important;
    }
    
    .review-author-info h3 {
        font-size: 15px !important;
    }
    
    .review-text {
        font-size: 13px !important;
    }
    
    .review-form {
        padding: 15px !important;
    }
    
    .rating-stars {
        gap: 5px !important;
    }
    
    .rating-star {
        font-size: 22px !important;
    }
    
    .auth-required-banner {
        padding: 20px !important;
    }
    
    .auth-required-banner h3 {
        font-size: 16px !important;
    }
    
    .auth-required-banner p {
        font-size: 13px !important;
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
                <a href="reviews.php" style="color: var(--accent);">Отзывы</a>
                <a href="contacts.php">Контакты</a>

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
            <a href="index.php">Главная</a> / <span>Отзывы</span>
        </div>
        
        <h1 class="page-title">Отзывы наших клиентов</h1>
        
        <div class="content-single">
            <!-- Блок статистики рейтингов показывается только если есть отзывы -->
            <?php if ($stats['total'] > 0): ?>
            <div class="rating-summary">
                <div class="average-rating">
                    <div class="average-number"><?= number_format($stats['avg_rating'], 1) ?></div>
                    <div class="average-stars"><?= getStars(round($stats['avg_rating'])) ?></div>
                    <div class="total-reviews">на основе <?= $stats['total'] ?> отзывов</div>
                </div>
                <div class="rating-bars">
                    <?php for ($i = 5; $i >= 1; $i--): 
                        $count = $stats['rating_' . $i] ?? 0;
                        $percent = $stats['total'] > 0 ? round($count / $stats['total'] * 100) : 0;
                    ?>
                    <div class="rating-bar-item">
                        <span class="rating-bar-label"><?= $i ?> ★</span>
                        <div class="rating-bar-bg">
                            <div class="rating-bar-fill" style="width: <?= $percent ?>%"></div>
                        </div>
                        <span class="rating-bar-count"><?= $count ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
            <!-- панель сортировки -->
            <div class="sort-bar">
                <span class="sort-label">Сортировать:</span>
                <a href="?sort=date" class="sort-link <?= $sort == 'date' ? 'active' : '' ?>">по дате</a>
                <a href="?sort=rating" class="sort-link <?= $sort == 'rating' ? 'active' : '' ?>">по рейтингу</a>
            </div>
            <!-- список отзывов -->
            <div class="reviews-list">
                <?php if (empty($reviews)): ?>
                    <p style="text-align: center; padding: 50px; color: var(--text-muted);">Пока нет отзывов. Будьте первым!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div class="review-author">
                                <div class="review-avatar"><?= mb_substr($review['name'], 0, 1) ?></div>
                                <div class="review-author-info">
                                    <h3><?= htmlspecialchars($review['name']) ?></h3>
                                    <div class="review-rating">
                                        <?= getStars($review['rating']) ?>
                                    </div>
                                </div>
                            </div>
                            <span class="review-date"><?= formatDate($review['created_at']) ?></span>
                        </div>
                        <p class="review-text"><?= nl2br(htmlspecialchars($review['text'])) ?></p>
                        <?php if (!empty($review['cargo_info'])): ?>
                        <span class="review-cargo">Груз: <?= htmlspecialchars($review['cargo_info']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($review['city'])): ?>
                        <span style="margin-left: 10px; color: var(--text-muted); font-size: 14px;">г. <?= htmlspecialchars($review['city']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <!-- пагинация -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&sort=<?= $sort ?>" class="pagination-item <?= $i == $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            
            <!-- Форма добавления отзыва (только для авторизованных) -->
            <div class="review-form-section">
                <h2 class="review-form-title">Оставить отзыв</h2>
                
                <?php if (!$is_logged_in): ?>
                    <!-- Блок для неавторизованных пользователей -->
                <div class="auth-required-banner">
                    <h3>🔒 Только для авторизованных пользователей</h3>
                    <p>Чтобы оставить отзыв, необходимо <a href="login.php?redirect=reviews" style="color: var(--accent); font-weight: 600;">войти</a> или <a href="login.php?tab=register" style="color: var(--accent); font-weight: 600;">зарегистрироваться</a>.</p>
                    <a href="login.php?redirect=reviews" class="btn btn-primary" style="background: var(--accent);">Войти / Регистрация</a>
                </div>
                <?php else: ?>
                
                <?php if ($success): ?>
                    <!-- Сообщение об успешной отправке -->
                <div class="alert-success" style="max-width: 600px; margin: 0 auto 20px;">
                    ✓ Спасибо! Ваш отзыв опубликован
                </div>
                <?php endif; ?>
                 <!-- Сообщение об ошибке бд -->
                <?php if (!empty($errors['db'])): ?>
                <div class="alert-error" style="max-width: 600px; margin: 0 auto 20px;">
                    <?= $errors['db'] ?>
                </div>
                <?php endif; ?>
                
                <div class="review-form">
                     <!-- Приветственный баннер с именем пользователя -->
                    <div class="welcome-banner">
                        <img src="images/hand.gif" alt="👋" class="hand-gif">
                        <div class="text">
                            <strong>Здравствуйте, <?= htmlspecialchars($userData['name']) ?>!</strong>
                            <p>Ваши данные автоматически подставлены из личного кабинета</p>
                        </div>
                    </div>
                    
                    <form method="POST" id="reviewForm">
                         <!-- Интерактивные звёзды для выбора оценки -->
                        <div class="rating-input">
                            <span class="rating-label">Ваша оценка:</span>
                            <div class="rating-stars" id="ratingStars">
                                <span class="rating-star" data-rating="1">★</span>
                                <span class="rating-star" data-rating="2">★</span>
                                <span class="rating-star" data-rating="3">★</span>
                                <span class="rating-star" data-rating="4">★</span>
                                <span class="rating-star" data-rating="5">★</span>
                            </div>
                            <input type="hidden" name="rating" id="ratingValue" value="5">
                            <div class="error-message" id="ratingError">Поставьте оценку</div>
                        </div>
                        <!-- Поле Имя -->
                        <div class="form-group">
                            <input type="text" name="name" id="reviewName" placeholder="Ваше имя *" 
                                   value="<?= htmlspecialchars($_POST['name'] ?? ($userData['name'] ?? '')) ?>">
                            <div class="error-message" id="nameError">Введите имя (минимум 2 символа)</div>
                        </div>
                        <!-- Поле Город -->
                        <div class="form-group">
                            <input type="text" name="city" id="reviewCity" placeholder="Город (необязательно)" 
                                   value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                        </div>
                        <!-- Поле отзыва -->
                        <div class="form-group">
                            <textarea name="text" id="reviewText" rows="4" placeholder="Ваш отзыв *"><?= htmlspecialchars($_POST['text'] ?? '') ?></textarea>
                            <div class="error-message" id="textError">Напишите отзыв (минимум 10 символов)</div>
                        </div>
                        <!-- Поле инфа о грузе -->
                        <div class="form-group">
                            <input type="text" name="cargo" id="reviewCargo" placeholder="Что перевозили? (необязательно)" 
                                   value="<?= htmlspecialchars($_POST['cargo'] ?? '') ?>">
                        </div>
                        <!-- Кнопка отправки (изначально неактивна, активируется после валидации) -->
                        <button type="submit" name="submit_review" class="btn" id="submitBtn" style="width: 100%;" disabled>Отправить отзыв</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
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
                    <p style="color: #cbd5e1;">+7 (4852) 00-00-00</p>
                    <p style="color: #cbd5e1;">info@translogistic.ru</p>
                </div>
            </div>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const stars = document.querySelectorAll('.rating-star');
        const ratingInput = document.getElementById('ratingValue');
        let currentRating = 5;
        
        if (stars.length && ratingInput) {
        // Клик по звезде устанавливаем оценку
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    currentRating = parseInt(this.getAttribute('data-rating'));
                    ratingInput.value = currentRating;
                   // Подсвечиваем все звёзды до выбранной 
                    stars.forEach(s => s.classList.remove('selected'));
                    for (let i = 0; i < currentRating; i++) {
                        stars[i].classList.add('selected');
                    }
                    
                    validateRating();
                    checkFormValidity();
                });
               // Наведение мыши временная подсветка
                star.addEventListener('mouseenter', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    stars.forEach(s => s.classList.remove('hover'));
                    for (let i = 0; i < rating; i++) {
                        stars[i].classList.add('hover');
                    }
                });
            });
             // Убираем подсветку при уходе мыши с области звёзд
            document.querySelector('.rating-stars').addEventListener('mouseleave', function() {
                stars.forEach(s => s.classList.remove('hover'));
                for (let i = 0; i < currentRating; i++) {
                    stars[i].classList.add('selected');
                }
            });
            // Инициализация подсвечиваем 5 звёзд по умолчанию:)
            for (let i = 0; i < currentRating; i++) {
                stars[i].classList.add('selected');
            }
        }
        // получаем элементы формы
        const nameInput = document.getElementById('reviewName');
        const textInput = document.getElementById('reviewText');
        const submitBtn = document.getElementById('submitBtn');
        // ф-ции валидации
        // имя не пустое минимум 2 символа русские буквы пробелы дефисы
        function validateName() {
            const name = nameInput?.value.trim() || '';
            const errorDiv = document.getElementById('nameError');
            
            if (name.length === 0 || name.length < 2) {
                errorDiv.textContent = 'Введите имя (минимум 2 символа)';
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
        // текст отзыва не пустой 10 символов
        function validateText() {
            const text = textInput?.value.trim() || '';
            const errorDiv = document.getElementById('textError');
            
            if (text.length === 0 || text.length < 10) {
                errorDiv.classList.add('show');
                textInput.classList.add('invalid');
                textInput.classList.remove('valid');
                return false;
            } else {
                errorDiv.classList.remove('show');
                textInput.classList.remove('invalid');
                textInput.classList.add('valid');
                return true;
            }
        }
        // рэйтинг
        function validateRating() {
            const errorDiv = document.getElementById('ratingError');
            if (currentRating < 1 || currentRating > 5) {
                errorDiv.classList.add('show');
                return false;
            } else {
                errorDiv.classList.remove('show');
                return true;
            }
        }
        // общая проверка
        function checkFormValidity() {
            const isValid = validateName() && validateText() && validateRating();
            if (submitBtn) submitBtn.disabled = !isValid;
            return isValid;
        }
        // Вешаем обработчики на поля
        nameInput?.addEventListener('input', checkFormValidity);
        textInput?.addEventListener('input', checkFormValidity);
        // Первоначальная проверка на случай если поля уже заполнены
        setTimeout(checkFormValidity, 100);
    });
    </script>
</body>
</html>