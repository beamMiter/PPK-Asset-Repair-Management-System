<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Attachment
 *
 * Polymorphic attachments (แนบได้หลายโมดูล) อ้างอิงไฟล์จริงผ่าน files.id
 *
 * @property int $id
 * @property int $file_id
 * @property string $original_name
 * @property string|null $extension
 * @property bool $is_private
 * @property string|null $caption
 * @property string|null $alt_text
 * @property int $order_column
 * @property \Illuminate\Support\Carbon|null $expires_at
 *
 * @property-read \App\Models\File|null $file
 * @property-read string|null $url
 * @property-read string $filename
 * @property-read bool $is_image
 */
class Attachment extends Model
{
    use SoftDeletes;

    const HERO_ORDER = -1;


    protected $fillable = [
        // polymorphic target
        'attachable_type',
        'attachable_id',

        // file link
        'file_id',

        // presentation
        'original_name',
        'extension',
        'caption',
        'alt_text',
        'order_column',

        // privacy & audit
        'is_private',
        'uploaded_by',
        'source',

        // retention
        'expires_at',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'expires_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = ['url', 'filename', 'is_image'];
    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function attachable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeForAttachable($q, Model $model)
    {
        return $q->where('attachable_type', $model->getMorphClass())
                 ->where('attachable_id', $model->getKey());
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('order_column')->orderBy('id');
    }

    public function scopePublic($q)
    {
        return $q->where('is_private', false);
    }

    public function scopePrivate($q)
    {
        return $q->where('is_private', true);
    }

    public function scopeImages($q)
    {
        return $q->whereHas('file', fn($qq) => $qq->where('mime', 'like', 'image/%'));
    }

    public function scopeOfMime($q, string $pattern)
    {
        return $q->whereHas('file', fn($qq) => $qq->where('mime', 'like', $pattern));
    }

    public function getFilenameAttribute(): string
    {
        if ($this->original_name) {
            return $this->original_name;
        }
        $path = optional($this->file)->path;
        return $path ? basename($path) : 'file';
    }

    public function getUrlAttribute(): ?string
    {
        if ($this->is_private) {
            return null;
        }
        return optional($this->file)->url;
    }

    public function getIsImageAttribute(): bool
    {
        return (bool) (optional($this->file)->isImage());
    }

    /**
     * ลบ attachment และออปชัน cleanup ไฟล์จริงถ้าไม่มีใครอ้างแล้ว
     * @param bool $deleteOrphanFile เมื่อ true และไม่มี attachment อื่นชี้ไฟล์นี้ จะลบไฟล์ (เรคอร์ดใน files) ทิ้งด้วย
     */
    public function deleteAndCleanup(bool $deleteOrphanFile = false): bool
    {
        $fileId = $this->file_id;
        $ok = (bool) $this->delete();

        if ($ok && $deleteOrphanFile && $fileId) {
            $stillUsed = static::query()->where('file_id', $fileId)->exists();
            if (!$stillUsed) {
                if ($file = File::find($fileId)) {
                    $file->deleteWithPhysical();
                }
            }
        }
        return $ok;
    }

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            $m->source       = $m->source ?: 'web';
            $m->order_column = $m->order_column ?? 0;
            $m->is_private   = $m->is_private ?? false;

            if (empty($m->extension) && $m->original_name) {
                $m->extension = pathinfo($m->original_name, PATHINFO_EXTENSION) ?: null;
            }
        });
    }
}
