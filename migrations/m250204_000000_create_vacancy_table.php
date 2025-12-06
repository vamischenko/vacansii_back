<?php

use yii\db\Migration;

/**
 * Миграция для создания таблицы вакансий
 *
 * Создает таблицу vacancy с полями:
 * - id: первичный ключ
 * - title: название вакансии
 * - description: описание вакансии
 * - salary: зарплата (целое число >= 0)
 * - additional_fields: дополнительные поля в формате JSON
 * - created_at: время создания (Unix timestamp)
 * - updated_at: время обновления (Unix timestamp)
 *
 * Также создает индексы для оптимизации запросов:
 * - idx-vacancy-title: для поиска по названию
 * - idx-vacancy-salary: для фильтрации по зарплате
 * - idx-vacancy-created_at: для сортировки по дате создания
 */
class m250204_000000_create_vacancy_table extends Migration
{
    /**
     * Имя таблицы
     */
    private const TABLE_NAME = '{{%vacancy}}';

    /**
     * Применяет миграцию
     *
     * Создает таблицу vacancy с полями и индексами.
     * Добавляет SQL комментарии к таблице и полям для документирования структуры БД.
     * Устанавливает CHECK constraint для проверки корректности значения зарплаты.
     *
     * @return void
     */
    public function safeUp()
    {
        // Создание таблицы
        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey()->comment('Уникальный идентификатор вакансии'),
            'title' => $this->string(255)->notNull()->comment('Название вакансии'),
            'description' => $this->text()->notNull()->comment('Подробное описание вакансии'),
            'salary' => $this->integer()->notNull()->comment('Зарплата (в минимальных единицах валюты)'),
            'additional_fields' => $this->json()->defaultValue(null)->comment('Дополнительные поля в формате JSON'),
            'created_at' => $this->integer()->notNull()->comment('Время создания записи (Unix timestamp)'),
            'updated_at' => $this->integer()->notNull()->comment('Время последнего обновления (Unix timestamp)'),
        ]);

        // Добавление комментария к таблице
        $this->addCommentOnTable(self::TABLE_NAME, 'Таблица вакансий');

        // Добавление CHECK constraint для проверки зарплаты
        $this->execute('ALTER TABLE ' . self::TABLE_NAME . ' ADD CONSTRAINT chk_vacancy_salary_positive CHECK (salary >= 0)');

        // Создание индекса для поиска по названию
        $this->createIndex(
            'idx-vacancy-title',
            self::TABLE_NAME,
            'title'
        );

        // Создание индекса для фильтрации по зарплате
        $this->createIndex(
            'idx-vacancy-salary',
            self::TABLE_NAME,
            'salary'
        );

        // Создание индекса для сортировки по дате создания
        $this->createIndex(
            'idx-vacancy-created_at',
            self::TABLE_NAME,
            'created_at'
        );
    }

    /**
     * Откатывает миграцию
     *
     * Удаляет индексы и таблицу vacancy.
     * Индексы удаляются явно для обеспечения симметричности с safeUp().
     *
     * @return void
     */
    public function safeDown()
    {
        // Удаление индексов
        $this->dropIndex('idx-vacancy-created_at', self::TABLE_NAME);
        $this->dropIndex('idx-vacancy-salary', self::TABLE_NAME);
        $this->dropIndex('idx-vacancy-title', self::TABLE_NAME);

        // Удаление таблицы (CHECK constraint удалится автоматически)
        $this->dropTable(self::TABLE_NAME);
    }
}
