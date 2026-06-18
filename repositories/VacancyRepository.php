<?php

namespace app\repositories;

use app\models\Vacancy;
use yii\data\ActiveDataProvider;
use yii\db\Expression;

/**
 * Репозиторий для управления сущностями вакансий
 *
 * Предоставляет слой абстракции для операций доступа к данным,
 * связанных с вакансиями. Инкапсулирует все запросы к базе данных
 * и предоставляет чистый интерфейс для работы с данными вакансий.
 *
 * @package app\repositories
 * @author Система управления вакансиями
 * @version 1.0.0
 */
class VacancyRepository implements VacancyRepositoryInterface
{
    /**
     * Размер страницы для пагинации
     */
    private const PAGE_SIZE = 10;


    /**
     * Найти вакансию по ID
     *
     * Получает одну запись вакансии из базы данных по её первичному ключу.
     *
     * @param int $id Уникальный идентификатор вакансии
     * @return Vacancy|null Модель вакансии если найдена, null в противном случае
     *
     * @example
     * ```php
     * $vacancy = $repository->findById(1);
     * if ($vacancy !== null) {
     *     echo $vacancy->title;
     * }
     * ```
     */
    public function findById(int $id): ?Vacancy
    {
        return Vacancy::findOne($id);
    }

    /**
     * Получить все вакансии с пагинацией и сортировкой
     *
     * Получает постраничный список вакансий с настраиваемой сортировкой.
     * Результат обернут в ActiveDataProvider, который предоставляет
     * возможности пагинации и сортировки.
     *
     * @param int $page Номер страницы (нумерация с 1)
     * @param string $sortBy Поле для сортировки (salary или created_at)
     * @param string $sortOrder Направление сортировки (asc или desc)
     * @return ActiveDataProvider Провайдер данных с настроенной пагинацией и сортировкой
     *
     * @example
     * ```php
     * // Получить вторую страницу, отсортированную по зарплате по убыванию
     * $dataProvider = $repository->findAll(2, 'salary', 'desc');
     * $vacancies = $dataProvider->getModels();
     * $totalCount = $dataProvider->getTotalCount();
     * ```
     *
     * @see ActiveDataProvider
     */
    public function findAll(int $page, string $sortBy, string $sortOrder): ActiveDataProvider
    {
        $query = Vacancy::find()->orderBy([
            $sortBy => $sortOrder === 'desc' ? SORT_DESC : SORT_ASC,
        ]);

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => self::PAGE_SIZE,
                'page' => $page - 1,
            ],
            'sort' => false,
        ]);
    }

    /**
     * Сохранить вакансию в базу данных
     *
     * Сохраняет модель вакансии в базу данных. Метод работает как для
     * новых записей (INSERT), так и для существующих (UPDATE).
     * Правила валидации модели применяются перед сохранением.
     *
     * @param Vacancy $vacancy Модель вакансии для сохранения
     * @return bool True если сохранение успешно, false в противном случае
     *
     * @example
     * ```php
     * $vacancy = new Vacancy();
     * $vacancy->title = 'PHP Developer';
     * $vacancy->description = 'Требуется PHP разработчик';
     * $vacancy->salary = 150000;
     *
     * if ($repository->save($vacancy)) {
     *     echo "Сохранено с ID: " . $vacancy->id;
     * } else {
     *     print_r($vacancy->errors);
     * }
     * ```
     *
     * @see Vacancy::save()
     */
    public function save(Vacancy $vacancy): bool
    {
        return $vacancy->save(false);
    }

    /**
     * Удалить вакансию по ID
     *
     * Удаляет запись вакансии из базы данных. Сначала пытается найти
     * вакансию по ID, затем удаляет её, если она найдена.
     *
     * @param int $id Уникальный идентификатор вакансии для удаления
     * @return bool True если удаление успешно, false если вакансия не найдена или удаление не удалось
     *
     * @example
     * ```php
     * if ($repository->delete(1)) {
     *     echo "Вакансия успешно удалена";
     * } else {
     *     echo "Вакансия не найдена или удаление не удалось";
     * }
     * ```
     *
     * @see findById()
     * @see Vacancy::delete()
     */
    public function delete(int $id): bool
    {
        $vacancy = $this->findById($id);
        if ($vacancy === null) {
            return false;
        }

        return $vacancy->delete() !== false;
    }

    /**
     * Получить общее количество всех вакансий
     *
     * Возвращает общее количество записей вакансий в базе данных.
     * Полезно для расчетов пагинации и статистики.
     *
     * @return int Общее количество вакансий
     *
     * @example
     * ```php
     * $total = $repository->getTotalCount();
     * echo "Всего вакансий в базе данных: " . $total;
     * ```
     *
     * @see Vacancy::find()
     */
    public function getTotalCount(): int
    {
        return Vacancy::find()->count();
    }

    /**
     * Поиск вакансий по ключевым словам с использованием FULLTEXT индекса
     *
     * Выполняет полнотекстовый поиск по полям title и description.
     * Реализация зависит от используемой СУБД:
     * - PostgreSQL: использует ts_rank для ранжирования результатов
     * - MySQL: использует MATCH AGAINST для полнотекстового поиска
     * - Другие СУБД: использует LIKE поиск (менее эффективно)
     *
     * @param string $query Поисковый запрос (ключевые слова)
     * @param int $page Номер страницы (нумерация с 1)
     * @param string $sortOrder Направление сортировки: 'relevance' (по умолчанию), 'asc', 'desc'
     * @return ActiveDataProvider Провайдер данных с результатами поиска
     *
     * @example
     * ```php
     * // Поиск по ключевому слову "PHP"
     * $dataProvider = $repository->search('PHP', 1, 'relevance');
     * $vacancies = $dataProvider->getModels();
     *
     * // Поиск с несколькими словами
     * $dataProvider = $repository->search('PHP разработчик опыт', 1);
     * ```
     */
    public function search(string $query, int $page, string $sortOrder = 'relevance'): ActiveDataProvider
    {
        $query = trim($query);

        // Получаем тип СУБД
        $db = \Yii::$app->db;
        $driver = $db->driverName;

        $vacancyQuery = Vacancy::find();

        if ($driver === 'pgsql') {
            // plainto_tsquery безопасно обрабатывает произвольный ввод пользователя
            $vacancyQuery->andWhere(
                'search_vector @@ plainto_tsquery(:query)',
                [':query' => $query]
            );

            if ($sortOrder === 'relevance') {
                $vacancyQuery->addSelect('*')
                    ->addSelect(['rank' => 'ts_rank(search_vector, plainto_tsquery(:rankQuery))'])
                    ->addParams([':rankQuery' => $query])
                    ->orderBy(['rank' => SORT_DESC]);
            }
        } elseif ($driver === 'mysql') {
            $vacancyQuery->andWhere(
                'MATCH(title, description) AGAINST (:searchQuery IN NATURAL LANGUAGE MODE)',
                [':searchQuery' => $query]
            );

            if ($sortOrder === 'relevance') {
                $vacancyQuery->orderBy(new Expression(
                    'MATCH(title, description) AGAINST (:relevanceQuery IN NATURAL LANGUAGE MODE) DESC',
                    [':relevanceQuery' => $query]
                ));
            }
        } else {
            // Fallback для других СУБД - используем LIKE
            // Менее эффективно, но работает везде
            $words = explode(' ', $query);
            foreach ($words as $word) {
                if (!empty($word)) {
                    $vacancyQuery->andWhere([
                        'or',
                        ['like', 'title', $word],
                        ['like', 'description', $word]
                    ]);
                }
            }

            // Простая сортировка без ранжирования
            if ($sortOrder === 'relevance') {
                $vacancyQuery->orderBy(['created_at' => SORT_DESC]);
            }
        }

        // Применяем дополнительную сортировку если указано
        if ($sortOrder === 'asc') {
            $vacancyQuery->addOrderBy(['created_at' => SORT_ASC]);
        } elseif ($sortOrder === 'desc') {
            $vacancyQuery->addOrderBy(['created_at' => SORT_DESC]);
        }

        return new ActiveDataProvider([
            'query' => $vacancyQuery,
            'pagination' => [
                'pageSize' => self::PAGE_SIZE,
                'page' => $page - 1,
            ],
        ]);
    }
}
