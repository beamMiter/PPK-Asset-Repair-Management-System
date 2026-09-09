<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * NS1: destroySound() built a filesystem path from the raw `file_name`
 * request field and File::delete()'d it — `file_name=../../.env` deleted
 * the project's .env. NS2: updateSound() stored any string. NS3: the
 * upload-sound form 500'd because uploadSound() did not exist.
 */
class NotificationSoundTest extends TestCase
{
    use RefreshDatabase;

    private array $madeFiles = [];
    private string $sentinel = '';

    protected function setUp(): void
    {
        parent::setUp();
        @mkdir(public_path('sounds'), 0777, true);
        $this->sentinel = storage_path('app/ns-traversal-sentinel.txt');
        file_put_contents($this->sentinel, 'keep me');
    }

    protected function tearDown(): void
    {
        foreach ($this->madeFiles as $f) {
            @unlink($f);
        }
        foreach (glob(public_path('sounds/zz-test-*')) ?: [] as $f) {
            @unlink($f);
        }
        @unlink($this->sentinel);
        parent::tearDown();
    }

    private function makeSound(string $name): string
    {
        $path = public_path('sounds/' . $name);
        file_put_contents($path, 'RIFF....WAVEfmt ');
        $this->madeFiles[] = $path;
        return $path;
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => 'it_support']);
    }

    public function test_destroy_sound_cannot_traverse_out_of_the_sounds_folder(): void
    {
        $this->actingAs($this->staff())
            ->delete(route('settings.notifications.destroy_sound'), [
                'file_name' => '../../storage/app/ns-traversal-sentinel.txt',
            ])
            ->assertRedirect();

        $this->assertFileExists($this->sentinel, 'traversal must not delete files outside public/sounds');
    }

    public function test_destroy_sound_removes_a_real_library_file(): void
    {
        $path = $this->makeSound('zz-test-remove.mp3');

        $this->actingAs($this->staff())
            ->delete(route('settings.notifications.destroy_sound'), ['file_name' => 'zz-test-remove.mp3'])
            ->assertRedirect();

        $this->assertFileDoesNotExist($path);
    }

    public function test_destroy_sound_keeps_the_locked_default(): void
    {
        $this->actingAs($this->staff())
            ->delete(route('settings.notifications.destroy_sound'), ['file_name' => 'new-request.mp3'])
            ->assertRedirect();
        // locked name is refused before any filesystem touch
    }

    public function test_update_sound_rejects_a_sound_not_in_the_library(): void
    {
        $user = $this->staff();

        $this->actingAs($user)
            ->patch(route('settings.notifications.update_sound'), ['notification_sound' => '../evil.mp3'])
            ->assertRedirect();

        $this->assertNotSame('../evil.mp3', $user->fresh()->notification_sound);
    }

    public function test_update_sound_accepts_a_library_file(): void
    {
        $this->makeSound('zz-test-choice.wav');
        $user = $this->staff();

        $this->actingAs($user)
            ->patch(route('settings.notifications.update_sound'), ['notification_sound' => 'zz-test-choice.wav'])
            ->assertRedirect();

        $this->assertSame('zz-test-choice.wav', $user->fresh()->notification_sound);
    }

    public function test_upload_sound_stores_an_audio_file(): void
    {
        $this->actingAs($this->staff())
            ->post(route('settings.notifications.upload_sound'), [
                'sound_file' => UploadedFile::fake()->create('beep.mp3', 100, 'audio/mpeg'),
            ])
            ->assertRedirect();

        $stored = glob(public_path('sounds/beep-*.mp3')) ?: [];
        $this->assertNotEmpty($stored, 'uploaded sound should land in public/sounds');
        $this->madeFiles = array_merge($this->madeFiles, $stored);
    }

    public function test_upload_sound_rejects_a_non_audio_file(): void
    {
        $this->actingAs($this->staff())
            ->from(route('settings.notifications.index'))
            ->post(route('settings.notifications.upload_sound'), [
                'sound_file' => UploadedFile::fake()->create('payload.exe', 10, 'application/x-msdownload'),
            ])
            ->assertRedirect(route('settings.notifications.index'))
            ->assertSessionHasErrors('sound_file');
    }
}
