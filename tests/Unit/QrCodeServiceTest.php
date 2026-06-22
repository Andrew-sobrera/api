<?php

namespace Tests\Unit;

use App\Services\QrCodeService;
use Tests\TestCase;

class QrCodeServiceTest extends TestCase
{
    public function test_generates_svg_qr_code_without_gd(): void
    {
        config(['tickets.qr_writer' => 'svg']);

        $result = app(QrCodeService::class)->generate('ticket-hash-123');

        $this->assertSame('svg', $result['extension']);
        $this->assertSame('image/svg+xml', $result['mime_type']);
        $this->assertStringContainsString('<svg', $result['content']);
    }

    public function test_falls_back_to_svg_when_png_requested_without_gd(): void
    {
        if (extension_loaded('gd')) {
            $this->markTestSkipped('GD is enabled on this environment.');
        }

        config(['tickets.qr_writer' => 'png']);

        $result = app(QrCodeService::class)->generate('ticket-hash-456');

        $this->assertSame('svg', $result['extension']);
    }
}
