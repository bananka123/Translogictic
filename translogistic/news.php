<?php
session_start();
require_once 'config/config.php';

// Пагинация
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 3;
$offset = ($page - 1) * $limit;

$totalStmt = $pdo->query("SELECT COUNT(*) FROM news WHERE is_published = 1");
$totalNews = $totalStmt->fetchColumn();
$totalPages = ceil($totalNews / $limit);

$stmt = $pdo->prepare("SELECT * FROM news WHERE is_published = 1 ORDER BY published_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$news = $stmt->fetchAll();

function formatDate($date) {
    $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
        'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $d = new DateTime($date);
    return $d->format('d') . ' ' . $months[$d->format('n') - 1] . ' ' . $d->format('Y');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новости — ТрансЛогистик</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .news-detailed {
            scroll-margin-top: 100px;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .news-detailed.hidden {
            opacity: 0;
            transform: translateY(-20px);
            pointer-events: none;
            height: 0;
            margin: 0;
            overflow: hidden;
        }
        
        .news-detail {
            display: none;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.4s ease, transform 0.4s ease;
            background: white;
            border-radius: 24px;
            padding: 40px;
            margin-top: 20px;
            border: 2px solid var(--accent);
        }
        .news-detail.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        
        .close-detail {
            color: var(--text-muted);
            font-size: 24px;
            text-decoration: none;
            transition: transform 0.2s;
            display: inline-block;
        }
        .close-detail:hover {
            transform: scale(1.2);
            color: var(--accent);
        }
        
        .news-image-card {
            width: 120px;
            height: 120px;
            border-radius: 20px;
            overflow: hidden;
            flex-shrink: 0;
        }
        .news-image-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .news-badge {
            background: var(--accent);
            color: white;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .news-full-content {
            line-height: 1.8;
        }
        .news-full-content p {
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .news-image-card {
                width: 100%;
                height: 180px;
            }
        }

        /* ===== ДОП АДАПТАЦИЯ СТР ===== */

/*свёрнутое состояние*/
@media (max-width: 768px) {
    .news-detailed > div[style*="display: flex; gap: 25px"] {
        flex-direction: column !important;
        text-align: center !important;
        padding: 20px !important;
    }
    
    .news-image-card {
        width: 100% !important;
        height: 180px !important;
    }
    
    .news-detailed h3 {
        font-size: 20px !important;
    }
    
    .news-detailed p {
        font-size: 14px !important;
    }
    
    .news-detailed .btn {
        font-size: 13px !important;
        padding: 8px 16px !important;
    }
}

/*развёрнутое состояние*/
@media (max-width: 768px) {
    .news-detail {
        padding: 20px !important;
    }
    
    .news-detail > div[style*="display: flex; justify-content: space-between"] h2 {
        font-size: 20px !important;
    }
    
    /* Картинка в деталях */
    .news-detail img[style*="max-height: 400px"] {
        max-height: 200px !important;
        object-fit: cover !important;
    }
    
    /* Текст новости */
    .news-full-content {
        font-size: 14px !important;
        line-height: 1.6 !important;
    }
    
    .news-full-content p {
        font-size: 14px !important;
    }
    
    /* Блок с датой и категорией */
    .news-detail > div[style*="margin-top: 30px; padding-top: 20px"] {
        margin-top: 20px !important;
        padding-top: 15px !important;
    }
    
    .news-detail .news-badge {
        margin-left: 0 !important;
        margin-top: 8px !important;
        display: inline-block !important;
    }
}

/*320px*/
@media (max-width: 480px) {
    .news-detail {
        padding: 15px !important;
    }
    
    .news-detail h2 {
        font-size: 18px !important;
    }
    
    .news-detail img[style*="max-height: 400px"] {
        max-height: 150px !important;
    }
    
    .news-full-content {
        font-size: 13px !important;
    }
    
    .news-badge {
        font-size: 10px !important;
        padding: 3px 10px !important;
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

    <div class="container">
        <div class="breadcrumbs">
            <a href="index.php">Главная</a> / <span>Новости</span>
        </div>
        
        <h1 class="page-title">Новости компании</h1>
        
        <div class="content-single">
            <?php if (empty($news)): ?>
                <p style="text-align: center; padding: 50px; color: var(--text-muted);">Новостей пока нет</p>
            <?php else: ?>
                <?php foreach ($news as $index => $item): ?>
                <!-- Карточка новости -->
                <div id="news-<?= $item['id'] ?>" class="news-detailed" data-news="<?= $item['id'] ?>">
                    <div style="display: flex; gap: 25px; margin-bottom: 30px; padding: 25px; background: #f8fafc; border-radius: 24px; align-items: center; flex-wrap: wrap;">
                        <div class="news-image-card">
                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                        </div>
                        <div style="flex: 1;">
                            <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 10px; flex-wrap: wrap;">
                                <?php if (!empty($item['category'])): ?>
                                    <span class="news-badge"><?= htmlspecialchars($item['category']) ?></span>
                                <?php endif; ?>
                                <span style="color: var(--text-muted); font-size: 14px;"><?= formatDate($item['published_at']) ?></span>
                            </div>
                            <h3 style="font-size: 24px; color: var(--primary); margin-bottom: 10px;">
                                <?= htmlspecialchars($item['title']) ?>
                            </h3>
                            <p style="color: var(--text-muted); margin-bottom: 15px;">
                                <?= htmlspecialchars($item['announcement']) ?>
                            </p>
                            <a href="#" class="btn" style="display: inline-block; padding: 8px 20px; font-size: 14px;" onclick="toggleNewsDetail('news-detail-<?= $item['id'] ?>', 'news-<?= $item['id'] ?>'); return false;">
                                Читать полностью →
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Детальная информация о новости -->
                <div id="news-detail-<?= $item['id'] ?>" class="news-detail">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                        <h2 style="color: var(--primary); font-size: 28px;"><?= htmlspecialchars($item['title']) ?></h2>
                        <a href="#" class="close-detail" onclick="toggleNewsDetail('news-detail-<?= $item['id'] ?>', 'news-<?= $item['id'] ?>'); return false;">✕</a>
                    </div>
                    
                    <div style="margin-bottom: 25px;">
                        <img src="<?= htmlspecialchars($item['image']) ?>" 
                             alt="<?= htmlspecialchars($item['title']) ?>" 
                             style="width: 100%; max-height: 400px; object-fit: cover; border-radius: 20px;">
                    </div>
                    
                    <div class="news-full-content">
                        <?= nl2br(htmlspecialchars($item['content'])) ?>
                    </div>
                    
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--gray-border);">
                        <span style="color: var(--text-muted);">Опубликовано: <?= formatDate($item['published_at']) ?></span>
                        <?php if (!empty($item['category'])): ?>
                            <span class="news-badge" style="margin-left: 15px;"><?= htmlspecialchars($item['category']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="pagination-item <?= $i == $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
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
                    <p style="color: #cbd5e1;">г. Ярославль, ул. Строителей, 5</p>
                    <p style="color: #cbd5e1;">+7 (4852) 00-00-00</p>
                    <p style="color: #cbd5e1;">info@translogistic.ru</p>
                </div>
            </div>
        </div>
    </footer>

   <script src="script.js"></script>
</body>
</html>