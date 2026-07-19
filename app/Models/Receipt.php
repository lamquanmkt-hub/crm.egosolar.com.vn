<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $table = 'receipts';

    protected $fillable = [
        'account_id',
        'code',
        'receipt_date',
        'payer_name',
        'payer_phone',
        'category',
        'payment_method',
        'amount',
        'note',
        'created_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public const CATEGORY_SALES = 'thu_khach_hang';
    public const CATEGORY_OTHER = 'thu_khac';

    public const METHOD_CASH = 'cash';
    public const METHOD_BANK = 'bank';
    public const METHOD_TRANSFER = 'transfer';

    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_SALES => 'Thu khách hàng',
            self::CATEGORY_OTHER => 'Thu khác',
        ];
    }

    public static function paymentMethodOptions(): array
    {
        return [
            self::METHOD_CASH => 'Tiền mặt',
            self::METHOD_BANK => 'Ngân hàng',
            self::METHOD_TRANSFER => 'Chuyển khoản',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}