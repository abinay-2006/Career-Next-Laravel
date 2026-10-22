<?php

namespace Tests\Unit;

use App\Support\ProfilePhoto;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    public function test_it_normalizes_current_and_legacy_photo_values(): void
    {
        $this->assertSame('photos/avatar.png', ProfilePhoto::path('avatar.png'));
        $this->assertSame('photos/avatar.png', ProfilePhoto::path('photos/avatar.png'));
        $this->assertSame('photos/avatar.png', ProfilePhoto::path('/storage/photos/avatar.png'));
        $this->assertSame('registration/photos/avatar.png', ProfilePhoto::path('registration/photos/avatar.png'));
        $this->assertNull(ProfilePhoto::path('../avatar.png'));
        $this->assertNull(ProfilePhoto::path('photos/nested/avatar.png'));
    }

    public function test_it_only_returns_a_url_for_an_existing_public_photo(): void
    {
        Storage::fake('public');

        $this->assertNull(ProfilePhoto::url('missing.png'));

        Storage::disk('public')->put('photos/avatar.png', 'image bytes');

        $this->assertSame(url('/storage/photos/avatar.png'), ProfilePhoto::url('photos/avatar.png'));
    }

    public function test_it_resolves_legacy_public_photo_paths_without_moving_the_file(): void
    {
        $filename = 'legacy-' . bin2hex(random_bytes(8)) . '.jpg';
        $relativePath = 'upload/photos/' . $filename;
        $absolutePath = public_path($relativePath);

        $this->assertSame(strlen('image'), file_put_contents($absolutePath, 'image'));

        try {
            $this->assertSame($relativePath, ProfilePhoto::path('public/' . $relativePath));
            $this->assertSame(url('/' . $relativePath), ProfilePhoto::url('public/' . $relativePath));
            $this->assertSame(url('/' . $relativePath), ProfilePhoto::url($filename));
        } finally {
            unlink($absolutePath);
        }
    }
}
