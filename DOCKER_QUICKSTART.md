# 🐳 Docker - Быстрый старт

## Самый быстрый способ запустить проект

### 1. Запуск одной командой

```bash
./docker-setup.sh
```

Это автоматически:
- Запустит MySQL + PHP + phpMyAdmin
- Установит все зависимости
- Применит миграции
- Добавит тестовые данные

### 2. Доступ к сервисам

После запуска откройте в браузере:

- **API:** http://localhost:8000/vacancy
- **phpMyAdmin:** http://localhost:8080 (root / rootsecret)

### 3. Проверка работы

```bash
# Получить список вакансий
curl http://localhost:8000/vacancy

# Создать вакансию
curl -X POST http://localhost:8000/vacancy \
  -H "Content-Type: application/json" \
  -d '{"title":"Test","description":"Test desc","salary":100000}'
```

## Управление

```bash
# Остановить
docker-compose down

# Посмотреть логи
docker-compose logs -f

# Перезапустить
docker-compose restart

# Удалить всё (включая данные БД)
docker-compose down -v
```

## PostgreSQL вместо MySQL

```bash
# Запуск с PostgreSQL
docker-compose -f docker-compose.postgres.yml up -d

# Настройка
cp config/db-docker-postgres.php config/db.php
docker-compose -f docker-compose.postgres.yml exec php php yii migrate --interactive=0
docker-compose -f docker-compose.postgres.yml exec php php seed_data.php
```

**pgAdmin:** http://localhost:8080 (admin@admin.com / admin)

## Подробная документация

См. [DOCKER.md](DOCKER.md) для полной информации
