<?php
namespace App\Models\Content;
use Illuminate\Database\Eloquent\Model;
class ContentFile extends Model
{
    protected $fillable = [
        'content_calendar_id',
        'file_path',
        'file_name',
        'file_type',
        'uploaded_by',
    ];
}
