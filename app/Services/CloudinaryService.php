<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CloudinaryService
{
    public function __construct(protected QrCodeService $qrCodeService)
    {
        $this->cloudinary = new \Cloudinary\Cloudinary(config('cloudinary.cloud_url'));
    }

    private \Cloudinary\Cloudinary $cloudinary;

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
        $qr = $this->qrCodeService->generate($content);

        $tempPath = sys_get_temp_dir().'/'.Str::uuid().'.'.$qr['extension'];
        file_put_contents($tempPath, $qr['content']);

        try {
            Log::info('Uploading ticket QR code to Cloudinary', [
                'format' => $qr['extension'],
                'folder' => $folder,
            ]);

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
