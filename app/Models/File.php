<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;

/**
 * Class File
 *
 * เก็บข้อมูลไฟล์จริง (path/disk/mime/size/checksum/meta)
 * เชื่อมกับ Attachment ผ่าน file_id
 *
 * @property int $id
 * @property string $path
 * @property string $disk
 * @property string|null $mime
 * @property int|null $size
 * @property string|null $checksum_sha256
 * @property array|null $meta
 * @property string|null $path_hash
 *
 * @property-read string|null $url
 */
class File extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'path',
        'disk',
        'mime',
        'size',
        'checksum_sha256',
        'meta',
        'variant_of_id',
        'path_hash',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    protected $appends = ['url'];

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function parentVariant()
    {
        return $this->belongsTo(File::class, 'variant_of_id');
    }

    public function variants()
    {
        return $this->hasMany(File::class, 'variant_of_id');
    }

    public function getUrlAttribute(): ?string
    {
        try {
            /** @var FilesystemAdapter $fs */
            $fs = Storage::disk($this->disk);
            return method_exists($fs, 'url') ? $fs->url($this->path) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function isImage(): bool
    {
        return is_string($this->mime) && str_starts_with($this->mime, 'image/');
    }

    public function deleteWithPhysical(): bool
    {
        try {
            if (Storage::disk($this->disk)->exists($this->path)) {
                Storage::disk($this->disk)->delete($this->path);
            }
        } catch (\Throwable $e) {
        }

        return (bool) $this->delete();
    }

    public function scopeWithChecksum($q, ?string $sha)
    {
        return $sha ? $q->where('checksum_sha256', $sha) : $q;
    }

    public function scopeImages($q)
    {
        return $q->where('mime', 'like', 'image/%');
    }

    protected static function booted(): void
    {
        static::creating(function (self $file) {
            if (empty($file->path_hash) && $file->path) {
                $file->path_hash = hash('sha256', $file->path);
            }
        });
    }
}
