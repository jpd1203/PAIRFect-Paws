<?php

namespace App\Services;

use App\Support\ResearchMode;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ResearchPrivateMedia
{
    public const DISK = 'research-private';

    private const EXTENSIONS = [
        'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        'video/webm' => 'webm', 'video/mp4' => 'mp4',
    ];

    public function store(UploadedFile $file, string $kind, int $ownerKey): string
    {
        $extension = self::EXTENSIONS[$file->getMimeType()] ?? null;
        abort_unless($extension, 422, 'Unsupported file format.');
        abort_unless(ResearchMode::ready(), 404);
        $path = $this->privateDisk()->putFileAs("{$kind}/{$ownerKey}", $file, Str::uuid().'.'.$extension);
        abort_unless(is_string($path) && $path !== '', 503, 'Private media storage is temporarily unavailable.');

        return $path;
    }

    public function forget(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function response(string $kind, int $ownerKey, ?string $path, ?string $mime, bool $download = false): BinaryFileResponse
    {
        abort_unless(ResearchMode::ready(), 404);
        $extension = self::EXTENSIONS[$mime] ?? null;
        $prefix = preg_quote("{$kind}/{$ownerKey}/", '~');
        abort_unless($extension && $path && preg_match('~\A'.$prefix.'[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\.'.$extension.'\z~', $path), 404);
        $disk = $this->privateDisk();
        abort_unless($disk->exists($path), 404);
        $headers = ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff'];

        return $download
            ? response()->download($disk->path($path), "identity-document.{$extension}", $headers)
            : response()->file($disk->path($path), $headers);
    }

    private function privateDisk(): FilesystemAdapter
    {
        $config = config('filesystems.disks.'.self::DISK);
        abort_unless($config['driver'] === 'local' && $config['visibility'] === 'private' && $config['serve'] === false, 503, 'Private media storage is temporarily unavailable.');
        $disk = Storage::disk(self::DISK);
        $root = $disk->path('');
        File::ensureDirectoryExists($root, 0700, true);
        $resolved = realpath($root);
        abort_unless($resolved !== false, 503, 'Private media storage is temporarily unavailable.');
        $normalize = static function (string $path): string {
            $path = rtrim(str_replace('\\', '/', $path), '/');

            return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
        };
        $resolved = $normalize($resolved);
        foreach ([public_path(), storage_path('app/public')] as $publicRoot) {
            $publicRoot = $normalize(realpath($publicRoot) ?: $publicRoot);
            abort_if($resolved === $publicRoot || str_starts_with($resolved, $publicRoot.'/'), 503, 'Private media storage is temporarily unavailable.');
        }

        return $disk;
    }
}
