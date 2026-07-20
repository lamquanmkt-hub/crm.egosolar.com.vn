<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingLead extends Model
{
    use HasFactory;

    protected $table = 'marketing_leads';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'source',
        'campaign',
        'adset',
        'ad',
        'status',
        'assigned_user_id',
        'imported_by',
        'import_date',
        'note',
    ];

    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_WON = 'won';

    public const STATUS_LOST = 'lost';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => 'Mới',
            self::STATUS_CONTACTED => 'Đã liên hệ',
            self::STATUS_QUALIFIED => 'Tiềm năng',
            self::STATUS_WON => 'Chốt',
            self::STATUS_LOST => 'Hủy',
        ];
    }

    public function assignedUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_user_id');
    }

    public function importedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'imported_by');
    }
}
