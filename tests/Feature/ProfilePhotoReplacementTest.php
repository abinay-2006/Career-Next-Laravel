<?php

namespace Tests\Feature;

use App\Models\Employer;
use App\Models\JobSeeker;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ProfilePhotoReplacementTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/aWQAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId');
            $table->string('JobSeekerName');
            $table->string('Address')->nullable();
            $table->string('City')->nullable();
            $table->string('Email');
            $table->string('Mobile')->nullable();
            $table->string('Qualification')->nullable();
            $table->string('Gender')->nullable();
            $table->string('BirthDate')->nullable();
            $table->string('UserName');
            $table->string('Password')->nullable();
            $table->string('Status')->default('Approved');
            $table->string('Photo')->nullable();
        });

        Schema::create('employer_reg', function (Blueprint $table): void {
            $table->increments('EmployerId');
            $table->string('CompanyName');
            $table->string('ContactPerson')->nullable();
            $table->text('Address')->nullable();
            $table->string('City')->nullable();
            $table->string('Email');
            $table->string('Mobile')->nullable();
            $table->string('Area_Work')->nullable();
            $table->string('UserName');
            $table->string('Password')->nullable();
            $table->string('Status')->default('Approved');
            $table->string('Photo')->nullable();
        });

        Storage::fake('public');
    }

    public function testProfilePhotoTest_both_roles_replace_after_database_update_and_preserve_other_fields(): void
    {
        foreach (['jobseeker', 'employer'] as $role) {
            [$model, $route, $oldPath, $fields] = $this->makeAccount($role, 'photos/old.jpg');
            Storage::disk('public')->put($oldPath, 'old-image');
            $oldFileWasPresentAtUpdate = null;
            $table = $role === 'jobseeker' ? 'jobseeker_reg' : 'employer_reg';

            DB::listen(function (QueryExecuted $query) use (&$oldFileWasPresentAtUpdate, $table, $oldPath): void {
                if (str_starts_with(strtolower(ltrim($query->sql)), 'update') && str_contains(strtolower($query->sql), $table)) {
                    $oldFileWasPresentAtUpdate = Storage::disk('public')->exists($oldPath);
                }
            });

            $response = $this->loginAs($model, $role)->put(route($route), $fields + [
                'Photo' => $this->image(),
            ]);

            $response->assertRedirect(route($role.'.profile.index'));
            $updated = $model->newQuery()->findOrFail($model->getKey());
            $this->assertStringStartsWith('photos/', $updated->Photo);
            $this->assertNotSame($oldPath, $updated->Photo);
            $this->assertTrue(Storage::disk('public')->exists($updated->Photo));
            $this->assertFalse(Storage::disk('public')->exists($oldPath));
            $this->assertTrue($oldFileWasPresentAtUpdate, 'Old photo must remain until the database update succeeds.');
            $this->assertSame('Updated name', $role === 'jobseeker' ? $updated->JobSeekerName : $updated->CompanyName);
        }
    }

    public function testProfilePhotoTest_both_roles_keep_legacy_neighbors_and_replace_when_old_file_is_missing(): void
    {
        foreach ([
            ['legacy.jpg', 'photos/legacy.jpg', 'registration/photos/legacy.jpg'],
            ['photos/legacy.jpg', 'photos/legacy.jpg', 'registration/photos/legacy.jpg'],
            ['registration/photos/legacy.jpg', 'registration/photos/legacy.jpg', 'photos/legacy.jpg'],
        ] as [$legacyValue, $resolvedOld, $unrelated]) {
            foreach (['jobseeker', 'employer'] as $role) {
                [$model, $route, , $fields] = $this->makeAccount($role, $legacyValue);
                Storage::disk('public')->put($resolvedOld, 'legacy-photo');
                Storage::disk('public')->put($unrelated, 'unrelated');

                $this->loginAs($model, $role)->put(route($route), $fields + [
                    'Photo' => $this->image(),
                ])->assertRedirect(route($role.'.profile.index'));

                $this->assertTrue(Storage::disk('public')->exists($unrelated));
                $updated = $model->newQuery()->findOrFail($model->getKey());
                $this->assertTrue(Storage::disk('public')->exists($updated->Photo));
                $this->assertSame('photos/', substr($updated->Photo, 0, 7));
                $this->assertFalse(Storage::disk('public')->exists($resolvedOld));
            }
        }
    }

    public function testProfilePhotoTest_both_roles_replace_when_the_old_file_is_missing(): void
    {
        foreach (['jobseeker', 'employer'] as $role) {
            [$model, $route, $oldPath, $fields] = $this->makeAccount($role, 'photos/missing.jpg');

            $this->loginAs($model, $role)->put(route($route), $fields + [
                'Photo' => $this->image(),
            ])->assertRedirect(route($role.'.profile.index'));

            $updated = $model->newQuery()->findOrFail($model->getKey());
            $this->assertNotSame($oldPath, $updated->Photo);
            $this->assertTrue(Storage::disk('public')->exists($updated->Photo));
        }
    }

    public function testProfilePhotoTest_both_roles_do_not_update_or_delete_old_photo_when_storage_fails(): void
    {
        foreach (['jobseeker', 'employer'] as $role) {
            [$model, $route, $oldPath, $fields] = $this->makeAccount($role, 'photos/old.jpg');
            Storage::disk('public')->put($oldPath, 'old-image');
            $disk = Mockery::mock();
            $disk->shouldReceive('putFileAs')->andReturn(false);
            $disk->shouldReceive('put')->andReturn(false);
            $disk->shouldReceive('exists')->with($oldPath)->andReturn(true);
            Storage::shouldReceive('disk')->with('public')->andReturn($disk);

            $this->loginAs($model, $role)->from('/profile')->put(route($route), $fields + [
                'Photo' => $this->image(),
            ])->assertRedirect('/profile')->assertSessionHasErrors('Photo');

            $this->assertSame($oldPath, $model->newQuery()->findOrFail($model->getKey())->Photo);
            $this->assertTrue($disk->exists($oldPath));
        }
    }

    public function testProfilePhotoTest_both_roles_keep_old_and_clean_up_replacement_when_database_fails(): void
    {
        foreach (['jobseeker', 'employer'] as $role) {
            [$model, $route, $oldPath, $fields] = $this->makeAccount($role, 'photos/old.jpg');
            Storage::disk('public')->put($oldPath, 'old-image');
            $table = $role === 'jobseeker' ? 'jobseeker_reg' : 'employer_reg';
            DB::connection()->beforeExecuting(function (string $sql) use ($table): void {
                if (str_starts_with(strtolower(ltrim($sql)), 'update') && str_contains(strtolower($sql), $table)) {
                    throw new \RuntimeException('simulated persistence failure');
                }
            });

            $this->loginAs($model, $role)->from('/profile')->put(route($route), $fields + [
                'Photo' => $this->image(),
            ])->assertRedirect('/profile')->assertSessionHasErrors($role === 'jobseeker' ? 'Photo' : 'profile');

            $this->assertSame($oldPath, $model->newQuery()->findOrFail($model->getKey())->Photo);
            $this->assertTrue(Storage::disk('public')->exists($oldPath));
            $this->assertSame([$oldPath], Storage::disk('public')->allFiles('photos'));
        }
    }

    private function makeAccount(string $role, string $photo): array
    {
        $fields = $role === 'jobseeker' ? [
            'JobSeekerName' => 'Updated name', 'Address' => 'Street', 'City' => 'City', 'Email' => 'seeker@example.test',
            'Mobile' => '123456', 'Qualification' => 'Degree', 'Gender' => 'Other', 'BirthDate' => '2000-01-01', 'UserName' => 'seeker',
        ] : [
            'CompanyName' => 'Updated name', 'Email' => 'employer@example.test', 'UserName' => 'employer',
            'ContactPerson' => 'Contact', 'Address' => 'Street', 'City' => 'City', 'Mobile' => '123456', 'Area_Work' => 'Area',
        ];
        $model = $role === 'jobseeker' ? new JobSeeker() : new Employer();
        $model->forceFill($fields + ['Photo' => $photo, 'Password' => 'secret', 'Status' => 'Approved']);
        $model->save();

        return [$model, $role.'.profile.update', $photo, $fields];
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('replacement.png', base64_decode(self::PNG));
    }

    private function loginAs($model, string $role)
    {
        foreach (['admin', 'employer', 'jobseeker'] as $guard) {
            auth()->guard($guard)->logout();
        }

        return $this->actingAs($model, $role);
    }
}
