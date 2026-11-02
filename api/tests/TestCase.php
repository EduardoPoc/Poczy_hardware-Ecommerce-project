<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");
        $username = $app['config']->get("database.connections.{$connection}.username");

        if (! $app->environment('testing') || $app->configurationIsCached()
            || $connection !== 'mysql' || $database !== 'ecommerce_test' || $username !== 'ecommerce_test') {
            throw new RuntimeException('Testes exigem APP_ENV=testing, configuração sem cache e MySQL ecommerce_test com usuário ecommerce_test.');
        }

        return $app;
    }
}
