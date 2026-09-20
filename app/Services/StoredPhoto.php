<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoredPhoto
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const MAX_SIDE = 4096;

    public const ALLOWED = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    public static function fromUpload(UploadedFile $file, string $dir): string
    {
        abort_unless($file->isValid(), 422, 'Berkas foto tidak valid.');
        abort_unless($file->getSize() !== false && $file->getSize() <= self::MAX_BYTES, 422, 'Ukuran foto maksimal 5MB.');
        $info = @getimagesize($file->getRealPath());
        abort_unless($info !== false, 422, 'Berkas bukan gambar yang valid.');
        abort_unless($info[0] <= self::MAX_SIDE && $info[1] <= self::MAX_SIDE, 422, 'Dimensi foto maksimal 4096px.');
        $ekstensi = strtolower($file->getClientOriginalExtension());
        abort_unless(array_key_exists($ekstensi, self::ALLOWED), 422, 'Format foto: jpg, png, atau webp.');
        abort_unless(in_array(mime_content_type($file->getRealPath()), self::ALLOWED, true), 422, 'Isi berkas bukan gambar yang didukung.');
        $nama = Str::uuid()->toString().'.'.$ekstensi;
        $path = trim($dir, '/').'/'.$nama;
        Storage::disk('local')->makeDirectory(trim($dir, '/'));
        if (extension_loaded('gd')) {
            $img = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
            abort_unless($img !== false, 422, 'Berkas bukan gambar yang valid.');
            $menulis = match ($ekstensi) {
                'png' => imagepng($img, Storage::disk('local')->path($path)),
                'webp' => imagewebp($img, Storage::disk('local')->path($path)),
                default => imagejpeg($img, Storage::disk('local')->path($path), 90),
            };
            imagedestroy($img);
            abort_unless($menulis, 422, 'Foto gagal disimpan.');
        } else {
            Storage::disk('local')->putFileAs(trim($dir, '/'), $file, $nama);
        }

        return $path;
    }

    public static function delete(?string $path): void
    {
        if ($path !== null && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public static function mimeFor(string $path): string
    {
        return self::ALLOWED[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }
}
