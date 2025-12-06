<?php

namespace tests\unit\models;

use app\models\Vacancy;
use Codeception\Test\Unit;

/**
 * Unit тесты для модели Vacancy
 *
 * Тестирует валидацию, правила и поведение модели вакансии.
 */
class VacancyTest extends Unit
{
    /**
     * Тест успешной валидации с корректными данными
     */
    public function testValidationSuccess()
    {
        $vacancy = new Vacancy();
        $vacancy->title = 'PHP Developer';
        $vacancy->description = 'Требуется опытный PHP разработчик';
        $vacancy->salary = 150000;

        $this->assertTrue($vacancy->validate(), 'Валидация должна пройти успешно');
        $this->assertEmpty($vacancy->errors, 'Не должно быть ошибок валидации');
    }

    /**
     * Тест валидации обязательных полей
     */
    public function testRequiredFieldsValidation()
    {
        $vacancy = new Vacancy();

        $this->assertFalse($vacancy->validate(), 'Валидация должна провалиться без обязательных полей');
        $this->assertArrayHasKey('title', $vacancy->errors, 'Должна быть ошибка для поля title');
        $this->assertArrayHasKey('description', $vacancy->errors, 'Должна быть ошибка для поля description');
        $this->assertArrayHasKey('salary', $vacancy->errors, 'Должна быть ошибка для поля salary');
    }

    /**
     * Тест валидации отрицательной зарплаты
     */
    public function testNegativeSalaryValidation()
    {
        $vacancy = new Vacancy();
        $vacancy->title = 'Test';
        $vacancy->description = 'Test description';
        $vacancy->salary = -1000;

        $this->assertFalse($vacancy->validate(), 'Валидация должна провалиться для отрицательной зарплаты');
        $this->assertArrayHasKey('salary', $vacancy->errors, 'Должна быть ошибка для поля salary');
    }

    /**
     * Тест валидации нулевой зарплаты (граничное значение)
     */
    public function testZeroSalaryValidation()
    {
        $vacancy = new Vacancy();
        $vacancy->title = 'Test';
        $vacancy->description = 'Test description';
        $vacancy->salary = 0;

        $this->assertTrue($vacancy->validate(), 'Зарплата 0 должна быть валидной');
    }

    /**
     * Тест превышения максимальной длины заголовка
     */
    public function testTitleMaxLengthValidation()
    {
        $vacancy = new Vacancy();
        $vacancy->title = str_repeat('a', 256); // 256 символов - превышение лимита
        $vacancy->description = 'Test';
        $vacancy->salary = 100000;

        $this->assertFalse($vacancy->validate(), 'Валидация должна провалиться для слишком длинного заголовка');
        $this->assertArrayHasKey('title', $vacancy->errors, 'Должна быть ошибка для поля title');
    }

    /**
     * Тест максимальной допустимой длины заголовка
     */
    public function testTitleExactMaxLength()
    {
        $vacancy = new Vacancy();
        $vacancy->title = str_repeat('a', 255); // Ровно 255 символов
        $vacancy->description = 'Test';
        $vacancy->salary = 100000;

        $this->assertTrue($vacancy->validate(), 'Заголовок из 255 символов должен быть валидным');
    }

    /**
     * Тест валидации additional_fields как массива
     */
    public function testAdditionalFieldsArrayValidation()
    {
        $vacancy = new Vacancy();
        $vacancy->title = 'PHP Developer';
        $vacancy->description = 'Test';
        $vacancy->salary = 100000;
        $vacancy->additional_fields = ['location' => 'Москва', 'remote' => true];

        $this->assertTrue($vacancy->validate(), 'additional_fields с массивом должен быть валидным');
    }

    /**
     * Тест валидации additional_fields с некорректным типом
     */
    public function testAdditionalFieldsInvalidTypeValidation()
    {
        $vacancy = new Vacancy();
        $vacancy->title = 'PHP Developer';
        $vacancy->description = 'Test';
        $vacancy->salary = 100000;
        $vacancy->additional_fields = 'invalid_string'; // Строка вместо массива

        $this->assertFalse($vacancy->validate(), 'Валидация должна провалиться для некорректного типа additional_fields');
        $this->assertArrayHasKey('additional_fields', $vacancy->errors);
    }

    /**
     * Тест валидации слишком больших additional_fields
     */
    public function testAdditionalFieldsTooLargeValidation()
    {
        $vacancy = new Vacancy();
        $vacancy->title = 'PHP Developer';
        $vacancy->description = 'Test';
        $vacancy->salary = 100000;

        // Создаем массив, который превысит лимит в 5000 символов JSON
        $largeArray = [];
        for ($i = 0; $i < 500; $i++) {
            $largeArray["field_$i"] = str_repeat('x', 20);
        }
        $vacancy->additional_fields = $largeArray;

        $this->assertFalse($vacancy->validate(), 'Валидация должна провалиться для слишком больших additional_fields');
        $this->assertArrayHasKey('additional_fields', $vacancy->errors);
    }

    /**
     * Тест что пустой additional_fields валиден
     */
    public function testEmptyAdditionalFieldsValidation()
    {
        $vacancy = new Vacancy();
        $vacancy->title = 'PHP Developer';
        $vacancy->description = 'Test';
        $vacancy->salary = 100000;
        $vacancy->additional_fields = null;

        $this->assertTrue($vacancy->validate(), 'Пустой additional_fields должен быть валидным');
    }

    /**
     * Тест attributeLabels() возвращает корректные метки
     */
    public function testAttributeLabels()
    {
        $vacancy = new Vacancy();
        $labels = $vacancy->attributeLabels();

        $this->assertArrayHasKey('title', $labels);
        $this->assertArrayHasKey('description', $labels);
        $this->assertArrayHasKey('salary', $labels);
        $this->assertArrayHasKey('additional_fields', $labels);
        $this->assertEquals('Название вакансии', $labels['title']);
        $this->assertEquals('Зарплата', $labels['salary']);
    }

    /**
     * Тест fields() возвращает список полей для API
     */
    public function testFieldsForApi()
    {
        $vacancy = new Vacancy();
        $fields = $vacancy->fields();

        $this->assertIsArray($fields);
        $this->assertContains('id', $fields);
        $this->assertContains('title', $fields);
        $this->assertContains('description', $fields);
        $this->assertContains('salary', $fields);
        $this->assertContains('additional_fields', $fields);
        $this->assertContains('created_at', $fields);
        $this->assertContains('updated_at', $fields);
    }

    /**
     * Тест tableName() возвращает корректное имя таблицы
     */
    public function testTableName()
    {
        $this->assertEquals('{{%vacancy}}', Vacancy::tableName());
    }
}
