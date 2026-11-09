<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The app calls its staff "เจ้าหน้าที่"; the manual still said "ช่าง" in seven places. The owner asked for one word. */
class ManualCallsTheStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manual_says_เจ้าหน้าที่_not_ช่าง(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'member']))->get(route('help.manual'))->assertOk()->getContent();

        $this->assertStringNotContainsString('ช่าง', $html);
        $this->assertStringContainsString('เจ้าหน้าที่กำลังปฏิบัติงาน ณ พื้นที่', $html);
    }
}
