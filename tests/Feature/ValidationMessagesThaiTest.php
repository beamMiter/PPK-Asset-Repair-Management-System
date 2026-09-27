<?php

namespace Tests\Feature;

use App\Models\ChatThread;
use App\Models\User;
use App\Support\AssetInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

/**
 * The staff who read these are Thai, so a toast says "กรุณากรอกข้อมูล หัวข้อ" — not "The title field is required." Technical terms
 * (SLA, HIS, Serial) may stay in English inside a Thai sentence, but the sentence is Thai.
 *
 * It was not: the app ran with locale `en` (a checkout's .env said so, the framework's stock value), so lang/th/validation.php was never
 * read — and that file lacked the framework's newer keys and the names of most fields anyway.
 */
class ValidationMessagesThaiTest extends TestCase
{
    use RefreshDatabase;

    private const THAI = '/[\x{0E00}-\x{0E7F}]/u';

    /** dotted keys of a translation file: 'size.string', 'password.letters' … */
    private function keysOf(array $lines, string $prefix = ''): array
    {
        $keys = [];
        foreach ($lines as $key => $value) {
            is_array($value) && $value !== []
                ? array_push($keys, ...$this->keysOf($value, $prefix . $key . '.'))
                : $keys[] = $prefix . $key;
        }

        return $keys;
    }

    private function thai(): array
    {
        return require base_path('lang/th/validation.php');
    }

    public function test_the_app_is_thai_whatever_the_env_says(): void
    {
        $this->assertSame('th', config('app.locale'));
        $this->assertSame('th', app()->getLocale());
        $this->assertSame('th', \Carbon\Carbon::getLocale(), 'dates and "2 ชั่วโมงที่แล้ว" follow it');
    }

    /** A Laravel upgrade that adds a rule adds a key: without it here the message would fall back to English. */
    public function test_every_key_of_the_frameworks_own_file_has_a_thai_message(): void
    {
        $framework = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');

        // 'custom' and 'attributes' are the app's own (placeholders in the framework's file)
        $keys = fn (array $lines) => array_filter($this->keysOf($lines), fn ($k) => ! str_starts_with($k, 'custom') && ! str_starts_with($k, 'attributes'));

        $missing = array_values(array_diff($keys($framework), $keys($this->thai())));

        $this->assertSame([], $missing, 'lang/th/validation.php lacks these keys of the framework\'s lang/en/validation.php');
    }

    public function test_every_message_has_thai_in_it(): void
    {
        $messages = $this->thai();
        unset($messages['attributes'], $messages['custom']);

        $english = [];
        array_walk_recursive($messages, function ($text, $key) use (&$english) {
            if (! preg_match(self::THAI, $text)) {
                $english[] = "$key: $text";
            }
        });

        $this->assertSame([], $english);
    }

    /** The fields a form of this system can get wrong: each needs the name it is called by in a Thai message. */
    public function test_every_field_a_form_validates_has_a_thai_name(): void
    {
        $fields = [
            // the person, login, profile
            'name', 'citizen_id', 'email', 'password', 'password_confirmation', 'current_password', 'role', 'department', 'avatar',
            // a request and what is done to it
            'title', 'description', 'asset_id', 'department_id', 'type_id', 'location_text', 'reporter_name', 'reporter_phone',
            'reporter_email', 'request_date', 'user_ids', 'user_ids.*', 'note', 'remark', 'reject_reason', 'cancel_reason',
            'resolution_note', 'cost', 'files', 'files.*', 'captions.*', 'operation_date', 'operation_method', 'property_code',
            'issue_hardware', 'issue_software', 'require_precheck',
            // chat, SLA, settings
            'body', 'default_response_minutes', 'default_resolution_minutes', 'types', 'types.*.default_response_minutes',
            'types.*.default_resolution_minutes', 'is_active', 'sort_order', 'sound_file', 'notification_sound', 'file_name',
            'signature', 'tickets',
            // every field of the asset form
            ...array_keys(AssetInput::rules()),
        ];

        $named = $this->thai()['attributes'];
        $without = array_values(array_filter(array_unique($fields), fn ($f) => ! isset($named[$f])));

        $this->assertSame([], $without, 'no Thai name in lang/th/validation.php → the message says the raw field');
    }

