#!/bin/bash

# Скрипт для автоматической настройки HTTPS с Let's Encrypt
# Использование: sudo ./setup_https.sh yourdomain.com your@email.com

set -e

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Проверка аргументов
if [ "$#" -ne 2 ]; then
    echo -e "${RED}Использование: sudo ./setup_https.sh DOMAIN EMAIL${NC}"
    echo "Пример: sudo ./setup_https.sh api.example.com admin@example.com"
    exit 1
fi

DOMAIN=$1
EMAIL=$2

echo -e "${GREEN}=== Настройка HTTPS для $DOMAIN ===${NC}\n"

# Проверка прав root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Ошибка: Этот скрипт требует прав root${NC}"
    echo "Запустите: sudo ./setup_https.sh $DOMAIN $EMAIL"
    exit 1
fi

# Проверка наличия Nginx
if ! command -v nginx &> /dev/null; then
    echo -e "${RED}Nginx не установлен. Установите Nginx и повторите попытку.${NC}"
    exit 1
fi

echo -e "${YELLOW}[1/5] Установка Certbot...${NC}"
if command -v certbot &> /dev/null; then
    echo "Certbot уже установлен"
else
    apt-get update
    apt-get install -y certbot python3-certbot-nginx
fi

echo -e "\n${YELLOW}[2/5] Проверка конфигурации Nginx...${NC}"
nginx -t

echo -e "\n${YELLOW}[3/5] Получение SSL сертификата от Let's Encrypt...${NC}"
echo "Домен: $DOMAIN"
echo "Email: $EMAIL"

# Получение сертификата
certbot --nginx -d $DOMAIN --non-interactive --agree-tos --email $EMAIL --redirect

echo -e "\n${YELLOW}[4/5] Настройка автоматического обновления сертификата...${NC}"

# Проверка cron job для автообновления
if ! crontab -l 2>/dev/null | grep -q 'certbot renew'; then
    (crontab -l 2>/dev/null; echo "0 3 * * * certbot renew --quiet --post-hook 'systemctl reload nginx'") | crontab -
    echo "Cron job для автообновления добавлен (каждый день в 3:00)"
else
    echo "Cron job для автообновления уже существует"
fi

# Тестовое обновление (dry-run)
echo -e "\n${YELLOW}[5/5] Тест обновления сертификата...${NC}"
certbot renew --dry-run

echo -e "\n${GREEN}✅ HTTPS успешно настроен!${NC}\n"
echo "Информация о сертификате:"
echo "  Домен: $DOMAIN"
echo "  Email: $EMAIL"
echo "  Сертификаты: /etc/letsencrypt/live/$DOMAIN/"
echo "  Автообновление: Да (cron, каждый день в 3:00)"
echo ""
echo "Проверьте работу HTTPS:"
echo "  curl -I https://$DOMAIN"
echo ""
echo "Информация о сертификате:"
echo "  certbot certificates"
echo ""
echo -e "${GREEN}Готово!${NC}"
