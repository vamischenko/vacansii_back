# 🐳 Docker Setup

## Описание

Проект включает полную Docker конфигурацию для быстрого развертывания с MySQL или PostgreSQL.

## Что включено

### Docker Compose с MySQL (по умолчанию)

- **PHP 8.1 + Apache** - веб-сервер с Yii2
- **MySQL 8.0** - база данных
- **phpMyAdmin** - веб-интерфейс для управления БД

### Docker Compose с PostgreSQL (альтернатива)

- **PHP 8.1 + Apache** - веб-сервер с Yii2
- **PostgreSQL 15** - база данных
- **pgAdmin 4** - веб-интерфейс для управления БД

## Быстрый старт

### Вариант 1: Автоматический запуск (MySQL)

```bash
./docker-setup.sh
```

Этот скрипт автоматически:
1. Запустит все контейнеры
2. Установит зависимости Composer
3. Применит миграции
4. Добавит тестовые данные

### Вариант 2: Ручной запуск (MySQL)

```bash
# 1. Запуск контейнеров
docker-compose up -d

# 2. Ожидание запуска MySQL
sleep 10

# 3. Установка зависимостей
docker-compose exec php composer install

# 4. Копирование конфигурации БД
cp config/db-docker.php config/db.php

# 5. Применение миграций
docker-compose exec php php yii migrate --interactive=0

# 6. Добавление тестовых данных (опционально)
docker-compose exec php php seed_data.php
```

### Вариант 3: PostgreSQL

```bash
# 1. Запуск контейнеров с PostgreSQL
docker-compose -f docker-compose.postgres.yml up -d

# 2. Ожидание запуска PostgreSQL
sleep 10

# 3. Установка зависимостей
docker-compose -f docker-compose.postgres.yml exec php composer install

# 4. Копирование конфигурации БД
cp config/db-docker-postgres.php config/db.php

# 5. Применение миграций
docker-compose -f docker-compose.postgres.yml exec php php yii migrate --interactive=0

# 6. Добавление тестовых данных (опционально)
docker-compose -f docker-compose.postgres.yml exec php php seed_data.php
```

## Доступ к сервисам

### MySQL версия

| Сервис | URL | Учетные данные |
|--------|-----|----------------|
| **API** | http://localhost:8000/vacancy | - |
| **phpMyAdmin** | http://localhost:8080 | root / rootsecret |
| **MySQL** (внешний доступ) | localhost:3306 | yii2 / yii2secret |

### PostgreSQL версия

| Сервис | URL | Учетные данные |
|--------|-----|----------------|
| **API** | http://localhost:8000/vacancy | - |
| **pgAdmin** | http://localhost:8080 | admin@admin.com / admin |
| **PostgreSQL** (внешний доступ) | localhost:5432 | yii2 / yii2secret |

## Примеры запросов к API

```bash
# Получение списка вакансий
curl http://localhost:8000/vacancy

# Получение конкретной вакансии
curl http://localhost:8000/vacancy/1

# Создание вакансии
curl -X POST http://localhost:8000/vacancy \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Docker Developer",
    "description": "Требуется DevOps инженер",
    "salary": 200000
  }'
```

## Управление контейнерами

### Просмотр логов

```bash
# Все контейнеры
docker-compose logs -f

# Только PHP
docker-compose logs -f php

# Только MySQL
docker-compose logs -f mysql
```

### Вход в контейнеры

```bash
# PHP контейнер
docker-compose exec php bash

# MySQL контейнер
docker-compose exec mysql bash

# MySQL клиент
docker-compose exec mysql mysql -u yii2 -pyii2secret vakansii_db
```

### Остановка и удаление

```bash
# Остановить контейнеры
docker-compose down

# Остановить и удалить volumes (все данные БД будут удалены!)
docker-compose down -v

# Пересобрать контейнеры
docker-compose up -d --build
```

## Работа с базой данных

### Подключение через phpMyAdmin (MySQL)

