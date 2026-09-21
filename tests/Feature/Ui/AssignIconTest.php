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
 * "Assign the team" is a bare icon (group_add) in the top right of the "เจ้าหน้าที่รับผิดชอบ" section, on the job page and on the
 * edit page — the same look and the same corner as the paperclip and camera of the files section (the `ghost` variant at `icon-lg`:
 * no box, no border, no background, a soft circle on hover). It used to be a green text button, "มอบหมายทีมเจ้าหน้าที่", which broke
 * onto its own line under the title on a phone. It is still a real <button> (keyboard, screen reader) with the same id the page's
 * script opens the dialog from, and it says what it is on hover (title) and to a screen reader (aria-label).
 */
class AssignIconTest extends TestCase
{
    use RefreshDatabase;

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($dom);
    }

    private function icon(string $html): ?DOMElement
    {
        $found = $this->xpath($html)->query('//*[@id="openAssignModalBtn"]');

        return $found->length ? $found->item(0) : null;
    }

    private function jobFor(User $reporter, string $status = 'in_progress'): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create([
            'asset_id' => Asset::factory()->create()->id, 'reporter_id' => $reporter->id, 'technician_id' => null, 'status' => $status,
        ]);
    }

    private function assertBareIcon(string $html, string $where): void
    {
        $icon = $this->icon($html);
        $this->assertNotNull($icon, "$where: the assign icon is there");
        $this->assertSame('button', $icon->tagName, "$where: a button");
        $this->assertSame('group_add', trim($icon->textContent), "$where: only the icon, no words");
        $this->assertSame('มอบหมายทีมเจ้าหน้าที่', $icon->getAttribute('aria-label'), "$where: says what it is to a screen reader");
        $this->assertSame('มอบหมายทีมเจ้าหน้าที่', $icon->getAttribute('title'), "$where: says what it is on hover");

        // the system's bare-icon look (ghost, icon-lg), plus the one layout class that lines its centre up with the number circle
        $expected = $this->xpath(Blade::render('<x-ui.button variant="ghost" size="icon-lg" icon="group_add" id="x" class="-mt-1" />'))
            ->query('//*[@id="x"]')->item(0)->getAttribute('class');
        $class = $icon->getAttribute('class');
        $this->assertSame($expected, $class, "$where: the bare-icon look");
        $this->assertDoesNotMatchRegularExpression('/(?<![-\w:])border(?![-\w])/', $class, "$where: no border");
        $this->assertDoesNotMatchRegularExpression('/(?<![-\w:])bg-/', $class, "$where: no background at rest");
        $this->assertStringContainsString('hover:bg-slate-100', $class, "$where: a soft circle on hover");

        // top right of the section: the last block of the header that holds the title
        $xp = $this->xpath($html);
        $header = $xp->query('//*[@id="openAssignModalBtn"]/ancestor::section[1]/div[1]')->item(0);
        $this->assertStringContainsString('เจ้าหน้าที่รับผิดชอบ', $header->textContent, "$where: in the header of the team section");
        $this->assertSame(1, $xp->query('//*[@id="openAssignModalBtn"]/parent::div[contains(@class,"justify-between")]')->length, "$where: the right-hand end of the header");
        $this->assertSame(
            'openAssignModalBtn',
            $xp->query('//*[@id="openAssignModalBtn"]/parent::div/*[last()]')->item(0)->getAttribute('id'),
            "$where: after the title"
        );
    }

    public function test_the_job_page_shows_a_bare_assign_icon(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $req = $this->jobFor(User::factory()->create(['role' => 'member']));

        $html = $this->actingAs($admin)->get(route('maintenance.requests.show', $req))->assertOk()->getContent();

        $this->assertBareIcon($html, 'job page');
        $this->assertStringNotContainsString('มอบหมายทีมเจ้าหน้าที่</button>', $html, 'no text button');
    }

    public function test_the_edit_page_shows_a_bare_assign_icon(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $req = $this->jobFor(User::factory()->create(['role' => 'member']));

        $html = $this->actingAs($admin)->get(route('maintenance.requests.edit', $req))->assertOk()->getContent();

        $this->assertBareIcon($html, 'edit page');
    }

    public function test_it_is_still_only_for_those_who_may_assign(): void
    {
        $reporter = User::factory()->create(['role' => 'member']);
        $req = $this->jobFor($reporter);

        // a member may not assign; a finished job's team is a record nobody may change — not even an admin
        $this->assertNull($this->icon($this->actingAs($reporter)->get(route('maintenance.requests.show', $req))->assertOk()->getContent()));

        $closed = $this->jobFor($reporter, 'closed');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertNull($this->icon($this->actingAs($admin)->get(route('maintenance.requests.show', $closed))->assertOk()->getContent()));
    }
}
