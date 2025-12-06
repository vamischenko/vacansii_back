<?php

namespace tests\unit\services;

use app\models\Vacancy;
use app\services\VacancyService;
use app\repositories\VacancyRepositoryInterface;
use Codeception\Test\Unit;
use yii\data\ActiveDataProvider;

/**
 * Unit тесты для VacancyService
 *
 * Тестирует бизнес-логику сервиса вакансий с использованием mock объектов.
 */
class VacancyServiceTest extends Unit
{
    /**
     * Тест валидации некорректного поля сортировки
     */
    public function testGetVacancyListWithInvalidSortField()
    {
        // Создаем mock репозитория
        $repository = $this->createMock(VacancyRepositoryInterface::class);

        // Настраиваем ожидание вызова с дефолтным полем
        $repository->expects($this->once())
            ->method('findAll')
            ->with(1, 'created_at', 'desc') // Должно использовать дефолтное значение
            ->willReturn($this->createMockDataProvider([]));

        $service = new VacancyService($repository);

        // Передаем невалидное поле сортировки
        $result = $service->getVacancyList(1, 'invalid_field', 'desc');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
    }

    /**
     * Тест валидации некорректного направления сортировки
     */
    public function testGetVacancyListWithInvalidSortOrder()
    {
        $repository = $this->createMock(VacancyRepositoryInterface::class);

        $repository->expects($this->once())
            ->method('findAll')
            ->with(1, 'salary', 'desc') // Должно использовать дефолтное направление
            ->willReturn($this->createMockDataProvider([]));

        $service = new VacancyService($repository);

        // Передаем невалидное направление сортировки
        $result = $service->getVacancyList(1, 'salary', 'invalid_order');

        $this->assertIsArray($result);
    }

    /**
     * Тест успешного получения списка вакансий
     */
    public function testGetVacancyListSuccess()
    {
        $vacancy1 = new Vacancy(['id' => 1, 'title' => 'PHP Dev', 'salary' => 150000, 'description' => 'Test1']);
        $vacancy2 = new Vacancy(['id' => 2, 'title' => 'Java Dev', 'salary' => 180000, 'description' => 'Test2']);

        $repository = $this->createMock(VacancyRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findAll')
            ->willReturn($this->createMockDataProvider([$vacancy1, $vacancy2], 2));

        $service = new VacancyService($repository);
        $result = $service->getVacancyList(1, 'salary', 'desc');

        $this->assertCount(2, $result['data']);
        $this->assertEquals(1, $result['data'][0]['id']);
        $this->assertEquals('PHP Dev', $result['data'][0]['title']);
        $this->assertEquals(2, $result['pagination']['total']);
        $this->assertEquals(10, $result['pagination']['pageSize']);
    }

    /**
     * Тест получения несуществующей вакансии
     */
    public function testGetVacancyByIdNotFound()
    {
        $repository = $this->createMock(VacancyRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $service = new VacancyService($repository);
        $result = $service->getVacancyById(999);

        $this->assertNull($result);
    }

    /**
     * Тест успешного получения вакансии по ID
     */
    public function testGetVacancyByIdSuccess()
    {
        $vacancy = new Vacancy([
            'id' => 1,
            'title' => 'PHP Developer',
            'salary' => 150000,
            'description' => 'Test description',
            'additional_fields' => ['location' => 'Moscow']
        ]);

        $repository = $this->createMock(VacancyRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($vacancy);

        $service = new VacancyService($repository);
        $result = $service->getVacancyById(1);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('PHP Developer', $result['title']);
        $this->assertArrayHasKey('additional_fields', $result);
    }

    /**
     * Тест получения вакансии с фильтрацией полей
     */
    public function testGetVacancyByIdWithFieldsFilter()
    {
        $vacancy = new Vacancy([
            'id' => 1,
            'title' => 'PHP Developer',
            'salary' => 150000,
            'description' => 'Test description'
        ]);

        $repository = $this->createMock(VacancyRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($vacancy);

        $service = new VacancyService($repository);
        $result = $service->getVacancyById(1, ['title', 'salary']);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('salary', $result);
        $this->assertArrayNotHasKey('description', $result, 'description не должно быть в результате');
    }

    /**
     * Тест создания вакансии с ошибкой валидации
     */
    public function testCreateVacancyValidationError()
    {
        $repository = $this->createMock(VacancyRepositoryInterface::class);
        $repository->expects($this->never())->method('save');

        $service = new VacancyService($repository);

        // Передаем пустые данные - должна быть ошибка валидации
        $result = $service->createVacancy([]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
    }

    /**
     * Тест удаления несуществующей вакансии
     */
    public function testDeleteVacancyNotFound()
    {
        $repository = $this->createMock(VacancyRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('delete')
            ->with(999)
            ->willReturn(false);

        $service = new VacancyService($repository);
        $result = $service->deleteVacancy(999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Вакансия не найдена', $result['message']);
    }

    /**
     * Тест успешного удаления вакансии
     */
    public function testDeleteVacancySuccess()
    {
        $repository = $this->createMock(VacancyRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('delete')
            ->with(1)
            ->willReturn(true);

        $service = new VacancyService($repository);
        $result = $service->deleteVacancy(1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Вакансия успешно удалена', $result['message']);
    }

    /**
     * Вспомогательный метод для создания mock DataProvider
     *
     * @param array $models Массив моделей
     * @param int $totalCount Общее количество записей
     * @return ActiveDataProvider
     */
    private function createMockDataProvider(array $models, int $totalCount = 0): ActiveDataProvider
    {
        $dataProvider = $this->createMock(ActiveDataProvider::class);

        $dataProvider->method('getModels')
            ->willReturn($models);

        $dataProvider->method('getTotalCount')
            ->willReturn($totalCount);

        $pagination = new \stdClass();
        $pagination->pageCount = ceil($totalCount / 10);
        $dataProvider->pagination = $pagination;

        return $dataProvider;
    }
}
