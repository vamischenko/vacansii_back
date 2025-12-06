<?php

namespace app\services;

use app\models\Vacancy;
use app\repositories\VacancyRepositoryInterface;
use Yii;

/**
 * Сервис для работы с вакансиями.
 *
 * Содержит бизнес-логику приложения для управления вакансиями.
 * Является прослойкой между контроллерами и репозиторием,
 * обрабатывает данные, выполняет валидацию и форматирование ответов.
 *
 * @package app\services
 * @author Yii2 Vacancy Management System
 */
class VacancyService
{
    /**
     * Размер страницы для пагинации
     */
    private const PAGE_SIZE = 10;

    /**
     * Разрешенные поля для сортировки
     */
    private const ALLOWED_SORT_FIELDS = ['salary', 'created_at'];

    /**
     * Разрешенные направления сортировки
     */
    private const ALLOWED_SORT_ORDERS = ['asc', 'desc'];

    /**
     * Разрешенные поля для фильтрации при получении вакансии
     */
    private const ALLOWED_FILTER_FIELDS = ['title', 'description', 'salary', 'additional_fields'];

    /**
     * Время жизни кеша для списка вакансий (в секундах)
     * По умолчанию 5 минут
     */
    private const CACHE_DURATION_LIST = 300;

    /**
     * Время жизни кеша для одной вакансии (в секундах)
     * По умолчанию 10 минут
     */
    private const CACHE_DURATION_SINGLE = 600;

    /**
     * @var VacancyRepositoryInterface Репозиторий для работы с данными вакансий
     */
    private VacancyRepositoryInterface $repository;

