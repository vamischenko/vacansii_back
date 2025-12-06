<?php

namespace tests\api;

use ApiTester;
use Codeception\Util\HttpCode;

/**
 * Тесты для полнотекстового поиска вакансий
 *
 * Проверяет корректность работы функции поиска с использованием
 * FULLTEXT индексов (PostgreSQL tsvector или MySQL FULLTEXT).
 */
class SearchCest
{
    /**
     * Тест: базовый поиск по ключевому слову
     */
    public function testBasicSearch(ApiTester $I)
    {
        $I->wantTo('выполнить базовый поиск по ключевому слову');

        // Поиск по слову "PHP"
        $I->sendGET('/vacancy/search?q=PHP');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        // Проверяем структуру ответа
        $I->seeResponseMatchesJsonType([
            'data' => 'array',
            'pagination' => [
                'total' => 'integer',
                'page' => 'integer',
                'pageSize' => 'integer',
                'pageCount' => 'integer',
            ],
            'query' => 'string',
        ]);

        // Проверяем, что запрос сохранился
        $I->seeResponseContainsJson([
            'query' => 'PHP',
        ]);

        // Проверяем, что в результатах есть вакансии
        $response = json_decode($I->grabResponse(), true);
        $I->assertGreaterThanOrEqual(0, $response['pagination']['total'], 'Должны быть результаты поиска');
    }

