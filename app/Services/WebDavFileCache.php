<?php

namespace App\Services;

use Throwable;

final class WebDavFileCache
{
    private string $directory;

    private string $indexPath;

    private int $limit;

    public function __construct(int $strategyId, int $limit, ?string $root = null)
    {
        $this->limit = max(1, $limit);
        $baseDirectory = rtrim($root ?: storage_path('app/webdav-cache'), '/\\');
        $this->directory = $baseDirectory . DIRECTORY_SEPARATOR . $strategyId;
        $this->indexPath = $this->directory . DIRECTORY_SEPARATOR . 'index.json';
    }

    public static function clearStrategy(int $strategyId): void
    {
        (new self($strategyId, 1))->clear();
    }

    public function get(string $path): ?string
    {
        $key = $this->key($path);
        $cachePath = $this->mutateIndex(function (array &$index) use ($key): ?string {
            $entry = $index[$key] ?? null;
            if (! is_array($entry) || ! isset($entry['file'])) {
                return null;
            }

            $cachePath = $this->directory . DIRECTORY_SEPARATOR . basename($entry['file']);
            if (! is_file($cachePath)) {
                unset($index[$key]);
                return null;
            }

            $entry['accessed_at'] = microtime(true);
            $index[$key] = $entry;

            return $cachePath;
        });

        if (! is_string($cachePath)) {
            return null;
        }

        $contents = @file_get_contents($cachePath);
        if ($contents === false) {
            $this->forget($path);
            return null;
        }

        return $contents;
    }

    public function put(string $path, string $contents): void
    {
        if (! $this->ensureDirectory()) {
            return;
        }

        $key = $this->key($path);
        $filename = $key . '.cache';
        $cachePath = $this->directory . DIRECTORY_SEPARATOR . $filename;
        $temporaryPath = @tempnam($this->directory, 'webdav-');
        if ($temporaryPath === false) {
            return;
        }

        if (@file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
            @unlink($temporaryPath);
            return;
        }

        if (! @rename($temporaryPath, $cachePath)) {
            @unlink($cachePath);
            if (! @rename($temporaryPath, $cachePath)) {
                @unlink($temporaryPath);
                return;
            }
        }

        $updated = $this->mutateIndex(function (array &$index) use ($key, $filename, $path): bool {
            $index[$key] = [
                'file' => $filename,
                'path' => $path,
                'accessed_at' => microtime(true),
            ];
            $this->evict($index);

            return true;
        });

        if ($updated !== true) {
            @unlink($cachePath);
        }
    }

    public function forget(string $path): void
    {
        $key = $this->key($path);
        @unlink($this->cachePath($key));
        $this->mutateIndex(function (array &$index) use ($key): bool {
            unset($index[$key]);

            return true;
        });
    }

    public function forgetPrefix(string $path): void
    {
        $prefix = trim($path, '/') . '/';
        $this->mutateIndex(function (array &$index) use ($path, $prefix): bool {
            foreach ($index as $key => $entry) {
                $entryPath = is_array($entry) ? ($entry['path'] ?? '') : '';
                if ($entryPath === $path || str_starts_with($entryPath, $prefix)) {
                    if (is_array($entry) && isset($entry['file'])) {
                        @unlink($this->directory . DIRECTORY_SEPARATOR . basename($entry['file']));
                    }
                    unset($index[$key]);
                }
            }

            return true;
        });
    }

    public function clear(): void
    {
        if (! is_dir($this->directory)) {
            return;
        }

        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }
        }

        @rmdir($this->directory);
    }

    private function key(string $path): string
    {
        return hash('sha256', $path);
    }

    private function cachePath(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $key . '.cache';
    }

    private function ensureDirectory(): bool
    {
        return is_dir($this->directory) || @mkdir($this->directory, 0755, true);
    }

    private function mutateIndex(callable $callback): mixed
    {
        if (! $this->ensureDirectory()) {
            return null;
        }

        $handle = @fopen($this->indexPath, 'c+');
        if ($handle === false || ! @flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                @fclose($handle);
            }
            return null;
        }

        try {
            rewind($handle);
            $contents = stream_get_contents($handle);
            $index = json_decode($contents ?: '{}', true);
            if (! is_array($index)) {
                $index = [];
            }

            $result = $callback($index);
            $encoded = json_encode($index, JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                return null;
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $encoded);
            fflush($handle);

            return $result;
        } catch (Throwable) {
            return null;
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    private function evict(array &$index): void
    {
        uasort($index, static function ($left, $right): int {
            return ($left['accessed_at'] ?? 0) <=> ($right['accessed_at'] ?? 0);
        });

        while (count($index) > $this->limit) {
            $key = array_key_first($index);
            $entry = $index[$key];
            unset($index[$key]);

            if (is_array($entry) && isset($entry['file'])) {
                @unlink($this->directory . DIRECTORY_SEPARATOR . basename($entry['file']));
            }
        }
    }
}
