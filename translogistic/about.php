<?php
session_start();
require_once 'config/config.php';

//информация о компании из настроек
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Данные команды
$team = [
    [
        'name' => 'Иван Петров',
        'position' => 'Генеральный директор',
        'experience' => '12 лет',
        'phone' => '+7 (4852) 00-01',
        'email' => 'i.petrov@translogistic.ru',
        'photo' => 'images/32.jpg',
        'color' => 'var(--primary)'
    ],
    [
        'name' => 'Елена Соколова',
        'position' => 'Руководитель отдела логистики',
        'experience' => '8 лет',
        'phone' => '+7 (4852) 00-02',
        'email' => 'e.sokolova@translogistic.ru',
        'photo' => 'images/44.jpg',
        'color' => 'var(--accent)'
    ],
    [
        'name' => 'Алексей Смирнов',
        'position' => 'Ведущий менеджер',
        'experience' => '5 лет',
        'phone' => '+7 (4852) 00-03',
        'email' => 'a.smirnov@translogistic.ru',
        'photo' => 'images/75.jpg',
        'color' => 'var(--primary-light)'
    ]
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>О компании — ТрансЛогистик</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Стили для команды */
        .team-detailed {
            scroll-margin-top: 100px;
            margin-bottom: 20px;
        }
        
        .team-card-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 24px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .team-avatar-card {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            background: var(--primary);
        }
        .team-avatar-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .team-info-content {
            flex: 1;
            min-width: 180px;
        }
        
        .team-info-content h3 {
            font-size: 22px;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .team-info-content p {
            color: var(--text-muted);
            margin-bottom: 12px;
        }
        
        .team-detail-btn {
            display: inline-block;
            padding: 8px 20px;
            background-color: var(--accent);
            color: white;
            font-weight: 600;
            font-size: 14px;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .team-detail-btn:hover {
            background-color: var(--accent-hover);
            transform: translateY(-2px);
        }
        
        /* Детальный блок */
        .team-detail {
            display: none;
            background: white;
            border-radius: 20px;
            margin-top: 15px;
            padding: 20px;
            border: 1px solid var(--gray-border);
            background: #f8fafc;
        }
        
        .team-detail.show {
            display: block;
        }
        
        /* Контент детального блока */
        .detail-info {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            align-items: flex-start;
        }
        
        .detail-info-block {
            flex: 1;
            min-width: 180px;
        }
        
        .detail-info-block h4 {
            color: var(--primary);
            margin-bottom: 12px;
            font-size: 16px;
        }
        
        .detail-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .detail-list li {
            padding: 8px 0;
            border-bottom: 1px solid var(--gray-border);
        }
        
        .detail-list li:last-child {
            border-bottom: none;
        }
        
        .detail-list li strong {
            color: var(--primary);
            width: 100px;
            display: inline-block;
        }
        
        .detail-contact-buttons {
            margin-top: 15px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .btn-call, .btn-email-detail {
            display: inline-block;
            padding: 8px 20px;
            font-weight: 600;
            font-size: 13px;
            border-radius: 40px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-call {
            background-color: var(--accent);
            color: white;
        }
        
        .btn-call:hover {
            background-color: var(--accent-hover);
            transform: translateY(-2px);
        }
        
        .btn-email-detail {
            background: transparent;
            border: 1.5px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-email-detail:hover {
            background: var(--primary);
            color: white;
        }
        
        .stats-block {
            margin-top: 40px;
            padding: 25px;
            background: #f0f9ff;
            border-radius: var(--radius-sm);
            border-left: 5px solid var(--accent);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 15px;
        }
        
        .stat-item p:first-child {
            font-size: 32px;
            font-weight: 700;
            color: var(--accent);
            line-height: 1.2;
        }
        
        .stat-item p:last-child {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .sidebar-btn {
            width: 100%;
            margin-top: 20px;
            padding: 14px;
            background: var(--accent);
            border: none;
            border-radius: 40px;
            color: white;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .sidebar-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .team-card-item {
                flex-direction: column;
                text-align: center;
            }
            
            .team-info-content {
                text-align: center;
            }
            
            .detail-info {
                flex-direction: column;
            }
            
            .detail-info-block {
                width: 100%;
            }
            
            .detail-list li strong {
                width: auto;
                display: block;
                margin-bottom: 4px;
            }
            
            .detail-contact-buttons {
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .team-detail-btn {
                white-space: normal;
                width: 100%;
                text-align: center;
            }
            
            .btn-call, .btn-email-detail {
                width: 100%;
                text-align: center;
            }
        }

        /* ===== ДОП АДАПТАЦИЯ ===== */

@media (max-width: 768px) {
    /* Левая колонка с текстом */
    .about-text h2 {
        font-size: 22px !important;
    }
    
    .about-text p {
        font-size: 14px !important;
        line-height: 1.5 !important;
    }
    
    /* Блок статистики */
    .stats-block {
        padding: 20px !important;
    }
    
    .stats-block h3 {
        font-size: 18px !important;
        text-align: center !important;
    }
    
    .stat-item p:first-child {
        font-size: 24px !important;
    }
    
    .stat-item p:last-child {
        font-size: 12px !important;
    }
}

@media (max-width: 480px) {
    .about-text h2 {
        font-size: 20px !important;
    }
    
    .stats-grid {
        grid-template-columns: 1fr !important;
        text-align: center !important;
        gap: 15px !important;
    }
    
    .stat-item p:first-child {
        font-size: 22px !important;
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
            <a href="index.php">Главная</a> / <span>О компании</span>
        </div>
        
        <h1 class="page-title">О компании</h1>
        
        <div class="two-columns">
            <!-- Левая колонка с текстом -->
            <div class="content-block">
                <div class="about-text">
                    <h2 style="color: var(--primary); margin-bottom: 20px;">История компании</h2>
                    <p style="margin-bottom: 20px;">
                        ТрансЛогистик был основан в 2010 году в Ярославле. Мы начинали с одного автомобиля 
                        и небольшого склада, но благодаря ответственному подходу и ориентации на потребности 
                        клиентов, нам удалось вырасти в региональную транспортную компанию с собственным 
                        автопарком и филиалами в соседних областях.
                    </p>
                    
                    <h2 style="color: var(--primary); margin: 30px 0 20px;">Миссия и ценности</h2>
                    <p style="margin-bottom: 15px;">
                        <strong>Наша миссия:</strong> обеспечивать надежную и быструю доставку грузов, 
                        делая логистику простой и прозрачной для каждого клиента.
                    </p>
                    <p>
                        <strong>Наши ценности:</strong> честность, пунктуальность, ориентация на клиента 
                        и постоянное развитие.
                    </p>
                    
                    <div class="stats-block">
                        <h3 style="color: var(--primary); margin-bottom: 15px;">Цифры и факты</h3>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <p>15+</p>
                                <p>лет на рынке</p>
                            </div>
                            <div class="stat-item">
                                <p>100 000+</p>
                                <p>довольных клиентов</p>
                            </div>
                            <div class="stat-item">
                                <p>50+</p>
                                <p>единиц техники</p>
                            </div>
                            <div class="stat-item">
                                <p>24/7</p>
                                <p>поддержка</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Правая колонка с командой -->
            <div class="sidebar">
                <h3 style="color: var(--primary); margin-bottom: 24px; font-size: 22px;">Наша команда</h3>
                
                <?php foreach ($team as $index => $member): ?>
                <div class="team-detailed">
                    <!-- Карточка сотрудника -->
                    <div class="team-card-item">
                        <div class="team-avatar-card">
                            <img src="<?= htmlspecialchars($member['photo']) ?>" 
                                 alt="<?= htmlspecialchars($member['name']) ?>">
                        </div>
                        <div class="team-info-content">
                            <h3><?= htmlspecialchars($member['name']) ?></h3>
                            <p><?= htmlspecialchars($member['position']) ?></p>
                            <button class="team-detail-btn" onclick="toggleTeamDetail(this)">
                                Подробнее о сотруднике
                            </button>
                        </div>
                    </div>

                    <!-- Детальная информация появляется снизу -->
                    <div class="team-detail">
                        <div class="detail-info">
                            <div class="detail-info-block">
                                <h4>Информация о сотруднике</h4>
                                <ul class="detail-list">
                                    <li><strong>Должность:</strong> <?= htmlspecialchars($member['position']) ?></li>
                                    <li><strong>Стаж работы:</strong> <?= $member['experience'] ?></li>
                                    <li><strong>Телефон:</strong> <?= $member['phone'] ?></li>
                                    <li><strong>Email:</strong> <?= $member['email'] ?></li>
                                </ul>
                            </div>
                            <div class="detail-info-block">
                                <h4>Свяжитесь с сотрудником</h4>
                                <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 12px;">
                                    По любым вопросам вы можете связаться с <?= htmlspecialchars($member['name']) ?> 
                                </p>
                                <div class="detail-contact-buttons">
                                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $member['phone']) ?>" class="btn-call">Позвонить</a>
                                    <a href="mailto:<?= $member['email'] ?>" class="btn-email-detail">Написать</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <button class="sidebar-btn" onclick="alert('Спасибо за интерес к нашей команде! Мы свяжемся с вами.')">
                    ✉ Связаться с командой
                </button>
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

   <script src="script.js"></script>
</body>
</html>