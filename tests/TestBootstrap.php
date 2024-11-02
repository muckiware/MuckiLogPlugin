<?php declare(strict_types=1);

use Shopware\Core\TestBootstrapper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
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
(new Dotenv())->usePutenv()->load($testProjectDir . '/.env');

echo 'DATABASE_URL: '.getenv('DATABASE_URL')."\n";
echo '$testProjectDir: '.$testProjectDir."\n";
$loader = (new TestBootstrapper())
    ->addCallingPlugin()
    ->addActivePlugins('MuckiLogPlugin')
    ->setDatabaseUrl(getenv('TEST_DATABASE_URL'))
    ->setForceInstallPlugins(true)
    ->bootstrap()
    ->getClassLoader()
;

$loader->addPsr4('MuckiLogPlugin\\tests\\', __DIR__);
