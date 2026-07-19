<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'account_id',
        'code',
        'payment_date',
        'payee_name',
        'payee_phone',
        'category',
        'payment_method',
        'amount',
        'note',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public const CATEGORY_SUPPLIER = 'chi_nha_cung_cap';
    public const CATEGORY_SALARY = 'chi_luong';
    public const CATEGORY_OPERATING = 'chi_van_hanh';
    public const CATEGORY_OTHER = 'chi_khac';

    public const METHOD_CASH = 'cash';
    public const METHOD_BANK = 'bank';
    public const METHOD_TRANSFER = 'transfer';

    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_SUPPLIER => 'Chi nhà cung cấp',
            self::CATEGORY_SALARY => 'Chi lương',
            self::CATEGORY_OPERATING => 'Chi vận hành',
            self::CATEGORY_OTHER => 'Chi khác',
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