<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * Модель вакансии.
 *
 * ActiveRecord модель для работы с таблицей вакансий в базе данных.
 * Представляет сущность вакансии с основными полями и дополнительными данными в формате JSON.
 *
 * @property int $id Уникальный идентификатор вакансии
 * @property string $title Название вакансии (до 255 символов)
 * @property string $description Подробное описание вакансии
 * @property int $salary Зарплата в целочисленном формате (в минимальных единицах валюты)
 * @property array|null $additional_fields Дополнительные поля в формате JSON (опционально)
 * @property int $created_at Timestamp создания записи (автоматически)
 * @property int $updated_at Timestamp последнего обновления (автоматически)
 *
 * @package app\models
 * @author Yii2 Vacancy Management System
 */
class Vacancy extends ActiveRecord
{
    /**
     * Возвращает имя таблицы в базе данных.
     *
     * Метод определяет название таблицы для данной ActiveRecord модели.
     * Использует синтаксис {{%vacancy}}, где {{%...}} позволяет автоматически
     * подставлять префикс таблицы из конфигурации приложения.
     *
     * @return string Имя таблицы с поддержкой префикса
     */
    public static function tableName()
    {
        return '{{%vacancy}}';
    }

    /**
     * Определяет поведения модели.
     *
     * Настраивает автоматическое поведение для модели. В данном случае используется
     * TimestampBehavior, которое автоматически заполняет поля created_at и updated_at
     * Unix timestamp'ами при создании и обновлении записи.
     *
     * @return array Конфигурация поведений
     * @see \yii\behaviors\TimestampBehavior
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * Правила валидации атрибутов модели.
     *
     * Определяет правила проверки данных перед сохранением в базу данных.
     * Включает проверку обязательных полей, типов данных и ограничений.
     *
     * Правила:
     * - title, description, salary - обязательные поля
     * - title - строка, максимум 255 символов
     * - description - строка без ограничения длины
     * - salary - целое число, минимум 0
     * - additional_fields - безопасное поле (без валидации, для JSON данных)
     *
     * @return array Массив правил валидации
     *
     * @example
     * $vacancy = new Vacancy();
     * $vacancy->title = 'PHP Developer';
     * $vacancy->description = 'Требуется опытный PHP разработчик';
     * $vacancy->salary = 150000;
     * $vacancy->additional_fields = ['location' => 'Москва', 'remote' => true];
     *
     * if ($vacancy->validate()) {
     *     $vacancy->save();
     * } else {
     *     print_r($vacancy->errors);
     * }
     */
    public function rules()
    {
        return [
            [['title', 'description', 'salary'], 'required'],
            ['title', 'string', 'max' => 255],
            ['description', 'string'],
            ['salary', 'integer', 'min' => 0],
            ['additional_fields', 'validateAdditionalFields'],
        ];
    }

    /**
     * Кастомный валидатор для дополнительных полей.
     *
     * Проверяет, что additional_fields является массивом (если указано)
     * и не превышает максимальный размер при сериализации в JSON.
     *
     * @param string $attribute Имя атрибута для валидации
     * @param array $params Дополнительные параметры валидации
     * @return void
     */
    public function validateAdditionalFields($attribute, $params)
    {
        if (!empty($this->$attribute)) {
            // Проверка, что значение является массивом
            if (!is_array($this->$attribute)) {
                $this->addError($attribute, 'Дополнительные поля должны быть массивом.');
                return;
            }

            // Проверка на максимальный размер JSON (5000 символов)
            $jsonEncoded = json_encode($this->$attribute);
            if ($jsonEncoded === false) {
                $this->addError($attribute, 'Некорректный формат дополнительных полей.');
                return;
            }

            if (strlen($jsonEncoded) > 5000) {
                $this->addError($attribute, 'Дополнительные поля слишком большие (максимум 5000 символов в JSON).');
            }
        }
    }

    /**
     * Определяет поля для сериализации в API ответах.
     *
     * Указывает, какие атрибуты модели должны быть включены в JSON/XML ответ
     * при сериализации объекта (например, через REST API). Все основные поля
     * вакансии доступны для чтения через API.
     *
     * @return array Список полей для сериализации
     *
     * @example
     * // JSON ответ будет содержать все эти поля:
     * {
     *   "id": 1,
     *   "title": "PHP Developer",
     *   "description": "Требуется опытный разработчик",
     *   "salary": 150000,
     *   "additional_fields": {"location": "Москва"},
     *   "created_at": 1707091200,
     *   "updated_at": 1707091200
     * }
     */
    public function fields()
    {
        return [
            'id',
            'title',
            'description',
            'salary',
            'additional_fields',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Возвращает метки (названия) для атрибутов модели.
     *
     * Определяет человекочитаемые названия атрибутов на русском языке,
     * которые используются в формах, сообщениях об ошибках и представлениях.
     *
     * @return array Ассоциативный массив [атрибут => метка]
     *
     * @example
     * echo $vacancy->getAttributeLabel('title'); // "Название вакансии"
     * echo $vacancy->getAttributeLabel('salary'); // "Зарплата"
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Название вакансии',
            'description' => 'Описание',
            'salary' => 'Зарплата',
            'additional_fields' => 'Дополнительные поля',
            'created_at' => 'Дата создания',
            'updated_at' => 'Дата обновления',
        ];
    }
}
