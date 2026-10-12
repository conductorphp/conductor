<?php

declare(strict_types=1);

namespace ConductorTest;

use ConductorCore\Config\ConfigDirectory;
use ConductorCore\Crypt\CryptResolverFactory;
use ConductorCore\Crypt\SodiumCrypt;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function putenv;
use function sys_get_temp_dir;
use function uniqid;

/**
 * The binary's post-processor order: interpolate `${VAR}`, then decrypt. An encrypted environment
 * variable is decrypted exactly like a value written in the YAML, and a decrypted secret is never
 * scanned for placeholders (CTAP-2383).
 */
final class ConfigOrderTest extends TestCase
{
    private const CONFIG = __DIR__ . '/../config/config.php';

    private string $key;
    private string $directory;

    protected function setUp(): void
    {
        $this->key       = SodiumCrypt::generateKey();
        $this->directory = sys_get_temp_dir() . '/conductor-config-order-' . uniqid();
        mkdir($this->directory . '/config/app', 0775, true);

        putenv('CONDUCTOR_ENVIRONMENT=local');
        putenv('CONDUCTOR_CONFIG_CACHE=0');
        putenv('CONDUCTOR_CRYPT_KEY=' . $this->key);
    }

    protected function tearDown(): void
    {
        putenv('CONDUCTOR_ENVIRONMENT');
        putenv('CONDUCTOR_CONFIG_CACHE');
        putenv('CONDUCTOR_CRYPT_KEY');
        putenv('CTAP_2383_SECRET');
    }

    public function testAnEncryptedValueReachedThroughAVariableIsDecrypted(): void
    {
        putenv('CTAP_2383_SECRET=' . $this->encrypt('from the environment'));

        $config = $this->load("ctap_2383:\n  secret: '\${CTAP_2383_SECRET}'\n");

        $this->assertSame('from the environment', $config['ctap_2383']['secret']);
    }

    public function testAnEncryptedValueWrittenInTheYamlIsDecrypted(): void
    {
        $config = $this->load("ctap_2383:\n  secret: '" . $this->encrypt('from the yaml') . "'\n");

        $this->assertSame('from the yaml', $config['ctap_2383']['secret']);
    }

    public function testADecryptedValueIsNotInterpolated(): void
    {
        // Undefined, so interpolating it would fail the load.
        $plaintext = 'pa${CTAP_2383_UNDEFINED}ss';

        $config = $this->load("ctap_2383:\n  secret: '" . $this->encrypt($plaintext) . "'\n");

        $this->assertSame($plaintext, $config['ctap_2383']['secret']);
    }

    private function encrypt(string $plaintext): string
    {
        return (new CryptResolverFactory())->fromKeys($this->key, null)->encrypt($plaintext);
    }

    /** @return array<string, mixed> */
    private function load(string $yaml): array
    {
        file_put_contents($this->directory . '/config/app/global.yaml', $yaml);

        return (require self::CONFIG)(ConfigDirectory::resolve($this->directory, null, '/'));
    }
}
