<?php

namespace tests\_support;

use yii\db\Connection;

/**
 * Создаёт минимальную схему БД для SQLite-тестов без полных миграций.
 */
class TestSchema
{
    private static bool $initialized = false;

    public static function ensure(Connection $db): void
    {
        if (self::$initialized || $db->driverName !== 'sqlite') {
            return;
        }

        $db->open();

        if ($db->schema->getTableSchema('{{%vacancy}}', true) === null) {
            $db->createCommand()->createTable('{{%vacancy}}', [
                'id' => 'pk',
                'title' => 'varchar(255) NOT NULL',
                'description' => 'text NOT NULL',
                'salary' => 'integer NOT NULL',
                'additional_fields' => 'text NULL',
                'created_at' => 'integer NOT NULL',
                'updated_at' => 'integer NOT NULL',
            ])->execute();

            $db->createCommand()->createIndex('idx-vacancy-salary', '{{%vacancy}}', 'salary')->execute();
            $db->createCommand()->createIndex('idx-vacancy-created_at', '{{%vacancy}}', 'created_at')->execute();
        }

        if ($db->schema->getTableSchema('{{%user}}', true) === null) {
            $db->createCommand()->createTable('{{%user}}', [
                'id' => 'pk',
                'username' => 'varchar(255) NOT NULL UNIQUE',
                'email' => 'varchar(255) NOT NULL UNIQUE',
                'password_hash' => 'varchar(255) NOT NULL',
                'auth_key' => 'varchar(32) NOT NULL',
                'access_token' => 'varchar(255) UNIQUE',
                'status' => 'smallint NOT NULL DEFAULT 10',
                'created_at' => 'integer NOT NULL',
                'updated_at' => 'integer NOT NULL',
            ])->execute();
        }

        self::seedTestUser($db);

        self::$initialized = true;
    }

    private static function seedTestUser(Connection $db): void
    {
        $count = (int) $db->createCommand('SELECT COUNT(*) FROM {{%user}}')->queryScalar();
        if ($count > 0) {
            return;
        }

        $security = \Yii::$app->security;
        $db->createCommand()->insert('{{%user}}', [
            'id' => 100,
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password_hash' => $security->generatePasswordHash('admin'),
            'auth_key' => 'test100key',
            'access_token' => '100-token',
            'status' => 10,
            'created_at' => time(),
            'updated_at' => time(),
        ])->execute();
    }
}
