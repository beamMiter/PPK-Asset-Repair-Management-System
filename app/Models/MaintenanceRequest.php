<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    /** Still being worked on — the asset is not free while one of these exists. */
    public const OPEN_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACKNOWLEDGED,
        self::STATUS_ACCEPTED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_ON_HOLD,
    ];

    /** Somebody has taken the job and it is not over yet: it must never be left with nobody on it. */
    public const TEAM_REQUIRED_STATUSES = [
        self::STATUS_ACCEPTED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_ON_HOLD,
        self::STATUS_RESOLVED,
    ];

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

    /* ================= REQUEST NO ================= */

    public static function generateLegacyRequestNo(): string
    {
        $thaiYear = now()->year + 543;
        $yy = substr((string) $thaiYear, -2);

        $type = '10'; // legacy fixed type

        // ใช้ MAX(request_no) แทน count() เพื่อป้องกัน race condition
        // withTrashed(): a deleted request keeps its number in the table (unique key), so it must count — otherwise the
        // next request is given the same number and fails. The prefix (Thai year + type) also starts a new run each year.
        $lastNo = static::withTrashed()
            ->where('request_no', 'like', $yy . $type . '%')
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
            $model->applyTypeTargets();
        });

        // The type chosen (or changed) after the request was made: the deadlines follow it. A request made without a type
        // used to keep no deadline for good, and one moved to another type kept the old type's. A job that is over is history.
        static::updating(function (self $model) {
            if ($model->isDirty('type_id') && in_array((string) $model->status, self::OPEN_STATUSES, true)) {
                $model->applyTypeTargets();
            }
        });

        static::created(function (self $model) {
            $model->syncAssetStatus();
        });

        static::updated(function (self $model) {
            if ($model->isDirty(['status', 'asset_id'])) {
                $model->syncAssetStatus();

                // moved to another asset: the one it left may have nothing open any more
                if ($model->isDirty('asset_id') && $model->getOriginal('asset_id')) {
                    static::releaseAssetIfIdle((int) $model->getOriginal('asset_id'));
                }
            }
        });

        // A deleted request no longer keeps its asset busy (syncAssetStatus would not free it: it reads the request's own,
        // unchanged, status) — and a restored one does again.
        static::deleted(function (self $model) {
            if ($model->asset_id) {
                static::releaseAssetIfIdle((int) $model->asset_id);
            }
        });

        static::restored(function (self $model) {
            $model->syncAssetStatus();
        });
    }

    /**
     * Give an asset back ("in repair" → "active") when no open request is left on it. Requests in the trash do not count.
     */
    public static function releaseAssetIfIdle(int $assetId): void
    {
        $asset = Asset::find($assetId);
        if (! $asset || $asset->status !== 'in_repair') {
            return;
        }

        $busy = static::query()->where('asset_id', $assetId)->whereIn('status', self::OPEN_STATUSES)->exists();
        if ($busy) {
            return;
        }

        $asset->update(['status' => 'active']);
        \Illuminate\Support\Facades\Log::info('[MaintenanceRequest::releaseAssetIfIdle] Asset restored to active', ['asset_id' => $assetId]);
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

    /**
     * Requests this user may list: staff (admin / supervisor / technician roles) see everything, anybody else only
     * what they reported. No user, no rows.
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin() || $user->isSupervisor() || $user->isTechnician()) {
            return $query;
        }

        return $query->where('maintenance_requests.reporter_id', $user->id);
    }

    /**
     * List ordering. A blank `request_no` always sorts last, whatever the direction; every other column falls back to
     * `id` as tie-breaker; an unknown column becomes `id`.
     */
    public function scopeOrderedForList($query, string $sortBy, string $dir)
    {
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'request_no') {
            return $query
                ->orderByRaw("CASE WHEN maintenance_requests.request_no IS NULL OR maintenance_requests.request_no = '' THEN 1 ELSE 0 END ASC")
                ->orderBy('maintenance_requests.request_no', $dir)
                ->orderBy('maintenance_requests.id', $dir);
        }

        if (! in_array($sortBy, ['id', 'request_date', 'status', 'updated_at', 'created_at', 'title'], true)) {
            $sortBy = 'id';
        }

        $query->orderBy('maintenance_requests.'.$sortBy, $dir);

        return $sortBy === 'id' ? $query : $query->orderBy('maintenance_requests.id', $dir);
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

    public function needsTeam(): bool
    {
        return in_array((string) $this->status, self::TEAM_REQUIRED_STATUSES, true);
    }

    /**
     * SLA deadlines from the type: resolution = request date + the type's minutes + the time already spent on hold; response
     * = request date + the type's minutes (until the job is acknowledged — after that it is history). A type without minutes,
     * or none, leaves what is there.
     */
    public function applyTypeTargets(): void
    {
        $type = $this->type_id ? MaintenanceRequestType::find($this->type_id) : null;
        if (! $type) {
            return;
        }

        $base = $this->request_date ?? now();

        if ($type->default_response_minutes && ! $this->acknowledged_at) {
            $this->response_due_date = $base->copy()->addMinutes($type->default_response_minutes);
        }
        if ($type->default_resolution_minutes) {
            $this->sla_due_date = $base->copy()->addMinutes($type->default_resolution_minutes + (int) $this->paused_duration_minutes);
        }
    }

    /**
     * The resolution deadline as of $now. A job on hold has its clock stopped, so its deadline keeps moving out with the
     * time it has been on hold (that time is added to `sla_due_date` for good when the job resumes) — it is late only if it
     * was already late when it was put on hold.
     */
    public function slaDeadline(?\Carbon\Carbon $now = null): ?\Carbon\Carbon
    {
        if (! $this->sla_due_date) {
            return null;
        }

        $due = $this->sla_due_date->copy();

        if ((string) $this->status === self::STATUS_ON_HOLD && $this->on_hold_at) {
            $now ??= now();
            $due->addSeconds(max(0, $now->getTimestamp() - $this->on_hold_at->getTimestamp()));
        }

        return $due;
    }

    /**
     * How far past its SLA deadline the job is at $now, as the SLA page words it: "+2 วัน 3 ชม." or, under a day, "+5 ชม. 12 น."
     * (a job on hold has its clock stopped: see slaDeadline()). "-" for a job with no deadline.
     */
    public function overdueLabel(?\Carbon\Carbon $now = null): string
    {
        $now ??= now();
        $deadline = $this->slaDeadline($now);

        if (! $deadline) {
            return '-';
        }

        $minutes = (int) $deadline->diffInMinutes($now);
        $days = intdiv($minutes, 60 * 24);
        $hours = intdiv($minutes % (60 * 24), 60);

        return $days > 0
            ? "+{$days} วัน {$hours} ชม."
            : "+{$hours} ชม. " . ($minutes % 60) . ' น.';
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
