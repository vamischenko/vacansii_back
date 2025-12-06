<?php

namespace app\controllers;

use Yii;
use yii\rest\Controller;
use yii\web\Response;
use app\services\VacancyService;

/**
 * Контроллер для работы с вакансиями через REST API
 *
 * Обрабатывает HTTP-запросы, связанные с управлением вакансиями.
 * Контроллер следует принципу тонкого слоя представления - вся бизнес-логика
 * делегируется в VacancyService. Контроллер отвечает только за обработку
 * входящих запросов и формирование ответов.
 *
 * Поддерживаемые операции:
 * - GET /vacancy - получение списка вакансий с пагинацией и сортировкой
 * - GET /vacancy/{id} - получение конкретной вакансии
 * - POST /vacancy - создание новой вакансии
 * - PUT /vacancy/{id} - обновление существующей вакансии
 * - DELETE /vacancy/{id} - удаление вакансии
 *
 * @package app\controllers
 * @author Система управления вакансиями
 * @version 1.0.0
 */
class VacancyController extends Controller
{
    /**
     * @var VacancyService Сервис для работы с бизнес-логикой вакансий
     */
    private VacancyService $vacancyService;

    /**
     * Конструктор контроллера с внедрением зависимостей
     *
     * @param string $id Уникальный идентификатор контроллера
     * @param \yii\base\Module $module Модуль, которому принадлежит контроллер
     * @param VacancyService $vacancyService Сервис вакансий (внедряется через DI)
     * @param array $config Конфигурация контроллера
     */
    public function __construct($id, $module, VacancyService $vacancyService, $config = [])
    {
        $this->vacancyService = $vacancyService;
        parent::__construct($id, $module, $config);
    }

    /**
     * Настройка поведений контроллера
     *
     * Конфигурирует:
     * - Content Negotiator: устанавливает формат ответа JSON
     * - CORS Filter: настраивает политику Cross-Origin Resource Sharing
     *   для работы с frontend приложениями
     * - Rate Limiter: ограничивает количество запросов для защиты от злоупотреблений
     *
     * @return array Массив конфигураций поведений
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // Настройка формата ответа JSON
        $behaviors['contentNegotiator']['formats']['application/json'] = Response::FORMAT_JSON;

        // Настройка CORS для работы с frontend
        // Список разрешенных доменов загружается из .env файла
        $allowedOrigins = array_filter(array_map('trim', explode(',', getenv('CORS_ORIGIN') ?: 'http://localhost:3000')));

        $behaviors['corsFilter'] = [
            'class' => \yii\filters\Cors::class,
            'cors' => [
                'Origin' => $allowedOrigins,
                'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Credentials' => false,
                'Access-Control-Max-Age' => 86400,
            ],
        ];

        // Настройка Rate Limiting
        // Ограничивает количество запросов от одного IP-адреса
        // Лимиты загружаются из .env или используют значения по умолчанию
        $behaviors['rateLimiter'] = [
            'class' => \yii\filters\RateLimiter::class,
            'enableRateLimitHeaders' => true,
            'user' => false, // Используем IP вместо авторизованного пользователя
            'request' => function ($action) {
                // Получаем IP-адрес клиента
                $ip = Yii::$app->request->userIP;

                // Загружаем лимиты из .env
                $rateLimit = (int) (getenv('RATE_LIMIT_REQUESTS') ?: 100);
                $rateLimitWindow = (int) (getenv('RATE_LIMIT_WINDOW') ?: 3600);

                // Используем cache для хранения счетчиков
                $cache = Yii::$app->cache;
                $key = ['rate-limit', $ip, $action->controller->id, $action->id];

                $data = $cache->get($key);
                if ($data === false) {
                    // Первый запрос в окне
                    $data = [
                        'allowance' => $rateLimit - 1,
                        'allowance_updated_at' => time(),
                    ];
                    $cache->set($key, $data, $rateLimitWindow);
                    return [$rateLimit, $rateLimitWindow];
                }

                $timePassed = time() - $data['allowance_updated_at'];
                $allowanceToAdd = $timePassed * ($rateLimit / $rateLimitWindow);
                $data['allowance'] = min($rateLimit, $data['allowance'] + $allowanceToAdd);
                $data['allowance_updated_at'] = time();

                if ($data['allowance'] < 1) {
                    // Лимит превышен
                    Yii::warning("Rate limit exceeded for IP: {$ip}", __METHOD__);
                    $cache->set($key, $data, $rateLimitWindow);
                    throw new \yii\web\TooManyRequestsHttpException('Превышен лимит запросов. Попробуйте позже.');
                }

                $data['allowance']--;
                $cache->set($key, $data, $rateLimitWindow);

                return [$rateLimit, $rateLimitWindow];
            },
        ];

        return $behaviors;
    }

    /**
     * Получить список вакансий с пагинацией и сортировкой
     *
     * Обрабатывает GET запрос для получения списка всех вакансий.
     * Поддерживает пагинацию (10 записей на страницу) и сортировку
     * по зарплате или дате создания.
     *
     * @return array Массив с данными вакансий и информацией о пагинации
     *
     * Параметры запроса:
     * - page (int, optional): Номер страницы (по умолчанию: 1)
     * - sort (string, optional): Поле для сортировки: 'salary' или 'created_at' (по умолчанию: 'created_at')
     * - order (string, optional): Порядок сортировки: 'asc' или 'desc' (по умолчанию: 'desc')
     *
     * Пример запроса:
     * GET /vacancy?page=2&sort=salary&order=desc
     *
     * Пример ответа:
     * ```json
     * {
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "PHP Developer",
     *       "salary": 150000,
     *       "description": "Описание вакансии"
     *     }
     *   ],
     *   "pagination": {
     *     "total": 25,
     *     "page": 2,
     *     "pageSize": 10,
     *     "pageCount": 3
     *   }
     * }
     * ```
     */
    public function actionIndex()
    {
        $request = Yii::$app->request;

        // Валидация параметра page: от 1 до 10000
        $page = (int) $request->get('page', 1);
        $page = max(1, min(10000, $page));

        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');

        return $this->vacancyService->getVacancyList($page, $sortBy, $sortOrder);
    }

