<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CloudinaryService
{
    private \Cloudinary\Cloudinary $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new \Cloudinary\Cloudinary(config('cloudinary.cloud_url'));
    }

    public function uploadFloorPlan(UploadedFile $file): array
    {
        return $this->uploadImage($file->getRealPath(), 'floor-plans');
    }

    public function uploadBanner(UploadedFile $file): array
    {
        return $this->uploadImage($file->getRealPath(), 'banners');
    }

    public function uploadQrCode(string $content, string $folder = 'tickets/qr'): array
    {
        $qrCode = new QrCode(
            data: $content,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 400,
            margin: 10,
        );

        $writer = new PngWriter;
        $result = $writer->write($qrCode);

        $tempPath = sys_get_temp_dir().'/'.Str::uuid().'.png';
        file_put_contents($tempPath, $result->getString());

        try {
            return $this->uploadImage($tempPath, $folder);
        } finally {
            @unlink($tempPath);
        }
    }

    public function uploadImage(string $path, string $subfolder): array
    {
        $folder = trim(config('cloudinary.folder', 'nocal').'/'.$subfolder, '/');

        $result = $this->cloudinary->uploadApi()->upload($path, [
            'folder' => $folder,
            'resource_type' => 'image',
        ]);

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }

    public function delete(string $publicId): void
    {
        $this->cloudinary->uploadApi()->destroy($publicId, [
            'resource_type' => 'image',
        ]);
    }
}