    public function test_the_common_rules_say_it_in_thai_with_the_fields_thai_name(): void
    {
        $check = fn (array $data, array $rules) => Validator::make($data, $rules)->errors()->first();

        $this->assertSame('กรุณากรอกข้อมูล หัวข้อ', $check([], ['title' => 'required']));
        $this->assertSame('หัวข้อ จะต้องมีความยาวไม่เกิน 5 ตัวอักษร', $check(['title' => 'abcdefg'], ['title' => 'string|max:5']));
        $this->assertSame('อีเมล จะต้องเป็นที่อยู่อีเมลที่ถูกต้อง', $check(['email' => 'x'], ['email' => 'email']));
        $this->assertSame('การยืนยัน รหัสผ่าน ไม่ตรงกัน', $check(['password' => 'a', 'password_confirmation' => 'b'], ['password' => 'confirmed']));
        $this->assertSame('ค่าใช้จ่าย จะต้องเป็นตัวเลข', $check(['cost' => 'abc'], ['cost' => 'numeric']));
        $this->assertSame('หัวข้อ จะต้องเป็นข้อความ', $check(['title' => ['x']], ['title' => 'string']));

        $big = UploadedFile::fake()->create('a.mp3', 3000);
        $this->assertSame('ไฟล์เสียง จะต้องมีขนาดไม่เกิน 2048 กิโลไบต์', $check(['sound_file' => $big], ['sound_file' => 'file|max:2048']));
        $this->assertSame('ไฟล์เสียง จะต้องเป็นไฟล์ประเภท: mp3, wav', $check(['sound_file' => UploadedFile::fake()->create('a.txt', 1)], ['sound_file' => 'mimes:mp3,wav']));
    }

    /** `Password::defaults()` (register, reset) has its own five messages, under `password.*` — and `current_password` a sixth. */
    public function test_password_rules_speak_thai(): void
    {
        $first = fn (string $password, Password $rule) => Validator::make(['password' => $password], ['password' => [$rule]])->errors()->first();

        $this->assertSame('รหัสผ่าน จะต้องมีความยาวอย่างน้อย 8 ตัวอักษร', $first('abc', Password::min(8)));
        $this->assertSame('รหัสผ่าน จะต้องมีตัวอักษรอย่างน้อย 1 ตัว', $first('12345678', Password::min(8)->letters()));
        $this->assertSame('รหัสผ่าน จะต้องมีทั้งตัวพิมพ์ใหญ่และตัวพิมพ์เล็กอย่างน้อยอย่างละ 1 ตัว', $first('abcdefgh', Password::min(8)->mixedCase()));
        $this->assertSame('รหัสผ่าน จะต้องมีตัวเลขอย่างน้อย 1 ตัว', $first('abcdefgh', Password::min(8)->numbers()));
        $this->assertSame('รหัสผ่าน จะต้องมีสัญลักษณ์อย่างน้อย 1 ตัว', $first('abcdefgh', Password::min(8)->symbols()));

        $this->actingAs(User::factory()->create());
        $this->assertSame(
            'รหัสผ่านไม่ถูกต้อง',
            Validator::make(['current_password' => 'wrong'], ['current_password' => 'current_password'])->errors()->first(),
        );
    }

    /* ------------------------------------------------------------------ the toasts a person actually gets */

    public function test_a_refused_request_form_toasts_in_thai(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->from('/x')->post(route('maintenance.requests.store'), []);

        $this->assertStringContainsString('กรุณากรอกข้อมูล หัวข้อ', session('toast.message'));
        $this->assertDoesNotMatchRegularExpression('/[A-Za-z_]{4,} field/', session('toast.message'), 'no "The title field…"');
    }

    public function test_the_login_toast_is_thai(): void
    {
        User::factory()->create(['citizen_id' => '1234567890123', 'password' => bcrypt('secret-pass-1')]);

        $this->post(route('login.store'), ['citizen_id' => '1234567890123', 'password' => 'secret-pass-1']);

        $this->assertSame('เข้าสู่ระบบสำเร็จ', session('toast.message'));
    }

    public function test_a_refusal_to_delete_a_thread_is_a_thai_toast_not_a_403_page(): void
    {
        $author = User::factory()->create(['role' => 'admin']);
        $thread = ChatThread::create(['title' => 'x', 'author_id' => $author->id, 'is_locked' => false]);

        $this->actingAs(User::factory()->create(['role' => 'member']))->from('/x')->delete(route('chat.destroy', $thread))
            ->assertRedirect('/x');

        $this->assertSame('เฉพาะเจ้าของกระทู้และผู้ดูแลระบบเท่านั้นที่ลบกระทู้ได้', session('toast.message'));
        $this->assertSame('error', session('toast.type'));
        $this->assertDatabaseHas('chat_threads', ['id' => $thread->id]);
    }
}
