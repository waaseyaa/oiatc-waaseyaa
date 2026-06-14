<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use Anokii\Config\DistributionConfig;
use Anokii\Config\TenancyMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * OIATC ships config/anokii.yaml declaring the shared-graph tier. This pins the
 * posture the file must resolve to, so the distribution config can never quietly
 * drift back to the sovereign default.
 */
final class DistributionConfigTest extends TestCase
{
    private function config(): DistributionConfig
    {
        return DistributionConfig::fromFile(dirname(__DIR__, 3) . '/config/anokii.yaml');
    }

    #[Test]
    public function oiatc_is_a_shared_graph_install(): void
    {
        $this->assertSame(TenancyMode::SharedGraph, $this->config()->tenancyMode());
    }

    #[Test]
    public function data_residency_is_the_public_shared_posture(): void
    {
        $residency = $this->config()->dataResidency();

        $this->assertSame('shared', $residency['ownership']);
        $this->assertSame('public', $residency['default_classification']);
        $this->assertTrue($residency['cross_tenant_reads']);
    }

    #[Test]
    public function cointelligence_and_resources_are_live_others_are_preview(): void
    {
        $config = $this->config();

        $this->assertTrue($config->moduleEnabled('cointelligence'), 'Co-Intelligence is live');
        $this->assertTrue($config->moduleEnabled('resources'), 'Resources is live');

        foreach (['governed-drive', 'forms', 'tasks', 'data-rooms', 'docs', 'sheets', 'admin-centre'] as $module) {
            $this->assertTrue($config->modulePreview($module), $module . ' is preview');
            $this->assertFalse($config->moduleEnabled($module), $module . ' is not production-enabled');
        }
    }
}
