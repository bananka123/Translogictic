<?php
session_start();
require_once 'config/config.php';

// Получаем все активные услуги из базы
$stmt = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC");
$services = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Услуги — ТрансЛогистик</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .service-detailed {
            scroll-margin-top: 100px;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .service-detailed.hidden {
            opacity: 0;
            transform: translateY(-20px);
            pointer-events: none;
            height: 0;
            margin: 0;
            overflow: hidden;
        }
        .service-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 20px;
            background: var(--gray-bg);
        }
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        .feature-list li {
            padding: 8px 0 8px 30px;
            position: relative;
        }
        .feature-list li:before {
            content: "✓";
            color: var(--accent);
            font-weight: bold;
            position: absolute;
            left: 0;
        }
        .price-card {
            background: white;
            border: 1px solid var(--gray-border);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
        }
        
        /* Анимация */
        .service-detail {
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
        .service-detail.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        /* ===== ДОП АДАПТАЦИЯ ===== */

/* Блок с кнопками-якорями */
@media (max-width: 768px) {
    .container div[style*="display: flex; gap: 15px; flex-wrap: wrap"] {
        gap: 10px !important;
    }
    
    .container a.btn-outline[style*="padding: 10px 20px"] {
        padding: 8px 16px !important;
        font-size: 13px !important;
    }
}

/* Карточка услуги свернутое состояние */
@media (max-width: 768px) {
    .service-detailed > div[style*="display: flex; gap: 30px"] {
        flex-direction: column !important;
        text-align: center !important;
        padding: 20px !important;
    }
    
    .service-detailed img[style*="width: 120px"] {
        width: 80px !important;
        height: 80px !important;
    }
    
    .service-detailed h3 {
        font-size: 22px !important;
    }
    
    .service-detailed p {
        font-size: 14px !important;
    }
    
    .service-detailed .btn {
        font-size: 14px !important;
        padding: 8px 20px !important;
    }
}

/* Детальный блок развернутое состояние */
@media (max-width: 768px) {
    .service-detail {
        padding: 20px !important;
    }
    
    .service-detail > div[style*="display: flex; justify-content: space-between"] h2 {
        font-size: 22px !important;
    }
    
    /* Детальная информация картинка и текст в колонку */
    .service-detail > div[style*="display: grid; grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
        gap: 20px !important;
    }
    
    /* Картинка в деталях */
    .service-detail img[style*="height: 250px"] {
        height: 180px !important;
        object-fit: cover !important;
    }
    
    /* Заголовок что входит в услугу? */
    .service-detail h3 {
        font-size: 18px !important;
        margin-top: 10px !important;
    }
    
    /* Список преимуществ */
    .feature-list li {
        font-size: 13px !important;
        padding: 6px 0 6px 25px !important;
    }
    
    /* Блок с ценой */
    .service-detail div[style*="background: var(--gray-bg); border-radius: 16px"] {
        padding: 15px !important;
    }
    
    .service-detail div[style*="background: var(--gray-bg)"] h4 {
        font-size: 16px !important;
    }
    
    .service-detail div[style*="background: var(--gray-bg)"] p {
        font-size: 13px !important;
    }
    
    /* Кнопки в деталях */
    .service-detail > div[style*="margin-top: 30px; display: flex"] {
        flex-direction: column !important;
        gap: 12px !important;
    }
    
    .service-detail .btn {
        width: 100% !important;
        text-align: center !important;
    }
}

/* ===== ПОЧЕМУ ВЫБИРАЮТ НАС ===== */
@media (max-width: 768px) {
    /* Родительский блок */
    .content-single > div[style*="background: linear-gradient"] {
        padding: 30px 20px !important;
    }
    
    .content-single > div[style*="background: linear-gradient"] h2 {
        font-size: 24px !important;
        margin-bottom: 20px !important;
    }
    
    /* Сетка 3 колонки → 1 колонка */
    .content-single > div[style*="background: linear-gradient"] > div[style*="display: grid"] {
        grid-template-columns: 1fr !important;
        gap: 25px !important;
    }
    
    /* Иконки */
    .content-single div[style*="font-size: 48px"] img {
        width: 40px !important;
        height: 40px !important;
    }
    
    /* Заголовки внутри блока */
    .content-single > div[style*="background: linear-gradient"] h3 {
        font-size: 18px !important;
    }
    
    /* Текст */
    .content-single > div[style*="background: linear-gradient"] p {
        font-size: 13px !important;
        padding: 0 10px !important;
    }
}

/*320px*/
@media (max-width: 480px) {
    .service-detail {
        padding: 15px !important;
    }
    
    .service-detail h2 {
        font-size: 18px !important;
    }
    
    .service-detail img[style*="height: 250px"] {
        height: 150px !important;
    }
    
    .feature-list li {
        font-size: 12px !important;
    }
    
    .content-single > div[style*="background: linear-gradient"] {
        padding: 20px 15px !important;
    }
    
    .content-single > div[style*="background: linear-gradient"] h2 {
        font-size: 20px !important;
    }
}
        /* кнопка закрытия */
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
        
        /* Плавный скролл для якорей */
        html {
            scroll-behavior: smooth;
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
            <a href="index.php">Главная</a> / <span>Услуги</span>
        </div>
        
        <h1 class="page-title">Наши услуги</h1>
        
        <!-- Кнопки-якоря с плавным скроллом -->
        <?php if (!empty($services)): ?>
        <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 40px; justify-content: center;">
            <?php foreach ($services as $service): ?>
            <a href="#service-<?= $service['id'] ?>" class="btn btn-outline" style="padding: 10px 20px;" onclick="event.preventDefault(); document.getElementById('service-<?= $service['id'] ?>').scrollIntoView({ behavior: 'smooth', block: 'start' });">
                <?= htmlspecialchars($service['title']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="content-single">
            <div class="services-list">

                <?php if (empty($services)): ?>
                    <p style="text-align: center; padding: 50px; color: var(--text-muted);">Услуги временно не доступны</p>
                <?php else: ?>
                    <?php foreach ($services as $service): ?>
                    <!-- Услуга карточка -->
                    <div id="service-<?= $service['id'] ?>" class="service-detailed" data-service="<?= $service['id'] ?>">
                        <div style="display: flex; gap: 30px; margin-bottom: 40px; padding: 30px; background: #f8fafc; border-radius: 24px; align-items: center; flex-wrap: wrap;">
                            <?php if (!empty($service['icon'])): ?>
                            <img src="<?= htmlspecialchars($service['icon']) ?>" 
                                 alt="<?= htmlspecialchars($service['title']) ?>" 
                                 style="width: 120px; height: 120px; object-fit: cover; border-radius: 20px;">
                            <?php endif; ?>
                            <div style="flex: 1;">
                                <h3 style="font-size: 28px; color: var(--primary); margin-bottom: 10px;">
                                    <?= htmlspecialchars($service['title']) ?>
                                </h3>
                                <p style="color: var(--text-muted); margin-bottom: 15px;">
                                    <?= htmlspecialchars($service['short_desc']) ?>
                                </p>
                                <a href="#" class="btn" style="display: inline-block;" onclick="toggleDetail('detail-<?= $service['id'] ?>', 'service-<?= $service['id'] ?>'); return false;">
                                    Подробнее об услуге
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Детальная информация -->
                    <div id="detail-<?= $service['id'] ?>" class="service-detail">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                            <h2 style="color: var(--primary); font-size: 32px;"><?= htmlspecialchars($service['title']) ?></h2>
                            <a href="#" class="close-detail" onclick="toggleDetail('detail-<?= $service['id'] ?>', 'service-<?= $service['id'] ?>'); return false;">✕</a>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: center">
                            <div>
                                <?php if (!empty($service['image'])): ?>
                                <img src="<?= htmlspecialchars($service['image']) ?>" 
                                     alt="<?= htmlspecialchars($service['title']) ?>" 
                                     style="width: 100%; height: 250px; object-fit: cover; border-radius: 20px;">
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3 style="color: var(--primary); margin-bottom: 15px;">Что входит в услугу:</h3>
                                <?php 
                                $features = explode("\n", $service['full_desc']);
                                ?>
                                <ul class="feature-list">
                                    <?php foreach ($features as $feature): ?>
                                        <?php if (trim($feature)): ?>
                                        <li><?= htmlspecialchars(trim($feature)) ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <?php if (!empty($service['price_from'])): ?>
                                <div style="background: var(--gray-bg); border-radius: 16px; padding: 20px; margin-top: 20px;">
                                    <h4 style="color: var(--primary); margin-bottom: 10px;">Примерная стоимость:</h4>
                                    <p>От <strong><?= number_format($service['price_from'], 0, ',', ' ') ?> ₽</strong></p>
                                    <p style="font-size: 14px; color: var(--text-muted); margin-top: 10px;">* Точный расчет в калькуляторе на главной</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: center;">
                            <a href="index.php#calculator" class="btn">Рассчитать стоимость</a>
                            <a href="contacts.php" class="btn btn-outline">Связаться с менеджером</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Блок преимуществ -->
            <div style="margin-top: 60px; background: linear-gradient(145deg, var(--primary), var(--primary-light)); border-radius: 32px; padding: 50px; color: white;">
                <h2 style="color: white; font-size: 36px; text-align: center; margin-bottom: 30px;">Почему выбирают нас</h2>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;">
                    <div style="text-align: center;">
                        <div style="font-size: 48px; margin-bottom: 15px;">
                            <img src="images/4.1.png" alt="Иконка" style="width: 48px; height: 48px;">
                        </div>
                        <h3 style="color: white; margin-bottom: 10px;">Собственный автопарк</h3>
                        <p style="color: rgba(255,255,255,0.9);">Более 20 единиц техники разной грузоподъемности</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 48px; margin-bottom: 15px;">
                            <img src="images/8.png" alt="Иконка" style="width: 48px; height: 48px;">
                        </div>
                        <h3 style="color: white; margin-bottom: 10px;">Склады в 5 городах</h3>
                        <p style="color: rgba(255,255,255,0.9);">Временное хранение и консолидация грузов</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 48px; margin-bottom: 15px;">
                            <img src="images/5.png" alt="Иконка" style="width: 48px; height: 48px;">
                        </div>
                        <h3 style="color: white; margin-bottom: 10px;">Точность 98%</h3>
                        <p style="color: rgba(255,255,255,0.9);">Доставка строго по графику</p>
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
                    <p style="color: #cbd5e1;">г. Ярославль, ул. Строителей, 5</p>
                    <p style="color: #cbd5e1;">+7 (4852) 00-00-00</p>
                    <p style="color: #cbd5e1;">info@translogistic.ru</p>
                </div>
            </div>
        </div>
    </footer>

    <script>
    window.toggleDetail = function(detailId, serviceId) {
    const detailBlock = document.getElementById(detailId);
    const serviceBlock = document.getElementById(serviceId);
    
    if (!detailBlock || !serviceBlock) return;
    
    if (!detailBlock.classList.contains('show')) {
        document.querySelectorAll('.service-detail.show').forEach(block => {
            block.classList.remove('show');
            setTimeout(() => { block.style.display = 'none'; }, 300);
        });
        document.querySelectorAll('.service-detailed').forEach(block => {
            block.classList.remove('hidden');
        });
        serviceBlock.classList.add('hidden');
        detailBlock.style.display = 'block';
        setTimeout(() => {
            detailBlock.classList.add('show');
        }, 10);
    } else {
        detailBlock.classList.remove('show');
        serviceBlock.classList.remove('hidden');
        setTimeout(() => { detailBlock.style.display = 'none'; }, 300);
    }
};
    </script>
    <script src="script.js"></script>
</body>
</html>