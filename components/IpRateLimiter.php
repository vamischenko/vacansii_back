<?php

namespace app\components;

use Yii;
use yii\filters\RateLimitInterface;
use yii\base\BaseObject;

/**
 * Реализует rate limiting по IP-адресу через кеш.
 * Используется вместо авторизованного пользователя для публичного API.
 */
class IpRateLimiter extends BaseObject implements RateLimitInterface
{
    public int $limit;
    public int $window;
    private string $ip;
    private string $actionId;

    public function __construct(string $ip, string $actionId, int $limit, int $window, array $config = [])
    {
        $this->ip = $ip;
        $this->actionId = $actionId;
        $this->limit = $limit;
        $this->window = $window;
        parent::__construct($config);
    }

    public function getRateLimit($request, $action): array
    {
        return [$this->limit, $this->window];
    }

    public function loadAllowance($request, $action): array
    {
        $data = Yii::$app->cache->get($this->cacheKey());
        if ($data === false) {
            return [$this->limit, time()];
        }
        return [$data['allowance'], $data['timestamp']];
    }

    public function saveAllowance($request, $action, $allowance, $timestamp): void
    {
        Yii::$app->cache->set($this->cacheKey(), [
            'allowance' => $allowance,
            'timestamp' => $timestamp,
        ], $this->window);
    }

    private function cacheKey(): string
    {
        return 'rate_limit_' . md5($this->ip . '_' . $this->actionId);
    }
}
