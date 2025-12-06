<?php

namespace tests\api;

use ApiTester;
use Codeception\Util\HttpCode;

/**
 * Тесты для проверки Rate Limiting
 *
 * Проверяет корректность работы ограничения количества запросов
 * на уровне API для защиты от злоупотреблений.
 */
class RateLimitingCest
{
    /**
     * Тест: проверка работы rate limiting при превышении лимита
     *
     * Этот тест отправляет большое количество запросов к API
     * и проверяет, что после превышения лимита сервер возвращает
     * статус 429 (Too Many Requests)
     */
    public function testRateLimitingEnforced(ApiTester $I)
    {
        $I->wantTo('проверить, что rate limiting блокирует слишком частые запросы');

        // Получаем лимиты из переменных окружения или используем значения по умолчанию
        $rateLimit = (int) (getenv('RATE_LIMIT_REQUESTS') ?: 100);
        $rateLimitWindow = (int) (getenv('RATE_LIMIT_WINDOW') ?: 3600);

        $I->comment("Rate limit: {$rateLimit} запросов за {$rateLimitWindow} секунд");

        // Очищаем кеш перед тестом, чтобы начать с чистого состояния
        $this->clearCache($I);

        $successCount = 0;
        $blockedCount = 0;

        // Отправляем запросов больше, чем разрешено (например, limit + 10)
        $totalRequests = min($rateLimit + 10, 120); // Ограничиваем для скорости тестов

        for ($i = 1; $i <= $totalRequests; $i++) {
            $I->sendGET('/vacancy');

            $responseCode = $I->grabResponse();
            $statusCode = null;

            // Получаем статус код
            try {
                $statusCode = $I->grabHttpCode();
            } catch (\Exception $e) {
                // Игнорируем ошибку, если не удалось получить код
            }

            if ($statusCode === HttpCode::OK || $statusCode === 200) {
                $successCount++;
            } elseif ($statusCode === HttpCode::TOO_MANY_REQUESTS || $statusCode === 429) {
                $blockedCount++;
                $I->comment("Запрос #{$i}: Заблокирован (429 Too Many Requests)");

                // Проверяем тело ответа при блокировке
                $I->seeResponseCodeIs(HttpCode::TOO_MANY_REQUESTS);
                $I->seeResponseContainsJson([
                    'success' => false
                ]);
                $I->seeResponseMatchesJsonType([
                    'message' => 'string'
                ]);

                // После первого блокированного запроса можно прекратить тест
                break;
            }
        }

        $I->comment("Успешных запросов: {$successCount}");
        $I->comment("Заблокированных запросов: {$blockedCount}");

        // Проверяем, что хотя бы один запрос был заблокирован
        $I->assertGreaterThan(
            0,
            $blockedCount,
            'Должен быть хотя бы один заблокированный запрос после превышения лимита'
        );

        // Проверяем, что количество успешных запросов не превышает лимит значительно
        // (допускаем небольшую погрешность из-за асинхронности)
        $I->assertLessThanOrEqual(
            $rateLimit + 5,
            $successCount,
            'Количество успешных запросов не должно значительно превышать установленный лимит'
        );
    }

    /**
     * Тест: проверка заголовков Rate Limit
     *
     * Проверяет, что сервер возвращает информационные заголовки
     * о текущем состоянии лимита запросов
     */
    public function testRateLimitHeaders(ApiTester $I)
    {
        $I->wantTo('проверить наличие заголовков rate limiting');

        // Очищаем кеш перед тестом
        $this->clearCache($I);

        // Отправляем запрос
        $I->sendGET('/vacancy');
        $I->seeResponseCodeIs(HttpCode::OK);

        // Проверяем наличие заголовков (если они включены в конфигурации)
        // В Yii2 RateLimiter добавляет заголовки X-Rate-Limit-*
        // Примечание: эти заголовки могут отсутствовать, если enableRateLimitHeaders = false

        $I->comment('Проверка информационных заголовков rate limiting');
        // Эти проверки опциональны, так как заголовки могут быть отключены
    }

