<?php

use yii\db\Migration;

/**
 * Миграция для добавления полнотекстового поиска
 *
 * Добавляет поддержку FULLTEXT индексов для полей title и description
 * в таблице vacancy. Реализация зависит от СУБД:
 *
 * - PostgreSQL: использует tsvector колонку с GIN индексом
 * - MySQL: использует FULLTEXT индекс
 *
 * Полнотекстовый поиск позволяет эффективно искать по содержимому
 * вакансий с поддержкой ранжирования результатов.
 *
 * @author Vacancy Management System
 * @version 1.0.0
 */
class m250206_000000_add_fulltext_search extends Migration
{
    /**
     * Имя таблицы
     */
    private const TABLE_NAME = '{{%vacancy}}';

    /**
     * Применяет миграцию
     *
     * Добавляет FULLTEXT индекс в зависимости от используемой СУБД:
     * - PostgreSQL: создает tsvector колонку с триггером для автообновления
     * - MySQL: создает FULLTEXT индекс на существующих полях
     *
     * @return void
     */
    public function safeUp()
    {
        $driver = $this->db->driverName;

        if ($driver === 'pgsql') {
            // PostgreSQL реализация с использованием tsvector и триггеров

            // Добавляем колонку для хранения tsvector (полнотекстового индекса)
            $this->addColumn(
                self::TABLE_NAME,
                'search_vector',
                'tsvector'
            );

            $this->addCommentOnColumn(
                self::TABLE_NAME,
                'search_vector',
                'Полнотекстовый индекс для поиска (PostgreSQL tsvector)'
            );

            // Создаем функцию для обновления search_vector
            $this->execute("
                CREATE OR REPLACE FUNCTION vacancy_search_vector_update() RETURNS trigger AS $$
                BEGIN
                    NEW.search_vector :=
                        setweight(to_tsvector('russian', coalesce(NEW.title, '')), 'A') ||
                        setweight(to_tsvector('russian', coalesce(NEW.description, '')), 'B');
                    RETURN NEW;
                END
                $$ LANGUAGE plpgsql;
            ");

            // Создаем триггер для автоматического обновления search_vector
            $this->execute("
                CREATE TRIGGER vacancy_search_vector_trigger
                BEFORE INSERT OR UPDATE ON " . self::TABLE_NAME . "
                FOR EACH ROW
                EXECUTE FUNCTION vacancy_search_vector_update();
            ");

            // Обновляем существующие записи
            $this->execute("
                UPDATE " . self::TABLE_NAME . "
                SET search_vector =
                    setweight(to_tsvector('russian', coalesce(title, '')), 'A') ||
                    setweight(to_tsvector('russian', coalesce(description, '')), 'B');
            ");

            // Создаем GIN индекс для быстрого полнотекстового поиска
            $this->execute("
                CREATE INDEX idx_vacancy_search_vector
                ON " . self::TABLE_NAME . "
                USING GIN (search_vector);
            ");

            echo "    > PostgreSQL полнотекстовый поиск настроен (tsvector + GIN)\n";

        } elseif ($driver === 'mysql') {
            // MySQL реализация с использованием FULLTEXT индекса

            // Проверяем версию MySQL (FULLTEXT для InnoDB доступен с 5.6)
            $version = $this->db->createCommand('SELECT VERSION()')->queryScalar();
            echo "    > MySQL версия: {$version}\n";

            // Создаем FULLTEXT индекс на полях title и description
            $this->execute("
                ALTER TABLE " . self::TABLE_NAME . "
                ADD FULLTEXT INDEX idx_vacancy_fulltext (title, description)
            ");

            echo "    > MySQL FULLTEXT индекс создан\n";

        } else {
            echo "    > Внимание: полнотекстовый поиск не поддерживается для {$driver}\n";
            echo "    > Будет использован обычный LIKE поиск\n";
        }
    }

    /**
     * Откатывает миграцию
     *
     * Удаляет FULLTEXT индексы и связанные объекты БД
     *
     * @return void
     */
    public function safeDown()
    {
        $driver = $this->db->driverName;

        if ($driver === 'pgsql') {
            // Удаляем GIN индекс
            $this->execute("DROP INDEX IF EXISTS idx_vacancy_search_vector");

            // Удаляем триггер
            $this->execute("DROP TRIGGER IF EXISTS vacancy_search_vector_trigger ON " . self::TABLE_NAME);

            // Удаляем функцию
            $this->execute("DROP FUNCTION IF EXISTS vacancy_search_vector_update()");

            // Удаляем колонку search_vector
            $this->dropColumn(self::TABLE_NAME, 'search_vector');

            echo "    > PostgreSQL полнотекстовый поиск удален\n";

        } elseif ($driver === 'mysql') {
            // Удаляем FULLTEXT индекс
            $this->execute("
                ALTER TABLE " . self::TABLE_NAME . "
                DROP INDEX idx_vacancy_fulltext
            ");

            echo "    > MySQL FULLTEXT индекс удален\n";
        }
    }
}
