<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        // Proteção crítica: os testes usam RefreshDatabase e jamais podem
        // herdar DB_DATABASE=parque_aquatico do container da aplicação.
        putenv('APP_ENV=testing');
        putenv('DB_DATABASE=parque_aquatico_test');
        putenv('CACHE_STORE=array');
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
        $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = 'parque_aquatico_test';
        $_ENV['CACHE_STORE'] = $_SERVER['CACHE_STORE'] = 'array';

        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        $connection = (string) $app['config']->get('database.default');
        $app['config']->set("database.connections.{$connection}.database", 'parque_aquatico_test');
        // Cache Spatie/Permission com driver "database" dentro do RefreshDatabase
        // reconecta o PDO e zera a transação do teste (Exists de unidade falha).
        $app['config']->set('cache.default', 'array');
        $app['config']->set('permission.cache.store', 'array');
        $app->make('db')->purge($connection);

        $database = (string) $app['config']->get("database.connections.{$connection}.database");
        if ($database !== 'parque_aquatico_test') {
            throw new RuntimeException("Testes bloqueados: conexão apontando para o banco inseguro [{$database}].");
        }

        return $app;
    }
}
