#!/bin/bash

# Скрипт для генерации SSL сертификатов
# Для разработки: создает самоподписанные сертификаты
# Для продакшена: используйте Let's Encrypt (см. ниже)

set -e

echo "=== SSL Certificate Setup ==="
echo ""

# Определяем режим работы
MODE=${1:-dev}

if [ "$MODE" = "dev" ]; then
    echo "Режим: РАЗРАБОТКА (самоподписанный сертификат)"
    echo ""

    # Создаем директории для сертификатов
    mkdir -p docker/nginx/ssl
    mkdir -p docker/apache/ssl

    # Генерируем самоподписанный сертификат
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout docker/nginx/ssl/key.pem \
        -out docker/nginx/ssl/cert.pem \
        -subj "/C=RU/ST=Moscow/L=Moscow/O=Development/CN=localhost"

    # Копируем для Apache
    cp docker/nginx/ssl/key.pem docker/apache/ssl/key.pem
    cp docker/nginx/ssl/cert.pem docker/apache/ssl/cert.pem

    echo "✓ Самоподписанные сертификаты созданы"
    echo "  - Nginx: docker/nginx/ssl/"
    echo "  - Apache: docker/apache/ssl/"
    echo ""
    echo "ВНИМАНИЕ: Это сертификаты для разработки!"
    echo "Браузеры будут показывать предупреждение о безопасности."

elif [ "$MODE" = "production" ]; then
    echo "Режим: ПРОДАКШН (Let's Encrypt)"
    echo ""

    # Проверяем наличие certbot
    if ! command -v certbot &> /dev/null; then
        echo "ERROR: certbot не установлен"
        echo "Установите certbot:"
        echo "  Ubuntu/Debian: sudo apt-get install certbot python3-certbot-nginx"
        echo "  CentOS/RHEL: sudo yum install certbot python3-certbot-nginx"
        exit 1
    fi

    # Запрашиваем домен
    read -p "Введите ваш домен (например, api.example.com): " DOMAIN
    read -p "Введите email для уведомлений Let's Encrypt: " EMAIL

    if [ -z "$DOMAIN" ] || [ -z "$EMAIL" ]; then
        echo "ERROR: Домен и email обязательны!"
        exit 1
    fi

    echo ""
    echo "Получение сертификата Let's Encrypt для $DOMAIN..."
    echo ""

    # Получаем сертификат через certbot
    # Используем standalone режим (требует остановки веб-сервера на порту 80)
    sudo certbot certonly --standalone \
        -d "$DOMAIN" \
        --email "$EMAIL" \
        --agree-tos \
        --non-interactive

    # Создаем директории
    mkdir -p docker/nginx/ssl
    mkdir -p docker/apache/ssl

    # Копируем сертификаты Let's Encrypt
    sudo cp "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" docker/nginx/ssl/cert.pem
    sudo cp "/etc/letsencrypt/live/$DOMAIN/privkey.pem" docker/nginx/ssl/key.pem
    sudo cp "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" docker/apache/ssl/cert.pem
    sudo cp "/etc/letsencrypt/live/$DOMAIN/privkey.pem" docker/apache/ssl/key.pem

    # Устанавливаем права
    sudo chmod 644 docker/nginx/ssl/cert.pem docker/apache/ssl/cert.pem
    sudo chmod 600 docker/nginx/ssl/key.pem docker/apache/ssl/key.pem

    echo "✓ Сертификаты Let's Encrypt установлены"
    echo ""
    echo "ВАЖНО: Настройте автообновление сертификатов:"
    echo "  sudo certbot renew --dry-run"
    echo ""
    echo "Добавьте в crontab для автоматического обновления:"
    echo "  0 0 * * * certbot renew --quiet --deploy-hook 'docker-compose restart nginx'"

else
    echo "ERROR: Неизвестный режим '$MODE'"
    echo ""
    echo "Использование:"
    echo "  ./setup_ssl_certificates.sh dev        - для разработки (самоподписанный)"
    echo "  ./setup_ssl_certificates.sh production - для продакшена (Let's Encrypt)"
    exit 1
fi

echo ""
echo "=== Готово! ==="
