<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_upload_processes_image_via_intervention_v4(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        // real PNG so intervention/image can decode it
        $file = UploadedFile::fake()->image('avatar.png', 600, 600);

        $response = $this->actingAs($user)->from('/profile')->patch('/profile', [
            'name'   => $user->name,
            'email'  => $user->email,
            'avatar' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertNotNull($user->profile_photo_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);
    }
}
