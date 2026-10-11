<?php

declare(strict_types=1);

namespace Conductor;

use ConductorCore\Config\ConfigDirectory;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;

use function error_reporting;

/**
 * Boots conductor against a configuration directory and builds the console around it.
 *
 * This is what `bin/conductor` does, kept as two calls so a test can boot the same container and
 * console from a fixture directory.
 */
final class Kernel
{
    public static function boot(ConfigDirectory $configDirectory): ContainerInterface
    {
        return (require __DIR__ . '/../config/container.php')($configDirectory);
    }

    public static function createApplication(ContainerInterface $container): Application
    {
        $config = $container->get('config');

        $application = new Application('Conductor', Version::current());
        // Read by ConfigDirectory before the console exists; declared here so the console accepts it.
        $application->getDefinition()->addOption(new InputOption(
            ConfigDirectory::OPTION,
            null,
            InputOption::VALUE_REQUIRED,
            'The configuration directory (default: $' . ConfigDirectory::ENV_VARIABLE . ', then the working directory)',
        ));

        foreach ($config['console']['commands'] ?? [] as $command) {
            $application->addCommand($container->get($command));
        }

        if (!empty($config['debug'])) {
            error_reporting(-1);
        }

        return $application;
    }
}
