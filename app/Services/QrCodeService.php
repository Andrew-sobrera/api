<?php

namespace App\Services;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\WriterInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class QrCodeService
{
    /**
     * @return array{content: string, mime_type: string, extension: string}
     */
    public function generate(string $data): array
    {
        $size = max(200, (int) config('tickets.qr_size', 800));

        $qrCode = new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: (int) max(8, round($size * 0.025)),
        );

        ['writer' => $writer, 'mime_type' => $mimeType, 'extension' => $extension] = $this->resolveWriter();

        return [
            'content' => $writer->write($qrCode)->getString(),
            'mime_type' => $mimeType,
            'extension' => $extension,
        ];
    }

    /**
     * @return array{writer: WriterInterface, mime_type: string, extension: string}
     */
    private function resolveWriter(): array
    {
        $preferred = strtolower((string) config('tickets.qr_writer', 'svg'));

        if ($preferred === 'png' && extension_loaded('gd')) {
            return [
                'writer' => new PngWriter,
                'mime_type' => 'image/png',
                'extension' => 'png',
            ];
        }

        if ($preferred === 'png' && ! extension_loaded('gd')) {
            Log::warning('TICKET_QR_WRITER=png requires PHP GD. Falling back to SVG writer.');
        }

        return [
            'writer' => new SvgWriter,
            'mime_type' => 'image/svg+xml',
            'extension' => 'svg',
        ];
    }
}
