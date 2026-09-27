<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceLog extends Model
{
    protected $table = 'maintenance_logs';
    protected $fillable = [
        'request_id',
        'user_id',
        'action',
        'note',
        'from_status',
        'to_status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const ACTION_CREATE     = 'create_request';
    public const ACTION_UPDATE     = 'update_request';
    public const ACTION_ASSIGN     = 'assign_technician';
    public const ACTION_START      = 'start_request';
    public const ACTION_COMPLETE   = 'complete_request';
    public const ACTION_CANCEL     = 'cancel_request';
    public const ACTION_TRANSITION = 'transition';
    public const ACTION_NOTE       = 'note';

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForRequest($query, int $requestId)
    {
        return $query->where('request_id', $requestId);
    }

    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeTransitions($query)
    {
        return $query->where('action', self::ACTION_TRANSITION);
    }
}
