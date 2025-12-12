#!/bin/bash

echo "================================"
echo "Запуск Docker окружения"
echo "================================"
echo ""

# Проверка наличия docker-compose
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose не установлен!"
    echo "Установите Docker Compose: https://docs.docker.com/compose/install/"
    exit 1
fi

echo "🐳 Запуск контейнеров..."
docker-compose up -d

echo ""
echo "⏳ Ожидание запуска MySQL..."
sleep 10

# Проверка статуса контейнеров
echo ""
echo "📊 Статус контейнеров:"
docker-compose ps

echo ""
echo "🔧 Копирование конфигурации БД для Docker..."
cp config/db-docker.php config/db.php

echo ""
echo "📦 Установка зависимостей..."
docker-compose exec php composer install --no-interaction

echo ""
echo "🗃️  Применение миграций..."
docker-compose exec php php yii migrate --interactive=0

echo ""
echo "📊 Добавление тестовых данных..."
docker-compose exec php php seed_data.php

echo ""
echo "================================"
echo "✅ Установка завершена!"
echo "================================"
echo ""
echo "🌐 Сервисы доступны по адресам:"
echo "   - API:        http://localhost:8000/vacancy"
echo "   - phpMyAdmin: http://localhost:8080"
echo ""
echo "📋 Учетные данные MySQL:"
echo "   - Host:     mysql (внутри Docker) или localhost:3306 (снаружи)"
echo "   - Database: vakansii_db"
echo "   - User:     yii2"
echo "   - Password: yii2secret"
echo "   - Root Password: rootsecret"
echo ""
echo "🔧 Полезные команды:"
echo "   docker-compose logs -f          # Просмотр логов"
echo "   docker-compose exec php bash    # Войти в PHP контейнер"
echo "   docker-compose exec mysql bash  # Войти в MySQL контейнер"
echo "   docker-compose down             # Остановить контейнеры"
echo "   docker-compose down -v          # Остановить и удалить данные"
echo ""
