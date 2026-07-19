<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyDocumentFolder extends Model
{
    protected $fillable = ['department', 'parent_id', 'name', 'created_by'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function files()
    {
        return $this->hasMany(CompanyDocumentFile::class, 'folder_id')->latest();
    }
}
