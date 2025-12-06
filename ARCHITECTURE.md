# Архитектура проекта

## Обзор

Проект следует архитектурному подходу с разделением ответственности на слои:
- **Controllers** (Presentation Layer) - обработка HTTP запросов/ответов
- **Services** (Application Layer) - бизнес-логика
- **Repositories** (Data Access Layer) - работа с базой данных
- **Models** (Domain Layer) - сущности и валидация

## Структура слоев

```
vakansii-back/
├── controllers/           # Presentation Layer
│   └── VacancyController.php
├── services/             # Application Layer (Business Logic)
│   └── VacancyService.php
├── repositories/         # Data Access Layer
│   ├── VacancyRepositoryInterface.php
│   └── VacancyRepository.php
└── models/              # Domain Layer
    └── Vacancy.php
```

## Слои приложения

### 1. Controllers (Presentation Layer)

**Ответственность:**
- Обработка HTTP запросов
- Валидация входных данных (базовая)
- Вызов соответствующих методов сервисов
- Формирование HTTP ответов

**Пример:**
```php
class VacancyController extends Controller
{
    private VacancyService $vacancyService;

    public function actionIndex()
    {
        $page = (int) Yii::$app->request->get('page', 1);
        $sortBy = Yii::$app->request->get('sort', 'created_at');
        $sortOrder = Yii::$app->request->get('order', 'desc');

        return $this->vacancyService->getVacancyList($page, $sortBy, $sortOrder);
    }
}
```

**Правила:**
- ❌ НЕ должны содержать бизнес-логику
- ❌ НЕ должны напрямую работать с базой данных
- ✅ Только маршрутизация к сервисам
- ✅ Минимальная обработка запросов/ответов

### 2. Services (Application Layer)

**Ответственность:**
- Реализация бизнес-логики
- Координация работы между репозиториями
- Валидация бизнес-правил
- Трансформация данных для презентации

**Пример:**
```php
class VacancyService
{
    private VacancyRepositoryInterface $repository;

    public function getVacancyList(int $page, string $sortBy, string $sortOrder): array
    {
        // Валидация параметров
        $allowedSortFields = ['salary', 'created_at'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }

        // Получение данных через репозиторий
        $dataProvider = $this->repository->findAll($page, $sortBy, $sortOrder);

        // Трансформация данных
        $vacancies = [];
        foreach ($dataProvider->getModels() as $vacancy) {
            $vacancies[] = [
                'id' => $vacancy->id,
                'title' => $vacancy->title,
                'salary' => $vacancy->salary,
                'description' => $vacancy->description,
            ];
        }

        return [
            'data' => $vacancies,
            'pagination' => [
                'total' => $dataProvider->getTotalCount(),
                'page' => $page,
                'pageSize' => 10,
                'pageCount' => $dataProvider->pagination->pageCount,
            ],
        ];
    }
}
```

**Правила:**
- ✅ Содержат всю бизнес-логику
- ✅ Используют репозитории для доступа к данным
- ✅ Могут использовать несколько репозиториев
- ❌ НЕ должны знать о деталях HTTP (request/response)

### 3. Repositories (Data Access Layer)

**Ответственность:**
- Абстракция работы с базой данных
- CRUD операции
- Построение запросов
- Работа с моделями

**Интерфейс:**
```php
interface VacancyRepositoryInterface
{
    public function findById(int $id): ?Vacancy;
    public function findAll(int $page, string $sortBy, string $sortOrder): ActiveDataProvider;
    public function save(Vacancy $vacancy): bool;
    public function delete(int $id): bool;
    public function getTotalCount(): int;
}
```

**Реализация:**
```php
class VacancyRepository implements VacancyRepositoryInterface
{
    public function findById(int $id): ?Vacancy
    {
        return Vacancy::findOne($id);
    }

    public function findAll(int $page, string $sortBy, string $sortOrder): ActiveDataProvider
    {
        $query = Vacancy::find();

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 10, 'page' => $page - 1],
            'sort' => [
                'defaultOrder' => [$sortBy => ($sortOrder === 'desc' ? SORT_DESC : SORT_ASC)],
                'attributes' => ['salary', 'created_at'],
            ],
        ]);
    }

    public function save(Vacancy $vacancy): bool
    {
        return $vacancy->save();
    }
}
```

**Правила:**
- ✅ Единственный слой, работающий с базой данных
- ✅ Всегда использовать интерфейсы
- ✅ Скрывают детали реализации хранения данных
- ❌ НЕ должны содержать бизнес-логику

### 4. Models (Domain Layer)

**Ответственность:**
- Представление сущностей
- Валидация данных на уровне модели
- Определение связей между сущностями

