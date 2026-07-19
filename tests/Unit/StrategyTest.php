<?php

namespace Tests\Unit;

use App\Enums\StrategyKey;
use App\Models\Strategy;
use PHPUnit\Framework\TestCase;

class StrategyTest extends TestCase
{
    public function test_cloudflare_r2_driver_is_registered()
    {
        $this->assertSame('Cloudflare R2', Strategy::DRIVERS[StrategyKey::R2]);
    }

    public function test_webdav_proxy_mode_is_only_enabled_for_webdav_strategy()
    {
        $webdav = new Strategy([
            'key' => StrategyKey::Webdav,
            'configs' => ['proxy' => 1],
        ]);
        $r2 = new Strategy([
            'key' => StrategyKey::R2,
            'configs' => ['proxy' => 1],
        ]);

        $this->assertTrue($webdav->isWebDavProxyEnabled());
        $this->assertFalse($r2->isWebDavProxyEnabled());
    }
}