1. Откройте http://localhost:8080
2. Используйте учетные данные:
   - **Server:** mysql
   - **Username:** root
   - **Password:** rootsecret

### Подключение через pgAdmin (PostgreSQL)

1. Откройте http://localhost:8080
2. Войдите с учетными данными: admin@admin.com / admin
3. Добавьте новый сервер:
   - **Host:** postgres
   - **Port:** 5432
   - **Database:** vakansii_db
   - **Username:** yii2
   - **Password:** yii2secret

### Подключение из приложений (например, DBeaver, DataGrip)

#### MySQL:
- **Host:** localhost
- **Port:** 3306
- **Database:** vakansii_db
- **Username:** yii2
- **Password:** yii2secret

#### PostgreSQL:
- **Host:** localhost
- **Port:** 5432
- **Database:** vakansii_db
- **Username:** yii2
- **Password:** yii2secret

## Выполнение команд Yii

```bash
# Миграции
docker-compose exec php php yii migrate

# Откат последней миграции
docker-compose exec php php yii migrate/down

# Добавление тестовых данных
docker-compose exec php php seed_data.php

# Очистка кэша
docker-compose exec php php yii cache/flush-all
```

## Структура Docker файлов

```
vakansii-back/
├── docker-compose.yml                    # MySQL версия
├── docker-compose.postgres.yml          # PostgreSQL версия
├── docker-setup.sh                       # Скрипт автоматической установки
├── .env.docker                           # Переменные окружения
├── config/
│   ├── db-docker.php                    # Конфигурация БД для MySQL
│   └── db-docker-postgres.php           # Конфигурация БД для PostgreSQL
└── docker/
    ├── mysql/
    │   └── init/
    │       └── 01-init.sql              # Инициализация MySQL
    └── postgres/
        └── init/                         # Инициализация PostgreSQL
```

## Переменные окружения

Вы можете изменить настройки в файле `.env.docker`:

```env
# MySQL Configuration
MYSQL_ROOT_PASSWORD=rootsecret
MYSQL_DATABASE=vakansii_db
MYSQL_USER=yii2
MYSQL_PASSWORD=yii2secret

# Ports
PHP_PORT=8000
MYSQL_PORT=3306
PHPMYADMIN_PORT=8080
```

После изменения переменных перезапустите контейнеры:

```bash
docker-compose down
docker-compose up -d
```

## Решение проблем

### Порт уже занят

Если порты 8000, 3306 или 8080 уже заняты, измените их в `docker-compose.yml`:

```yaml
ports:
  - '8001:80'  # Вместо 8000
```

### MySQL не запускается

```bash
# Проверьте логи
docker-compose logs mysql

# Удалите volume и пересоздайте
docker-compose down -v
docker-compose up -d
```

### Ошибка подключения к БД

Убедитесь, что:
1. Контейнер MySQL запущен: `docker-compose ps`
2. Скопирован правильный конфигурационный файл: `config/db.php`
3. MySQL полностью инициализирован (подождите 10-15 секунд после запуска)

### Очистка всех данных

```bash
# Остановить контейнеры
docker-compose down

# Удалить volumes
docker volume rm vakansii-back_mysql_data

# Запустить заново
./docker-setup.sh
```

## Production использование

⚠️ **Важно:** Эта конфигурация предназначена для разработки. Для production:

1. Измените пароли в `.env.docker`
2. Отключите phpMyAdmin/pgAdmin
3. Используйте секреты Docker вместо переменных окружения
4. Настройте backup'ы для volume с данными БД
5. Используйте nginx вместо встроенного Apache
6. Настройте SSL/TLS

## Тестирование в Docker

```bash
# Запуск тестов API
docker-compose exec php bash -c "cd /app && ./test_api.sh http://localhost"
```

## Полезные ссылки

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Yii2 Docker Images](https://github.com/yiisoft/yii2-docker)
- [MySQL Docker Image](https://hub.docker.com/_/mysql)
- [PostgreSQL Docker Image](https://hub.docker.com/_/postgres)
