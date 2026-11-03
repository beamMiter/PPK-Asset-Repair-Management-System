<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A file is kept once per content (`files.checksum_sha256` is unique). The upload wrote the bytes first and looked for
 * the row afterwards, so every repeat left an unreferenced copy on the disk; and it did not see a deleted row, so
 * putting back a photo that had just been removed hit the unique index and answered with an error.
 */
class AttachmentReuploadTest extends TestCase
{
    use RefreshDatabase;

    private User $reporter;

    private UploadedFile $bytes; // held here: the temporary file lives only as long as this object

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->reporter = User::factory()->create(['role' => 'member']);
        $this->bytes = UploadedFile::fake()->image('same.jpg', 40, 40);
    }

    private function job(): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create([
            'status' => 'pending', 'reporter_id' => $this->reporter->id, 'technician_id' => null, 'asset_id' => null,
        ]);
    }

    private function upload(MaintenanceRequest $job)
    {
        return $this->actingAs($this->reporter)->post(route('maintenance.requests.attachments', $job), [
            'files' => [new UploadedFile($this->bytes->getRealPath(), 'same.jpg', 'image/jpeg', null, true)],
        ]);
    }

    public function test_a_photo_can_be_put_back_after_it_was_removed(): void
    {
        $job = $this->job();
        $this->upload($job);
        $first = $job->attachments()->firstOrFail();

        $this->actingAs($this->reporter)->delete(route('maintenance.requests.attachments.destroy', [$job, $first]));
        $this->assertSame(0, $job->attachments()->count());
        $this->assertSame([], Storage::disk('public')->allFiles('maintenance'), 'removing the last use erases the bytes');

        $this->upload($job)->assertSessionHas('toast.type', 'success');

        $back = $job->attachments()->with('file')->firstOrFail();
        $this->assertNotNull($back->file, 'the attachment has a live file row');
        Storage::disk('public')->assertExists($back->file->path);
    }

    public function test_the_same_photo_on_two_requests_is_stored_once(): void
    {
        $this->upload($this->job());
        $this->upload($this->job());

        $this->assertCount(1, Storage::disk('public')->allFiles('maintenance'));
    }

    public function test_removing_it_from_one_request_leaves_it_on_the_other(): void
    {
        $one = $this->job();
        $two = $this->job();
        $this->upload($one);
        $this->upload($two);

        $this->actingAs($this->reporter)->delete(route('maintenance.requests.attachments.destroy', [$one, $one->attachments()->firstOrFail()]));

        $left = $two->attachments()->with('file')->firstOrFail();
        Storage::disk('public')->assertExists($left->file->path);
    }

    public function test_bytes_that_went_missing_from_the_disk_are_written_again(): void
    {
        $job = $this->job();
        $this->upload($job);
        Storage::disk('public')->deleteDirectory('maintenance');

        $this->upload($job);

        $att = $job->attachments()->with('file')->firstOrFail();
        Storage::disk('public')->assertExists($att->file->path);
    }
}
