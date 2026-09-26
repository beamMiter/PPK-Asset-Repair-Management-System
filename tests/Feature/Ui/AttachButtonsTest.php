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
 * "Attach a file" and "take a photo" are two bare icons (a paperclip, a camera) wherever a file can be added: no box, no border, no
 * background — just the icon, with a soft circle on hover (the system's `ghost` variant at `icon-lg`, the look of the icons in the
 * chat header). They are still real <button>s underneath, so the keyboard and screen readers work, and each says what it is on hover
 * (title) and to a screen reader (aria-label). The request form did it as two square boxed buttons; the job page and the asset form
 * used text buttons ("เลือกไฟล์เพิ่ม", "เลือกรูปภาพ", "เลือกไฟล์แนบ") and, on the job page, a third "แนบไฟล์" beside the paperclip. The
 * upload button of the job page belongs to the list of chosen files, so it only shows once there is something to upload.
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

    public function test_the_pair_is_two_bare_icons(): void
    {
        $html = Blade::render('<x-ui.attach-buttons any="a_btn" camera="c_btn" />');

        $this->assertIconOnly($html, 'a_btn', 'attach_file', 'the pair');
        $this->assertIconOnly($html, 'c_btn', 'photo_camera', 'the pair');
        $this->assertSame('แนบไฟล์', $this->element($html, 'a_btn')->getAttribute('aria-label'));
        $this->assertSame('ถ่ายรูป', $this->element($html, 'c_btn')->getAttribute('aria-label'));

        // the system's bare-icon look (ghost, icon-lg — the icons of the chat header), not a look of its own
        $ghost = Blade::render('<x-ui.button variant="ghost" size="icon-lg" icon="attach_file" id="x" />');
        foreach (['a_btn' => 'the paperclip', 'c_btn' => 'the camera'] as $id => $what) {
            $class = $this->element($html, $id)->getAttribute('class');
            $this->assertSame($this->element($ghost, 'x')->getAttribute('class'), $class, "$what: the bare-icon look");

            // ...which means no box: no border, no background at rest (only on hover)
            $this->assertDoesNotMatchRegularExpression('/(?<![-\w:])border(?![-\w])/', $class, "$what: no border");
            $this->assertDoesNotMatchRegularExpression('/(?<![-\w:])bg-/', $class, "$what: no background at rest");
            $this->assertStringContainsString('hover:bg-slate-100', $class, "$what: a soft circle on hover");
        }

        $named = Blade::render('<x-ui.attach-buttons any="a" camera="c" any-label="เลือกรูปภาพ" camera-label="ถ่ายรูปจากกล้อง" />');
        $this->assertSame('เลือกรูปภาพ', $this->element($named, 'a')->getAttribute('title'));
        $this->assertSame('ถ่ายรูปจากกล้อง', $this->element($named, 'c')->getAttribute('aria-label'));
    }

    public function test_the_request_form_the_job_page_and_the_asset_form_all_use_bare_icons(): void
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

    /**
     * The paperclip and the camera are the right-hand end of the heading of their section — the same corner on every page, the corner
     * where the assign-team icon sits on the job page — not in the body, not in a form row beside a hint.
     */
    private function assertTopRight(string $html, array $ids, string $title, string $where): void
    {
        $xp = $this->xpath($html);

        foreach ($ids as $id) {
            $it = '//*[@id="'.$id.'"]';
            $this->assertSame(1, $xp->query($it)->length, "$where: one #$id");

            // the heading is the first block of the section, and the section's title is in it
            $header = $xp->query($it.'/ancestor::section[1]/div[1]')->item(0);
            $this->assertNotNull($header, "$where: #$id sits in a section");
            $this->assertStringContainsString($title, $header->textContent, "$where: #$id is in the heading of \"$title\"");
            $this->assertStringContainsString('justify-between', $header->getAttribute('class'), "$where: the heading is a left / right row");

            // ...in its right-hand block (the one after the title block)
            $this->assertSame(2, $xp->query('div', $header)->length, "$where: the heading has a title block and an icon block");
            $this->assertSame(1, $xp->query('div[last()]//*[@id="'.$id.'"]', $header)->length, "$where: #$id is in the right-hand block");
            $this->assertSame(0, $xp->query('div[1]//*[@id="'.$id.'"]', $header)->length, "$where: #$id is not with the title");
        }
    }

    public function test_the_icons_sit_top_right_of_their_section_on_every_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reporter = User::factory()->create(['role' => 'member']);
        $req = MaintenanceRequest::factory()->create([
            'asset_id' => Asset::factory()->create()->id, 'reporter_id' => $reporter->id, 'technician_id' => null, 'status' => 'pending',
        ]);
        $asset = Asset::factory()->create();
        $pair = ['mr_files_any_btn', 'mr_files_camera_btn'];

        $create = $this->actingAs($reporter)->get(route('maintenance.requests.create'))->assertOk()->getContent();
        $this->assertTopRight($create, $pair, 'ไฟล์แนบ', 'request form');

        $edit = $this->actingAs($admin)->get(route('maintenance.requests.edit', $req))->assertOk()->getContent();
        $this->assertTopRight($edit, $pair, 'ไฟล์แนบ', 'request edit page');

        $job = $this->actingAs($admin)->get(route('maintenance.requests.show', $req))->assertOk()->getContent();
        $this->assertTopRight($job, $pair, 'ไฟล์แนบ', 'job page');

        foreach ([
            'asset form (create)' => route('assets.create'),
            'asset form (edit)' => route('assets.edit', $asset),
        ] as $where => $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertTopRight($html, ['hero_image_any_btn', 'hero_image_camera_btn'], 'ภาพประกอบครุภัณฑ์', "$where, picture");
            $this->assertTopRight($html, ['att_files_any_btn', 'att_files_camera_btn'], 'ไฟล์แนบ', "$where, files");
        }
    }

    public function test_the_file_inputs_the_icons_open_are_still_in_the_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $html = $this->actingAs($admin)->get(route('assets.create'))->assertOk()->getContent();
        $xp = $this->xpath($html);

        // moved into the heading with the icons: still inside the <form>, so what is chosen is still submitted, and still hidden
        foreach (['hero_image_any', 'hero_image_camera', 'att_files_submit', 'att_files_any', 'att_files_camera'] as $id) {
            $input = $xp->query('//input[@id="'.$id.'"]')->item(0);
            $this->assertNotNull($input, "#$id exists");
            $this->assertSame(1, $xp->query('ancestor::form', $input)->length, "#$id is inside the form");
            $this->assertStringContainsString('hidden', $input->getAttribute('class'), "#$id stays hidden");
        }

        $this->assertStringNotContainsString('เลือกรูปภาพครุภัณฑ์', $html, 'no orphan label left where the icons used to be');
        $this->assertStringNotContainsString('เลือกไฟล์เอกสารเพิ่มเติม', $html, 'no orphan label left where the icons used to be');
    }

    public function test_on_the_job_page_only_someone_who_may_attach_gets_the_icons(): void
    {
        $reporter = User::factory()->create(['role' => 'member']);
        $req = MaintenanceRequest::factory()->create([
            'asset_id' => Asset::factory()->create()->id, 'reporter_id' => $reporter->id, 'technician_id' => null, 'status' => 'closed',
        ]);

        $closed = $this->actingAs($reporter)->get(route('maintenance.requests.show', $req))->assertOk()->getContent();
        $this->assertNull($this->element($closed, 'mr_files_any_btn'), 'no paperclip when attaching is not allowed');
        $this->assertNull($this->element($closed, 'mr_files_camera_btn'), 'no camera when attaching is not allowed');
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
