<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceAssignment extends Model
{
    protected $table = 'maintenance_assignments';

    // status (งานระดับ assignment)
    public const STATUS_ASSIGNED    = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE        = 'done';
    public const STATUS_CANCELLED   = 'cancelled';

    // response_status (การตอบรับงาน)
    public const RESP_PENDING       = 'pending';
    public const RESP_ACCEPTED      = 'accepted';
    public const RESP_REJECTED      = 'rejected';
    public const RESP_ACKNOWLEDGED  = 'acknowledged';

    protected $fillable = [
        'maintenance_request_id',
        'user_id',
        'role',
        'is_lead',
        'assigned_at',

        'response_status',
        'responded_at',
        'remark',

        'status',
    ];

    protected $casts = [
        'assigned_at'     => 'datetime',
        'responded_at'    => 'datetime',
        'is_lead'         => 'boolean',
        'status'          => 'string',
        'response_status' => 'string',
    ];

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_IN_PROGRESS]);
    }

    public function scopeInProgress(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopePendingResponse(Builder $q): Builder
    {
        return $q->where('response_status', self::RESP_PENDING);
    }

    public function scopeAccepted(Builder $q): Builder
    {
        return $q->where('response_status', self::RESP_ACCEPTED);
    }

    public function scopeRejected(Builder $q): Builder
    {
        return $q->where('response_status', self::RESP_REJECTED);
    }

    public function scopeAcknowledged(Builder $q): Builder
    {
        return $q->where('response_status', self::RESP_ACKNOWLEDGED);
    }
}