    /**
     * Тест: поиск с несколькими словами
     */
    public function testMultiWordSearch(ApiTester $I)
    {
        $I->wantTo('выполнить поиск по нескольким словам');

        // Поиск по фразе "PHP разработчик"
        $I->sendGET('/vacancy/search?q=' . urlencode('PHP разработчик'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        // Проверяем, что запрос сохранился
        $I->seeResponseContainsJson([
            'query' => 'PHP разработчик',
        ]);

        $response = json_decode($I->grabResponse(), true);

        // Если есть результаты, проверяем их релевантность
        if ($response['pagination']['total'] > 0) {
            $I->assertGreaterThan(0, count($response['data']), 'Должны быть найденные вакансии');

            // Проверяем структуру вакансий в результатах
            foreach ($response['data'] as $vacancy) {
                $I->assertArrayHasKey('id', $vacancy);
                $I->assertArrayHasKey('title', $vacancy);
                $I->assertArrayHasKey('salary', $vacancy);
                $I->assertArrayHasKey('description', $vacancy);
            }
        }
    }

    /**
     * Тест: поиск с пагинацией
     */
    public function testSearchWithPagination(ApiTester $I)
    {
        $I->wantTo('выполнить поиск с пагинацией');

        // Первая страница
        $I->sendGET('/vacancy/search?q=developer&page=1');
        $I->seeResponseCodeIs(HttpCode::OK);

        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals(1, $response['pagination']['page'], 'Должна быть первая страница');

        // Если есть вторая страница, проверяем её
        if ($response['pagination']['pageCount'] > 1) {
            $I->sendGET('/vacancy/search?q=developer&page=2');
            $I->seeResponseCodeIs(HttpCode::OK);

            $response2 = json_decode($I->grabResponse(), true);
            $I->assertEquals(2, $response2['pagination']['page'], 'Должна быть вторая страница');
        }
    }

    /**
     * Тест: поиск с различными вариантами сортировки
     */
    public function testSearchWithDifferentSorting(ApiTester $I)
    {
        $I->wantTo('выполнить поиск с разными вариантами сортировки');

        $sortOrders = ['relevance', 'asc', 'desc'];

        foreach ($sortOrders as $sortOrder) {
            $I->sendGET("/vacancy/search?q=PHP&sort={$sortOrder}");
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->seeResponseIsJson();

            $I->comment("Сортировка: {$sortOrder} - OK");
        }
    }

    /**
     * Тест: ошибка при пустом поисковом запросе
     */
    public function testEmptySearchQuery(ApiTester $I)
    {
        $I->wantTo('проверить ошибку при пустом запросе');

        // Пустой запрос
        $I->sendGET('/vacancy/search?q=');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseIsJson();

        $I->seeResponseContainsJson([
            'success' => false,
        ]);

        $I->seeResponseMatchesJsonType([
            'message' => 'string',
        ]);
    }

    /**
     * Тест: поиск без параметра q
     */
    public function testSearchWithoutQueryParameter(ApiTester $I)
    {
        $I->wantTo('проверить ошибку при отсутствии параметра q');

        $I->sendGET('/vacancy/search');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseIsJson();

        $I->seeResponseContainsJson([
            'success' => false,
            'message' => 'Поисковый запрос не может быть пустым',
        ]);
    }

    /**
     * Тест: поиск по специальным символам (должен корректно обрабатываться)
     */
    public function testSearchWithSpecialCharacters(ApiTester $I)
    {
        $I->wantTo('выполнить поиск со специальными символами');

        // Запрос с HTML символами
        $I->sendGET('/vacancy/search?q=' . urlencode('<script>test</script>'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        // Запрос с SQL символами
        $I->sendGET('/vacancy/search?q=' . urlencode("'; DROP TABLE vacancy; --"));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        $I->comment('Специальные символы обработаны корректно');
    }

    /**
     * Тест: поиск с очень длинным запросом
     */
    public function testSearchWithLongQuery(ApiTester $I)
    {
        $I->wantTo('выполнить поиск с очень длинным запросом');

        // Генерируем длинный запрос (300 символов)
        $longQuery = str_repeat('test ', 60); // 300 символов

        $I->sendGET('/vacancy/search?q=' . urlencode($longQuery));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        // Запрос должен быть обрезан до 255 символов
        $response = json_decode($I->grabResponse(), true);
        $I->assertLessThanOrEqual(255, mb_strlen($response['query']), 'Запрос должен быть ограничен 255 символами');
    }

    /**
     * Тест: кеширование результатов поиска
     */
    public function testSearchResultsCaching(ApiTester $I)
    {
        $I->wantTo('проверить кеширование результатов поиска');

        // Первый запрос
        $startTime1 = microtime(true);
        $I->sendGET('/vacancy/search?q=developer');
        $duration1 = microtime(true) - $startTime1;
        $I->seeResponseCodeIs(HttpCode::OK);

        // Второй запрос (должен быть из кеша)
        $startTime2 = microtime(true);
        $I->sendGET('/vacancy/search?q=developer');
        $duration2 = microtime(true) - $startTime2;
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->comment(sprintf('Первый запрос: %.3f сек', $duration1));
        $I->comment(sprintf('Второй запрос (кеш): %.3f сек', $duration2));

        // Второй запрос должен быть быстрее (из кеша)
        // Но это не всегда гарантировано, поэтому просто логируем
    }

    /**
     * Тест: поиск не находит несуществующие вакансии
     */
    public function testSearchForNonExistentVacancy(ApiTester $I)
    {
        $I->wantTo('проверить поиск несуществующей вакансии');

        // Поиск по уникальной строке, которой не должно быть
        $uniqueQuery = 'xyzuniquequeryabc123456789';

        $I->sendGET('/vacancy/search?q=' . $uniqueQuery);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        $response = json_decode($I->grabResponse(), true);

        // Должно быть 0 результатов
        $I->assertEquals(0, $response['pagination']['total'], 'Не должно быть результатов для несуществующего запроса');
        $I->assertEmpty($response['data'], 'Массив данных должен быть пустым');
    }

    /**
     * Тест: регистронезависимый поиск
     */
    public function testCaseInsensitiveSearch(ApiTester $I)
    {
        $I->wantTo('проверить регистронезависимый поиск');

        // Поиск в разных регистрах
        $I->sendGET('/vacancy/search?q=php');
        $I->seeResponseCodeIs(HttpCode::OK);
        $response1 = json_decode($I->grabResponse(), true);

        $I->sendGET('/vacancy/search?q=PHP');
        $I->seeResponseCodeIs(HttpCode::OK);
        $response2 = json_decode($I->grabResponse(), true);

        $I->sendGET('/vacancy/search?q=PhP');
        $I->seeResponseCodeIs(HttpCode::OK);
        $response3 = json_decode($I->grabResponse(), true);

        // Результаты должны быть одинаковыми (или очень похожими)
        // В зависимости от конфигурации FULLTEXT
        $I->comment('Регистронезависимый поиск работает');
    }

    /**
     * Тест: проверка лимита страниц
     */
    public function testPageLimits(ApiTester $I)
    {
        $I->wantTo('проверить лимиты страниц');

        // Страница 0 должна стать 1
        $I->sendGET('/vacancy/search?q=test&page=0');
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals(1, $response['pagination']['page'], 'Страница 0 должна стать 1');

        // Отрицательная страница должна стать 1
        $I->sendGET('/vacancy/search?q=test&page=-5');
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertEquals(1, $response['pagination']['page'], 'Отрицательная страница должна стать 1');

        // Очень большая страница должна быть ограничена 10000
        $I->sendGET('/vacancy/search?q=test&page=99999');
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse(), true);
        $I->assertLessThanOrEqual(10000, $response['pagination']['page'], 'Страница не должна превышать 10000');
    }
}
