<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceRating extends Model
{
    use SoftDeletes;

    protected $table = 'maintenance_ratings';

    protected $fillable = [
        'maintenance_request_id',
        'rater_id',
        'technician_id',
        'score',
        'comment',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }


    public function scopeForTechnician(Builder $query, int $technicianId): Builder
    {
        return $query->where('technician_id', $technicianId);
    }

    public function scopeForRequest(Builder $query, int $requestId): Builder
    {
        return $query->where('maintenance_request_id', $requestId);
    }

    public static function hasRated(int $requestId, int $raterId): bool
    {
        return self::query()
            ->where('maintenance_request_id', $requestId)
            ->where('rater_id', $raterId)
            ->exists();
    }
}
