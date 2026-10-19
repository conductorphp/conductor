<?php

declare(strict_types=1);

namespace Conductor\Config;

use Generator;
use Laminas\ConfigAggregator\GlobTrait;
use Symfony\Component\Yaml\Yaml;

use function basename;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function hash_file;
use function is_array;
use function is_file;
use function rename;
use function sprintf;
use function uniqid;
use function unlink;
use function var_export;

/**
 * The application's YAML files, parsed once and cached as written (CTAP-2759).
 *
 * The cache holds what the files say and nothing else: `${VAR}` placeholders and `enc:v1` ciphertext
 * stay as they are, so interpolation and decryption run on every load, and a changed variable or key
 * takes effect on the next run. Nothing from the process environment is in it, nor in its name.
 *
 * Alongside the parsed files it records a content hash of every file the patterns matched, in order.
 * On load the patterns are globbed and hashed again, and any difference (an edited, added, removed or
 * reordered file) rebuilds the cache. Hashing is cheap next to parsing; mtime and size were not used
 * because a checkout or an edit within the same second can keep both.
 *
 * Each file is yielded as its own array, so the aggregator merges them exactly as it merges a
 * {@see \ConductorCore\YamlFileProvider} per pattern.
 *
 * Laminas's own cache (`ConfigAggregator::ENABLE_CACHE`) stored the merged config after the
 * post-processors, so it held decrypted secrets, resolved variables and the crypt keys, and was never
 * checked against the files. It used the same file names, so rebuilding this cache deletes every other
 * `config-cache-*.php` beside it: one left by an earlier conductor version still holds those secrets,
 * and one for another environment is only rebuilt on its next run. A plaintext file at this cache's
 * own path fails the check on load and is overwritten.
 */
final class CachedYamlFileProvider
{
    use GlobTrait;

    private const CACHE_FILE_PATTERN = 'config-cache-*.php';

    /**
     * @param list<string> $patterns  Glob patterns, in merge order
     * @param string|null  $cachePath Null turns the cache off
     */
    public function __construct(private readonly array $patterns, private readonly ?string $cachePath)
    {
    }

    public function __invoke(): Generator
    {
        $hashes = [];
        foreach ($this->patterns as $pattern) {
            foreach ($this->glob($pattern) as $file) {
                $hashes[] = [$file, hash_file('xxh128', $file)];
            }
        }

        $files = $this->read($hashes) ?? $this->parse($hashes);

        yield from $files;
    }

    /**
     * @param list<array{string, string}> $hashes
     * @return list<array<mixed>>|null The cached files, or null when there is no cache or it is stale
     */
    private function read(array $hashes): ?array
    {
        if ($this->cachePath === null || !is_file($this->cachePath)) {
            return null;
        }

        $cache = include $this->cachePath;

        return is_array($cache) && ($cache['files'] ?? null) === $hashes && is_array($cache['config'] ?? null)
            ? $cache['config']
            : null;
    }

    /**
     * @param list<array{string, string}> $hashes
     * @return list<array<mixed>>
     */
    private function parse(array $hashes): array
    {
        $config = [];
        foreach ($hashes as [$file]) {
            $config[] = Yaml::parse((string) file_get_contents($file)) ?? [];
        }

        if ($this->cachePath !== null) {
            $this->write(['files' => $hashes, 'config' => $config]);
        }

        return $config;
    }

    /** @param array{files: list<array{string, string}>, config: list<array<mixed>>} $cache */
    private function write(array $cache): void
    {
        $directory = dirname($this->cachePath);
        foreach (glob($directory . '/' . self::CACHE_FILE_PATTERN) ?: [] as $other) {
            if ($other !== $this->cachePath) {
                @unlink($other);
            }
        }

        // Written beside the target and renamed, so a concurrent run reads the old file or the new one.
        $temporary = sprintf('%s/.%s.%s', $directory, basename($this->cachePath), uniqid('', true));
        if (@file_put_contents($temporary, '<?php return ' . var_export($cache, true) . ";\n") === false) {
            return;
        }
        if (!@rename($temporary, $this->cachePath)) {
            @unlink($temporary);
        }
    }
}