    /**
     * Получить конкретную вакансию по ID
     *
     * Обрабатывает GET запрос для получения детальной информации о вакансии.
     * Поддерживает выборочное отображение полей через параметр fields.
     *
     * @param int $id Уникальный идентификатор вакансии
     * @return array Массив с данными вакансии или сообщением об ошибке
     *
     * Параметры запроса:
     * - fields (string, optional): Список полей через запятую для выборочного отображения
     *   Доступные поля: title, description, salary, additional_fields
     *
     * Примеры запросов:
     * GET /vacancy/1 - получить все поля
     * GET /vacancy/1?fields=title,salary - получить только title и salary
     *
     * Пример успешного ответа:
     * ```json
     * {
     *   "id": 1,
     *   "title": "PHP Developer",
     *   "salary": 150000,
     *   "description": "Описание",
     *   "additional_fields": {"company": "Tech Corp"}
     * }
     * ```
     *
     * Пример ответа при ошибке (404):
     * ```json
     * {
     *   "success": false,
     *   "message": "Вакансия не найдена"
     * }
     * ```
     */
    public function actionView($id)
    {
        $request = Yii::$app->request;
        $fieldsParam = $request->get('fields');

        // Валидация и ограничение количества полей (максимум 10)
        $fields = null;
        if ($fieldsParam) {
            $fields = array_slice(explode(',', $fieldsParam), 0, 10);
        }

        $result = $this->vacancyService->getVacancyById((int) $id, $fields);

        if ($result === null) {
            Yii::$app->response->statusCode = 404;
            return [
                'success' => false,
                'message' => 'Вакансия не найдена',
            ];
        }

        return $result;
    }

    /**
     * Создать новую вакансию
     *
     * Обрабатывает POST запрос для создания новой вакансии.
     * Принимает данные в формате JSON в теле запроса.
     *
     * @return array Массив с результатом создания и ID новой вакансии
     *
     * Обязательные поля в теле запроса:
     * - title (string): Название вакансии (макс. 255 символов)
     * - description (string): Описание вакансии
     * - salary (int): Зарплата (положительное число)
     *
     * Опциональные поля:
     * - additional_fields (object): Дополнительные поля в формате JSON
     *
     * Пример запроса:
     * POST /vacancy
     * Content-Type: application/json
     * ```json
     * {
     *   "title": "PHP Developer",
     *   "description": "Требуется PHP разработчик",
     *   "salary": 150000,
     *   "additional_fields": {
     *     "company": "Tech Corp",
     *     "location": "Москва"
     *   }
     * }
     * ```
     *
     * Пример успешного ответа (201 Created):
     * ```json
     * {
     *   "success": true,
     *   "id": 13,
     *   "message": "Вакансия успешно создана"
     * }
     * ```
     *
     * Пример ответа с ошибкой валидации (400 Bad Request):
     * ```json
     * {
     *   "success": false,
     *   "errors": {
     *     "title": ["Название вакансии не может быть пустым."],
     *     "salary": ["Зарплата должна быть целым числом."]
     *   },
     *   "message": "Ошибка при создании вакансии"
     * }
     * ```
     */
    public function actionCreate()
    {
        $request = Yii::$app->request;
        $data = $request->post();

        return $this->vacancyService->createVacancy($data);
    }

