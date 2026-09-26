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
 * GET /attachments/{id} used to serve any private file to any signed-in user — change the id, read someone else's
 * photos and documents. A private file is now as visible as what it is attached to.
 */
class PrivateAttachmentAccessTest extends TestCase
{
    use RefreshDatabase;

    private function attach(object $target, array $extra = []): Attachment
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/secret.txt', 'secret contents');

        $file = File::create(['path' => 'private/secret.txt', 'disk' => 'local', 'mime' => 'text/plain', 'size' => 15]);

        return Attachment::create($extra + [
            'attachable_type' => $target->getMorphClass(),
            'attachable_id' => $target->getKey(),
            'file_id' => $file->id,
            'original_name' => 'secret.txt',
            'extension' => 'txt',
            'is_private' => true,
        ]);
    }

    public function test_the_reporter_and_an_admin_can_read_a_requests_private_file(): void
    {
        $reporter = User::factory()->create(['role' => 'member']);
        $req = MaintenanceRequest::factory()->create(['reporter_id' => $reporter->id]);
        $att = $this->attach($req);

        $this->actingAs($reporter)->get(route('attachments.show', $att))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('attachments.show', $att))->assertOk();
    }

    public function test_another_member_cannot_read_it_by_guessing_the_id(): void
    {
        $req = MaintenanceRequest::factory()->create(['reporter_id' => User::factory()->create(['role' => 'member'])->id]);
        $att = $this->attach($req);
        $stranger = User::factory()->create(['role' => 'member']);

        $res = $this->actingAs($stranger)->get(route('attachments.show', $att));

        $res->assertRedirect(); // AuthorizationException → back / dashboard with an error toast
        $res->assertSessionHas('toast.type', 'error');
        $this->assertStringNotContainsString('secret contents', (string) $res->getContent());

        $this->actingAs($stranger)->getJson(route('attachments.show', $att))->assertForbidden();
    }

    public function test_an_asset_file_follows_the_asset_policy_and_an_orphan_only_its_uploader_or_admin(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $asset = Asset::factory()->create();
        $this->actingAs($member)->get(route('attachments.show', $this->attach($asset)))->assertOk(); // assets are visible to everyone

        $uploader = User::factory()->create(['role' => 'member']);
        $orphan = $this->attach(MaintenanceRequest::factory()->create(), ['attachable_id' => 999999, 'uploaded_by' => $uploader->id]);

        $this->actingAs($uploader)->get(route('attachments.show', $orphan))->assertOk();
        $this->actingAs($member)->getJson(route('attachments.show', $orphan))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('attachments.show', $orphan))->assertOk();
    }

    public function test_an_expired_attachment_is_not_served(): void
    {
        $reporter = User::factory()->create(['role' => 'member']);
        $req = MaintenanceRequest::factory()->create(['reporter_id' => $reporter->id]);
        $att = $this->attach($req, ['expires_at' => now()->subDay()]);

        $this->actingAs($reporter)->getJson(route('attachments.show', $att))->assertStatus(410);

        $att->update(['expires_at' => now()->addDay()]);
        $this->actingAs($reporter)->get(route('attachments.show', $att))->assertOk();
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $req = MaintenanceRequest::factory()->create();

        $this->get(route('attachments.show', $this->attach($req)))->assertRedirect(route('login'));
    }
}
