<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerProfileDocument extends Model
{
    protected $table = 'customer_profile_documents';

    protected $fillable = [
        'customer_profile_id',
        'document_type',
        'title',
        'original_name',
        'file_path',
        'mime_type',
        'size_bytes',
        'note',
        'uploaded_by',
    ];

    public function profile()
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id');
    }
}
