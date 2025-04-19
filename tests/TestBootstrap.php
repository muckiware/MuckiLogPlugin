<?php declare(strict_types=1);

use Shopware\Core\TestBootstrapper;
use Symfony\Component\Dotenv\Dotenv;

function getProjectDir(): string
{
    if (isset($_SERVER['PROJECT_ROOT']) && file_exists($_SERVER['PROJECT_ROOT'])) {
        return $_SERVER['PROJECT_ROOT'];
    }
    if (isset($_ENV['PROJECT_ROOT']) && file_exists($_ENV['PROJECT_ROOT'])) {
        return $_ENV['PROJECT_ROOT'];
    }

    $rootDir = __DIR__;
    $dir = $rootDir;
    while (!file_exists($dir . '/.env')) {
        if ($dir === dirname($dir)) {
            return $rootDir;
        }
        $dir = dirname($dir);
    }

    return $dir;
}

$testProjectDir = getProjectDir();
echo 'DATABASE_URL: '.getenv('DATABASE_URL')."\n";
echo '$testProjectDir: '.$testProjectDir."\n";
(new Dotenv())->usePutenv()->load($testProjectDir . '/.env');

$shopwareBootstrapLookup = [
    $testProjectDir . '/vendor/shopware/core/TestBootstrapper.php',
    $testProjectDir . '/src/Core/TestBootstrapper.php',
];

foreach ($shopwareBootstrapLookup as $item) {
    if (is_readable($item)) {
        require_once $item;

        break;
    }
}

if (!class_exists(TestBootstrapper::class)) {
    throw new \RuntimeException("Shopware bootstrapper was not found. Tried locations: \n" . implode("\n", $shopwareBootstrapLookup));
}

$loader = (new TestBootstrapper())
    ->setProjectDir($testProjectDir)
    ->addCallingPlugin()
    ->addActivePlugins('MuckiLogPlugin')
    ->setDatabaseUrl(getenv('TEST_DATABASE_URL'))
    ->setForceInstallPlugins(true)
    ->bootstrap()
    ->getClassLoader()
;

$loader->addPsr4('MuckiLogPlugin\\tests\\', __DIR__);
