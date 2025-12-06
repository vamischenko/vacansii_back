# Настройка HTTPS для Vacancy API

Этот документ описывает процесс настройки HTTPS для вашего API.

## Для разработки (самоподписанный сертификат)

### Шаг 1: Генерация сертификатов

```bash
./setup_ssl_certificates.sh dev
```

Скрипт создаст самоподписанные SSL сертификаты в директориях:
- `docker/nginx/ssl/` - для Nginx
- `docker/apache/ssl/` - для Apache

### Шаг 2: Запуск с Nginx

Обновите `docker-compose.yml`:

```yaml
services:
  nginx:
    build: ./docker/nginx
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./web:/app/web
      - ./docker/nginx/ssl:/etc/nginx/ssl:ro
    depends_on:
      - php
```

### Шаг 3: Перезапустите контейнеры

```bash
docker-compose down
docker-compose up -d
```

### Шаг 4: Проверка

Откройте браузер и перейдите на `https://localhost`

**Внимание:** Браузер покажет предупреждение о безопасности, так как сертификат самоподписанный. Это нормально для разработки.

---

## Для продакшена (Let's Encrypt)

### Предварительные требования

1. Доменное имя, указывающее на ваш сервер
2. Открытые порты 80 и 443
3. Установленный certbot

### Шаг 1: Установка certbot

**Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install certbot python3-certbot-nginx
```

**CentOS/RHEL:**
```bash
sudo yum install certbot python3-certbot-nginx
```

### Шаг 2: Получение сертификата

```bash
./setup_ssl_certificates.sh production
```

Скрипт запросит:
- Ваш домен (например, `api.example.com`)
- Email для уведомлений Let's Encrypt

### Шаг 3: Обновите конфигурацию Nginx

Отредактируйте `docker/nginx/nginx.conf` и замените `server_name _;` на ваш домен:

```nginx
server {
    listen 443 ssl http2;
    server_name api.example.com;  # Ваш домен
    # ...
}
```

### Шаг 4: Автообновление сертификатов

Let's Encrypt сертификаты действительны 90 дней. Настройте автообновление:

```bash
# Проверка обновления
sudo certbot renew --dry-run

# Добавьте в crontab
sudo crontab -e

# Добавьте строку:
0 0 * * * certbot renew --quiet --deploy-hook 'docker-compose -f /path/to/your/docker-compose.yml restart nginx'
```

### Шаг 5: Включите HSTS (опционально, но рекомендуется)

В `docker/nginx/nginx.conf` раскомментируйте строку:

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

---

## Для Apache (альтернатива Nginx)

### Конфигурация Docker Compose

```yaml
services:
  apache:
    image: php:8.1-apache
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - .:/app
      - ./docker/apache/apache-ssl.conf:/etc/apache2/sites-available/000-default.conf
      - ./docker/apache/.htaccess:/app/web/.htaccess
      - ./docker/apache/ssl:/etc/ssl/certs:ro
      - ./docker/apache/ssl:/etc/ssl/private:ro
    command: >
      bash -c "a2enmod rewrite ssl headers &&
               a2ensite 000-default &&
               apache2-foreground"
```

---

## Проверка SSL конфигурации

После настройки проверьте качество SSL:

1. **SSL Labs:** https://www.ssllabs.com/ssltest/
2. **Командная строка:**
   ```bash
   openssl s_client -connect yourdomain.com:443 -tls1_2
   ```

---

## Рекомендации по безопасности

1. **Всегда используйте HTTPS в продакшене**
2. **Включите HSTS** для защиты от downgrade атак
3. **Регулярно обновляйте сертификаты** (автоматизируйте через cron)
4. **Используйте только TLS 1.2 и 1.3** (отключите старые версии)
5. **Настройте правильные CORS заголовки** в `.env`:
   ```
   CORS_ORIGIN=https://yourdomain.com
   ```

---

## Устранение проблем

### Ошибка "NET::ERR_CERT_AUTHORITY_INVALID"

**Причина:** Самоподписанный сертификат в разработке

**Решение:** Это нормально для dev среды. Для продакшена используйте Let's Encrypt.

### Certbot не может получить сертификат

**Возможные причины:**
1. Порт 80 или 443 закрыт - проверьте firewall
2. DNS не указывает на ваш сервер - проверьте A-запись домена
3. Веб-сервер работает на порту 80 - остановите его перед запуском certbot

### Сертификат истёк

```bash
# Обновите вручную
sudo certbot renew

# Скопируйте новые сертификаты
sudo cp /etc/letsencrypt/live/yourdomain.com/fullchain.pem docker/nginx/ssl/cert.pem
sudo cp /etc/letsencrypt/live/yourdomain.com/privkey.pem docker/nginx/ssl/key.pem

# Перезапустите контейнеры
docker-compose restart nginx
```

---

## Дополнительные ресурсы

- [Let's Encrypt документация](https://letsencrypt.org/docs/)
- [Mozilla SSL Configuration Generator](https://ssl-config.mozilla.org/)
- [OWASP Transport Layer Protection](https://cheatsheetseries.owasp.org/cheatsheets/Transport_Layer_Protection_Cheat_Sheet.html)
