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

/**
 * Throwaway Postgres and Redis for the integration tests (Testcontainers),
 * the same images as docker-compose.yml and the Nest integration tests.
 * Started once per test run, only when the first integration test needs
 * them, and removed when the run ends; unit tests never start them.
 *
 * Containers are reached through the Docker host: "localhost" when PHP runs
 * on the machine itself (CI), TESTCONTAINERS_HOST_OVERRIDE when it runs in a
 * container (the admin-tests compose service sets host.docker.internal).
 */
final class TestServices
{
    private const DATABASE = 'ecommerce_test';

    private const USER = 'ecommerce';

    private const PASSWORD = 'secret';

    /** Lets a crashed run's leftovers be found: docker ps -a --filter label=ecommerce-platform.tests=admin-service */
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

    /**
     * Last line of defence before RefreshDatabase drops and rebuilds every
     * table: aborts the run unless Laravel really points at the throwaway
     * containers, so a test can never touch the development database.
     */
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

    /**
     * testcontainers-php hands docker-php its own HTTP client, which makes
     * docker-php add its fixed /v1.45 path on top of the negotiated version
     * (requests become /v1.41/v1.45/info and fail with 404). A client built
     * by docker-php's own factory negotiates the API version with the engine
     * (as the docker CLI does), so old and new Docker engines both work.
     */
    private static function useNegotiatingDockerClient(): void
    {
        DockerContainerClient::setDockerClient(Docker::create(DockerClientFactory::createFromEnv(), applyServerPlugins: false));
    }

    /**
     * Set before Laravel boots, and in all three places Laravel's env() reads,
     * so they win over .env (which never overwrites existing values).
     *
     * @param  array<string, string>  $variables
     */
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
