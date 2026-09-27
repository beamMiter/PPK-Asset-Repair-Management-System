<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MaintenanceRequestType extends Model
{
    private const SELECT_CACHE_KEY = 'maintenance_request_types';

    protected $fillable = [
        'name',
        'description',
        'default_department_code',
        'default_role_code',
        'default_user_id',
        'is_active',
        'sort_order',
        'default_response_minutes',
        'default_resolution_minutes',
    ];

    public function requests()
    {
        return $this->hasMany(MaintenanceRequest::class, 'type_id');
    }

    public function defaultUser()
    {
        return $this->belongsTo(User::class, 'default_user_id');
    }

    /**
     * Active types (id, name) in display order — the option list of every request form and filter. Cached for an
     * hour, and dropped whenever a type is saved or deleted: it used to be cached without any invalidation, so a
     * type an admin had just added, renamed or disabled kept its old dropdown entry for up to an hour.
     */
    public static function activeForSelect(): Collection
    {
        return Cache::remember(self::SELECT_CACHE_KEY, 3600, fn () => static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']));
    }

    protected static function booted(): void
    {
        $forget = fn () => Cache::forget(self::SELECT_CACHE_KEY);

        static::saved($forget);
        static::deleted($forget);
    }
}
