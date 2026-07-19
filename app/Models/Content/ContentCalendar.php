<?php
namespace App\Models\Content;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentCalendar extends Model
{
    protected $fillable = [
        'publish_date',
        'platform',
        'content_type',
        'title',
        'description',
        'campaign_id',
        'created_by',
        'status',
        'full_content',
        'submitted_at',
        'approved_at',
        'approved_by',
        'link',
        'assignee_user_id',
        // phụ trách
        'assignee',
        'assignees',
        // nếu bạn có cột này
        'attachment_path',
    ];
    // để $item->assignees trả về array (vì DB lưu JSON trong TEXT)
    protected $casts = [
        'publish_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
        'assignees'    => 'array',
    ];
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function files(): HasMany
    {
        return $this->hasMany(ContentFile::class);
    }
}
