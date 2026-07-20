<?php

namespace App\Models\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaMetadata extends Model
{
    protected $table = 'media_metadata';

    protected $fillable = [
        'media_id', 'width', 'height', 'duration', 'bitrate', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Metadata thuộc về một MediaFile.
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'media_id');
    }
}
