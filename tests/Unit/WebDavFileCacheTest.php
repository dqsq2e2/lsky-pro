<?php

namespace Tests\Unit;

use App\Services\WebDavFileCache;
use PHPUnit\Framework\TestCase;

class WebDavFileCacheTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/lsky-webdav-cache-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        (new WebDavFileCache(1, 1, $this->root))->clear();
        @rmdir($this->root);
        parent::tearDown();
    }

    public function test_least_recently_used_file_is_evicted()
    {
        $cache = new WebDavFileCache(1, 2, $this->root);

        $cache->put('first.png', 'first');
        usleep(1000);
        $cache->put('second.png', 'second');
        usleep(1000);
        $this->assertSame('first', $cache->get('first.png'));
        usleep(1000);
        $cache->put('third.png', 'third');

        $this->assertSame('first', $cache->get('first.png'));
        $this->assertNull($cache->get('second.png'));
        $this->assertSame('third', $cache->get('third.png'));
    }
}
