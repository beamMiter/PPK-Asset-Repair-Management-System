<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "At most 3 files per request" only looked at the files already there or only at the files being sent, never at both:
 * 2 on the job and 3 more made 5, on the job page and on the edit form alike.
 */
class AttachmentLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $reporter;

    private MaintenanceRequest $job;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->reporter = User::factory()->create(['role' => 'member']);
        $this->job = MaintenanceRequest::factory()->create([
            'status' => 'pending', 'reporter_id' => $this->reporter->id, 'technician_id' => null, 'asset_id' => null,
        ]);
    }

    /** @return UploadedFile[] each with its own bytes */
    private function images(int $n): array
    {
        static $size = 10; // a different size is different bytes: the same bytes are one file on the request

        $files = [];
        for ($i = 0; $i < $n; $i++) {
            $size++;
            $files[] = UploadedFile::fake()->image('photo.jpg', $size, $size);
        }

        return $files;
    }

    private function upload(int $n)
    {
        return $this->actingAs($this->reporter)->post(route('maintenance.requests.attachments', $this->job), ['files' => $this->images($n)]);
    }

    public function test_the_job_page_refuses_files_that_would_go_over_the_limit(): void
    {
        $this->upload(2);

        $this->upload(3)->assertSessionHas('toast', fn ($t) => $t['type'] === 'warning' && str_contains($t['message'], 'แนบเพิ่มได้อีก 1 ไฟล์'));

        $this->assertSame(2, $this->job->attachments()->count());
    }

    public function test_the_job_page_takes_files_that_still_fit(): void
    {
        $this->upload(2);
        $this->upload(1);

        $this->assertSame(3, $this->job->attachments()->count());
    }

    public function test_the_job_page_says_so_when_the_request_is_already_full(): void
    {
        $this->upload(3);

        $this->upload(1)->assertSessionHas('toast', fn ($t) => $t['type'] === 'warning' && str_contains($t['message'], 'สูงสุด 3 ไฟล์ต่อใบงาน'));

        $this->assertSame(3, $this->job->attachments()->count());
    }

    public function test_the_edit_form_refuses_files_that_would_go_over_the_limit(): void
    {
        $this->upload(2);

        $this->actingAs($this->reporter)
            ->put(route('maintenance.requests.update', $this->job), ['title' => 'แก้หัวข้อ', 'files' => $this->images(2)])
            ->assertSessionHas('toast', fn ($t) => $t['type'] === 'warning' && str_contains($t['message'], 'แนบเพิ่มได้อีก 1 ไฟล์'));

        $this->assertSame(2, $this->job->attachments()->count());
        $this->assertNotSame('แก้หัวข้อ', $this->job->fresh()->title, 'nothing of a refused edit is saved');
    }

    public function test_the_edit_form_counts_the_files_it_removes_in_the_same_save(): void
    {
        $this->upload(3);
        $first = $this->job->attachments()->first();

        $this->actingAs($this->reporter)->put(route('maintenance.requests.update', $this->job), [
            'title' => 'แก้หัวข้อ',
            'remove_attachments' => [$first->id],
            'files' => $this->images(1),
        ]);

        $this->assertSame(3, $this->job->attachments()->count());
        $this->assertSame('แก้หัวข้อ', $this->job->fresh()->title);
    }

    public function test_a_request_already_over_the_limit_can_still_be_edited(): void
    {
        // the old check let requests reach 5 files: they must not become impossible to edit
        foreach (range(1, 4) as $i) {
            $file = File::create(['path' => "maintenance/old/$i.jpg", 'disk' => 'public', 'mime' => 'image/jpeg', 'size' => 1]);
            $this->job->attachments()->create(['file_id' => $file->id, 'original_name' => "$i.jpg"]);
        }

        $this->actingAs($this->reporter)->put(route('maintenance.requests.update', $this->job), ['title' => 'แก้หัวข้อ']);

        $this->assertSame('แก้หัวข้อ', $this->job->fresh()->title);
    }

    public function test_the_api_answers_422_when_an_update_would_go_over_the_limit(): void
    {
        $this->upload(3);
        Sanctum::actingAs($this->reporter);

        $this->putJson('/api/repair-requests/'.$this->job->id, ['files' => $this->images(1)])->assertStatus(422);

        $this->assertSame(3, $this->job->attachments()->count());
    }
}
