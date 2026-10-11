<?php

declare(strict_types=1);

namespace ConductorTest;

use Conductor\Kernel;
use ConductorAppOrchestration\Platform\PlatformManager;
use ConductorCore\Config\ConfigDirectory;
use ConductorCore\Exception\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;
use function putenv;

/**
 * The install-all application booted against the two in-repo configurations, `custom` and
 * `magento2`, the way `bin/conductor` does. No per-configuration PHP, no ConfigProvider list to keep
 * in step: the configuration directory is the only input (CTAP-1760).
 */
final class KernelTest extends TestCase
{
    private const TOOLS = __DIR__ . '/../../../tools';

    protected function setUp(): void
    {
        putenv('CONDUCTOR_ENVIRONMENT=local');
        putenv('CONDUCTOR_CONFIG_CACHE=0');
    }

    protected function tearDown(): void
    {
        putenv('CONDUCTOR_ENVIRONMENT');
        putenv('CONDUCTOR_CONFIG_CACHE');
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function configurations(): iterable
    {
        yield 'middleware (custom)' => ['middleware-conductor', 'custom', 'Conductor Test Middleware'];
        yield 'magento (magento2)'  => ['magento-conductor', 'magento2', 'Clicktap Magento (fixture)'];
    }

    #[DataProvider('configurations')]
    public function testBootsAConfigurationDirectoryAndBuildsEveryCommand(string $dir, string $platform, string $appName): void
    {
        $configDirectory = ConfigDirectory::resolve(self::TOOLS . '/' . $dir, null, '/');
        $container       = Kernel::boot($configDirectory);

        $this->assertSame($configDirectory, $container->get(ConfigDirectory::class));
        $this->assertSame($appName, $container->get('config')['application_orchestration']['application']['app_name']);
        $this->assertSame($platform, $container->get(PlatformManager::class)->getPlatform());
        $this->assertTrue($container->get(PlatformManager::class)->isInstalled(), 'every platform module is installed');

        $application = Kernel::createApplication($container);
        $this->assertTrue($application->getDefinition()->hasOption('config'));
        $this->assertTrue($application->has('app:deploy'));
        $this->assertTrue($application->has('database:export'));
        $this->assertTrue($application->has('filesystem:sync'));
        $this->assertSame('Conductor', $application->getName());
    }

    public function testTheWorkingDirectoryIsTheDefaultConfiguration(): void
    {
        $directory = ConfigDirectory::resolve(null, null, self::TOOLS . '/middleware-conductor');

        $this->assertSame(realpath(self::TOOLS . '/middleware-conductor'), $directory->path);
    }

    public function testAnUnconfiguredDirectoryIsRefusedBeforeAnythingBoots(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No conductor configuration in');
        ConfigDirectory::resolve(null, null, sys_get_temp_dir());
    }
}
