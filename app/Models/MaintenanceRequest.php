<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class MaintenanceRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'maintenance_requests';

    protected $fillable = [
        // ===== อ้างอิง / พื้นฐาน =====
        'request_no',
        'asset_id',
        'department_id',
        'type_id',
        'reporter_id',
        'title',
        'description',
        'status',

        'status_updated_at',
        'status_updated_by',

        'technician_id',

        // ===== ผู้แจ้ง =====
        'reporter_name',
        'reporter_phone',
        'reporter_email',
        'reporter_position',

        'legacy_payload',

        // ===== สถานที่ =====
        'location_text',

        // ===== timeline =====
        'request_date',
        'assigned_date',
        'completed_date', // legacy
        'acknowledged_at',
        'accepted_at',
        'started_at',
        'on_hold_at',
        'resolved_at',
        'closed_at',
        
        'response_due_date',
        'sla_due_date',
        'paused_duration_minutes',

        // ===== อื่น ๆ =====
        'remark',
        'resolution_note',
        'cost',
        'source',
        'extra',
    ];

    protected $casts = [
        'request_date'        => 'datetime',
        'assigned_date'       => 'datetime',
        'completed_date'      => 'datetime',
        'acknowledged_at'     => 'datetime',
        'accepted_at'         => 'datetime',
        'started_at'          => 'datetime',
        'on_hold_at'          => 'datetime',
        'resolved_at'         => 'datetime',
        'closed_at'           => 'datetime',
        'response_due_date'   => 'datetime',
        'sla_due_date'        => 'datetime',
        'paused_duration_minutes' => 'integer',

        'status_updated_at'   => 'datetime',

        'cost'                => 'decimal:2',

        // หมายเหตุ: ใน seeder อาจเป็น json_encode string ได้ แต่ cast array จะ decode ให้
        'legacy_payload'      => 'array',
        'extra'               => 'array',

        'deleted_at'          => 'datetime',
    ];

    /* ================= STATUS ================= */

    public const STATUS_PENDING      = 'pending';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_ACCEPTED     = 'accepted';
    public const STATUS_IN_PROGRESS  = 'in_progress';
    public const STATUS_ON_HOLD      = 'on_hold';
    public const STATUS_RESOLVED     = 'resolved';
    public const STATUS_CLOSED       = 'closed';
    public const STATUS_CANCELLED    = 'cancelled';
    public const STATUS_REJECTED     = 'rejected';

    // legacy
    public const STATUS_COMPLETED    = 'completed';

    /**
     * Transition map: สถานะปัจจุบัน => สถานะที่อนุญาตให้เปลี่ยนไปได้
     */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_PENDING      => [self::STATUS_ACKNOWLEDGED, self::STATUS_CANCELLED, self::STATUS_REJECTED],
        self::STATUS_ACKNOWLEDGED => [self::STATUS_ACCEPTED, self::STATUS_CANCELLED, self::STATUS_REJECTED],
        self::STATUS_ACCEPTED     => [self::STATUS_IN_PROGRESS, self::STATUS_ON_HOLD, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS  => [self::STATUS_RESOLVED, self::STATUS_CANCELLED, self::STATUS_ON_HOLD],
        self::STATUS_ON_HOLD      => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
        self::STATUS_RESOLVED     => [self::STATUS_CLOSED],
        self::STATUS_CLOSED       => [],
        self::STATUS_CANCELLED    => [],
        self::STATUS_REJECTED     => [],
    ];

    public static function operationLabels(): array
    {
        return [
            'requisition' => 'เบิกอะไหล่',
            'service_fee' => 'ค่าจ้างซ่อม/บริการ',
            'other'       => 'อื่น ๆ (ระบุในหมายเหตุ)',
        ];
    }

    public static function operationMethodLabels(): array
    {
        return self::operationLabels();
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING      => 'รอดำเนินการ',
            self::STATUS_ACKNOWLEDGED => 'รับทราบแล้ว',
            self::STATUS_ACCEPTED     => 'รับเรื่องแล้ว',
            self::STATUS_IN_PROGRESS  => 'กำลังดำเนินการ',
            self::STATUS_ON_HOLD      => 'หยุดการซ่อมบำรุงชั่วคราว',
            self::STATUS_RESOLVED     => 'ซ่อมบำรุงเสร็จสิ้น',
            self::STATUS_CLOSED       => 'อนุมัติผลการซ่อมบำรุง',
            self::STATUS_CANCELLED    => 'ยกเลิกการซ่อมบำรุง',
            self::STATUS_REJECTED     => 'ไม่รับเรื่อง',
            self::STATUS_COMPLETED    => 'เสร็จสิ้น (legacy)',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? (string) $this->status;
    }

    /* ================= RELATION ================= */

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function statusUpdatedBy()
    {
        return $this->belongsTo(User::class, 'status_updated_by');
    }

    // ตารางนี้บังคับ 1 ใบงาน -> 1 รายงานปฏิบัติงาน
    public function operationLog()
    {
        return $this->hasOne(MaintenanceOperationLog::class, 'maintenance_request_id');
    }

    public function assignments()
    {
        return $this->hasMany(MaintenanceAssignment::class, 'maintenance_request_id');
    }

    public function workers()
    {
        return $this->belongsToMany(User::class, 'maintenance_assignments')
            ->withPivot(['role', 'is_lead', 'assigned_at', 'status'])
            ->withTimestamps();
    }

    public function logs()
    {
        return $this->hasMany(MaintenanceLog::class, 'request_id');
    }

    public function attachments()
    {
        return $this->morphMany(\App\Models\Attachment::class, 'attachable')->ordered();
    }

    public function imageAttachments()
    {
        return $this->attachments()
            ->whereHas('file', fn ($q) => $q->where('mime', 'like', 'image/%'));
    }

    public function latestAttachment()
    {
        return $this->morphOne(\App\Models\Attachment::class, 'attachable')->latestOfMany('id');
    }

    public function rating()
    {
        return $this->hasOne(MaintenanceRating::class, 'maintenance_request_id');
    }

    public function ratingBy(int $userId)
    {
        return $this->hasOne(MaintenanceRating::class, 'maintenance_request_id')
            ->where('rater_id', $userId);
    }

    /* ================= ACCESSOR ================= */

    public function getNormalizedStatusAttribute(): string
    {
        if ($this->status === self::STATUS_COMPLETED && $this->resolved_at) {
            return self::STATUS_RESOLVED;
        }
        return (string) $this->status;
    }

    /* ================= REQUEST NO ================= */

    public static function generateLegacyRequestNo(): string
    {
        $thaiYear = now()->year + 543;
        $yy = substr((string) $thaiYear, -2);

        $type = '10'; // legacy fixed type

        // ใช้ MAX(request_no) แทน count() เพื่อป้องกัน race condition
        $lastNo = static::query()
            ->whereYear('created_at', now()->year)
            ->lockForUpdate()
            ->max('request_no');

        if ($lastNo && strlen($lastNo) >= 7) {
            $lastRun = (int) substr($lastNo, -5);
            $nextRun = $lastRun + 1;
        } else {
            $nextRun = 1;
        }

        $run = str_pad((string) $nextRun, 5, '0', STR_PAD_LEFT);

        return $yy . $type . $run;
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->request_no)) {
                $model->request_no = static::generateLegacyRequestNo();
            }
            if (empty($model->source)) {
                $model->source = 'web';
            }

            if (empty($model->status_updated_at)) {
                $model->status_updated_at = now();
            }

            // --- Auto-calculate SLA Targets from Type ---
            if ($model->type_id) {
                $type = \App\Models\MaintenanceRequestType::find($model->type_id);
                if ($type) {
                    $baseDate = $model->request_date ?? now();
                    if ($type->default_response_minutes) {
                        $model->response_due_date = $baseDate->copy()->addMinutes($type->default_response_minutes);
                    }
                    if ($type->default_resolution_minutes) {
                        $model->sla_due_date = $baseDate->copy()->addMinutes($type->default_resolution_minutes);
                    }
                }
            }
        });

        static::created(function (self $model) {
            $model->syncAssetStatus();
        });

        static::updated(function (self $model) {
            if ($model->isDirty(['status', 'asset_id'])) {
                $model->syncAssetStatus();
            }
        });

        static::deleted(function (self $model) {
            $model->syncAssetStatus();
        });
    }

    public function scopeStatus($q, ?string $s)
    {
        return $s ? $q->where('status', $s) : $q;
    }

    public function scopeRequestedBetween($q, ?string $from, ?string $to)
    {
        if ($from) $q->where('request_date', '>=', $from);
        if ($to)   $q->where('request_date', '<=', $to);
        return $q;
    }

    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') return $query;

        return $query->where(function ($q) use ($term) {
            // 1. Basic Fields
            $q->where('maintenance_requests.request_no', 'like', "%{$term}%")
              ->orWhere('maintenance_requests.title', 'like', "%{$term}%")
              ->orWhere('maintenance_requests.description', 'like', "%{$term}%")
              ->orWhere('maintenance_requests.reporter_name', 'like', "%{$term}%")
              ->orWhere('maintenance_requests.reporter_phone', 'like', "%{$term}%")
              ->orWhere('maintenance_requests.reporter_email', 'like', "%{$term}%")
              ->orWhere('maintenance_requests.location_text', 'like', "%{$term}%");

            // 2. ID (Numeric)
            if (ctype_digit($term) || (str_starts_with($term, '#') && ctype_digit(substr($term, 1)))) {
                $numericTerm = ltrim($term, '#');
                $q->orWhere('maintenance_requests.id', (int) $numericTerm);
            }

            // 3. Asset Relations
            $q->orWhereHas('asset', fn ($qa) =>
                $qa->where('assets.name', 'like', "%{$term}%")
                   ->orWhere('assets.asset_code', 'like', "%{$term}%")
                   ->orWhere('assets.his_asset_id', 'like', "%{$term}%")
                   ->orWhere('assets.serial_number', 'like', "%{$term}%")
            );

            // 4. Reporter User Relation
            $q->orWhereHas('reporter', fn ($qr) =>
                $qr->where('users.name', 'like', "%{$term}%")
                   ->orWhere('users.email', 'like', "%{$term}%")
            );

            // 5. Department Relation
            $q->orWhereHas('department', fn ($qd) =>
                $qd->where('departments.name_th', 'like', "%{$term}%")
                   ->orWhere('departments.name_en', 'like', "%{$term}%")
                   ->orWhere('departments.code', 'like', "%{$term}%")
            );

            // 6. Technician Relation
            $q->orWhereHas('technician', fn ($qt) =>
                $qt->where('users.name', 'like', "%{$term}%")
            );
        });
    }

    /* ================= STATE HELPERS ================= */

    public function hasStatus(string $status): bool
    {
        return (string) $this->status === $status;
    }

    public function type()
    {
        return $this->belongsTo(\App\Models\MaintenanceRequestType::class, 'type_id');
    }

    /**
     * ซิงค์สถานะของ Asset ตามสถานะของใบแจ้งซ่อม
     */
    public function syncAssetStatus(): void
    {
        if (empty($this->asset_id)) {
            return;
        }

        $asset = $this->asset;
        if (!$asset) {
            return;
        }

        // สถานะที่ถือว่า "กำลังซ่อม/รอซ่อม" (ทำให้ Asset เป็น in_repair)
        $activeStatuses = [
            self::STATUS_PENDING,
            self::STATUS_ACKNOWLEDGED,
            self::STATUS_ACCEPTED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_ON_HOLD,
        ];

        // สถานะที่ถือว่า "จบงาน" (ทำให้ Asset กลับเป็น active ถ้าไม่มีใบงานอื่นค้าง)
        $resolvedStatuses = [
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
            self::STATUS_CANCELLED,
            self::STATUS_REJECTED,
        ];

        if (in_array((string) $this->status, $activeStatuses, true)) {
            // ถ้าใบงานนี้กำลังดำเนินการอยู่ ให้ Asset เป็น in_repair
            if ($asset->status !== 'in_repair' && $asset->status !== 'disposed') {
                $asset->update(['status' => 'in_repair']);
                \Illuminate\Support\Facades\Log::info('[MaintenanceRequest::syncAssetStatus] Asset set to in_repair', [
                    'asset_id' => $asset->id,
                    'request_id' => $this->id,
                    'status' => $this->status,
                ]);
            }
        } elseif (in_array((string) $this->status, $resolvedStatuses, true)) {
            // ถ้าใบงานนี้จบแล้ว ให้เช็คว่ามีใบงานอื่นที่ยังค้างอยู่ไหมสำหรับ Asset นี้
            $hasOtherActive = static::query()
                ->where('asset_id', $this->asset_id)
                ->where('id', '!=', $this->id)
                ->whereIn('status', $activeStatuses)
                ->exists();

            if (!$hasOtherActive) {
                if ($asset->status === 'in_repair') {
                    $asset->update(['status' => 'active']);
                    \Illuminate\Support\Facades\Log::info('[MaintenanceRequest::syncAssetStatus] Asset restored to active', [
                        'asset_id' => $asset->id,
                        'request_id' => $this->id,
                        'status' => $this->status,
                    ]);
                }
            } else {
                \Illuminate\Support\Facades\Log::info('[MaintenanceRequest::syncAssetStatus] Asset kept in_repair (other MR still active)', [
                    'asset_id' => $asset->id,
                    'request_id' => $this->id,
                ]);
            }
        }
    }

}
