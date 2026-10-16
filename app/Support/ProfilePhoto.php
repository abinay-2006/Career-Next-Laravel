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

        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }

        return url('/storage/'.$path);
    }
}
