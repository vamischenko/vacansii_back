#!/bin/bash

echo "================================"
echo "Настройка базы данных вакансий"
echo "================================"
echo ""

read -p "Выберите СУБД (1 - MySQL, 2 - PostgreSQL): " db_choice

if [ "$db_choice" == "1" ]; then
    echo "Вы выбрали MySQL"
    read -p "Введите имя базы данных [vakansii_db]: " db_name
    db_name=${db_name:-vakansii_db}

    read -p "Введите имя пользователя MySQL [root]: " db_user
    db_user=${db_user:-root}

    read -sp "Введите пароль MySQL: " db_password
    echo ""

    read -p "Введите хост [localhost]: " db_host
    db_host=${db_host:-localhost}

    echo ""
    echo "Создание базы данных $db_name..."
    mysql -h "$db_host" -u "$db_user" -p"$db_password" -e "CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null

    if [ $? -eq 0 ]; then
        echo "База данных успешно создана!"
    else
        echo "Ошибка при создании базы данных. Проверьте учетные данные."
        exit 1
    fi

    cat > config/db.php <<EOF
<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=$db_host;dbname=$db_name',
    'username' => '$db_user',
    'password' => '$db_password',
    'charset' => 'utf8mb4',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];
EOF

elif [ "$db_choice" == "2" ]; then
    echo "Вы выбрали PostgreSQL"
    read -p "Введите имя базы данных [vakansii_db]: " db_name
    db_name=${db_name:-vakansii_db}

    read -p "Введите имя пользователя PostgreSQL [postgres]: " db_user
    db_user=${db_user:-postgres}

    read -sp "Введите пароль PostgreSQL: " db_password
    echo ""

    read -p "Введите хост [localhost]: " db_host
    db_host=${db_host:-localhost}

    read -p "Введите порт [5432]: " db_port
    db_port=${db_port:-5432}

    echo ""
    echo "Создание базы данных $db_name..."
    PGPASSWORD="$db_password" psql -h "$db_host" -p "$db_port" -U "$db_user" -c "CREATE DATABASE $db_name ENCODING 'UTF8';" 2>/dev/null

    if [ $? -eq 0 ]; then
        echo "База данных успешно создана!"
    else
        echo "База данных уже существует или произошла ошибка."
    fi

    cat > config/db.php <<EOF
<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'pgsql:host=$db_host;port=$db_port;dbname=$db_name',
    'username' => '$db_user',
    'password' => '$db_password',
    'charset' => 'utf8',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];
EOF

else
    echo "Неверный выбор!"
    exit 1
fi

echo ""
echo "Конфигурация базы данных обновлена!"
echo ""
echo "Применение миграций..."
./yii migrate --interactive=0

if [ $? -eq 0 ]; then
    echo ""
    echo "================================"
    echo "Настройка завершена успешно!"
    echo "================================"
    echo ""
    echo "Теперь вы можете запустить веб-сервер и начать использовать API."
else
    echo ""
    echo "Ошибка при применении миграций!"
    exit 1
fi
