<?php

namespace Tests\Feature\Ui;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * "Attach a file" and "take a photo" are two icon-only square buttons (paperclip, camera) wherever a file can be added — the request
 * form already did it; the job page and the asset form used text buttons ("เลือกไฟล์เพิ่ม", "เลือกรูปภาพ", "เลือกไฟล์แนบ") and, on the
 * job page, a third "แนบไฟล์" beside the paperclip. The upload button of the job page belongs to the list of chosen files, so it only
 * shows once there is something to upload.
 */
class AttachButtonsTest extends TestCase
{
    use RefreshDatabase;

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($dom);
    }

    private function element(string $html, string $id): ?DOMElement
    {
        $found = $this->xpath($html)->query('//*[@id="'.$id.'"]');

        return $found->length ? $found->item(0) : null;
    }

    /** the words of a button: its text without the Material Symbols glyph name */
    private function words(DOMElement $button): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(['attach_file', 'photo_camera', 'upload_file'], '', $button->textContent)));
    }

    private function assertIconOnly(string $html, string $id, string $glyph, string $what): void
    {
        $button = $this->element($html, $id);
        $this->assertNotNull($button, "$what: #$id exists");
        $this->assertSame('button', $button->tagName, "$what: #$id is a button");
        $this->assertStringContainsString($glyph, $button->textContent, "$what: #$id shows the $glyph icon");
        $this->assertSame('', $this->words($button), "$what: #$id has no words, only the icon");
        $this->assertNotSame('', $button->getAttribute('aria-label'), "$what: #$id says what it is to a screen reader");
        $this->assertNotSame('', $button->getAttribute('title'), "$what: #$id says what it is on hover");
    }

    public function test_the_pair_is_two_icon_only_square_buttons(): void
    {
        $html = Blade::render('<x-ui.attach-buttons any="a_btn" camera="c_btn" />');

        $this->assertIconOnly($html, 'a_btn', 'attach_file', 'the pair');
        $this->assertIconOnly($html, 'c_btn', 'photo_camera', 'the pair');
        $this->assertSame('แนบไฟล์', $this->element($html, 'a_btn')->getAttribute('aria-label'));
        $this->assertSame('ถ่ายรูป', $this->element($html, 'c_btn')->getAttribute('aria-label'));

        // the standard square button, not a look of its own
        $square = Blade::render('<x-ui.button size="square" icon="attach_file" id="x" />');
        $this->assertSame(
            $this->element($square, 'x')->getAttribute('class'),
            $this->element($html, 'a_btn')->getAttribute('class'),
        );

        $named = Blade::render('<x-ui.attach-buttons any="a" camera="c" any-label="เลือกรูปภาพ" camera-label="ถ่ายรูปจากกล้อง" />');
        $this->assertSame('เลือกรูปภาพ', $this->element($named, 'a')->getAttribute('title'));
        $this->assertSame('ถ่ายรูปจากกล้อง', $this->element($named, 'c')->getAttribute('aria-label'));
    }

    public function test_the_request_form_the_job_page_and_the_asset_form_all_use_icons_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member']);

        $form = $this->actingAs($member)->get(route('maintenance.requests.create'))->assertOk()->getContent();
        $this->assertIconOnly($form, 'mr_files_any_btn', 'attach_file', 'request form');
        $this->assertIconOnly($form, 'mr_files_camera_btn', 'photo_camera', 'request form');

        $req = MaintenanceRequest::factory()->create([
            'asset_id' => Asset::factory()->create()->id, 'reporter_id' => $member->id, 'technician_id' => null, 'status' => 'in_progress',
        ]);
        $page = $this->actingAs($admin)->get(route('maintenance.requests.show', $req))->assertOk()->getContent();
        $this->assertIconOnly($page, 'mr_files_any_btn', 'attach_file', 'job page');
        $this->assertIconOnly($page, 'mr_files_camera_btn', 'photo_camera', 'job page');

        $asset = $this->actingAs($admin)->get(route('assets.create'))->assertOk()->getContent();
        foreach (['hero_image' => 'the picture of the asset', 'att_files' => 'the files of the asset'] as $prefix => $what) {
            $this->assertIconOnly($asset, "{$prefix}_any_btn", 'attach_file', $what);
            $this->assertIconOnly($asset, "{$prefix}_camera_btn", 'photo_camera', $what);
        }
    }

    public function test_the_job_page_offers_the_upload_only_once_files_are_chosen(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $req = MaintenanceRequest::factory()->create([
            'asset_id' => Asset::factory()->create()->id,
            'reporter_id' => User::factory()->create(['role' => 'member'])->id,
            'technician_id' => null,
            'status' => 'in_progress',
        ]);

        $html = $this->actingAs($admin)->get(route('maintenance.requests.show', $req))->assertOk()->getContent();
        $xp = $this->xpath($html);

        $preview = $xp->query('//*[@id="mr_files_preview"]')->item(0);
        $this->assertNotNull($preview);
        $this->assertStringContainsString('hidden', $preview->getAttribute('class'), 'the list of chosen files is hidden until there is one');

        $form = $xp->query('ancestor::form', $preview)->item(0);
        $submits = $xp->query('.//button[@type="submit"]', $form);
        $this->assertSame(1, $submits->length, 'one upload button in the attachments form');
        $this->assertSame(1, $xp->query('.//button[@type="submit"]', $preview)->length, 'and it is inside the list of chosen files');
        $this->assertStringContainsString('แนบไฟล์', $submits->item(0)->textContent);
    }

    public function test_the_asset_form_has_one_remove_button_and_it_is_the_one_on_the_picture(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $html = $this->actingAs($admin)->get(route('assets.create'))->assertOk()->getContent();

        // a hidden "ล้างรูปภาพ" button with the same id sat before the round × on the picture; the script binds the first element with
        // the id, so it was bound to the button nobody could see and the × did nothing
        $this->assertSame(1, substr_count($html, 'id="hero_image_remove_btn"'), 'one element with this id');
        $xp = $this->xpath($html);
        $remove = $xp->query('//*[@id="hero_image_remove_btn"]')->item(0);
        $inside = $xp->query('ancestor::*[@id="hero_image_preview_box"]', $remove);
        $this->assertSame(1, $inside->length, 'the × on the picture');
        $this->assertStringNotContainsString('ล้างรูปภาพ', $html);
    }
}
