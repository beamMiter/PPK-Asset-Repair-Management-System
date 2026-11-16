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


    /** The ratings a person has: those of the requests they are (still) on the team of. */
    public function scopeForTechnician(Builder $query, int $technicianId): Builder
    {
        return $query->whereIn('maintenance_request_id', MaintenanceAssignment::query()
            ->select('maintenance_request_id')
            ->where('user_id', $technicianId)
            ->where('status', '!=', MaintenanceAssignment::STATUS_CANCELLED));
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
