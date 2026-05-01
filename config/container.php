<?php
declare(strict_types=1);

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));
! defined('SWOOLE_HOOK_FLAGS') && define('SWOOLE_HOOK_FLAGS', SWOOLE_HOOK_ALL);

$appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
$envFile = $appEnv === 'testing' ? '.env.testing' : '.env';
Dotenv\Dotenv::createMutable(BASE_PATH, $envFile)->safeLoad();

if (! function_exists('make')) {
    function make(string $abstract, array $parameters = []): mixed
    {
        return \Hyperf\Context\ApplicationContext::getContainer()->make($abstract, $parameters);
    }
}

if (! function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

$scanHandler = $appEnv === 'testing' ? new \Hyperf\Di\ScanHandler\NullScanHandler() : null;
$scanHandler ? ClassLoader::init(handler: $scanHandler) : ClassLoader::init();

$container = new Container((new DefinitionSourceFactory())());

if (! $container instanceof Psr\Container\ContainerInterface) {
    throw new RuntimeException('The dependency injection container is invalid.');
}

ApplicationContext::setContainer($container);

return $container;
