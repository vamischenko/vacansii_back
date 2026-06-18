<?php

namespace app\components;

use tests\_support\TestSchema;
use yii\base\BootstrapInterface;

/**
 * Инициализирует тестовую схему SQLite при старте приложения в test-окружении.
 */
class TestDbBootstrap implements BootstrapInterface
{
    public function bootstrap($app): void
    {
        if (!$app->has('db')) {
            return;
        }

        TestSchema::ensure($app->db);
    }
}
