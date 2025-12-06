<?php

namespace tests\api;

use app\models\Vacancy;
use ApiTester;

/**
 * API тесты для endpoints вакансий
 *
 * Тестирует REST API endpoints контроллера VacancyController
 */
class VacancyCest
{
    /**
     * Подготовка перед каждым тестом
     */
    public function _before(ApiTester $I)
    {
        // Очистка таблицы вакансий перед каждым тестом
        Vacancy::deleteAll();
    }

    /**
     * Тест получения пустого списка вакансий
     */
    public function testGetEmptyVacancyList(ApiTester $I)
    {
        $I->sendGET('/vacancy');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'data' => [],
            'pagination' => [
                'total' => 0,
                'page' => 1,
                'pageSize' => 10
            ]
        ]);
    }

    /**
     * Тест получения списка вакансий
     */
    public function testGetVacancyList(ApiTester $I)
    {
        // Создаем тестовые вакансии
        $vacancy1 = new Vacancy([
            'title' => 'PHP Developer',
            'description' => 'Senior PHP Dev',
            'salary' => 150000
        ]);
        $vacancy1->save(false);

        $vacancy2 = new Vacancy([
            'title' => 'Java Developer',
            'description' => 'Middle Java Dev',
            'salary' => 120000
        ]);
        $vacancy2->save(false);

        $I->sendGET('/vacancy');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseMatchesJsonType([
            'data' => 'array',
            'pagination' => [
                'total' => 'integer',
                'page' => 'integer',
                'pageSize' => 'integer',
                'pageCount' => 'integer'
            ]
        ]);
        $I->seeResponseContainsJson([
            'pagination' => [
                'total' => 2,
                'pageSize' => 10
            ]
        ]);
    }

    /**
     * Тест получения списка с сортировкой по зарплате
     */
    public function testGetVacancyListWithSorting(ApiTester $I)
    {
        $vacancy1 = new Vacancy(['title' => 'Test1', 'description' => 'Desc1', 'salary' => 100000]);
        $vacancy1->save(false);

        $vacancy2 = new Vacancy(['title' => 'Test2', 'description' => 'Desc2', 'salary' => 200000]);
        $vacancy2->save(false);

        $I->sendGET('/vacancy?sort=salary&order=desc');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();

        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals(200000, $response['data'][0]['salary'], 'Первая вакансия должна иметь наибольшую зарплату');
    }

    /**
     * Тест создания вакансии с корректными данными
     */
    public function testCreateVacancySuccess(ApiTester $I)
    {
        $I->sendPOST('/vacancy', [
            'title' => 'Test Vacancy',
            'description' => 'Test Description',
            'salary' => 150000,
            'additional_fields' => [
                'location' => 'Москва',
                'remote' => true
            ]
        ]);

        $I->seeResponseCodeIs(201);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'Вакансия успешно создана'
        ]);
        $I->seeResponseMatchesJsonType([
            'id' => 'integer'
        ]);
    }

    /**
     * Тест создания вакансии с ошибкой валидации
     */
    public function testCreateVacancyValidationError(ApiTester $I)
    {
        $I->sendPOST('/vacancy', [
            'title' => '', // Пустое обязательное поле
            'description' => 'Test'
        ]);

        $I->seeResponseCodeIs(400);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => false
        ]);
        $I->seeResponseMatchesJsonType([
            'errors' => 'array'
        ]);
    }

    /**
     * Тест получения вакансии по ID
     */
    public function testGetVacancyById(ApiTester $I)
    {
        $vacancy = new Vacancy([
            'title' => 'PHP Developer',
            'description' => 'Senior Developer',
            'salary' => 150000,
            'additional_fields' => ['location' => 'Moscow']
        ]);
        $vacancy->save(false);

        $I->sendGET("/vacancy/{$vacancy->id}");
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'id' => $vacancy->id,
            'title' => 'PHP Developer',
            'salary' => 150000
        ]);
    }

    /**
     * Тест получения несуществующей вакансии
     */
    public function testGetVacancyByIdNotFound(ApiTester $I)
    {
        $I->sendGET('/vacancy/99999');
        $I->seeResponseCodeIs(404);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => false,
            'message' => 'Вакансия не найдена'
        ]);
    }

    /**
     * Тест получения вакансии с фильтрацией полей
     */
    public function testGetVacancyByIdWithFields(ApiTester $I)
    {
        $vacancy = new Vacancy([
            'title' => 'PHP Developer',
            'description' => 'Senior Developer',
            'salary' => 150000
        ]);
        $vacancy->save(false);

        $I->sendGET("/vacancy/{$vacancy->id}?fields=title,salary");
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();

        $response = json_decode($I->grabResponse(), true);
        $I->assertArrayHasKey('id', $response);
        $I->assertArrayHasKey('title', $response);
        $I->assertArrayHasKey('salary', $response);
        $I->assertArrayNotHasKey('description', $response, 'description не должно быть в ответе');
    }

    /**
     * Тест обновления вакансии
     */
    public function testUpdateVacancySuccess(ApiTester $I)
    {
        $vacancy = new Vacancy([
            'title' => 'Old Title',
            'description' => 'Old Description',
            'salary' => 100000
        ]);
        $vacancy->save(false);

        $I->sendPUT("/vacancy/{$vacancy->id}", [
            'title' => 'New Title',
            'salary' => 200000
        ]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'Вакансия успешно обновлена'
        ]);

        // Проверяем, что данные действительно обновились
        $vacancy->refresh();
        $I->assertEquals('New Title', $vacancy->title);
        $I->assertEquals(200000, $vacancy->salary);
        $I->assertEquals('Old Description', $vacancy->description); // Не должно измениться
    }

    /**
     * Тест обновления несуществующей вакансии
     */
    public function testUpdateVacancyNotFound(ApiTester $I)
    {
        $I->sendPUT('/vacancy/99999', [
            'title' => 'Test'
        ]);

        $I->seeResponseCodeIs(404);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => false,
            'message' => 'Вакансия не найдена'
        ]);
    }

    /**
     * Тест удаления вакансии
     */
    public function testDeleteVacancySuccess(ApiTester $I)
    {
        $vacancy = new Vacancy([
            'title' => 'To Delete',
            'description' => 'Test',
            'salary' => 100000
        ]);
        $vacancy->save(false);

        $I->sendDELETE("/vacancy/{$vacancy->id}");
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'Вакансия успешно удалена'
        ]);

        // Проверяем, что вакансия действительно удалена
        $I->assertNull(Vacancy::findOne($vacancy->id));
    }

    /**
     * Тест удаления несуществующей вакансии
     */
    public function testDeleteVacancyNotFound(ApiTester $I)
    {
        $I->sendDELETE('/vacancy/99999');
        $I->seeResponseCodeIs(404);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => false,
            'message' => 'Вакансия не найдена'
        ]);
    }
}
