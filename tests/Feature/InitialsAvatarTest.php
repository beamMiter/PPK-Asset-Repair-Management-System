<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\InitialsAvatar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A user without a photo gets an avatar with their initials. It used to be an <img> of `https://ui-avatars.com/api/?…`: a request
 * to a third party for every avatar on every page the user opens (4 on a normal page, 23 on My Jobs, 34 on the user list) — and a
 * hospital network with no internet waits on each of them. The avatar is now a small SVG carried in the `src` itself
 * (`data:image/svg+xml,…`): no request at all.
 */
class InitialsAvatarTest extends TestCase
{
    use RefreshDatabase;

    private const PREFIX = 'data:image/svg+xml;charset=utf-8,';

    /** the SVG a data URI carries */
    private function svg(string $url): string
    {
        $this->assertStringStartsWith(self::PREFIX, $url, 'the avatar is a data URI, not a link to another site');

        return rawurldecode(substr($url, strlen(self::PREFIX)));
    }

    /** the letters drawn in the avatar */
    private function letters(string $url): string
    {
        $this->assertSame(1, preg_match('#<text[^>]*>(.*?)</text>#su', $this->svg($url), $m), 'one text element');

        return html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    public function test_it_draws_the_first_letter_of_the_first_two_words(): void
    {
        $this->assertSame('อส', $this->letters(InitialsAvatar::url('อรทัย สุขสันต์')));
        $this->assertSame('SJ', $this->letters(InitialsAvatar::url('somchai jaidee')));
        $this->assertSame('SJ', $this->letters(InitialsAvatar::url('  somchai   jaidee  kaew ')), 'two letters at most, spaces do not count');
        $this->assertSame('A', $this->letters(InitialsAvatar::url('Admin')), 'one word: one letter');
    }

    public function test_a_leading_thai_vowel_is_not_an_initial(): void
    {
        // เ แ โ ใ ไ are written before the consonant they follow in sound — the initial is the consonant
        $this->assertSame('กศ', $this->letters(InitialsAvatar::url('เกียรติ ศักดิ์')));
        $this->assertSame('จช', $this->letters(InitialsAvatar::url('ใจดี ไชยา')));
        $this->assertSame('ม', $this->letters(InitialsAvatar::url('แมว')));
    }

    public function test_nothing_to_draw_gives_a_placeholder_not_an_empty_circle(): void
    {
        $this->assertSame('?', $this->letters(InitialsAvatar::url('')));
        $this->assertSame('?', $this->letters(InitialsAvatar::url('   ')));
    }

    public function test_a_name_cannot_break_out_of_the_svg(): void
    {
        $svg = $this->svg(InitialsAvatar::url('<script>alert(1)</script> "><img src=x onerror=1>'));

        $this->assertStringNotContainsString('<script', $svg);
        $this->assertStringNotContainsString('<img', $svg);
        $this->assertSame(1, substr_count($svg, '<text'), 'still one text element');
    }

    public function test_it_is_a_square_of_the_asked_size_with_a_colour_per_name(): void
    {
        $svg = $this->svg(InitialsAvatar::url('อรทัย สุขสันต์', 96));

        $this->assertStringContainsString('width="96"', $svg);
        $this->assertStringContainsString('height="96"', $svg);
        $this->assertStringContainsString('viewBox="0 0 100 100"', $svg);

        // the same name always gets the same colour, and two different names can differ
        $this->assertSame(InitialsAvatar::colorFor('อรทัย'), InitialsAvatar::colorFor('อรทัย'));
        $this->assertContains(InitialsAvatar::colorFor('อรทัย'), InitialsAvatar::PALETTE);
        $this->assertGreaterThan(1, count(array_unique(array_map([InitialsAvatar::class, 'colorFor'], ['a', 'b', 'c', 'd', 'e', 'f']))));
    }

    public function test_a_user_without_a_photo_gets_it_instead_of_a_link(): void
    {
        $user = User::factory()->create(['name' => 'อรทัย สุขสันต์', 'profile_photo_path' => null, 'profile_photo_thumb' => null]);

        foreach (['avatar_url', 'avatar_thumb_url'] as $accessor) {
            $this->assertStringNotContainsString('ui-avatars.com', $user->$accessor, $accessor);
            $this->assertSame('อส', $this->letters($user->$accessor), $accessor);
        }
    }

    public function test_no_page_asks_another_site_for_an_avatar(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Test']);
        User::factory()->count(3)->create(['role' => 'member', 'profile_photo_path' => null]);

        foreach ([
            route('maintenance.requests.index'), route('assets.index'), route('admin.users.index'), route('repair.dashboard'),
            route('repairs.my_jobs'), route('chat.index'), route('profile.show'),
        ] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('ui-avatars.com', $html, $url);
        }
    }

    public function test_the_source_has_no_link_to_the_avatar_service_left(): void
    {
        $offenders = [];
        foreach (['app', 'resources/views', 'resources/js'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile() && str_contains(file_get_contents($file->getPathname()), 'ui-avatars.com')) {
                    $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], $offenders, 'these files still call ui-avatars.com');
    }
}
