<?php

use yii\db\Migration;

/**
 * Миграция для создания таблицы пользователей
 *
 * Создает таблицу user с полями:
 * - id: первичный ключ
 * - username: имя пользователя (уникальное)
 * - email: email пользователя (уникальное)
 * - password_hash: хешированный пароль
 * - auth_key: ключ для автоматической авторизации
 * - access_token: токен доступа для API
 * - status: статус пользователя (10 - active, 0 - deleted)
 * - created_at: время создания
 * - updated_at: время обновления
 *
 * Также создает индексы для username, email и access_token
 */
class m250205_000000_create_user_table extends Migration
{
    /**
     * Имя таблицы
     */
    private const TABLE_NAME = '{{%user}}';

    /**
     * Статусы пользователей
     */
    private const STATUS_DELETED = 0;
    private const STATUS_ACTIVE = 10;

    /**
     * Применяет миграцию
     *
     * Создает таблицу user с полями, индексами и дефолтным пользователем admin.
     *
     * @return void
     */
    public function safeUp()
    {
        // Создание таблицы
        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey()->comment('Уникальный идентификатор пользователя'),
            'username' => $this->string(255)->notNull()->unique()->comment('Имя пользователя'),
            'email' => $this->string(255)->notNull()->unique()->comment('Email пользователя'),
            'password_hash' => $this->string(255)->notNull()->comment('Хешированный пароль'),
            'auth_key' => $this->string(32)->notNull()->comment('Ключ для автоматической авторизации'),
            'access_token' => $this->string(255)->unique()->comment('Токен доступа для API'),
            'status' => $this->smallInteger()->notNull()->defaultValue(self::STATUS_ACTIVE)->comment('Статус пользователя (10-active, 0-deleted)'),
            'created_at' => $this->integer()->notNull()->comment('Время создания записи (Unix timestamp)'),
            'updated_at' => $this->integer()->notNull()->comment('Время последнего обновления (Unix timestamp)'),
        ]);

        // Добавление комментария к таблице
        $this->addCommentOnTable(self::TABLE_NAME, 'Таблица пользователей');

        // Создание индекса для username
        $this->createIndex(
            'idx-user-username',
            self::TABLE_NAME,
            'username'
        );

        // Создание индекса для email
        $this->createIndex(
            'idx-user-email',
            self::TABLE_NAME,
            'email'
        );

        // Создание индекса для access_token
        $this->createIndex(
            'idx-user-access_token',
            self::TABLE_NAME,
            'access_token'
        );

        // Создание индекса для status
        $this->createIndex(
            'idx-user-status',
            self::TABLE_NAME,
            'status'
        );

        // Вставка дефолтного администратора
        // Пароль: admin123 (в production обязательно изменить!)
        $this->insert(self::TABLE_NAME, [
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password_hash' => \Yii::$app->security->generatePasswordHash('admin123'),
            'auth_key' => \Yii::$app->security->generateRandomString(32),
            'access_token' => \Yii::$app->security->generateRandomString(32),
            'status' => self::STATUS_ACTIVE,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
    }

    /**
     * Откатывает миграцию
     *
     * Удаляет индексы и таблицу user.
     *
     * @return void
     */
    public function safeDown()
    {
        // Удаление индексов
        $this->dropIndex('idx-user-status', self::TABLE_NAME);
        $this->dropIndex('idx-user-access_token', self::TABLE_NAME);
        $this->dropIndex('idx-user-email', self::TABLE_NAME);
        $this->dropIndex('idx-user-username', self::TABLE_NAME);

        // Удаление таблицы
        $this->dropTable(self::TABLE_NAME);
    }
}
