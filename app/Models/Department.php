<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\Like;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_th',
        'name_en',
    ];

    // ===== Relations =====

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    public function maintenanceRequests()
    {
        return $this->hasManyThrough(
            MaintenanceRequest::class, // ปลายทาง
            Asset::class,              // กลาง
            'department_id',           // FK บน assets → departments.id
            'asset_id',                // FK บน maintenance_requests → assets.id
            'id',                      // PK departments
            'id'                       // PK assets
        );
    }

    // ===== Accessors =====

    // ให้เรียก $department->name ได้ และจะเลือกชื่อไทยก่อน ถ้าไม่มีค่อยใช้อังกฤษ
    public function getNameAttribute(): string
    {
        return $this->name_th ?: ($this->name_en ?: '');
    }

    public function getDisplayNameAttribute(): string
    {
        return trim((string) $this->name_th) ?: trim((string) $this->name_en);
    }

    protected $appends = [
        'display_name',
    ];

    // ===== Scopes =====

    public function scopeCode($q, ?string $code)
    {
        return $code ? $q->where('code', $code) : $q;
    }

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;

        return $q->where(function ($qq) use ($term) {
            $qq->where('code', 'like', Like::contains($term))
               ->orWhere('name_th', 'like', Like::contains($term))
               ->orWhere('name_en', 'like', Like::contains($term));
        });
    }
}
