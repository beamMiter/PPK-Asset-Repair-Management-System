<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use App\Support\InitialsAvatar;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // การกำหนดค่า Role พื้นฐาน
    public const ROLE_ADMIN       = 'admin';
    public const ROLE_SUPERVISOR  = 'supervisor';
    public const ROLE_IT_SUPPORT  = 'it_support';
    public const ROLE_NETWORK     = 'network';
    public const ROLE_DEVELOPER   = 'programmer';
    public const ROLE_MEMBER      = 'member';
    public const ROLE_COMPUTER_OFFICER = self::ROLE_MEMBER;
    public const ROLE_TECHNICIAN  = 'technician';

    protected $fillable = [
        'name',
        'citizen_id',
        'email',
        'password',
        'department',
        'role',
        'profile_photo_path',
        'profile_photo_thumb',
        'notification_sound',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'avatar_url',
        'avatar_thumb_url',
        'department_name',
        'role_label',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'suspended_at'      => 'datetime',
            'password'          => 'hashed',
        ];
    }

    /** Suspended = keeps all history, cannot sign in or be assigned new work (see EnsureAccountIsActive). */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function scopeActive($q)
    {
        return $q->whereNull('suspended_at');
    }

    // งานที่ User คนนี้ถูกมอบหมาย (ผ่านตาราง Assignment)
    public function maintenanceAssignments()
    {
        return $this->hasMany(MaintenanceAssignment::class, 'user_id');
    }

    // ดึงงานซ่อมทั้งหมดที่คนนี้ต้องทำผ่าน Pivot
    public function assignedMaintenanceRequests()
    {
        return $this->belongsToMany(MaintenanceRequest::class, 'maintenance_assignments')
                    ->withPivot(['role', 'is_lead', 'assigned_at', 'status'])
                    ->withTimestamps();
    }

    /**
     * URL of the alert sound this user plays for a new request: their pick from the sound library
     * (`public/sounds`), or the default when they never chose, the file has been removed from the library since, or the
     * column holds something that is not a bare file name.
     */
    public function notificationSoundUrl(): string
    {
        $default = 'new-request.mp3';
        $file = basename(trim((string) $this->notification_sound));

        if ($file === '' || $file !== trim((string) $this->notification_sound) || ! is_file(public_path('sounds/'.$file))) {
            $file = $default;
        }

        return asset('sounds/'.rawurlencode($file));
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSupervisor(): bool
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }

    public function isMember(): bool
    {
        return in_array($this->role, [
            self::ROLE_MEMBER,
            self::ROLE_COMPUTER_OFFICER,
        ], true);
    }

    // ตรวจสอบว่าเป็นกลุ่มเจ้าหน้าที่หรือทีมทำงานหรือไม่
    public function isTechnician(): bool
    {
        return in_array($this->role, self::workerRoles(), true);
    }

    public function isWorker(): bool
    {
        return $this->isTechnician();
    }

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles, true);
    }

    public static function availableRoles(): array
    {
        return Role::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('code')
            ->all();
    }

    // นิยามกลุ่มที่เป็นทีมปฏิบัติการ (Workers)
    public static function workerRoles(): array
    {
        return [
            self::ROLE_IT_SUPPORT,
            self::ROLE_NETWORK,
            self::ROLE_DEVELOPER,
            self::ROLE_TECHNICIAN,
        ];
    }

    // กลุ่มทีมบริหารและทีมปฏิบัติการรวมกัน
    public static function teamRoles(): array
    {
        return array_merge([self::ROLE_ADMIN, self::ROLE_SUPERVISOR], self::workerRoles());
    }

    public static function roleLabels(): array
    {
        return Role::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name_th', 'code')
            ->all();
    }

    public function getCleanNameAttribute(): string
    {
        $name = $this->name ?? '';
        
        // 1. Remove common English role-based prefixes (e.g. "IT Support ", "Programmer ")
        $roles = [
            'IT Support', 'Network Engineer', 'Programmer', 'Technician', 
            'Admin', 'Supervisor', 'Member'
        ];
        foreach ($roles as $r) {
            if (str_starts_with($name, $r . ' ')) {
                $name = trim(substr($name, strlen($r) + 1));
            }
        }

        // 2. Remove common Thai prefixes/titles
        $prefixes = [
            'นาย', 'นางสาว', 'นาง', 'น.ส.', 'นส.', 
            'ดร.', 'นพ.', 'พญ.', 'ทันตแพทย์', 'ทพ.', 'ทพญ.',
            'ผศ.', 'รศ.', 'ศ.', 'ว่าที่ร้อยตรี', 'ว่าที่ ร.ต.'
        ];
        
        foreach ($prefixes as $p) {
            if (str_starts_with($name, $p)) {
                // Check if it's followed by a space or another character
                // Some people write "นายสมชาย" (no space) or "นาย สมชาย" (with space)
                $len = mb_strlen($p);
                $after = mb_substr($name, $len);
                if (str_starts_with($after, ' ')) {
                    $name = trim(mb_substr($name, $len + 1));
                } else {
                    $name = trim($after);
                }
            }
        }

        return $name ?: ($this->name ?? 'Unknown');
    }

    public function getRoleLabelAttribute(): string
    {
        return match($this->role) {
            self::ROLE_ADMIN        => 'ผู้ดูแลระบบ (Admin)',
            self::ROLE_SUPERVISOR   => 'หัวหน้างาน (Supervisor)',
            self::ROLE_IT_SUPPORT   => 'IT Support',
            self::ROLE_NETWORK      => 'Network Engineer',
            self::ROLE_DEVELOPER    => 'Programmer',
            self::ROLE_TECHNICIAN   => 'เจ้าหน้าที่ซ่อมบำรุง',
            self::ROLE_MEMBER       => 'บุคลากรทั่วไป',
            default                 => ucfirst($this->role ?? 'Unknown'),
        };
    }

    public function roleRef()
    {
        return $this->belongsTo(Role::class, 'role', 'code');
    }

    public function scopeRole($q, string $role)
    {
        return $q->where('role', $role);
    }

    public function scopeInRoles($q, array $roles)
    {
        return $q->whereIn('role', $roles);
    }

    public function scopeDepartment($q, ?string $code)
    {
        return $code ? $q->where('department', $code) : $q;
    }

    public function scopeHasAvatar($q)
    {
        return $q->whereNotNull('profile_photo_path')
                 ->where('profile_photo_path', '!=', '');
    }

    public function scopeTechnicians($q)
    {
        return $q->whereIn('role', self::workerRoles());
    }

    // รายการใบแจ้งซ่อมที่ User คนนี้เป็นผู้แจ้ง
    public function reportedRequests()
    {
        return $this->hasMany(MaintenanceRequest::class, 'reporter_id');
    }

    // ประวัติการบันทึก Log ของ User
    public function logs()
    {
        return $this->hasMany(MaintenanceLog::class, 'user_id');
    }

    // ข้อมูลแผนก
    public function departmentRef()
    {
        return $this->belongsTo(Department::class, 'department', 'code');
    }

    public function getDepartmentNameAttribute(): ?string
    {
        return $this->departmentRef?->name_th ?? $this->departmentRef?->name_en;
    }

    // การให้คะแนนโดย User คนนี้
    public function givenRatings()
    {
        return $this->hasMany(MaintenanceRating::class, 'rater_id');
    }

    public function getAvatarUrlAttribute(): string
    {
        $path = $this->profile_photo_path;

        if ($path && Storage::disk('public')->exists($path)) {
            return Storage::url($path);
        }

        // Try .webp fallback if the original extension was different
        if ($path) {
            $webpPath = preg_replace('/\.(jpg|jpeg|png|avif|gif)$/i', '.webp', $path);
            if ($webpPath !== $path && Storage::disk('public')->exists($webpPath)) {
                return Storage::url($webpPath);
            }
        }

        return $this->uiAvatarUrl(256);
    }

    public function getAvatarThumbUrlAttribute(): string
    {
        $thumb = $this->profile_photo_thumb;
        $main  = $this->profile_photo_path;

        if ($thumb && Storage::disk('public')->exists($thumb)) {
            return Storage::url($thumb);
        }

        // Try .webp fallback for thumb
        if ($thumb) {
            $webpThumb = preg_replace('/\.(jpg|jpeg|png|avif|gif)$/i', '.webp', $thumb);
            if ($webpThumb !== $thumb && Storage::disk('public')->exists($webpThumb)) {
                return Storage::url($webpThumb);
            }
        }

        if ($main && Storage::disk('public')->exists($main)) {
            return Storage::url($main);
        }

        // Try .webp fallback for main if no thumb
        if ($main) {
            $webpMain = preg_replace('/\.(jpg|jpeg|png|avif|gif)$/i', '.webp', $main);
            if ($webpMain !== $main && Storage::disk('public')->exists($webpMain)) {
                return Storage::url($webpMain);
            }
        }

        return $this->uiAvatarUrl(128);
    }

    // รูปโปรไฟล์จำลองกรณีไม่มีการอัปโหลดรูป: ตัวอักษรย่อของชื่อบนพื้นสี เป็น SVG ในตัว src เอง (ไม่ต้องขอไปที่เว็บอื่น)
    private function uiAvatarUrl(int $size = 256): string
    {
        return InitialsAvatar::url($this->clean_name ?: 'User', $size, InitialsAvatar::colorFor($this->name ?? 'user'));
    }

    // คะแนนที่ User คนนี้ได้รับในฐานะเจ้าหน้าที่
    public function technicianRatings()
    {
        return $this->hasMany(\App\Models\MaintenanceRating::class, 'technician_id');
    }

    public function technicianAssignments()
    {
        return $this->hasMany(MaintenanceAssignment::class, 'user_id');
    }
}
