<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\File;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A request's files used to land on the public disk, where the web server hands them to anybody holding the address:
 * a repair photo in a hospital can show a patient's name on a screen. They now go to the private disk and are reached
 * only through GET /attachments/{id}, which asks the request's own policy.
 */
class PrivateUploadsTest extends TestCase
{
    use RefreshDatabase;

    private User $reporter;

    private MaintenanceRequest $job;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        $this->reporter = User::factory()->create(['role' => 'member']);
        $this->job = $this->newJob();
    }

    private function newJob(array $extra = []): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create($extra + [
            'status' => 'pending', 'reporter_id' => $this->reporter->id, 'technician_id' => null, 'asset_id' => null,
        ]);
    }

    private function upload(MaintenanceRequest $job, array $extra = [])
    {
        return $this->actingAs($this->reporter)->post(route('maintenance.requests.attachments', $job), $extra + [
            'files' => [UploadedFile::fake()->image('photo.jpg', 20, 20)],
        ]);
    }

    /** A file from before this change: on the public disk, with its bytes hashed, attached to $job. */
    private function legacyPublicFile(MaintenanceRequest $job, string $bytes): Attachment
    {
        Storage::disk('public')->put('maintenance/old/photo.jpg', $bytes);
        $file = File::create([
            'path' => 'maintenance/old/photo.jpg', 'disk' => 'public', 'mime' => 'image/jpeg', 'size' => strlen($bytes),
            'checksum_sha256' => hash('sha256', $bytes),
        ]);

        return $job->attachments()->create(['file_id' => $file->id, 'original_name' => 'photo.jpg', 'is_private' => false]);
    }

    private function assertIsPrivate(Attachment $att): void
    {
        $this->assertTrue($att->is_private);
        $this->assertSame('local', $att->file->disk);
        Storage::disk('local')->assertExists($att->file->path);
        Storage::disk('public')->assertMissing($att->file->path);
    }

    public function test_a_file_added_on_the_job_page_goes_to_the_private_disk(): void
    {
        $this->upload($this->job);

        $this->assertIsPrivate($this->job->attachments()->with('file')->firstOrFail());
    }

    public function test_a_file_added_on_the_edit_form_goes_to_the_private_disk(): void
    {
        $this->actingAs($this->reporter)->put(route('maintenance.requests.update', $this->job), [
            'title' => 'แก้หัวข้อ',
            'files' => [UploadedFile::fake()->image('photo.jpg', 21, 21)],
        ]);

        $this->assertIsPrivate($this->job->attachments()->with('file')->firstOrFail());
    }

    public function test_a_file_sent_with_a_new_request_is_private_and_the_answer_gives_the_guarded_address(): void
    {
        Sanctum::actingAs($this->reporter);

        $res = $this->postJson('/api/repair-requests', ['title' => 'จอเสีย', 'files' => [UploadedFile::fake()->image('photo.jpg', 22, 22)]])->assertCreated();

        $att = Attachment::with('file')->findOrFail($res->json('data.attachments.0.id'));
        $this->assertIsPrivate($att);
        $this->assertSame(route('attachments.show', $att), $res->json('data.attachments.0.url'));
        $this->assertNull($res->json('data.attachments.0.file.url'), 'the private disk has no public address to give');
    }

    public function test_a_request_cannot_ask_for_a_public_file(): void
    {
        $this->upload($this->job, ['is_private' => 0]);

        $this->assertIsPrivate($this->job->attachments()->with('file')->firstOrFail());
    }

    public function test_the_bytes_cannot_be_reached_at_a_storage_address(): void
    {
        $this->upload($this->job);
        $path = $this->job->attachments()->with('file')->firstOrFail()->file->path;

        $status = $this->get('/storage/'.$path)->getStatusCode();

        $this->assertContains($status, [403, 404], 'the framework route for the private disk wants a signature nobody is given');
    }

    public function test_the_type_on_record_comes_from_the_content_not_from_what_the_client_claims(): void
    {
        $image = UploadedFile::fake()->image('photo.jpg', 23, 23);

        $this->upload($this->job, ['files' => [new UploadedFile($image->getRealPath(), 'photo.jpg', 'text/html', null, true)]]);

        $this->assertSame('image/jpeg', $this->job->attachments()->with('file')->firstOrFail()->file->mime);
    }

    public function test_the_reporter_still_opens_the_file_after_work_has_started(): void
    {
        $this->upload($this->job);
        $att = $this->job->attachments()->firstOrFail();

        // now somebody else is in charge: the reporter may still view the job, but may no longer update it
        $this->job->update(['status' => 'in_progress', 'technician_id' => User::factory()->create(['role' => 'technician'])->id, 'started_at' => now()]);

        $html = $this->actingAs($this->reporter)->get(route('maintenance.requests.show', $this->job))->assertOk()->getContent();
        $this->assertStringContainsString('src="'.route('attachments.show', $att).'"', $html);
        $this->assertStringNotContainsString('LOCKED', $html);

        $this->actingAs($this->reporter)->get(route('attachments.show', $att))->assertOk();
    }

    public function test_someone_else_cannot_open_it(): void
    {
        $this->upload($this->job);
        $att = $this->job->attachments()->firstOrFail();

        $this->actingAs(User::factory()->create(['role' => 'member']))->getJson(route('attachments.show', $att))->assertForbidden();
    }

    public function test_bytes_already_public_move_to_the_private_disk_when_they_are_attached_again(): void
    {
        $image = UploadedFile::fake()->image('same.jpg', 24, 24);
        $bytes = file_get_contents($image->getRealPath());
        $old = $this->legacyPublicFile($this->newJob(), $bytes);

        $this->upload($this->job, ['files' => [new UploadedFile($image->getRealPath(), 'same.jpg', 'image/jpeg', null, true)]]);

        $this->assertIsPrivate($this->job->attachments()->with('file')->firstOrFail());
        $this->assertIsPrivate($old->fresh('file'));
        $this->assertSame($bytes, Storage::disk('local')->get($old->fresh('file')->file->path), 'the bytes moved whole');

        // the older attachment shares them: it keeps working, from its new home
        $res = $this->actingAs($this->reporter)->get(route('attachments.show', $old))->assertOk();
        $this->assertSame($bytes, $res->streamedContent());
    }

    public function test_an_attachment_sharing_bytes_that_went_missing_keeps_working_when_they_come_back(): void
    {
        $image = UploadedFile::fake()->image('same.jpg', 25, 25);
        $old = $this->legacyPublicFile($this->newJob(), file_get_contents($image->getRealPath()));
        Storage::disk('public')->delete('maintenance/old/photo.jpg');

        $this->upload($this->job, ['files' => [new UploadedFile($image->getRealPath(), 'same.jpg', 'image/jpeg', null, true)]]);

        $this->assertIsPrivate($old->fresh('file'));
        $this->actingAs($this->reporter)->get(route('attachments.show', $old))->assertOk();
    }

    public function test_the_app_opens_a_file_with_its_bearer_token_and_the_address_it_was_given(): void
    {
        $this->upload($this->job);
        $att = $this->job->attachments()->firstOrFail();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $token = $this->reporter->createToken('app')->plainTextToken;

        $this->withToken($token)->get(route('attachments.show', $att), ['Accept' => 'image/*'])->assertOk();
    }

    public function test_another_persons_token_is_refused(): void
    {
        $this->upload($this->job);
        $att = $this->job->attachments()->firstOrFail();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $token = User::factory()->create(['role' => 'member'])->createToken('app')->plainTextToken;

        $this->withToken($token)->getJson(route('attachments.show', $att))->assertForbidden();
    }

    public function test_a_suspended_account_and_one_waiting_to_change_its_password_are_stopped_with_a_token_too(): void
    {
        $this->upload($this->job);
        $att = $this->job->attachments()->firstOrFail();

        $this->reporter->forceFill(['suspended_at' => now()])->save();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->withToken($this->reporter->createToken('app')->plainTextToken)
            ->getJson(route('attachments.show', $att))
            ->assertForbidden()
            ->assertJsonPath('code', 'account_suspended');

        // a new token: the refusal above ended the first one
        $this->reporter->forceFill(['suspended_at' => null, 'must_change_password' => true])->save();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->withToken($this->reporter->createToken('app')->plainTextToken)
            ->getJson(route('attachments.show', $att))
            ->assertForbidden()
            ->assertJsonPath('code', 'password_change_required');
    }
}
