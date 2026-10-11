<?php

declare(strict_types=1);

use ConductorCore\Config\ConfigDirectory;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;

return static function (ConfigDirectory $configDirectory): ContainerInterface {
    $config = (require __DIR__ . '/config.php')($configDirectory);

    $dependencies                                   = $config['dependencies'];
    $dependencies['services']['config']             = $config;
    $dependencies['services'][ConfigDirectory::class] = $configDirectory;

    return new ServiceManager($dependencies);
};
