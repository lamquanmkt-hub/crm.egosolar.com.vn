<?php

namespace App\Models\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MediaRelation extends Model
{
    protected $table = 'media_relations'; // tên bảng

    protected $fillable = [
        'media_id', 'model_type', 'model_id', 'usage_type', 'sort_order', 'attached_by',
    ];

    /**
     * Trỏ tới file media.
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'media_id');
    }

    /**
     * Polymorphic: Model sở hữu file (Product, Post, User…)
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
