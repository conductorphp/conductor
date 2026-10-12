<?php

declare(strict_types=1);

use Conductor\Version;
use ConductorAppOrchestration\Config\EnvVarInterpolationPostProcessor;
use ConductorCore\Config\ConfigDirectory;
use ConductorCore\Config\DecryptConfigPostProcessor;
use ConductorCore\Config\EnvironmentConfig;
use ConductorCore\Crypt\CryptResolverFactory;
use ConductorCore\YamlFileProvider;
use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;

/**
 * The one config.php. Every module's ConfigProvider is listed; since CTAP-1757 their order carries no
 * meaning, platform strategies and adapters being selected by configuration, not by which provider
 * was merged last. The application's own configuration is read from the ConfigDirectory.
 */
return static function (ConfigDirectory $configDirectory): array {
    // CONDUCTOR_ENVIRONMENT / CONDUCTOR_CRYPT_KEY / CONDUCTOR_CRYPT_KEYS_PREVIOUS from the process
    // environment (CTAP-1741). The keys are only needed while configuration carries encrypted values;
    // with none set, $crypt is null and such values are left as written (CTAP-1968).
    $environmentConfig = EnvironmentConfig::resolve();
    $environment       = $environmentConfig->environment;
    $crypt             = (new CryptResolverFactory())->fromEnvironmentConfig($environmentConfig);

    // Keyed by conductor version and environment so an upgrade never serves a stale cache. Off when
    // the cache directory is not writable, or with CONDUCTOR_CONFIG_CACHE=0.
    $cachePath   = $configDirectory->cachePath($environment, Version::current());
    $cacheConfig = [
        'config_cache_path'            => $cachePath,
        ConfigAggregator::ENABLE_CACHE => $cachePath !== null && getenv('CONDUCTOR_CONFIG_CACHE') !== '0',
    ];

    $appConfig = $configDirectory->appConfigPath();

    $aggregator = new ConfigAggregator([
        \Laminas\ServiceManager\ConfigProvider::class,
        \ConductorCore\ConfigProvider::class,
        \ConductorAppOrchestration\ConfigProvider::class,
        \ConductorAwsS3FilesystemSupport\ConfigProvider::class,
        \ConductorAzureBlobFilesystemSupport\ConfigProvider::class,
        \ConductorGitVcsSupport\ConfigProvider::class,
        \ConductorMySqlSupport\ConfigProvider::class,
        \ConductorSshSupport\ConfigProvider::class,
        \ConductorClicktapPlatformSupport\ConfigProvider::class,
        \ConductorDrupalPlatformSupport\ConfigProvider::class,
        \ConductorMagento1PlatformSupport\ConfigProvider::class,
        \ConductorMagento2PlatformSupport\ConfigProvider::class,
        \ConductorSymfony4PlatformSupport\ConfigProvider::class,
        \ConductorWordpressPlatformSupport\ConfigProvider::class,
        new ArrayProvider($cacheConfig),
        // The application: global files, then the environment's overrides.
        new YamlFileProvider($appConfig . '/{,*.}yaml'),
        new YamlFileProvider($appConfig . '/environments/' . $environment . '/{,*.}yaml'),
        new ArrayProvider($environmentConfig->toArray()),
        new ArrayProvider(['conductor' => ['config_directory' => $configDirectory->path]]),
    ], $cachePath, [
        // `${VAR}` placeholders anywhere in the merged config, filled from the process environment and
        // then `application.environment_vars`. An undefined variable fails the load before any plan
        // step runs. Plan step commands are left to the shell.
        new EnvVarInterpolationPostProcessor(),
        // Encrypted values (enc:v1:<keyId>:... and the legacy ENC[...]) anywhere in the merged config,
        // decrypted with the keys above. After interpolation, so an encrypted environment variable
        // reached through `${VAR}` is decrypted exactly like a value written in the YAML, and a
        // decrypted secret is never scanned for placeholders (CTAP-2383).
        new DecryptConfigPostProcessor($crypt),
    ]);

    return $aggregator->getMergedConfig();
};