**Пример:**
```php
class Vacancy extends ActiveRecord
{
    public function rules()
    {
        return [
            [['title', 'description', 'salary'], 'required'],
            ['title', 'string', 'max' => 255],
            ['description', 'string'],
            ['salary', 'integer', 'min' => 0],
            ['additional_fields', 'safe'],
        ];
    }
}
```

**Правила:**
- ✅ Содержат правила валидации
- ✅ Определяют структуру данных
- ❌ НЕ должны содержать бизнес-логику
- ❌ НЕ должны знать о репозиториях или сервисах

## Dependency Injection

Используется встроенный DI контейнер Yii2:

**Конфигурация (config/web.php):**
```php
'container' => [
    'singletons' => [
        \app\repositories\VacancyRepositoryInterface::class => \app\repositories\VacancyRepository::class,
    ],
],
```

**Внедрение зависимостей в контроллер:**
```php
class VacancyController extends Controller
{
    private VacancyService $vacancyService;

    public function __construct($id, $module, VacancyService $vacancyService, $config = [])
    {
        $this->vacancyService = $vacancyService;
        parent::__construct($id, $module, $config);
    }
}
```

**Внедрение зависимостей в сервис:**
```php
class VacancyService
{
    private VacancyRepositoryInterface $repository;

    public function __construct(VacancyRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }
}
```

## Поток данных

```
HTTP Request
     ↓
Controller (VacancyController)
     ↓ (вызов метода сервиса)
Service (VacancyService)
     ↓ (вызов метода репозитория)
Repository (VacancyRepository)
     ↓ (работа с моделью)
Model (Vacancy) ↔ Database
     ↑
Repository (возвращает модель/данные)
     ↑
Service (обрабатывает, трансформирует)
     ↑
Controller (формирует ответ)
     ↓
HTTP Response (JSON)
```

## Преимущества архитектуры

### 1. Разделение ответственности (SRP)
Каждый слой имеет четко определенную ответственность

### 2. Тестируемость
- Легко писать unit-тесты для сервисов
- Можно мокировать репозитории
- Изолированное тестирование бизнес-логики

### 3. Поддерживаемость
- Изменения в одном слое не затрагивают другие
- Легко найти, где находится нужная логика

### 4. Расширяемость
- Легко добавлять новые сервисы
- Можно менять реализацию репозиториев (например, с SQL на NoSQL)

### 5. Переиспользование
- Сервисы можно использовать из разных контроллеров
- Репозитории можно использовать в разных сервисах

## Примеры использования

### Добавление новой функциональности

**1. Добавление метода в репозиторий:**
```php
// VacancyRepositoryInterface.php
public function findByCompany(string $company): array;

// VacancyRepository.php
public function findByCompany(string $company): array
{
    return Vacancy::find()
        ->where(['like', 'additional_fields', $company])
        ->all();
}
```

**2. Добавление метода в сервис:**
```php
// VacancyService.php
public function getVacanciesByCompany(string $company): array
{
    $vacancies = $this->repository->findByCompany($company);

    // Трансформация данных
    return array_map(function($vacancy) {
        return [
            'id' => $vacancy->id,
            'title' => $vacancy->title,
            'company' => $vacancy->additional_fields['company'] ?? null,
        ];
    }, $vacancies);
}
```

**3. Добавление action в контроллер:**
```php
// VacancyController.php
public function actionFindByCompany()
{
    $company = Yii::$app->request->get('company');
    return $this->vacancyService->getVacanciesByCompany($company);
}
```

## Лучшие практики

### ✅ DO (Делать)

1. **Контроллеры:**
   - Держите их тонкими
   - Только маршрутизация к сервисам
   - Минимальная обработка запросов

2. **Сервисы:**
   - Вся бизнес-логика здесь
   - Используйте type hints
   - Возвращайте структурированные данные

3. **Репозитории:**
   - Всегда используйте интерфейсы
   - Один метод = одна операция с БД
   - Возвращайте модели или коллекции

4. **Модели:**
   - Только структура и валидация
   - Не добавляйте методы бизнес-логики

### ❌ DON'T (Не делать)

1. **Не смешивайте слои:**
   - Контроллер НЕ должен обращаться к репозиторию напрямую
   - Сервис НЕ должен работать с Request/Response
   - Репозиторий НЕ должен содержать бизнес-логику

2. **Не дублируйте код:**
   - Переиспользуйте методы сервисов
   - Создавайте общие репозитории для схожих операций

3. **Не нарушайте Single Responsibility:**
   - Один сервис = одна бизнес-область
   - Один репозиторий = одна сущность

## Заключение

Эта архитектура обеспечивает:
- ✅ Чистый и поддерживаемый код
- ✅ Легкое тестирование
- ✅ Простое расширение функциональности
- ✅ Независимость слоев
- ✅ Следование SOLID принципам
