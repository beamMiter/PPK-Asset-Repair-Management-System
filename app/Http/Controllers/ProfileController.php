<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\ImageManager;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->loadMissing('departmentRef');
        return view('profile.show', compact('user'));
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->user()->loadMissing('departmentRef');
        return view('profile.edit', compact('user'));
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        Log::info('User update started', ['user_id' => $user->id]);

        $data = $request->validated();

        $request->validate([
            'avatar'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('remove_avatar') && $request->hasFile('avatar')) {
            Log::warning('User tried to upload and remove avatar at the same time', ['user_id' => $user->id]);
            return back()->with('toast', [
                'type'     => 'warning',
                'message'  => 'เลือกระหว่างอัปโหลดรูปใหม่หรือ "ลบรูปปัจจุบัน" อย่างใดอย่างหนึ่ง',
                'position' => 'tc',
                'timeout'  => 3800,
                'size'     => 'md',
            ]);
        }

        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $avatarChanged = false;
        $avatarRemoved = false;

        $disk        = Storage::disk('public');
        $disk->makeDirectory('avatars');
        $hasThumbCol = Schema::hasColumn('users', 'profile_photo_thumb');

        $driver = null;
        if (extension_loaded('imagick')) {
            $driver = new \Intervention\Image\Drivers\Imagick\Driver();
        } elseif (extension_loaded('gd')) {
            $driver = new \Intervention\Image\Drivers\Gd\Driver();
        }

        $manager = $driver ? new ImageManager($driver) : null;

        $supportsWebp = true;
        if ($driver instanceof \Intervention\Image\Drivers\Gd\Driver) {
            $supportsWebp = function_exists('imagewebp');
        }
        $targetExt = $supportsWebp ? 'webp' : 'jpg';

        // กรณีลบรูปโปรไฟล์
        if ($request->boolean('remove_avatar') === true) {
            Log::info('Removing avatar', ['user_id' => $user->id]);
            $toDelete = array_values(array_filter([
                $user->profile_photo_path ?: null,
                $hasThumbCol ? ($user->profile_photo_thumb ?: null) : null,
            ]));
            if ($toDelete) $disk->delete($toDelete);

            $user->profile_photo_path = null;
            if ($hasThumbCol) $user->profile_photo_thumb = null;
            $avatarRemoved = true;
        }

        // กรณีอัปโหลดรูปใหม่
        if ($request->hasFile('avatar')) {
            Log::info('Uploading new avatar', ['user_id' => $user->id]);
            $toDelete = array_values(array_filter([
                $user->profile_photo_path ?: null,
                $hasThumbCol ? ($user->profile_photo_thumb ?: null) : null,
            ]));
            if ($toDelete) $disk->delete($toDelete);

            $file = $request->file('avatar');

            try {
                if (!$manager) {
                    $basename = $user->id . '-' . time();
                    $ext      = strtolower($file->getClientOriginalExtension() ?: 'jpg');
                    $mainPath = "avatars/{$basename}.{$ext}";
                    $disk->putFileAs('avatars', $file, "{$basename}.{$ext}");
                    $user->profile_photo_path = $mainPath;
                    if ($hasThumbCol) $user->profile_photo_thumb = $mainPath;
                    $avatarChanged = true;
                } else {
                    $basename  = $user->id . '-' . time();
                    $mainPath  = "avatars/{$basename}-512.{$targetExt}";
                    $thumbPath = "avatars/{$basename}-128.{$targetExt}";

                    $main  = $this->readImage($manager, $file->getRealPath())->cover(512, 512)->encodeUsingFileExtension($targetExt, quality: 80);
                    $thumb = $this->readImage($manager, $file->getRealPath())->cover(128, 128)->encodeUsingFileExtension($targetExt, quality: 80);

                    $disk->put($mainPath,  (string) $main);
                    $disk->put($thumbPath, (string) $thumb);

                    $user->profile_photo_path = $mainPath;
                    if ($hasThumbCol) $user->profile_photo_thumb = $thumbPath;
                    $avatarChanged = true;
                }
            } catch (\Throwable $e) {
                Log::error('Avatar process failed: ' . $e->getMessage(), ['user_id' => $user->id]);
                return back()->with('toast', [
                    'type'     => 'error',
                    'message'  => 'ไฟล์รูปไม่ถูกต้องหรืออ่านไม่ได้',
                    'position' => 'tc',
                    'timeout'  => 3600,
                    'size'     => 'md',
                ]);
            }
        }

        $user->save();
        Log::info('User profile updated successfully', ['user_id' => $user->id]);

        $message = 'อัปเดตโปรไฟล์เรียบร้อย';
        if ($avatarChanged)     $message .= ' — อัปเดตรูปโปรไฟล์ใหม่แล้ว';
        elseif ($avatarRemoved) $message .= ' — ลบรูปโปรไฟล์เรียบร้อย';

        return Redirect::route('profile.show')->with('toast', [
            'type'     => 'success',
            'message'  => $message,
            'position' => 'tc',
            'timeout'  => 2800,
            'size'     => 'lg',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($request->user()->role === 'member') {
            return back()->with('toast', [
                'type'     => 'error',
                'message'  => 'สมาชิกทั่วไปไม่ได้รับอนุญาตให้ลบบัญชีด้วยตนเอง',
                'position' => 'tc',
                'timeout'  => 4000,
            ]);
        }

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        Log::warning('User deleting account', ['user_id' => $user->id]);

        $disk = Storage::disk('public');
        $toDelete = array_values(array_filter([
            $user->profile_photo_path ?: null,
            Schema::hasColumn('users', 'profile_photo_thumb') ? ($user->profile_photo_thumb ?: null) : null,
        ]));
        if ($toDelete) $disk->delete($toDelete);

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/')->with('toast', [
            'type'     => 'success',
            'message'  => 'ลบบัญชีเรียบร้อย',
            'position' => 'tc',
            'timeout'  => 3200,
            'size'     => 'lg',
        ]);
    }

    /**
     * อ่านไฟล์รูปภาพด้วย ImageManager และ return ImageInterface
     * เพื่อให้ static analysis tools (intelephense) infer type ได้ถูกต้อง
     *
     * intervention/image v4: ImageManager::read() ถูกแทนที่ด้วย decodePath()
     */
    private function readImage(ImageManager $manager, string $path): ImageInterface
    {
        return $manager->decodePath($path);
    }
}