    /**
     * Конструктор сервиса.
     *
     * @param VacancyRepositoryInterface $repository Репозиторий вакансий (внедряется через DI)
     */
    public function __construct(VacancyRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Получить список вакансий с пагинацией и сортировкой.
     *
     * Возвращает постраничный список вакансий с указанной сортировкой.
     * Выполняет валидацию параметров сортировки и устанавливает значения по умолчанию
     * для недопустимых значений.
     *
     * @param int $page Номер страницы (начиная с 1)
     * @param string $sortBy Поле для сортировки ('salary' или 'created_at')
     * @param string $sortOrder Направление сортировки ('asc' или 'desc')
     *
     * @return array Массив с данными вакансий и информацией о пагинации
     *               Структура ответа:
     *               [
     *                   'data' => [массив вакансий с полями: id, title, salary, description],
     *                   'pagination' => [
     *                       'total' => общее количество вакансий,
     *                       'page' => текущая страница,
     *                       'pageSize' => размер страницы (10),
     *                       'pageCount' => общее количество страниц
     *                   ]
     *               ]
     *
     * @example
     * $service = new VacancyService($repository);
     * $result = $service->getVacancyList(1, 'salary', 'desc');
     * // Вернёт первую страницу вакансий, отсортированных по зарплате (от большей к меньшей)
     */
    public function getVacancyList(int $page, string $sortBy, string $sortOrder): array
    {
        // Валидация параметров сортировки
        if (!in_array($sortBy, self::ALLOWED_SORT_FIELDS)) {
            Yii::warning("Invalid sort field requested: {$sortBy}. Using default 'created_at'", __METHOD__);
            $sortBy = 'created_at';
        }

        if (!in_array($sortOrder, self::ALLOWED_SORT_ORDERS)) {
            Yii::warning("Invalid sort order requested: {$sortOrder}. Using default 'desc'", __METHOD__);
            $sortOrder = 'desc';
        }

        // Проверка кеша
        $cacheKey = "vacancy_list_{$page}_{$sortBy}_{$sortOrder}";
        $cache = Yii::$app->cache;

        $result = $cache->get($cacheKey);
        if ($result !== false) {
            Yii::info("Cache HIT for vacancy list: {$cacheKey}", __METHOD__);
            return $result;
        }

        try {
            Yii::info("Cache MISS. Fetching vacancy list from DB: page={$page}, sortBy={$sortBy}, sortOrder={$sortOrder}", __METHOD__);

            $dataProvider = $this->repository->findAll($page, $sortBy, $sortOrder);

            $vacancies = [];
            foreach ($dataProvider->getModels() as $vacancy) {
                $vacancies[] = [
                    'id' => $vacancy->id,
                    'title' => $vacancy->title,
                    'salary' => $vacancy->salary,
                    'description' => $vacancy->description,
                ];
            }

            $result = [
                'data' => $vacancies,
                'pagination' => [
                    'total' => $dataProvider->getTotalCount(),
                    'page' => $page,
                    'pageSize' => self::PAGE_SIZE,
                    'pageCount' => $dataProvider->pagination->pageCount,
                ],
            ];

            // Сохранение в кеш
            $cache->set($cacheKey, $result, self::CACHE_DURATION_LIST);
            Yii::info("Successfully fetched and cached {$dataProvider->getTotalCount()} vacancies", __METHOD__);

            return $result;
        } catch (\Exception $e) {
            Yii::error("Failed to fetch vacancy list: " . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }

    /**
     * Получить вакансию по ID с опциональной фильтрацией полей.
     *
     * Возвращает данные конкретной вакансии. Если указан параметр fields,
     * возвращает только запрошенные поля (плюс обязательное поле id).
     *
     * @param int $id Уникальный идентификатор вакансии
     * @param array|null $fields Массив названий полей для фильтрации (опционально)
     *                           Допустимые значения: 'title', 'description', 'salary', 'additional_fields'
     *
     * @return array|null Массив с данными вакансии или null, если вакансия не найдена
     *                    При указании $fields возвращает только запрошенные поля + id
     *                    Без $fields возвращает все поля: id, title, salary, description, additional_fields (если есть)
     *
     * @example
     * // Получить все поля вакансии
     * $vacancy = $service->getVacancyById(1);
     * // Результат: ['id' => 1, 'title' => 'PHP Dev', 'salary' => 150000, 'description' => '...', 'additional_fields' => [...]]
     *
     * @example
     * // Получить только определённые поля
     * $vacancy = $service->getVacancyById(1, ['title', 'salary']);
     * // Результат: ['id' => 1, 'title' => 'PHP Dev', 'salary' => 150000]
     */
    public function getVacancyById(int $id, ?array $fields = null): ?array
    {
        // Проверка кеша (только если fields не указан, чтобы упростить)
        $cacheKey = "vacancy_{$id}";
        $cache = Yii::$app->cache;

        if ($fields === null) {
            $result = $cache->get($cacheKey);
            if ($result !== false) {
                Yii::info("Cache HIT for vacancy: {$cacheKey}", __METHOD__);
                return $result;
            }
        }

        try {
            Yii::info("Cache MISS. Fetching vacancy by ID from DB: {$id}", __METHOD__);

            $vacancy = $this->repository->findById($id);

            if (!$vacancy) {
                Yii::warning("Vacancy not found: ID={$id}", __METHOD__);
                return null;
            }

            $result = [
                'id' => $vacancy->id,
                'title' => $vacancy->title,
                'salary' => $vacancy->salary,
                'description' => $vacancy->description,
            ];

            if ($fields) {
                $filteredResult = ['id' => $vacancy->id];
                foreach ($fields as $field) {
                    $field = trim($field);
                    if (in_array($field, self::ALLOWED_FILTER_FIELDS) && isset($result[$field])) {
                        $filteredResult[$field] = $result[$field];
                    } elseif ($field === 'additional_fields' && $vacancy->additional_fields) {
                        $filteredResult['additional_fields'] = $vacancy->additional_fields;
                    }
                }

                Yii::info("Successfully fetched vacancy {$id} with filtered fields", __METHOD__);
                return $filteredResult;
            }

            if ($vacancy->additional_fields) {
                $result['additional_fields'] = $vacancy->additional_fields;
            }

            // Сохраняем в кеш только полный результат (без фильтрации полей)
            if ($fields === null) {
                $cache->set($cacheKey, $result, self::CACHE_DURATION_SINGLE);
            }

            Yii::info("Successfully fetched vacancy {$id}", __METHOD__);
            return $result;
        } catch (\Exception $e) {
            Yii::error("Failed to fetch vacancy {$id}: " . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }

    /**
     * Создать новую вакансию.
     *
     * Создаёт новую вакансию на основе переданных данных.
     * Выполняет валидацию и сохраняет запись в базу данных через репозиторий.
     * Устанавливает соответствующий HTTP статус код (201 при успехе, 400 при ошибке).
     *
     * @param array $data Массив с данными вакансии
     *                    Обязательные поля: title, description, salary
     *                    Опциональные поля: additional_fields (массив)
     *
     * @return array Массив с результатом операции
     *               При успехе:
     *               ['success' => true, 'id' => ID созданной вакансии, 'message' => 'Вакансия успешно создана']
     *               При ошибке:
     *               ['success' => false, 'errors' => массив ошибок валидации, 'message' => 'Ошибка при создании вакансии']
     *
     * @example
     * $result = $service->createVacancy([
     *     'title' => 'PHP Developer',
     *     'description' => 'Требуется опытный разработчик',
     *     'salary' => 150000,
     *     'additional_fields' => ['location' => 'Москва', 'remote' => true]
     * ]);
     * // При успехе: ['success' => true, 'id' => 42, 'message' => 'Вакансия успешно создана']
     * // HTTP статус: 201 Created
     */
    public function createVacancy(array $data): array
    {
        try {
            Yii::info("Creating new vacancy", __METHOD__);

            $vacancy = new Vacancy();
            $vacancy->title = $data['title'] ?? null;
            $vacancy->description = $data['description'] ?? null;
            $vacancy->salary = $data['salary'] ?? null;

            if (isset($data['additional_fields'])) {
                $vacancy->additional_fields = $data['additional_fields'];
            }

            if ($vacancy->validate()) {
                if ($this->repository->save($vacancy)) {
                    // Инвалидация кеша списков при создании
                    $this->invalidateListCache();

                    Yii::info("Vacancy created successfully: ID={$vacancy->id}", __METHOD__);
                    Yii::$app->response->statusCode = 201;
                    return [
                        'success' => true,
                        'id' => $vacancy->id,
                        'message' => 'Вакансия успешно создана',
                    ];
                }

                // Ошибка сохранения в БД
                Yii::error("Failed to save vacancy to database", __METHOD__);
                Yii::$app->response->statusCode = 500;
                return [
                    'success' => false,
                    'message' => 'Внутренняя ошибка сервера при сохранении вакансии',
                ];
            }

            // Ошибка валидации
            Yii::warning("Vacancy validation failed: " . json_encode($vacancy->errors), __METHOD__);
            Yii::$app->response->statusCode = 400;
            return [
                'success' => false,
                'errors' => $vacancy->errors,
                'message' => 'Ошибка при создании вакансии',
            ];
        } catch (\Exception $e) {
            Yii::error("Exception while creating vacancy: " . $e->getMessage(), __METHOD__);
            Yii::$app->response->statusCode = 500;
            return [
                'success' => false,
                'message' => 'Внутренняя ошибка сервера',
            ];
        }
    }

    /**
     * Обновить существующую вакансию.
     *
     * Обновляет данные вакансии по её ID. Обновляются только те поля,
     * которые присутствуют в массиве $data. Выполняет валидацию обновлённых данных.
     * Устанавливает соответствующий HTTP статус код (200 при успехе, 404 если не найдена, 400 при ошибке валидации).
     *
     * @param int $id Уникальный идентификатор вакансии для обновления
     * @param array $data Массив с данными для обновления
     *                    Все поля опциональны: title, description, salary, additional_fields
     *                    Обновляются только переданные поля
     *
     * @return array Массив с результатом операции
     *               При успехе:
     *               ['success' => true, 'message' => 'Вакансия успешно обновлена']
     *               Если вакансия не найдена:
     *               ['success' => false, 'message' => 'Вакансия не найдена'] (HTTP 404)
     *               При ошибке валидации:
     *               ['success' => false, 'errors' => массив ошибок, 'message' => 'Ошибка при обновлении вакансии'] (HTTP 400)
     *
     * @example
     * // Обновить только зарплату
     * $result = $service->updateVacancy(1, ['salary' => 180000]);
     *
     * @example
     * // Обновить несколько полей
     * $result = $service->updateVacancy(1, [
     *     'title' => 'Senior PHP Developer',
     *     'salary' => 200000,
     *     'additional_fields' => ['remote' => true]
     * ]);
     * // При успехе: ['success' => true, 'message' => 'Вакансия успешно обновлена']
     */
    public function updateVacancy(int $id, array $data): array
    {
        try {
            Yii::info("Updating vacancy: ID={$id}", __METHOD__);

            $vacancy = $this->repository->findById($id);

            if (!$vacancy) {
                Yii::warning("Vacancy not found for update: ID={$id}", __METHOD__);
                Yii::$app->response->statusCode = 404;
                return [
                    'success' => false,
                    'message' => 'Вакансия не найдена',
                ];
            }

            // Обновление полей
            if (isset($data['title'])) {
                $vacancy->title = $data['title'];
            }
            if (isset($data['description'])) {
                $vacancy->description = $data['description'];
            }
            if (isset($data['salary'])) {
                $vacancy->salary = $data['salary'];
            }
            if (isset($data['additional_fields'])) {
                $vacancy->additional_fields = $data['additional_fields'];
            }

            if ($vacancy->validate()) {
                if ($this->repository->save($vacancy)) {
                    // Инвалидация кеша при обновлении
                    $this->invalidateVacancyCache($id);
                    $this->invalidateListCache();

                    Yii::info("Vacancy updated successfully: ID={$id}", __METHOD__);
                    return [
                        'success' => true,
                        'message' => 'Вакансия успешно обновлена',
                    ];
                }

                // Ошибка сохранения в БД
                Yii::error("Failed to update vacancy in database: ID={$id}", __METHOD__);
                Yii::$app->response->statusCode = 500;
                return [
                    'success' => false,
                    'message' => 'Внутренняя ошибка сервера при обновлении вакансии',
                ];
            }

            // Ошибка валидации
            Yii::warning("Vacancy validation failed during update: ID={$id}, errors=" . json_encode($vacancy->errors), __METHOD__);
            Yii::$app->response->statusCode = 400;
            return [
                'success' => false,
                'errors' => $vacancy->errors,
                'message' => 'Ошибка при обновлении вакансии',
            ];
        } catch (\Exception $e) {
            Yii::error("Exception while updating vacancy {$id}: " . $e->getMessage(), __METHOD__);
            Yii::$app->response->statusCode = 500;
            return [
                'success' => false,
                'message' => 'Внутренняя ошибка сервера',
            ];
        }
    }

    /**
     * Удалить вакансию.
     *
     * Удаляет вакансию по её ID из базы данных.
     * Устанавливает соответствующий HTTP статус код (200 при успехе, 404 если не найдена).
     *
     * @param int $id Уникальный идентификатор вакансии для удаления
     *
     * @return array Массив с результатом операции
     *               При успехе:
     *               ['success' => true, 'message' => 'Вакансия успешно удалена']
     *               Если вакансия не найдена:
     *               ['success' => false, 'message' => 'Вакансия не найдена'] (HTTP 404)
     *
     * @example
     * $result = $service->deleteVacancy(1);
     * // При успехе: ['success' => true, 'message' => 'Вакансия успешно удалена']
     * // Если не найдена: ['success' => false, 'message' => 'Вакансия не найдена']
     */
    public function deleteVacancy(int $id): array
    {
        try {
            Yii::info("Attempting to delete vacancy: ID={$id}", __METHOD__);

            if ($this->repository->delete($id)) {
                // Инвалидация кеша при удалении
                $this->invalidateVacancyCache($id);
                $this->invalidateListCache();

                Yii::info("Vacancy deleted successfully: ID={$id}", __METHOD__);
                return [
                    'success' => true,
                    'message' => 'Вакансия успешно удалена',
                ];
            }

            Yii::warning("Vacancy not found for deletion: ID={$id}", __METHOD__);
            Yii::$app->response->statusCode = 404;
            return [
                'success' => false,
                'message' => 'Вакансия не найдена',
            ];
        } catch (\Exception $e) {
            Yii::error("Exception while deleting vacancy {$id}: " . $e->getMessage(), __METHOD__);
            Yii::$app->response->statusCode = 500;
            return [
                'success' => false,
                'message' => 'Внутренняя ошибка сервера',
            ];
        }
    }

    /**
     * Инвалидация кеша конкретной вакансии
     *
     * Удаляет из кеша данные одной вакансии по её ID
     *
     * @param int $id ID вакансии
     * @return void
     */
    private function invalidateVacancyCache(int $id): void
    {
        $cacheKey = "vacancy_{$id}";
        Yii::$app->cache->delete($cacheKey);
        Yii::info("Cache invalidated for vacancy: {$cacheKey}", __METHOD__);
    }

    /**
     * Инвалидация кеша списков вакансий
     *
     * Полностью очищает кеш для всех вариантов списков вакансий
     * (все страницы, все сортировки). Используется при создании,
     * обновлении или удалении вакансий.
     *
     * @return void
     */
    private function invalidateListCache(): void
    {
        // Простое решение: очищаем весь кеш с префиксом vacancy_list_
        // Для более точной инвалидации можно хранить список ключей
        $cache = Yii::$app->cache;

        // В Yii2 FileCache нет встроенного метода для удаления по паттерну,
        // поэтому используем flush() для простоты (или можно вручную перебирать ключи)
        // Альтернатива: сохранять ключи в отдельном массиве и удалять их

        // Для production лучше использовать tagged cache или хранить список ключей
        // Здесь упрощенный вариант - удаляем популярные комбинации
        $sortFields = self::ALLOWED_SORT_FIELDS;
        $sortOrders = self::ALLOWED_SORT_ORDERS;

        // Очищаем первые 10 страниц для всех комбинаций сортировок
        for ($page = 1; $page <= 10; $page++) {
            foreach ($sortFields as $field) {
                foreach ($sortOrders as $order) {
                    $cacheKey = "vacancy_list_{$page}_{$field}_{$order}";
                    $cache->delete($cacheKey);
                }
            }
        }

        Yii::info("Cache invalidated for vacancy lists", __METHOD__);
    }

    /**
     * Поиск вакансий по ключевым словам
     *
     * Выполняет полнотекстовый поиск вакансий по названию и описанию.
     * Использует FULLTEXT индекс для эффективного поиска.
     * Результаты кешируются на 5 минут.
     *
     * @param string $searchQuery Поисковый запрос (ключевые слова)
     * @param int $page Номер страницы (начиная с 1)
     * @param string $sortOrder Направление сортировки ('relevance', 'asc', 'desc')
     *
     * @return array Массив с результатами поиска и информацией о пагинации
     *               Структура ответа:
     *               [
     *                   'data' => [массив вакансий с полями: id, title, salary, description],
     *                   'pagination' => [
     *                       'total' => общее количество найденных вакансий,
     *                       'page' => текущая страница,
     *                       'pageSize' => размер страницы (10),
     *                       'pageCount' => общее количество страниц
     *                   ],
     *                   'query' => поисковый запрос
     *               ]
     *
     * @example
     * $service = new VacancyService($repository);
     * $result = $service->searchVacancies('PHP разработчик', 1, 'relevance');
     * // Вернёт вакансии, содержащие "PHP" и "разработчик", отсортированные по релевантности
     */
    public function searchVacancies(string $searchQuery, int $page, string $sortOrder = 'relevance'): array
    {
        // Валидация и очистка поискового запроса
        $searchQuery = trim($searchQuery);

        if (empty($searchQuery)) {
            Yii::warning("Empty search query provided", __METHOD__);
            return [
                'data' => [],
                'pagination' => [
                    'total' => 0,
                    'page' => $page,
                    'pageSize' => self::PAGE_SIZE,
                    'pageCount' => 0,
                ],
                'query' => '',
            ];
        }

        // Ограничение длины запроса (максимум 255 символов)
        if (mb_strlen($searchQuery) > 255) {
            $searchQuery = mb_substr($searchQuery, 0, 255);
            Yii::warning("Search query truncated to 255 characters", __METHOD__);
        }

        // Валидация параметра сортировки
        $allowedSortOrders = ['relevance', 'asc', 'desc'];
        if (!in_array($sortOrder, $allowedSortOrders)) {
            Yii::warning("Invalid sort order: {$sortOrder}. Using default 'relevance'", __METHOD__);
            $sortOrder = 'relevance';
        }

        // Проверка кеша
        $cacheKey = "vacancy_search_" . md5($searchQuery) . "_{$page}_{$sortOrder}";
        $cache = Yii::$app->cache;

        $result = $cache->get($cacheKey);
        if ($result !== false) {
            Yii::info("Cache HIT for vacancy search: {$cacheKey}", __METHOD__);
            return $result;
        }

        try {
            Yii::info("Cache MISS. Searching vacancies: query='{$searchQuery}', page={$page}, sort={$sortOrder}", __METHOD__);

            // Выполняем поиск через репозиторий
            $dataProvider = $this->repository->search($searchQuery, $page, $sortOrder);

            $vacancies = [];
            foreach ($dataProvider->getModels() as $vacancy) {
                $vacancies[] = [
                    'id' => $vacancy->id,
                    'title' => $vacancy->title,
                    'salary' => $vacancy->salary,
                    'description' => $vacancy->description,
                ];
            }

            $result = [
                'data' => $vacancies,
                'pagination' => [
                    'total' => $dataProvider->getTotalCount(),
                    'page' => $page,
                    'pageSize' => self::PAGE_SIZE,
                    'pageCount' => $dataProvider->pagination->pageCount,
                ],
                'query' => $searchQuery,
            ];

            // Сохранение в кеш
            $cache->set($cacheKey, $result, self::CACHE_DURATION_LIST);
            Yii::info("Successfully searched and cached {$dataProvider->getTotalCount()} vacancies for query '{$searchQuery}'", __METHOD__);

            return $result;
        } catch (\Exception $e) {
            Yii::error("Failed to search vacancies: " . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }
}
