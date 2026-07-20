<?php

namespace App\Models\Media;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MediaFile extends Model
{
    protected $table = 'media_files';

    protected $fillable = [
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'alt_text',
        'title',
        'caption',
        'uploaded_by',
    ];

    /**
     * Một file có thể được gán cho nhiều model qua media_relations (polymorphic).
     */
    public function relations(): HasMany
    {
        return $this->hasMany(MediaRelation::class, 'media_id');
    }

    /**
     * Lấy metadata riêng của file.
     */
    public function metadata(): HasOne
    {
        return $this->hasOne(MediaMetadata::class, 'media_id');
    }

    /**
     * Lấy user đã upload file (nếu có).
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // runtime url helper
    public function url(): string
    {
        return \Storage::disk('public')->url($this->file_path);
    }
}
