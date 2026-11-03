<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\File;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * GET /attachments/{id} redirected to a public file before it looked at who was asking: a member refused a job's page
 * still got the address of its photos by counting up the id. Every file is now as visible as what it is attached to.
 */
class PublicAttachmentAccessTest extends TestCase
{
    use RefreshDatabase;

    private function publicFileOn(object $target, array $extra = []): Attachment
    {
        Storage::fake('public');
        Storage::disk('public')->put('maintenance/1/photo.jpg', 'photo bytes');

        $file = File::create(['path' => 'maintenance/1/photo.jpg', 'disk' => 'public', 'mime' => 'image/jpeg', 'size' => 11]);

        return Attachment::create($extra + [
            'attachable_type' => $target->getMorphClass(),
            'attachable_id' => $target->getKey(),
            'file_id' => $file->id,
            'original_name' => 'photo.jpg',
            'extension' => 'jpg',
            'is_private' => false,
        ]);
    }

    public function test_another_member_is_not_given_the_address_of_a_requests_file(): void
    {
        $job = MaintenanceRequest::factory()->create(['reporter_id' => User::factory()->create(['role' => 'member'])->id]);
        $att = $this->publicFileOn($job);
        $stranger = User::factory()->create(['role' => 'member']);

        $page = $this->actingAs($stranger)->get(route('attachments.show', $att));
        $page->assertRedirect();
        $page->assertSessionHas('toast.type', 'error');
        $this->assertStringNotContainsString('/storage/', (string) $page->headers->get('Location'));

        $this->actingAs($stranger)->getJson(route('attachments.show', $att))->assertForbidden();
    }

    public function test_whoever_may_see_the_request_still_gets_its_file(): void
    {
        $reporter = User::factory()->create(['role' => 'member']);
        $job = MaintenanceRequest::factory()->create(['reporter_id' => $reporter->id]);
        $att = $this->publicFileOn($job);

        $this->actingAs($reporter)->get(route('attachments.show', $att))->assertRedirect($att->url);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('attachments.show', $att))->assertRedirect($att->url);
    }

    public function test_an_assets_file_stays_open_to_everyone_signed_in(): void
    {
        $att = $this->publicFileOn(Asset::factory()->create());

        $this->actingAs(User::factory()->create(['role' => 'member']))
            ->get(route('attachments.show', $att))
            ->assertRedirect($att->url);
    }

    public function test_a_file_whose_target_is_gone_is_only_for_its_uploader_or_an_admin(): void
    {
        $uploader = User::factory()->create(['role' => 'member']);
        $orphan = $this->publicFileOn(MaintenanceRequest::factory()->create(), ['attachable_id' => 999999, 'uploaded_by' => $uploader->id]);

        $this->actingAs(User::factory()->create(['role' => 'member']))->getJson(route('attachments.show', $orphan))->assertForbidden();
        $this->actingAs($uploader)->get(route('attachments.show', $orphan))->assertRedirect($orphan->url);
    }

    public function test_an_expired_public_file_is_no_longer_handed_out(): void
    {
        $reporter = User::factory()->create(['role' => 'member']);
        $job = MaintenanceRequest::factory()->create(['reporter_id' => $reporter->id]);
        $att = $this->publicFileOn($job, ['expires_at' => now()->subDay()]);

        $this->actingAs($reporter)->getJson(route('attachments.show', $att))->assertStatus(410);
    }
}
