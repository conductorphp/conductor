<?php

declare(strict_types=1);

namespace ConductorTest;

use Conductor\Version;
use ConductorCore\Config\ConfigDirectory;
use ConductorCore\Crypt\CryptResolverFactory;
use ConductorCore\Crypt\SodiumCrypt;
use ConductorCore\Exception\RuntimeException;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function file_put_contents;
use function glob;
use function mkdir;
use function putenv;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * The binary's config cache holds the YAML as written and nothing else, and is checked against the
 * files on every load (CTAP-2759). Every test runs with the cache on.
 */
final class ConfigCacheTest extends TestCase
{
    private const CONFIG = __DIR__ . '/../config/config.php';

    private string $key;
    private string $directory;

    protected function setUp(): void
    {
        $this->key       = SodiumCrypt::generateKey();
        $this->directory = sys_get_temp_dir() . '/conductor-config-cache-' . uniqid();
        mkdir($this->directory . '/config/app/environments/local', 0775, true);

        putenv('CONDUCTOR_ENVIRONMENT=local');
        putenv('CONDUCTOR_CONFIG_CACHE');
        putenv('CONDUCTOR_CRYPT_KEY=' . $this->key);
    }

    protected function tearDown(): void
    {
        putenv('CONDUCTOR_ENVIRONMENT');
        putenv('CONDUCTOR_CONFIG_CACHE');
        putenv('CONDUCTOR_CRYPT_KEY');
        putenv('CONDUCTOR_CRYPT_KEYS_PREVIOUS');
        putenv('CTAP_2759_VALUE');
    }

    public function testTheCacheIsWrittenAndServesAnUnchangedConfiguration(): void
    {
        $this->write('global.yaml', "ctap_2759:\n  value: one\n");

        $this->assertSame('one', $this->load()['ctap_2759']['value']);
        $this->assertFileExists($this->cachePath());
        $this->assertSame('one', $this->load()['ctap_2759']['value']);
    }

    public function testAnEditedFileTakesEffect(): void
    {
        $this->write('global.yaml', "ctap_2759:\n  value: one\n");
        $this->load();

        // Same size, and almost certainly the same second: only the content tells them apart.
        $this->write('global.yaml', "ctap_2759:\n  value: two\n");

        $this->assertSame('two', $this->load()['ctap_2759']['value']);
    }

    public function testAnAddedFileTakesEffect(): void
    {
        $this->write('global.yaml', "ctap_2759:\n  value: global\n");
        $this->load();

        $this->write('environments/local/config.yaml', "ctap_2759:\n  value: local\n");

        $this->assertSame('local', $this->load()['ctap_2759']['value']);
    }

    public function testARemovedFileTakesEffect(): void
    {
        $this->write('global.yaml', "ctap_2759:\n  value: global\n");
        $this->write('extra.yaml', "ctap_2759:\n  extra: here\n");
        $this->assertSame('here', $this->load()['ctap_2759']['extra']);

        unlink($this->directory . '/config/app/extra.yaml');

        $this->assertArrayNotHasKey('extra', $this->load()['ctap_2759']);
    }

    public function testAChangedVariableTakesEffect(): void
    {
        $this->write('global.yaml', "ctap_2759:\n  value: '\${CTAP_2759_VALUE}'\n");

        putenv('CTAP_2759_VALUE=first');
        $this->assertSame('first', $this->load()['ctap_2759']['value']);

        putenv('CTAP_2759_VALUE=second');
        $this->assertSame('second', $this->load()['ctap_2759']['value']);
        $this->assertStringNotContainsString('second', $this->cache());
    }

    public function testNoSecretIsWrittenToTheCache(): void
    {
        putenv('CTAP_2759_VALUE=resolved-variable');
        $this->write('global.yaml', "ctap_2759:\n  secret: '" . $this->encrypt('decrypted-secret') . "'\n"
            . "  value: '\${CTAP_2759_VALUE}'\n");

        $config = $this->load();
        $this->assertSame('decrypted-secret', $config['ctap_2759']['secret']);
        $this->assertSame('resolved-variable', $config['ctap_2759']['value']);

        $cache = $this->cache();
        $this->assertStringNotContainsString('decrypted-secret', $cache);
        $this->assertStringNotContainsString('resolved-variable', $cache);
        $this->assertStringNotContainsString($this->key, $cache, 'the crypt key is not cached either');
        $this->assertStringContainsString('enc:v1:', $cache);
        $this->assertStringContainsString('${CTAP_2759_VALUE}', $cache);
    }

    public function testACacheBuiltUnderOneKeyIsDecryptedWithTheKeyItIsReadUnder(): void
    {
        $this->write('global.yaml', "ctap_2759:\n  secret: '" . $this->encrypt('rotated') . "'\n");
        $this->load();

        // Rotated: the value still decrypts through the retired key.
        $previous = $this->key;
        putenv('CONDUCTOR_CRYPT_KEY=' . SodiumCrypt::generateKey());
        putenv('CONDUCTOR_CRYPT_KEYS_PREVIOUS=' . $previous);
        $this->assertSame('rotated', $this->load()['ctap_2759']['secret']);

        // Without the key it was encrypted under, the cached file cannot hand back the plaintext.
        putenv('CONDUCTOR_CRYPT_KEYS_PREVIOUS');
        $this->expectException(RuntimeException::class);
        $this->load();
    }

    public function testAPlaintextCacheFromAnEarlierVersionIsReplacedAndOthersRemoved(): void
    {
        $this->write('global.yaml', "ctap_2759:\n  value: current\n");
        mkdir($this->directory . '/data/cache', 0775, true);
        $earlier = $this->directory . '/data/cache/config-cache-1.0.0-local.php';
        file_put_contents($earlier, "<?php return ['ctap_2759' => ['value' => 'stale-secret']];\n");
        file_put_contents($this->cachePath(), "<?php return ['ctap_2759' => ['value' => 'stale-secret']];\n");

        $this->assertSame('current', $this->load()['ctap_2759']['value']);
        $this->assertFileDoesNotExist($earlier);
        $this->assertSame([$this->cachePath()], glob($this->directory . '/data/cache/config-cache-*.php'));
        $this->assertStringNotContainsString('stale-secret', $this->cache());
    }

    public function testCacheCanBeTurnedOff(): void
    {
        putenv('CONDUCTOR_CONFIG_CACHE=0');
        $this->write('global.yaml', "ctap_2759:\n  value: one\n");

        $this->assertSame('one', $this->load()['ctap_2759']['value']);
        $this->assertSame([], glob($this->directory . '/data/cache/config-cache-*.php'));
    }

    private function write(string $file, string $yaml): void
    {
        file_put_contents($this->directory . '/config/app/' . $file, $yaml);
    }

    private function encrypt(string $plaintext): string
    {
        return (new CryptResolverFactory())->fromKeys($this->key, null)->encrypt($plaintext);
    }

    private function cachePath(): string
    {
        return (string) ConfigDirectory::resolve($this->directory, null, '/')->cachePath('local', Version::current());
    }

    private function cache(): string
    {
        return (string) file_get_contents($this->cachePath());
    }

    /** @return array<string, mixed> */
    private function load(): array
    {
        return (require self::CONFIG)(ConfigDirectory::resolve($this->directory, null, '/'));
    }
}
