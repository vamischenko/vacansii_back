# Production Deployment Guide

Руководство по развертыванию проекта vakansii-back в production окружении.

## Оглавление

1. [Предварительные требования](#предварительные-требования)
2. [Подготовка сервера](#подготовка-сервера)
3. [Настройка окружения](#настройка-окружения)
4. [Настройка базы данных](#настройка-базы-данных)
5. [Настройка веб-сервера](#настройка-веб-сервера)
6. [Безопасность](#безопасность)
7. [Мониторинг и логирование](#мониторинг-и-логирование)
8. [Checklist перед деплоем](#checklist-перед-деплоем)
9. [Troubleshooting](#troubleshooting)

---

## Предварительные требования

### Системные требования

- **OS**: Ubuntu 20.04+ / CentOS 7+ / Debian 10+
- **PHP**: 7.4 или выше (рекомендуется 8.0+)
- **MySQL**: 5.7+ или MariaDB 10.3+
- **Web Server**: Nginx или Apache
- **Composer**: 2.x
- **Memory**: минимум 512 MB RAM (рекомендуется 1GB+)
- **Disk**: минимум 500 MB свободного места

### PHP расширения

Убедитесь, что установлены следующие расширения:

```bash
php -m | grep -E "pdo|pdo_mysql|mbstring|json|openssl|curl|intl"
```

Если что-то отсутствует, установите:

```bash
# Ubuntu/Debian
sudo apt-get install php-cli php-mbstring php-xml php-mysql php-curl php-json php-intl

# CentOS
sudo yum install php-cli php-mbstring php-xml php-mysqlnd php-curl php-json php-intl
```

---

## Подготовка сервера

### 1. Клонирование репозитория

```bash
cd /var/www
sudo git clone <repository-url> vakansii-back
cd vakansii-back
```

### 2. Установка зависимостей

```bash
composer install --no-dev --optimize-autoloader
```

**Важно**: Флаг `--no-dev` исключает dev-зависимости, уменьшая размер и повышая безопасность.

### 3. Настройка прав доступа

```bash
# Владелец: пользователь веб-сервера (обычно www-data или nginx)
sudo chown -R www-data:www-data /var/www/vakansii-back

# Права на папки
sudo chmod -R 755 /var/www/vakansii-back

# Папки для записи (логи, кеш, ассеты)
sudo chmod -R 775 /var/www/vakansii-back/runtime
sudo chmod -R 775 /var/www/vakansii-back/web/assets
```

---

## Настройка окружения

### 1. Создание .env файла

```bash
cd /var/www/vakansii-back
cp .env.example .env
nano .env  # или используйте vim
```

### 2. Конфигурация .env для production

```env
# Database Configuration
DB_DSN=mysql:host=localhost;dbname=vakansii_prod
DB_USERNAME=vakansii_user
DB_PASSWORD=STRONG_RANDOM_PASSWORD_HERE_min_16_chars!@#$
DB_CHARSET=utf8mb4

# Application Configuration
YII_DEBUG=false
YII_ENV=prod

# CORS Configuration
# ⚠️ КРИТИЧНО: Укажите только разрешенные домены!
CORS_ORIGIN=https://yourdomain.com,https://www.yourdomain.com

# Rate Limiting Configuration
# Ограничение: 100 запросов в час на IP
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=3600
```

### 3. Безопасность .env файла

```bash
# Установите права только для чтения владельцем
sudo chmod 600 /var/www/vakansii-back/.env

# Убедитесь, что .env не отслеживается git
grep -q "^\.env$" .gitignore || echo ".env" >> .gitignore
```

---

## Настройка базы данных

### 1. Создание базы данных

```bash
mysql -u root -p
```

```sql
-- Создание базы данных
CREATE DATABASE vakansii_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Создание пользователя
CREATE USER 'vakansii_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';

-- Предоставление прав
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER
ON vakansii_prod.* TO 'vakansii_user'@'localhost';

-- Применение изменений
FLUSH PRIVILEGES;

-- Проверка
SHOW GRANTS FOR 'vakansii_user'@'localhost';
```

### 2. Применение миграций

```bash
cd /var/www/vakansii-back

# Проверка подключения к БД
php yii migrate/create test --interactive=0 --dry-run

# Применение всех миграций
php yii migrate --interactive=0
```

**Ожидаемый вывод:**
```
*** applying m250204_000000_create_vacancy_table
    > create table {{%vacancy}} ... done (time: 0.123s)
    > add comment on table {{%vacancy}} ... done (time: 0.045s)
    > create index idx-vacancy-title on {{%vacancy}} (title) ... done (time: 0.234s)
*** applied m250204_000000_create_vacancy_table (time: 0.512s)

*** applying m250205_000000_create_user_table
    > create table {{%user}} ... done (time: 0.156s)
*** applied m250205_000000_create_user_table (time: 0.298s)

2 migrations were applied.
```

### 3. Заполнение тестовыми данными (опционально)

```bash
# ⚠️ ТОЛЬКО для staging/test окружений!
php scripts/seed_data.php
```

---

## Настройка веб-сервера

### Вариант A: Nginx (рекомендуется)

Создайте конфиг:

```bash
sudo nano /etc/nginx/sites-available/vakansii-back
```

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name api.yourdomain.com;

    # Принудительный редирект на HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name api.yourdomain.com;

    # SSL сертификаты (настройте Let's Encrypt - см. раздел Безопасность)
    ssl_certificate /etc/letsencrypt/live/api.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    root /var/www/vakansii-back/web;
    index index.php;

    # Логи
    access_log /var/log/nginx/vakansii-back.access.log;
    error_log /var/log/nginx/vakansii-back.error.log warn;

    # Ограничение размера запроса (защита от DoS)
    client_max_body_size 10M;

    # Таймауты
    client_body_timeout 12;
    client_header_timeout 12;
    keepalive_timeout 15;
    send_timeout 10;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;  # Проверьте версию PHP
        fastcgi_index index.php;

        # Безопасность
        fastcgi_hide_header X-Powered-By;
    }

    # Запрет доступа к скрытым файлам
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Запрет доступа к composer файлам
    location ~ (composer\.json|composer\.lock|\.env) {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Кеширование статики
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        access_log off;
        add_header Cache-Control "public, immutable";
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
}
```

Активируйте конфиг:

```bash
# Создание символической ссылки
sudo ln -s /etc/nginx/sites-available/vakansii-back /etc/nginx/sites-enabled/

# Проверка конфига
sudo nginx -t

# Перезапуск Nginx
sudo systemctl restart nginx
```

### Вариант B: Apache

```bash
sudo nano /etc/apache2/sites-available/vakansii-back.conf
```

```apache
<VirtualHost *:80>
    ServerName api.yourdomain.com
    Redirect permanent / https://api.yourdomain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName api.yourdomain.com
    DocumentRoot /var/www/vakansii-back/web

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/api.yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/api.yourdomain.com/privkey.pem

    <Directory /var/www/vakansii-back/web>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Логи
    ErrorLog ${APACHE_LOG_DIR}/vakansii-back.error.log
    CustomLog ${APACHE_LOG_DIR}/vakansii-back.access.log combined

    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
</VirtualHost>
```

Активируйте:

```bash
sudo a2ensite vakansii-back.conf
sudo a2enmod rewrite ssl headers
sudo systemctl restart apache2
```

---

## Безопасность

### 1. HTTPS с Let's Encrypt

```bash
# Установка Certbot
sudo apt-get install certbot python3-certbot-nginx  # для Nginx
# или
sudo apt-get install certbot python3-certbot-apache  # для Apache

# Получение сертификата
sudo certbot --nginx -d api.yourdomain.com  # для Nginx
# или
sudo certbot --apache -d api.yourdomain.com  # для Apache

# Автообновление сертификата
sudo certbot renew --dry-run
```

### 2. Firewall (UFW)

```bash
sudo ufw allow 22/tcp      # SSH
sudo ufw allow 80/tcp      # HTTP
sudo ufw allow 443/tcp     # HTTPS
sudo ufw enable
sudo ufw status
```

### 3. Защита от brute-force (Fail2Ban)

```bash
sudo apt-get install fail2ban

# Создание правила для Nginx
sudo nano /etc/fail2ban/filter.d/nginx-vakansii.conf
```

```ini
[Definition]
failregex = ^<HOST> .* "(GET|POST|PUT|DELETE) .* HTTP.*" (4[0-9]{2}|5[0-9]{2})
ignoreregex =
```

```bash
sudo nano /etc/fail2ban/jail.local
```

```ini
[nginx-vakansii]
enabled = true
port = http,https
filter = nginx-vakansii
logpath = /var/log/nginx/vakansii-back.access.log
maxretry = 10
bantime = 3600
```

```bash
sudo systemctl restart fail2ban
sudo fail2ban-client status nginx-vakansii
```

### 4. Изменение пароля администратора

```bash
# Подключитесь к БД и смените дефолтный пароль admin123!
mysql -u vakansii_user -p vakansii_prod

# В консоли MySQL:
USE vakansii_prod;

# Обновите email и пароль дефолтного admin пользователя
UPDATE user SET
  email = 'your-real-email@example.com',
  password_hash = '$2y$13$NEW_HASH_HERE'
WHERE username = 'admin';

# Или создайте нового пользователя программно через Yii console:
```

```bash
php yii
# (создайте console команду для создания пользователя)
```

### 5. Регулярные обновления

```bash
# Настройте автоматические обновления безопасности
sudo apt-get install unattended-upgrades
sudo dpkg-reconfigure --priority=low unattended-upgrades
```

---

## Мониторинг и логирование

### 1. Логи приложения

```bash
# Просмотр логов Yii
tail -f /var/www/vakansii-back/runtime/logs/app.log

# Фильтрация по уровню
grep "error" /var/www/vakansii-back/runtime/logs/app.log
grep "VacancyService" /var/www/vakansii-back/runtime/logs/app.log
```

### 2. Ротация логов

```bash
sudo nano /etc/logrotate.d/vakansii-back
```

```
/var/www/vakansii-back/runtime/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        /usr/bin/killall -USR1 php-fpm
    endscript
}
```

### 3. Мониторинг производительности

```bash
# Установка htop для мониторинга системы
sudo apt-get install htop

# Мониторинг PHP-FPM
sudo tail -f /var/log/php8.0-fpm.log

# Статистика MySQL
mysqladmin -u vakansii_user -p processlist status
```

### 4. Настройка алертов (опционально)

Настройте отправку критических ошибок на email через Yii2 Logger:

```php
// config/web.php
'log' => [
    'targets' => [
        [
            'class' => 'yii\log\EmailTarget',
            'levels' => ['error'],
            'message' => [
                'from' => ['alerts@yourdomain.com'],
                'to' => ['admin@yourdomain.com'],
                'subject' => 'Error in vakansii-back',
            ],
        ],
    ],
],
```

---

## Checklist перед деплоем

### Обязательные проверки

- [ ] `.env` файл создан и настроен
- [ ] `YII_DEBUG=false` и `YII_ENV=prod`
- [ ] Пароль БД сильный (минимум 16 символов)
- [ ] CORS_ORIGIN содержит только разрешенные домены (без `*`)
- [ ] HTTPS настроен (SSL сертификат установлен)
- [ ] Миграции применены (`php yii migrate`)
- [ ] Права доступа настроены (`chown`, `chmod`)
- [ ] Логи веб-сервера настроены
- [ ] Firewall настроен (порты 22, 80, 443)
- [ ] Изменен дефолтный пароль admin123
- [ ] Composer зависимости установлены с `--no-dev`
- [ ] Rate limiting настроен в `.env`

### Рекомендуемые проверки

- [ ] Fail2Ban настроен
- [ ] Ротация логов настроена
- [ ] Backup базы данных настроен
- [ ] Мониторинг настроен
- [ ] Алерты на email настроены
- [ ] Тесты пройдены (`vendor/bin/codecept run`)

### Проверка работоспособности

```bash
# 1. Проверка подключения к БД
php yii migrate/fresh --interactive=0

# 2. Тест API endpoints
curl -X GET https://api.yourdomain.com/vacancy
# Ожидается: {"data":[],"pagination":{...}}

curl -X POST https://api.yourdomain.com/vacancy \
  -H "Content-Type: application/json" \
  -d '{"title":"Test","description":"Test","salary":100000}'
# Ожидается: {"success":true,"id":1,"message":"..."}

# 3. Проверка rate limiting
for i in {1..110}; do curl https://api.yourdomain.com/vacancy; done
# После 100 запросов должна вернуться ошибка 429 Too Many Requests

# 4. Проверка CORS
curl -X OPTIONS https://api.yourdomain.com/vacancy \
  -H "Origin: https://yourdomain.com" \
  -H "Access-Control-Request-Method: GET"
# Ожидается: заголовок Access-Control-Allow-Origin
```

---

## Troubleshooting

### Проблема: 500 Internal Server Error

**Решение:**

```bash
# 1. Проверьте логи Nginx/Apache
sudo tail -n 50 /var/log/nginx/vakansii-back.error.log

# 2. Проверьте логи PHP-FPM
sudo tail -n 50 /var/log/php8.0-fpm.log

# 3. Проверьте логи Yii
tail -n 50 /var/www/vakansii-back/runtime/logs/app.log

# 4. Временно включите debug
nano .env  # Установите YII_DEBUG=true
# После диагностики верните обратно false!
```

### Проблема: Connection refused (БД)

**Решение:**

```bash
# 1. Проверьте, работает ли MySQL
sudo systemctl status mysql

# 2. Проверьте credentials в .env
cat .env | grep DB_

# 3. Попробуйте подключиться вручную
mysql -u vakansii_user -p vakansii_prod

# 4. Проверьте права пользователя
mysql -u root -p -e "SHOW GRANTS FOR 'vakansii_user'@'localhost';"
```

### Проблема: CORS ошибки

**Решение:**

```bash
# 1. Проверьте CORS_ORIGIN в .env
cat .env | grep CORS_ORIGIN

# 2. Добавьте нужный домен
nano .env
# CORS_ORIGIN=https://yourdomain.com,https://www.yourdomain.com

# 3. Проверьте, что frontend отправляет правильный Origin header
curl -X OPTIONS https://api.yourdomain.com/vacancy \
  -H "Origin: https://yourdomain.com" -v
```

### Проблема: Rate limiting не работает

**Решение:**

```bash
# 1. Проверьте, что cache компонент настроен
# config/web.php должен содержать:
'cache' => [
    'class' => 'yii\caching\FileCache',
],

# 2. Проверьте права на runtime/cache
sudo chmod -R 775 /var/www/vakansii-back/runtime/cache
sudo chown -R www-data:www-data /var/www/vakansii-back/runtime/cache

# 3. Проверьте переменные окружения
cat .env | grep RATE_LIMIT
```

### Проблема: 404 Not Found на всех endpoints

**Решение:**

```bash
# 1. Проверьте mod_rewrite (Apache) или try_files (Nginx)
# Nginx:
sudo nginx -t

# Apache:
sudo a2enmod rewrite
sudo systemctl restart apache2

# 2. Проверьте DocumentRoot
# Должен указывать на /var/www/vakansii-back/web, а не на /var/www/vakansii-back
```

---

## Бэкап базы данных

### Автоматический ежедневный бэкап

```bash
# Создайте скрипт бэкапа
sudo nano /usr/local/bin/backup-vakansii-db.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/vakansii"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="vakansii_prod"
DB_USER="vakansii_user"
DB_PASS="YOUR_PASSWORD"

mkdir -p $BACKUP_DIR

mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/backup_$DATE.sql.gz

# Удаление бэкапов старше 30 дней
find $BACKUP_DIR -name "backup_*.sql.gz" -mtime +30 -delete

echo "Backup completed: backup_$DATE.sql.gz"
```

```bash
# Сделайте скрипт исполняемым
sudo chmod +x /usr/local/bin/backup-vakansii-db.sh

# Добавьте в cron (ежедневно в 2:00 AM)
sudo crontab -e
# Добавьте строку:
0 2 * * * /usr/local/bin/backup-vakansii-db.sh >> /var/log/vakansii-backup.log 2>&1
```

---

## Обновление приложения

```bash
cd /var/www/vakansii-back

# 1. Создайте бэкап БД перед обновлением!
/usr/local/bin/backup-vakansii-db.sh

# 2. Переключитесь в maintenance mode (создайте файл)
touch web/maintenance.html

# 3. Получите обновления
git pull origin main

# 4. Обновите зависимости
composer install --no-dev --optimize-autoloader

# 5. Примените новые миграции
php yii migrate --interactive=0

# 6. Очистите cache
php yii cache/flush-all

# 7. Выйдите из maintenance mode
rm web/maintenance.html

# 8. Перезапустите PHP-FPM
sudo systemctl restart php8.0-fpm
```

---

## Контакты и поддержка

Если возникли проблемы при развертывании, проверьте:
- [IMPROVEMENTS.md](IMPROVEMENTS.md) - детальный отчет об улучшениях
- [TESTING.md](TESTING.md) - документация по тестированию
- [FINAL_REPORT.md](FINAL_REPORT.md) - итоговый отчет проекта

---

**Версия:** 1.0.0
**Дата:** 2025-12-05
**Автор:** Claude Code