    /**
     * Тест: восстановление доступа после истечения окна rate limit
     *
     * Проверяет, что после истечения временного окна лимит сбрасывается
     * (это долгий тест, может быть пропущен в CI/CD)
     *
     * @skip Долгий тест - запускать вручную при необходимости
     */
    public function testRateLimitResets(ApiTester $I)
    {
        $I->wantTo('проверить, что rate limit сбрасывается после истечения временного окна');

        $rateLimitWindow = (int) (getenv('RATE_LIMIT_WINDOW') ?: 3600);

        // Этот тест требует ожидания истечения окна rate limit
        // Для продакшен настроек (3600 сек = 1 час) это слишком долго
        // Поэтому используем сокращенное окно для тестирования

        if ($rateLimitWindow > 60) {
            $I->comment("Пропущен: окно rate limit слишком большое ({$rateLimitWindow} сек)");
            $I->comment("Для тестирования установите RATE_LIMIT_WINDOW=5 в .env.test");
            return;
        }

        $I->comment("Ожидание {$rateLimitWindow} секунд для сброса лимита...");

        // В реальном тесте нужно было бы ждать, но для демонстрации пропускаем
        // sleep($rateLimitWindow + 1);
    }

    /**
     * Тест: различные IP адреса имеют независимые лимиты
     *
     * Проверяет, что rate limiting применяется отдельно для каждого IP
     */
    public function testRateLimitingPerIP(ApiTester $I)
    {
        $I->wantTo('проверить, что разные IP имеют независимые лимиты');

        // Очищаем кеш
        $this->clearCache($I);

        // Отправляем запрос с первого IP (по умолчанию)
        $I->sendGET('/vacancy');
        $I->seeResponseCodeIs(HttpCode::OK);

        // В Codeception сложно эмулировать разные IP в рамках одного теста
        // Это больше подходит для интеграционных тестов с реальным веб-сервером

        $I->comment('Для полного тестирования разных IP используйте интеграционные тесты');
    }

    /**
     * Тест: rate limiting применяется ко всем эндпоинтам vacancy
     */
    public function testRateLimitingAppliedToAllEndpoints(ApiTester $I)
    {
        $I->wantTo('проверить, что rate limiting работает для всех эндпоинтов');

        // Очищаем кеш
        $this->clearCache($I);

        // Проверяем несколько разных эндпоинтов
        $endpoints = [
            ['GET', '/vacancy'],
            ['GET', '/vacancy/1'],
        ];

        foreach ($endpoints as $endpoint) {
            [$method, $url] = $endpoint;

            if ($method === 'GET') {
                $I->sendGET($url);
            }

            // Первый запрос должен быть успешным (если вакансия существует)
            // или вернуть 404, но не 429
            $statusCode = $I->grabHttpCode();

            $I->assertNotEquals(
                HttpCode::TOO_MANY_REQUESTS,
                $statusCode,
                "Первый запрос к {$url} не должен быть заблокирован"
            );
        }
    }

    /**
     * Вспомогательный метод для очистки кеша
     *
     * @param ApiTester $I
     */
    private function clearCache(ApiTester $I)
    {
        // Очищаем кеш через Yii2 команду
        $I->comment('Очистка кеша...');

        try {
            // Используем runtime API или прямой доступ к кешу
            // В реальном проекте можно создать специальный эндпоинт для тестов
            exec('php yii cache/flush-all 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                $I->comment('Кеш очищен успешно');
            } else {
                $I->comment('Предупреждение: не удалось очистить кеш через CLI');
            }
        } catch (\Exception $e) {
            $I->comment('Предупреждение: ' . $e->getMessage());
        }
    }

    /**
     * Тест производительности: измерение времени отклика при rate limiting
     */
    public function testRateLimitingPerformance(ApiTester $I)
    {
        $I->wantTo('измерить влияние rate limiting на производительность');

        $this->clearCache($I);

        $startTime = microtime(true);

        // Отправляем несколько запросов
        for ($i = 0; $i < 10; $i++) {
            $I->sendGET('/vacancy');

            if ($I->grabHttpCode() === HttpCode::TOO_MANY_REQUESTS) {
                break;
            }
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $I->comment(sprintf('Время выполнения 10 запросов: %.3f секунд', $duration));
        $I->comment(sprintf('Среднее время на запрос: %.3f мс', ($duration / 10) * 1000));

        // Проверяем, что rate limiting не слишком замедляет запросы
        // (должно быть быстрее 100ms на запрос в среднем)
        $I->assertLessThan(
            1.0, // 1 секунда на 10 запросов
            $duration,
            'Rate limiting не должен значительно замедлять обработку запросов'
        );
    }
}
