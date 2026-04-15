// Калькулятор
document.addEventListener('DOMContentLoaded', function() {
    const calcBtn = document.getElementById('calcBtn');
    if (calcBtn) {
        calcBtn.addEventListener('click', function() {
            let weight = parseFloat(document.getElementById('weight').value) || 0;
            let volume = parseFloat(document.getElementById('volume').value) || 0;
            let from = document.getElementById('fromCity').value;
            let to = document.getElementById('toCity').value;
            let base = 500;
            let distanceFactor = 1.0;
            
            if (from.includes('Москва') && to.includes('Ярославль')) distanceFactor = 1.2;
            if (from.includes('Ярославль') && to.includes('Москва')) distanceFactor = 1.2;
            if (from.includes('Санкт-Петербург') || to.includes('Санкт-Петербург')) distanceFactor = 1.5;
            
            let price = Math.round((weight * 8 + volume * 400 + base) * distanceFactor);
            document.getElementById('priceValue').innerText = price;
            document.getElementById('calcResult').classList.add('show');
        });
    }

    // Трекер
    const trackBtn = document.getElementById('trackBtn');
    if (trackBtn) {
        trackBtn.addEventListener('click', function() {
            let trackNum = document.getElementById('trackNumber').value.trim();
            let statusSpan = document.getElementById('trackStatus');
            let trackDiv = document.getElementById('trackResult');
            
            if (trackNum === 'TR-2026-001') {
                statusSpan.innerText = 'В пути';
                trackDiv.style.background = '#fef9c3';
            } else if (trackNum === 'TR-2026-100') {
                statusSpan.innerText = 'Доставлен';
                trackDiv.style.background = '#dcfce7';
            } else {
                statusSpan.innerText = 'Не найден';
                trackDiv.style.background = '#fee2e2';
            }
            trackDiv.classList.add('show');
        });
    }

    // Валидация
    const sendBtn = document.getElementById('sendFeedback');
    if (sendBtn) {
        sendBtn.addEventListener('click', function(e) {
            e.preventDefault();
            let isValid = true;
            let name = document.getElementById('name').value.trim();
            let phone = document.getElementById('phone').value.trim();
            let email = document.getElementById('email').value.trim();
            let message = document.getElementById('message').value.trim();

            document.querySelectorAll('.error-message').forEach(el => el.classList.remove('show'));

            if (name === '') { 
                document.getElementById('nameError').classList.add('show'); 
                isValid = false; 
            }
            if (phone === '' || phone.length < 10) { 
                document.getElementById('phoneError').classList.add('show'); 
                isValid = false; 
            }
            if (email === '' || !email.includes('@')) { 
                document.getElementById('emailError').classList.add('show'); 
                isValid = false; 
            }
            if (message === '') { 
                document.getElementById('messageError').classList.add('show'); 
                isValid = false; 
            }

            if (isValid) {
                document.getElementById('successMsg').style.display = 'block';
                setTimeout(() => document.getElementById('successMsg').style.display = 'none', 3000);
                document.getElementById('feedbackForm').reset();
            }
        });
    }
        // ===== ОБЩИЕ ФУНКЦИИ ДЛЯ ВСЕХ СТРАНИЦ =====
    
    // Функция для раскрытия деталей услуги (services.php)
    window.toggleDetail = function(detailId, serviceId) {
        const detailBlock = document.getElementById(detailId);
        const serviceBlock = document.getElementById(serviceId);
        
        if (!detailBlock || !serviceBlock) return;
        
        if (!detailBlock.classList.contains('show')) {
            document.querySelectorAll('.service-detail.show').forEach(block => {
                block.classList.remove('show');
                setTimeout(() => {
                    block.style.display = 'none';
                }, 300);
            });
            
            document.querySelectorAll('.service-detailed').forEach(block => {
                block.classList.remove('hidden');
            });
            
            serviceBlock.classList.add('hidden');
            detailBlock.style.display = 'block';
            setTimeout(() => {
                detailBlock.classList.add('show');
                detailBlock.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 10);
        } else {
            detailBlock.classList.remove('show');
            serviceBlock.classList.remove('hidden');
            setTimeout(() => {
                detailBlock.style.display = 'none';
            }, 300);
        }
    };
    
    // Функция для раскрытия новости (news.php)
    window.toggleNewsDetail = function(detailId, newsId) {
        const detailBlock = document.getElementById(detailId);
        const newsBlock = document.getElementById(newsId);
        
        if (!detailBlock || !newsBlock) return;
        
        if (!detailBlock.classList.contains('show')) {
            document.querySelectorAll('.news-detail.show').forEach(block => {
                block.classList.remove('show');
                setTimeout(() => {
                    block.style.display = 'none';
                }, 300);
            });
            
            document.querySelectorAll('.news-detailed').forEach(block => {
                block.classList.remove('hidden');
            });
            
            newsBlock.classList.add('hidden');
            detailBlock.style.display = 'block';
            setTimeout(() => {
                detailBlock.classList.add('show');
                detailBlock.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 10);
        } else {
            detailBlock.classList.remove('show');
            newsBlock.classList.remove('hidden');
            setTimeout(() => {
                detailBlock.style.display = 'none';
            }, 300);
        }
    };
    
    // Функция для раскрытия информации о сотруднике (about.php)
    window.toggleTeamDetail = function(button) {
        const teamBlock = button.closest('.team-detailed');
        const detailBlock = teamBlock.querySelector('.team-detail');
        
        if (!detailBlock) return;
        
        detailBlock.classList.toggle('show');
        
        if (detailBlock.classList.contains('show')) {
            button.textContent = 'Скрыть информацию';
        } else {
            button.textContent = 'Подробнее о сотруднике';
        }
    };
    
    // Инициализация для страницы about.php
    if (document.querySelectorAll('.team-detail').length) {
        document.querySelectorAll('.team-detail').forEach(block => {
            block.classList.remove('show');
        });
    }
    
    // Инициализация для страницы news.php и services.php
    if (document.querySelectorAll('.news-detail').length) {
        document.querySelectorAll('.news-detail').forEach(block => {
            block.style.display = 'none';
        });
    }
    
    if (document.querySelectorAll('.service-detail').length) {
        document.querySelectorAll('.service-detail').forEach(block => {
            block.style.display = 'none';
        });
    }
});