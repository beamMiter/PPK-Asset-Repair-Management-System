<?php

namespace Tests\Feature\Ui;

use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The job page built the delete address with a parameter name the route does not have. The `route()` call threw, a
 * try/catch turned that into "no address", and so the delete button never appeared for anybody.
 */
class AttachmentDeleteButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_whoever_may_delete_a_file_gets_the_button_on_the_job_page(): void
    {
        Storage::fake('public');
        $reporter = User::factory()->create(['role' => 'member']);
        $job = MaintenanceRequest::factory()->create([
            'status' => 'pending', 'reporter_id' => $reporter->id, 'technician_id' => null, 'asset_id' => null,
        ]);
        $this->actingAs($reporter)->post(route('maintenance.requests.attachments', $job), ['files' => [UploadedFile::fake()->image('a.jpg', 10, 10)]]);
        $att = $job->attachments()->firstOrFail();

        $html = $this->actingAs($reporter)->get(route('maintenance.requests.show', $job))->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('maintenance.requests.attachments.destroy', [$job, $att]).'"', $html);
    }
}
