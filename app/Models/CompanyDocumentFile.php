<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyDocumentFile extends Model
{
    protected $fillable = [
        'department', 'folder_id', 'name', 'original_name', 'path', 'mime', 'size', 'uploaded_by'
    ];

    public function folder()
    {
        return $this->belongsTo(CompanyDocumentFolder::class, 'folder_id');
    }
}
