<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_IN_REPAIR = 'in_repair';
    public const STATUS_DISPOSED = 'disposed';

    private const REQ_FK = 'request_id';

    protected $fillable = [
        'asset_code',
        'his_asset_id',     // ← Key หลักสำหรับ HIS — ใช้ใน Maintenance & Timelog ห้ามลบ
        'name',
        'type',
        'brand',
        'model',
        'serial_number',
        'location',
        'internal_phone',
        'purchase_date',
        'warranty_start',
        'warranty_expire',
        'vendor_name',
        'vendor_phone',
        'price',
        'status',
        'department_id',
        'category_id',
        'his_synced_at',
        'his_raw',
    ];

    protected $casts = [
        'purchase_date'   => 'date',
        'warranty_start'  => 'date',
        'warranty_expire' => 'date',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
        'his_synced_at'   => 'datetime',
        'his_raw'         => 'array',
        'price'           => 'decimal:2', // ป้องกัน floating point error
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected $appends = [
        'display_name',
        'status_label',
        'status_color',
        'formatted_price',
        'hero_image_url',
    ];

    public static function statusLabels(): array
    {
        return [
            self::STATUS_ACTIVE => 'ใช้งานปกติ',
            self::STATUS_IN_REPAIR => 'กำลังซ่อม',
            self::STATUS_DISPOSED => 'จำหน่ายแล้ว',
        ];
    }

    public static function statusColors(): array
    {
        return [
            self::STATUS_ACTIVE => 'text-emerald-700',
            self::STATUS_IN_REPAIR => 'text-amber-700',
            self::STATUS_DISPOSED => 'text-rose-700',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[(string) $this->status] ?? (string) $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::statusColors()[(string) $this->status] ?? 'bg-slate-50 text-slate-700 ring-slate-600/20';
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInRepair(): bool
    {
        return $this->status === self::STATUS_IN_REPAIR;
    }

    public function isDisposed(): bool
    {
        return $this->status === self::STATUS_DISPOSED;
    }

    public function isSyncedFromHis(): bool
    {
        return !empty($this->his_asset_id);
    }

    /**
     * คืนค่า รหัสหลักที่ใช้แสดงผล โดยให้ความสำคัญกับ รพจ (HIS ID) เป็นอันดับแรก
     */
    public function getPrimaryCodeAttribute(): string
    {
        return $this->his_asset_id ?: $this->asset_code;
    }

    /**
     * ตรวจสอบว่ามีรหัสทั้ง 2 ตัวและมันเหมือนกันหรือไม่
     */
    public function getHasDuplicateCodesAttribute(): bool
    {
        if (empty($this->his_asset_id) || empty($this->asset_code)) {
            return false;
        }
        return $this->his_asset_id === $this->asset_code;
    }

    public function scopeHisLinked($query)
    {
        return $query->whereNotNull('his_asset_id')->where('his_asset_id', '!=', '');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function categoryRef()
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function maintenanceRequests()
    {
        return $this->hasMany(MaintenanceRequest::class, 'asset_id');
    }

    public function requestAttachments()
    {
        return $this->hasManyThrough(
            Attachment::class,
            MaintenanceRequest::class,
            'asset_id',
            'attachable_id',
            'id',
            'id'
        )->where('attachments.attachable_type', (new MaintenanceRequest())->getMorphClass());
    }

    public function requestLogs()
    {
        return $this->hasManyThrough(
            MaintenanceLog::class,
            MaintenanceRequest::class,
            'asset_id',
            self::REQ_FK,
            'id',
            'id'
        );
    }

    public function scopeSearch($q, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') return $q;

        return $q->where(function ($w) use ($term) {
            $w->where('asset_code', 'like', "%{$term}%")
              ->orWhere('his_asset_id', 'like', "%{$term}%")
              ->orWhere('name', 'like', "%{$term}%")
              ->orWhere('serial_number', 'like', "%{$term}%");
              
            if (ctype_digit($term)) {
                $w->orWhere('id', (int) $term);
            }
        });
    }

    public function scopeDepartmentId($q, ?int $departmentId)
    {
        return ($departmentId && $departmentId > 0) ? $q->where('department_id', $departmentId) : $q;
    }

    public function scopeStatus($q, ?string $status)
    {
        $status = trim((string) $status);
        return $status !== '' ? $q->where('status', $status) : $q;
    }

    // ✅ แก้ไข: เดิมว่างเปล่า ตอนนี้ filter category_id ได้แล้ว
    public function scopeCategory($q, mixed $categoryId)
    {
        if (empty($categoryId) || (int) $categoryId <= 0) return $q;
        return $q->where('category_id', (int) $categoryId);
    }

    public function scopeLocation($q, ?string $location)
    {
        $location = trim((string) $location);
        return $location !== '' ? $q->where('location', 'like', "%{$location}%") : $q;
    }

    public function scopeType($q, ?string $type)
    {
        $type = trim((string) $type);
        return $type !== '' ? $q->where('type', 'like', "%{$type}%") : $q;
    }

    public function scopeSortBySafe($q, ?string $by, string $dir = 'desc')
    {
        $map = [
            'id'              => 'id',
            'asset_code'      => 'asset_code',
            'name'            => 'name',
            'status'          => 'status',
            'purchase_date'   => 'purchase_date',
            'warranty_expire' => 'warranty_expire',
            'created_at'      => 'created_at',
        ];
        $col = $map[$by ?? 'id'] ?? 'id';
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';
        return $q->orderBy($col, $dir);
    }

    public function getDisplayNameAttribute(): string
    {
        $code = trim((string) $this->asset_code);
        $name = trim((string) $this->name);
        if ($code && $name) return $code.' - '.$name;
        return $code ?: $name;
    }

    /**
     * คืนค่าราคาในรูปแบบไทยบาท เช่น "฿ 45,000.00"
     * Currency hardcoded เป็น THB ใน display layer
     */
    public function getFormattedPriceAttribute(): ?string
    {
        if ($this->price === null) return null;
        return '฿ ' . number_format((float) $this->price, 2);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('order_column');
    }

    public function getHeroImageUrlAttribute(): ?string
    {
        // Prefer the eager-loaded collection when a caller has already loaded
        // `attachments` (e.g. the assets list endpoint) so this append does not
        // fire an N+1 query per row. Falls back to a scoped query otherwise.
        // The relation is defined with orderBy('order_column'), so the hero
        // (order_column = HERO_ORDER) still sorts first either way.
        $hero = $this->relationLoaded('attachments')
            ? $this->attachments->first(fn($a) => optional($a->file)->isImage())
            : $this->attachments()
                ->whereHas('file', fn($q) => $q->where('mime', 'like', 'image/%'))
                ->first();

        return $hero ? $hero->url : null;
    }
}
