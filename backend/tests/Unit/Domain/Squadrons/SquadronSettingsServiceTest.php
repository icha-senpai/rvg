<?php

namespace Tests\Unit\Domain\Squadrons;

use App\Domain\Squadrons\SquadronSettingsService;
use App\Models\Squadron;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SquadronSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_settings_preserves_rich_editor_callout_markup(): void
    {
        $service = app(SquadronSettingsService::class);
        $squadron = Squadron::create([
            'name' => 'Spectre',
            'slug' => 'spectre',
            'status' => 'active',
        ]);

        $updated = $service->updateSettings($squadron, [
            'description' => '<div data-type="hz-rte-callout" data-tone="green" class="hz-rte-callout hz-rte-callout-red" style="border-color:red;background:red;"><p>Field body</p></div>',
        ]);

        $this->assertSame(
            '<div data-type="hz-rte-callout" data-tone="green" class="hz-rte-callout hz-rte-callout-green"><p>Field body</p></div>',
            $updated->description
        );
    }

    public function test_update_settings_preserves_custom_callout_color_and_editor_classes(): void
    {
        $service = app(SquadronSettingsService::class);
        $squadron = Squadron::create([
            'name' => 'Valkyrie',
            'slug' => 'valkyrie',
            'status' => 'active',
        ]);

        $updated = $service->updateSettings($squadron, [
            'description' => '<div data-type="hz-rte-callout" data-tone="orange" data-accent-color="#abc" class="foo" style="color:red;"><p><span class="hz-rte-color-cyan hz-rte-size-18 nope">Styled text</span></p></div>',
        ]);

        $this->assertStringContainsString('data-type="hz-rte-callout"', $updated->description);
        $this->assertStringContainsString('data-tone="custom"', $updated->description);
        $this->assertStringContainsString('data-accent-color="#aabbcc"', $updated->description);
        $this->assertStringContainsString('class="hz-rte-callout hz-rte-callout-custom"', $updated->description);
        $this->assertStringContainsString('border-color: color-mix(in srgb, #aabbcc 45%, transparent); background: color-mix(in srgb, #aabbcc 14%, rgb(27 32 53 / 1));', $updated->description);
        $this->assertStringContainsString('class="hz-rte-color-cyan hz-rte-size-18"', $updated->description);
        $this->assertStringNotContainsString('nope', $updated->description);
    }

    public function test_update_settings_preserves_rich_image_alignment_metadata(): void
    {
        $service = app(SquadronSettingsService::class);
        $squadron = Squadron::create([
            'name' => 'Aegis',
            'slug' => 'aegis',
            'status' => 'active',
        ]);

        $updated = $service->updateSettings($squadron, [
            'recruitment_propaganda' => '<p><img src="https://example.com/banner.png" alt="Banner" class="hz-rich-image hz-rich-image-right ignored" data-align="right" data-width="63" style="width: 63%; height: auto; border: 0;" onclick="alert(1)"></p>',
        ]);

        $this->assertStringContainsString('class="hz-rich-image hz-rich-image-right"', $updated->recruitment_propaganda);
        $this->assertStringContainsString('data-align="right"', $updated->recruitment_propaganda);
        $this->assertStringContainsString('data-width="63"', $updated->recruitment_propaganda);
        $this->assertStringContainsString('style="width: 63%; height: auto"', $updated->recruitment_propaganda);
        $this->assertStringContainsString('loading="lazy"', $updated->recruitment_propaganda);
        $this->assertStringNotContainsString('onclick', $updated->recruitment_propaganda);
        $this->assertStringNotContainsString('ignored', $updated->recruitment_propaganda);
    }
}
