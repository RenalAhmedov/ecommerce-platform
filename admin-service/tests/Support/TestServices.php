<?php

namespace Tests\Support;

use Docker\Docker;
use Docker\DockerClientFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Env;
use RuntimeException;
use Testcontainers\Container\StartedGenericContainer;
use Testcontainers\ContainerClient\DockerContainerClient;
use Testcontainers\Modules\PostgresContainer;
use Testcontainers\Modules\RedisContainer;

final class TestServices
{
    private const DATABASE = 'ecommerce_test';

    private const USER = 'ecommerce';

    private const PASSWORD = 'secret';

    private const LABEL = ['ecommerce-platform.tests' => 'admin-service'];

    /** @var list<StartedGenericContainer> */
    private static array $containers = [];

    /** @var array<string, string> where the integration tests must connect */
    private static array $environment = [];

    public static function start(): void
    {
        if (self::$containers !== []) {
            return;
        }

        // testcontainers-php calls an API its own dependency (jane-php) has deprecated;
        // that notice is about vendor code only, so it is muted while the containers start.
        set_error_handler(fn (int $level, string $message, string $file) => str_contains($file, '/vendor/'), E_USER_DEPRECATED);

        try {
            self::useNegotiatingDockerClient();

            $postgres = (new PostgresContainer('16-alpine', self::USER, self::PASSWORD, self::DATABASE))
                ->withLabels(self::LABEL)
                ->withAutoRemove(true)
                ->start();
            $redis = (new RedisContainer('7-alpine'))
                ->withLabels(self::LABEL)
                ->withAutoRemove(true)
                ->start();
        } finally {
            restore_error_handler();
        }

        self::$containers = [$postgres, $redis];
        register_shutdown_function([self::class, 'stop']);

        self::$environment = [
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $postgres->getHost(),
            'DB_PORT' => (string) $postgres->getMappedPort(5432),
            'DB_DATABASE' => self::DATABASE,
            'DB_USERNAME' => self::USER,
            'DB_PASSWORD' => self::PASSWORD,
            'REDIS_HOST' => $redis->getHost(),
            'REDIS_PORT' => (string) $redis->getMappedPort(6379),
        ];
        self::setEnvironment(self::$environment);
    }

    // Last line of defence: aborts unless Laravel points at the throwaway containers, so a test run can never wipe the dev database.
    public static function assertIsTarget(Repository $config): void
    {
        $connection = $config->get('database.connections.'.$config->get('database.default'));
        $actual = [
            'DB_CONNECTION' => $config->get('database.default'),
            'DB_HOST' => (string) ($connection['host'] ?? ''),
            'DB_PORT' => (string) ($connection['port'] ?? ''),
            'DB_DATABASE' => (string) ($connection['database'] ?? ''),
            'REDIS_HOST' => (string) $config->get('database.redis.default.host'),
            'REDIS_PORT' => (string) $config->get('database.redis.default.port'),
        ];
        $expected = array_intersect_key(self::$environment, $actual);

        if ($expected === [] || $actual != $expected) {
            throw new RuntimeException('Refusing to run integration tests: Laravel is configured for '.json_encode($actual).', not the test containers '.json_encode($expected).'.');
        }
    }

    public static function stop(): void
    {
        foreach (self::$containers as $container) {
            $container->stop();
        }

        self::$containers = [];
    }

    // testcontainers-php's own HTTP client makes docker-php double the API version in paths (/v1.41/v1.45/...).
    private static function useNegotiatingDockerClient(): void
    {
        DockerContainerClient::setDockerClient(Docker::create(DockerClientFactory::createFromEnv(), applyServerPlugins: false));
    }

    // All three places env() reads, set before Laravel boots, so they win over .env.
    /** @param  array<string, string>  $variables */
    private static function setEnvironment(array $variables): void
    {
        foreach ($variables as $name => $value) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        // Laravel remembers which variables it loaded from .env and may overwrite them on the next
        // boot; resetting its env repository makes it read the values above as set from outside.
        Env::enablePutenv();
    }
}