    /**
     * Обновить существующую вакансию
     *
     * Обрабатывает PUT/POST запрос для обновления данных вакансии.
     * Принимает данные в формате JSON в теле запроса.
     *
     * @param int $id Уникальный идентификатор вакансии для обновления
     * @return array Массив с результатом обновления
     *
     * Все поля опциональны - обновляются только переданные поля:
     * - title (string): Название вакансии
     * - description (string): Описание вакансии
     * - salary (int): Зарплата
     * - additional_fields (object): Дополнительные поля
     *
     * Пример запроса:
     * PUT /vacancy/1
     * Content-Type: application/json
     * ```json
     * {
     *   "title": "Senior PHP Developer",
     *   "salary": 200000
     * }
     * ```
     *
     * Пример успешного ответа (200 OK):
     * ```json
     * {
     *   "success": true,
     *   "message": "Вакансия успешно обновлена"
     * }
     * ```
     *
     * Пример ответа при ошибке (404 Not Found):
     * ```json
     * {
     *   "success": false,
     *   "message": "Вакансия не найдена"
     * }
     * ```
     */
    public function actionUpdate($id)
    {
        $request = Yii::$app->request;
        $data = $request->post();

        return $this->vacancyService->updateVacancy((int) $id, $data);
    }

    /**
     * Удалить вакансию
     *
     * Обрабатывает DELETE запрос для удаления вакансии из базы данных.
     *
     * @param int $id Уникальный идентификатор вакансии для удаления
     * @return array Массив с результатом удаления
     *
     * Пример запроса:
     * DELETE /vacancy/1
     *
     * Пример успешного ответа (200 OK):
     * ```json
     * {
     *   "success": true,
     *   "message": "Вакансия успешно удалена"
     * }
     * ```
     *
     * Пример ответа при ошибке (404 Not Found):
     * ```json
     * {
     *   "success": false,
     *   "message": "Вакансия не найдена"
     * }
     * ```
     */
    public function actionDelete($id)
    {
        return $this->vacancyService->deleteVacancy((int) $id);
    }

    /**
     * Поиск вакансий по ключевым словам
     *
     * Обрабатывает GET запрос для полнотекстового поиска вакансий.
     * Использует FULLTEXT индекс для эффективного поиска по названию и описанию.
     * Результаты ранжируются по релевантности и кешируются на 5 минут.
     *
     * @return array Массив с результатами поиска и информацией о пагинации
     *
     * Параметры запроса:
     * - q (string, required): Поисковый запрос (ключевые слова)
     * - page (int, optional): Номер страницы (по умолчанию: 1)
     * - sort (string, optional): Порядок сортировки: 'relevance', 'asc', 'desc' (по умолчанию: 'relevance')
     *
     * Пример запроса:
     * GET /vacancy/search?q=PHP разработчик&page=1&sort=relevance
     *
     * Пример успешного ответа (200 OK):
     * ```json
     * {
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "Senior PHP Developer",
     *       "salary": 200000,
     *       "description": "Требуется опытный PHP разработчик..."
     *     }
     *   ],
     *   "pagination": {
     *     "total": 12,
     *     "page": 1,
     *     "pageSize": 10,
     *     "pageCount": 2
     *   },
     *   "query": "PHP разработчик"
     * }
     * ```
     *
     * Пример ответа при пустом запросе (400 Bad Request):
     * ```json
     * {
     *   "success": false,
     *   "message": "Поисковый запрос не может быть пустым"
     * }
     * ```
     */
    public function actionSearch()
    {
        $request = Yii::$app->request;

        // Получаем поисковый запрос
        $searchQuery = $request->get('q', '');
        $searchQuery = trim($searchQuery);

        // Валидация: запрос не должен быть пустым
        if (empty($searchQuery)) {
            Yii::$app->response->statusCode = 400;
            return [
                'success' => false,
                'message' => 'Поисковый запрос не может быть пустым',
            ];
        }

        // Валидация параметра page: от 1 до 10000
        $page = (int) $request->get('page', 1);
        $page = max(1, min(10000, $page));

        // Получаем параметр сортировки
        $sortOrder = $request->get('sort', 'relevance');

        return $this->vacancyService->searchVacancies($searchQuery, $page, $sortOrder);
    }
}
