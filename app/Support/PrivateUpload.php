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
        $disk = UploadStorage::privateDisk();
        if ($disk->exists($storedPath)) {
            return $disk->response($storedPath, $safeName, [
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ], 'inline');
        }

        // During an approved bucket transition, records can still point to
        // objects on this server's original private local disk.
        if (UploadStorage::privateDiskName() !== 'private') {
            $localPrivateDisk = Storage::disk('private');
            if ($localPrivateDisk->exists($storedPath)) {
                return $localPrivateDisk->response($storedPath, $safeName, [
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control' => 'private, no-store',
                ], 'inline');
            }
        }

        $legacyName = basename($storedPath);
        foreach ($legacyDirectories as $legacyDirectory) {
            // Keep the legacy key shape in the private disk. The same path
            // under public/ remains denied by Apache and is never read here.
            $legacyObject = trim(str_replace('\\', '/', $legacyDirectory), '/').'/'.$legacyName;
            if ($disk->exists($legacyObject)) {
                return $disk->response($legacyObject, $safeName, [
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control' => 'private, no-store',
                ], 'inline');
            }

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
