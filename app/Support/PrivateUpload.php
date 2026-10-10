<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PrivateUpload
{
    public static function response(string $storedPath, ?string $originalName, array $legacyDirectories = []): Response
    {
        $storedPath = str_replace('\\', '/', $storedPath);
        if ($storedPath === '' || str_starts_with($storedPath, '/') || preg_match('#(^|/)\.\.(/|$)#', $storedPath)) {
            abort(404);
        }

        $safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename($originalName ?: 'document')) ?: 'document';
        $disk = Storage::disk(config('filesystems.default'));
        if ($disk->exists($storedPath)) {
            return $disk->response($storedPath, $safeName, [
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ], 'inline');
        }

        // Legacy registration resumes were stored on the public disk before
        // uploads were moved to the private disk.
        $publicDisk = Storage::disk('public');
        if ($publicDisk->exists($storedPath)) {
            return $publicDisk->response($storedPath, $safeName, [
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ], 'inline');
        }

        $legacyName = basename($storedPath);
        foreach ($legacyDirectories as $legacyDirectory) {
            $root = realpath(public_path($legacyDirectory));
            $path = realpath(public_path($legacyDirectory . DIRECTORY_SEPARATOR . $legacyName));
            if ($root && $path && str_starts_with($path, $root . DIRECTORY_SEPARATOR) && File::isFile($path)) {
                $response = response()->file($path, ['X-Content-Type-Options' => 'nosniff']);
                $response->headers->set('Cache-Control', 'private, no-store');
                $response->headers->set('Content-Disposition', $response->headers->makeDisposition('inline', $safeName));
                return $response;
            }
        }

        abort(404);
    }
}
