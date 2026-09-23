<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\Toast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificationSettingController extends Controller
{
    /** The largest sound file, in KB (Laravel's `max:` for a file): the upload rule, the message, and the file input's data-max-kb on the page. */
    public const SOUND_MAX_KB = 2048;

    private const SOUND_EXTS   = ['mp3', 'wav', 'ogg'];
    private const LOCKED_SOUND = 'new-request.mp3';

    public function __construct()
    {
        $this->middleware('auth');

        // Admin only (same gate as the route — a deny-list of one role let every other role in)
        $this->middleware('can:manage-system');
    }

    /**
     * Filenames present in public/sounds (basename only).
     */
    private function availableSounds(): array
    {
        $dir = public_path('sounds');
        if (! File::exists($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->filter(fn ($f) => in_array(strtolower($f->getExtension()), self::SOUND_EXTS, true))
            ->map(fn ($f) => $f->getFilename())
            ->values()
            ->all();
    }

    public function index()
    {
        $sounds       = $this->availableSounds();
        $currentSound = Auth::user()->notification_sound;

        return view('settings.notifications.index', compact('sounds', 'currentSound'));
    }

    public function updateSound(Request $request)
    {
        $request->validate([
            'notification_sound' => ['required', 'string'],
        ]);

        // Must be one of the files actually in the library — no arbitrary paths.
        $choice = basename((string) $request->notification_sound);
        if (! in_array($choice, $this->availableSounds(), true)) {
            return back()->with('toast', Toast::error('ไม่พบไฟล์เสียงที่เลือก', 2200));
        }

        try {
            $user = Auth::user();
            $user->notification_sound = $choice;
            $user->save();

            Log::info('[NotificationSetting::updateSound] sound updated', [
                'sound'    => $choice,
                'actor_id' => Auth::id(),
            ]);

            return back()->with('toast', Toast::success('บันทึกการตั้งค่าเสียงเรียบร้อย', 1800));
        } catch (\Throwable $e) {
            Log::error('[NotificationSetting::updateSound] failed', ['error' => $e->getMessage()]);

            return back()->with('toast', Toast::error('ไม่สามารถบันทึกการตั้งค่าได้', 2500));
        }
    }

    public function uploadSound(Request $request)
    {
        $request->validate([
            'sound_file' => ['required', 'file', 'max:' . self::SOUND_MAX_KB, 'mimes:mp3,wav', 'mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/wave'],
        ], [
            'sound_file.required'  => 'กรุณาเลือกไฟล์เสียง',
            'sound_file.max'       => 'ไฟล์ต้องมีขนาดไม่เกิน ' . intdiv(self::SOUND_MAX_KB, 1024) . 'MB',
            'sound_file.mimes'     => 'รองรับเฉพาะไฟล์ .mp3 และ .wav',
            'sound_file.mimetypes' => 'รองรับเฉพาะไฟล์เสียง .mp3 และ .wav',
        ]);

        $file = $request->file('sound_file');
        $ext  = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'mp3');
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'sound';
        $name = $base . '-' . now()->format('YmdHis') . '.' . $ext;

        try {
            $file->move(public_path('sounds'), $name);

            Log::info('[NotificationSetting::uploadSound] uploaded', [
                'file'     => $name,
                'actor_id' => Auth::id(),
            ]);

            return back()->with('toast', Toast::success("เพิ่มไฟล์ {$name} เข้าคลังเสียงแล้ว", 1800));
        } catch (\Throwable $e) {
            Log::error('[NotificationSetting::uploadSound] failed', ['error' => $e->getMessage()]);

            return back()->with('toast', Toast::error('อัปโหลดไฟล์ไม่สำเร็จ', 2500));
        }
    }

    public function destroySound(Request $request)
    {
        $request->validate(['file_name' => ['required', 'string']]);

        // Strip any directory component — 'file_name' is fully user-controlled
        // and used to build a filesystem path.
        $fileName = basename((string) $request->file_name);
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (! in_array($ext, self::SOUND_EXTS, true)) {
            return back()->with('toast', Toast::error('ชนิดไฟล์ไม่ถูกต้อง', 2000));
        }

        if ($fileName === self::LOCKED_SOUND) {
            return back()->with('toast', Toast::warning('ไม่สามารถลบไฟล์มาตรฐานได้', 2200));
        }

        $dir      = public_path('sounds');
        $filePath = $dir . DIRECTORY_SEPARATOR . $fileName;
        $real     = realpath($filePath);

        if ($real === false || ! str_starts_with($real, realpath($dir) . DIRECTORY_SEPARATOR)) {
            return back()->with('toast', Toast::error('ไม่พบไฟล์ที่ต้องการลบ', 2000));
        }

        try {
            File::delete($real);

            Log::warning('[NotificationSetting::destroySound] file deleted', [
                'file'     => $fileName,
                'actor_id' => Auth::id(),
            ]);

            return back()->with('toast', Toast::success("ลบไฟล์ {$fileName} เรียบร้อย", 1600));
        } catch (\Throwable $e) {
            Log::error('[NotificationSetting::destroySound] failed', [
                'file'  => $fileName,
                'error' => $e->getMessage(),
            ]);

            return back()->with('toast', Toast::error('ไม่สามารถลบไฟล์ได้', 2500));
        }
    }
}
