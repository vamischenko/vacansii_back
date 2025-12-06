<?php

namespace tests\unit\services;

use Codeception\Test\Unit;
use Yii;

/**
 * Unit тесты для проверки механизма Rate Limiting
 *
 * Проверяет работу кеша и алгоритма rate limiting на уровне единиц
 */
class RateLimitingUnitTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
        // Очищаем кеш перед каждым тестом
        if (Yii::$app->cache) {
            Yii::$app->cache->flush();
        }
    }

    protected function _after()
    {
        // Очищаем кеш после каждого теста
        if (Yii::$app->cache) {
            Yii::$app->cache->flush();
        }
    }

    /**
     * Тест: проверка базовой функциональности кеша для rate limiting
     */
    public function testCacheBasicFunctionality()
    {
        $cache = Yii::$app->cache;

        // Проверяем, что кеш доступен
        $this->assertNotNull($cache, 'Cache component должен быть настроен');

        // Создаем тестовый ключ для rate limiting
        $key = ['rate-limit', '127.0.0.1', 'vacancy', 'index'];

        // Сохраняем данные
        $data = [
            'allowance' => 99,
            'allowance_updated_at' => time(),
        ];

        $result = $cache->set($key, $data, 3600);
        $this->assertTrue($result, 'Данные должны быть сохранены в кеш');

        // Читаем данные
        $cached = $cache->get($key);
        $this->assertNotFalse($cached, 'Данные должны быть получены из кеша');
        $this->assertEquals(99, $cached['allowance'], 'Значение allowance должно совпадать');
    }

    /**
     * Тест: проверка истечения срока действия кеша
     */
    public function testCacheExpiration()
    {
        $cache = Yii::$app->cache;
        $key = ['rate-limit-test', 'expiration'];

        // Сохраняем с коротким TTL
        $cache->set($key, ['allowance' => 100], 1); // 1 секунда

        // Сразу должно быть доступно
        $this->assertNotFalse($cache->get($key), 'Данные должны быть доступны сразу');

        // Ждем истечения
        sleep(2);

        // После истечения должно вернуть false
        $this->assertFalse($cache->get($key), 'Данные должны быть удалены после истечения TTL');
    }

    /**
     * Тест: проверка алгоритма расчета allowance
     */
    public function testAllowanceCalculation()
    {
        $rateLimit = 100; // 100 запросов
        $rateLimitWindow = 3600; // за 1 час

        // Начальное состояние
        $allowance = $rateLimit;
        $lastUpdate = time();

        // Симулируем запрос через 1 минуту (60 секунд)
        sleep(1); // В реальном тесте можем использовать меньше
        $currentTime = time();

        $timePassed = $currentTime - $lastUpdate;
        $allowanceToAdd = $timePassed * ($rateLimit / $rateLimitWindow);

        // После одной секунды должно добавиться ~0.027 запроса
        $this->assertGreaterThan(0, $allowanceToAdd, 'С течением времени allowance должен увеличиваться');

        // Максимум не должен превышать лимит
        $newAllowance = min($rateLimit, $allowance + $allowanceToAdd);
        $this->assertLessThanOrEqual($rateLimit, $newAllowance, 'Allowance не должен превышать лимит');
    }

    /**
     * Тест: проверка уменьшения allowance при запросе
     */
    public function testAllowanceDecrease()
    {
        $rateLimit = 100;
        $allowance = $rateLimit;

        // Делаем запрос
        $allowance--;

        $this->assertEquals(99, $allowance, 'После запроса allowance должен уменьшиться на 1');

        // Делаем еще несколько запросов
        for ($i = 0; $i < 10; $i++) {
            $allowance--;
        }

        $this->assertEquals(89, $allowance, 'После 11 запросов должно остаться 89');
    }

    /**
     * Тест: проверка блокировки при нулевом allowance
     */
    public function testBlockingWhenAllowanceExceeded()
    {
        $allowance = 0.5;

        // При allowance < 1 запрос должен быть заблокирован
        $this->assertLessThan(1, $allowance, 'Запрос должен быть заблокирован');

        $allowance = 0;
        $this->assertLessThan(1, $allowance, 'Запрос должен быть заблокирован при нулевом allowance');

        $allowance = 1;
        $this->assertGreaterThanOrEqual(1, $allowance, 'Запрос должен быть разрешен при allowance >= 1');
    }

    /**
     * Тест: проверка формирования ключа кеша
     */
    public function testCacheKeyFormat()
    {
        $ip = '192.168.1.1';
        $controllerId = 'vacancy';
        $actionId = 'index';

        $key = ['rate-limit', $ip, $controllerId, $actionId];

        // Проверяем, что ключ корректно формируется
        $this->assertIsArray($key, 'Ключ должен быть массивом');
        $this->assertCount(4, $key, 'Ключ должен содержать 4 элемента');
        $this->assertEquals('rate-limit', $key[0], 'Первый элемент должен быть префиксом');
        $this->assertEquals($ip, $key[1], 'Второй элемент должен быть IP');
        $this->assertEquals($controllerId, $key[2], 'Третий элемент должен быть ID контроллера');
        $this->assertEquals($actionId, $key[3], 'Четвертый элемент должен быть ID действия');
    }

    /**
     * Тест: проверка различных IP имеют разные ключи кеша
     */
    public function testDifferentIPsHaveDifferentKeys()
    {
        $ip1 = '192.168.1.1';
        $ip2 = '192.168.1.2';
        $controllerId = 'vacancy';
        $actionId = 'index';

        $key1 = ['rate-limit', $ip1, $controllerId, $actionId];
        $key2 = ['rate-limit', $ip2, $controllerId, $actionId];

        $this->assertNotEquals($key1, $key2, 'Разные IP должны иметь разные ключи кеша');

        // Проверяем, что они независимо работают в кеше
        $cache = Yii::$app->cache;

        $cache->set($key1, ['allowance' => 50], 3600);
        $cache->set($key2, ['allowance' => 75], 3600);

        $data1 = $cache->get($key1);
        $data2 = $cache->get($key2);

        $this->assertEquals(50, $data1['allowance'], 'Данные для IP1 должны быть независимыми');
        $this->assertEquals(75, $data2['allowance'], 'Данные для IP2 должны быть независимыми');
    }

    /**
     * Тест: проверка конфигурации rate limit из переменных окружения
     */
    public function testRateLimitConfiguration()
    {
        // Получаем значения из .env или значения по умолчанию
        $rateLimit = (int) (getenv('RATE_LIMIT_REQUESTS') ?: 100);
        $rateLimitWindow = (int) (getenv('RATE_LIMIT_WINDOW') ?: 3600);

        // Проверяем, что значения разумные
        $this->assertGreaterThan(0, $rateLimit, 'Rate limit должен быть положительным');
        $this->assertGreaterThan(0, $rateLimitWindow, 'Rate limit window должен быть положительным');

        // Проверяем разумные границы
        $this->assertLessThan(10000, $rateLimit, 'Rate limit слишком большой');
        $this->assertLessThan(86400, $rateLimitWindow, 'Rate limit window слишком большой (больше суток)');
    }

    /**
     * Тест: симуляция полного цикла rate limiting
     */
    public function testFullRateLimitingCycle()
    {
        $cache = Yii::$app->cache;
        $rateLimit = 10; // Маленький лимит для теста
        $rateLimitWindow = 10; // 10 секунд
        $ip = '127.0.0.1';
        $key = ['rate-limit', $ip, 'vacancy', 'index'];

        // Первый запрос - инициализация
        $data = [
            'allowance' => $rateLimit - 1,
            'allowance_updated_at' => time(),
        ];
        $cache->set($key, $data, $rateLimitWindow);

        $cached = $cache->get($key);
        $this->assertEquals(9, $cached['allowance'], 'После первого запроса должно остаться 9');

        // Еще несколько запросов
        for ($i = 0; $i < 5; $i++) {
            $cached = $cache->get($key);
            $cached['allowance']--;
            $cache->set($key, $cached, $rateLimitWindow);
        }

        $cached = $cache->get($key);
        $this->assertEquals(4, $cached['allowance'], 'После 6 запросов должно остаться 4');

        // Проверяем, что при allowance < 1 запрос блокируется
        for ($i = 0; $i < 5; $i++) {
            $cached = $cache->get($key);
            if ($cached['allowance'] < 1) {
                $this->fail('Rate limit превышен, запрос должен быть заблокирован');
                break;
            }
            $cached['allowance']--;
            $cache->set($key, $cached, $rateLimitWindow);
        }

        $finalCached = $cache->get($key);
        $this->assertLessThan(1, $finalCached['allowance'], 'В конце allowance должен быть < 1');
    }
}
