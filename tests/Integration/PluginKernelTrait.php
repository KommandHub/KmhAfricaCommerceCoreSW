<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Integration;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Kernel;

/**
 * Boots a plugin-aware kernel for integration tests, and wraps each test in a
 * rolled-back transaction.
 *
 * KernelTestBehaviour serves Shopware's shared test kernel, which loads no
 * plugins — this plugin's services are invisible to it. The test bootstrapper
 * installs and activates the plugin in the test database; a kernel booted with
 * the DbalKernelPluginLoader against that database is the container the plugin
 * really runs in. The services fetched here are marked public in services.yml.
 */
trait PluginKernelTrait
{
    private static ?ContainerInterface $pluginContainer = null;

    protected function setUp(): void
    {
        $this->connection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection()->rollBack();
    }

    public static function tearDownAfterClass(): void
    {
        self::$pluginContainer = null;
    }

    private function service(string $id): object
    {
        $container = self::pluginContainer();

        if (!$container->has($id)) {
            static::markTestSkipped(sprintf(
                'KmhAfricaCommerceCoreSW is not active in the test database, so "%s" is not in the container. '
                . 'Activate it with DATABASE_URL pointing at the *_test database: '
                . 'bin/console plugin:install --activate KmhAfricaCommerceCoreSW',
                $id,
            ));
        }

        $service = $container->get($id);
        static::assertIsObject($service);

        return $service;
    }

    private function connection(): Connection
    {
        $connection = self::pluginContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    private static function pluginContainer(): ContainerInterface
    {
        if (self::$pluginContainer !== null) {
            return self::$pluginContainer;
        }

        $projectRoot = $_SERVER['PROJECT_ROOT'] ?? \dirname(__DIR__, 5);
        $autoloadPath = $projectRoot . '/vendor/autoload.php';

        if (!file_exists($autoloadPath)) {
            static::markTestSkipped('Shopware autoloader not found. Integration tests require a full Shopware installation.');
        }

        $classLoader = require $autoloadPath;

        $kernel = KernelFactory::create(
            environment: 'test',
            debug: false,
            classLoader: $classLoader,
            pluginLoader: new DbalKernelPluginLoader($classLoader, null, Kernel::getConnection()),
        );
        $kernel->boot();

        return self::$pluginContainer = $kernel->getContainer();
    }
}
