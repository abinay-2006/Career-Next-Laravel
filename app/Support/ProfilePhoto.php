<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class ProfilePhoto
{
    /** Return a safe path relative to the public disk for current and legacy values. */
    public static function path(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = ltrim(str_replace('\\', '/', $value), '/');
        $value = preg_replace('#^(?:public/|storage/)#', '', $value);

        // Keep existing references to the two legacy public photo folders
        // intact. Their Apache locations are explicitly served as images.
        if (preg_match('#^(upload|uploads)/photos/([^/]+)$#', $value)) {
            return str_contains($value, '..') ? null : $value;
        }

        // Preserve the old registration upload location so already registered
        // accounts continue to work until they replace their photo.
        if (str_starts_with($value, 'registration/photos/')) {
            $filename = substr($value, strlen('registration/photos/'));
            return $filename !== '' && !str_contains($filename, '/') && !str_contains($filename, '..')
                ? 'registration/photos/'.$filename
                : null;
        }

        $value = preg_replace('#^photos/#', '', $value);

        if ($value === '' || str_contains($value, '..') || str_contains($value, '/')) {
            return null;
        }

        return 'photos/'.$value;
    }

    /** Build request-aware URLs so Render's HTTPS host is used, not APP_URL's local value. */
    public static function url(?string $value): ?string
    {
        $path = self::path($value);

        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'upload/photos/') || str_starts_with($path, 'uploads/photos/')) {
            if ($legacyUrl = self::legacyUrl($path)) {
                return $legacyUrl;
            }

            $disk = UploadStorage::publicDisk();
            return $disk->exists($path) ? UploadStorage::displayUrl($path) : null;
        }

        $disk = UploadStorage::publicDisk();
        if ($disk->exists($path)) {
            return UploadStorage::displayUrl($path);
        }

        if (UploadStorage::publicDiskName() !== 'public' && Storage::disk('public')->exists($path)) {
            return url('/storage/'.$path);
        }

        // Older profile rows often contain only the generated filename.
        foreach (['upload/photos/', 'uploads/photos/'] as $legacyDirectory) {
            // Build a possible legacy location for a bare filename.
            $legacyPath = $legacyDirectory . basename($path);

            // -----------------------------------------------------------------
            // Safety checks – allow only image extensions and prevent directory
            // traversal. `legacyUrl()` already performs a real‑path check, but we
            // also guard here to avoid unnecessary filesystem calls for obviously
            // unsafe values.
            // -----------------------------------------------------------------
            $extension = strtolower(pathinfo($legacyPath, PATHINFO_EXTENSION));
            $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
            if (!in_array($extension, $allowedExtensions, true)) {
                continue;
            }

            if ($url = self::legacyUrl($legacyPath)) {
                return $url;
            }
            if ($disk->exists($legacyPath)) {
                return UploadStorage::displayUrl($legacyPath);
            }
        }

        return null;
    }

    private static function legacyUrl(string $relativePath): ?string
    {
        $publicRoot = realpath(public_path());
        $directory = realpath(public_path(dirname($relativePath)));
        $file = realpath(public_path($relativePath));

        if (
            !$publicRoot || !$directory || !$file ||
            !str_starts_with($directory, $publicRoot . DIRECTORY_SEPARATOR) ||
            !str_starts_with($file, $directory . DIRECTORY_SEPARATOR) ||
            !is_file($file)
        ) {
            return null;
        }

        return url('/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath));
    }
}
