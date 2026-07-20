<?php

declare(strict_types=1);

namespace App\Models\CRM\Orders;

use App\Enums\OrderDepartment;
use App\Models\Core\Company;
use App\Models\Core\Warehouse;
use App\Models\CRM\Customers\Customer;
use App\Models\CRM\Customers\CustomerDebt;
use App\Models\CRM\Leads\Lead;
use App\Models\CRM\Tasks\SalesTask;
use App\Models\Payments\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model đơn hàng CRM.
 *
 * Đơn hàng đi qua luồng phê duyệt:
 * Sales → Sales Manager → Kế toán → Ban GĐ → Kho → Hoàn thành
 *
 * @property int $id
 * @property int|null $lead_id
 * @property string $order_code
 * @property string|null $order_date
 * @property int|null $warehouse_id
 * @property int|null $price_tier_id
 * @property float $total_amount
 * @property float $shipping_fee
 * @property float $discount_amount
 * @property float $tax_amount
 * @property int $created_by
 * @property int|null $approved_by
 * @property string|null $approved_at
 * @property bool $payment_recorded
 * @property string $current_department
 * @property int|null $current_status_type_id
 * @property string|null $shipping_status
 * @property bool $is_shipped
 * @property string|null $shipped_at
 * @property string|null $shipping_carrier
 * @property string|null $tracking_number
 * @property string|null $receiver_name
 * @property string|null $receiver_phone
 * @property string|null $shipping_address
 * @property string|null $shipping_note
 * @property string|null $estimated_delivery
 * @property bool $inventory_issued
 * @property string|null $inventory_issued_at
 * @property int|null $inventory_issued_by
 * @property-read Customer|null      $customer
 * @property-read float              $paid_amount
 * @property-read float              $remain_amount
 * @property-read string             $display_status_name
 * @property-read string             $display_status_color
 * @property-read Lead|null          $lead
 * @property-read User|null          $creator
 * @property-read User|null          $approver
 * @property-read Warehouse|null     $warehouse
 * @property-read OrderStatusType|null $currentStatusType
 * @property-read \Illuminate\Database\Eloquent\Collection<OrderItem>     $items
 * @property-read \Illuminate\Database\Eloquent\Collection<OrderApproval> $approvals
 * @property-read \Illuminate\Database\Eloquent\Collection<Payment>       $payments
 */
class Order extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var string */
    protected $table = 'crm_orders';

    /** @var array<int, string> */
    protected $fillable = [
        'lead_id',
        'order_code',
        'order_date',
        'warehouse_id',
        'price_tier_id',

        // 🔥 THÊM ĐOẠN NÀY
        'invoice_company_name',
        'invoice_tax_code',
        'invoice_address',
        'invoice_email',
        'invoice_status',
        'invoice_file',
        'shipping_address',
        'shipping_fee',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'created_by',
        'approved_by',
        'approved_at',
        'payment_recorded',
        'current_department',
        'shipping_status',
        'is_shipped',
        'shipped_at',
        'shipping_carrier',
        'tracking_number',
        'receiver_name',
        'receiver_phone',
        'shipping_note',
        'current_status_type_id',
        'estimated_delivery',
        'inventory_issued',
        'inventory_issued_at',
        'inventory_issued_by',
        'note',
        'company_id',
        'customer_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'order_date' => 'date',
        'approved_at' => 'datetime',
        'estimated_delivery' => 'datetime',
        'shipping_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_recorded' => 'boolean',
        'shipping_status' => 'string',
        'is_shipped' => 'boolean',
        'shipped_at' => 'datetime',
        'created_by' => 'integer',
        'approved_by' => 'integer',
        'warehouse_id' => 'integer',
        'company_id' => 'integer',
        'current_status_type_id' => 'integer',
        'inventory_issued' => 'boolean',
        'inventory_issued_at' => 'datetime',
        'inventory_issued_by' => 'integer',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /** Lead liên kết với đơn hàng. */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /** Khách hàng (thông qua Lead). */
    public function customer(): HasOneThrough
    {
        return $this->hasOneThrough(
            Customer::class,
            Lead::class,
            'id',           // Lead.id
            'id',           // Customer.id
            'lead_id',      // Order.lead_id
            'customer_id',  // Lead.customer_id
        );
    }

    /** Danh sách sản phẩm trong đơn. */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Lịch sử phê duyệt. */
    public function approvals(): HasMany
    {
        return $this->hasMany(OrderApproval::class);
    }

    /** Kho chính của đơn hàng. */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** Công ty của đơn hàng. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /** Người tạo đơn. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Người duyệt cuối cùng (Giám đốc). */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Danh sách thanh toán. */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Thanh toán gần nhất. */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany('payment_date');
    }

    /** Phê duyệt gần nhất. */
    public function latestApproval(): HasOne
    {
        return $this->hasOne(OrderApproval::class)->latestOfMany('approved_at');
    }

    /** Công nợ của đơn hàng. */
    public function debt(): HasOne
    {
        return $this->hasOne(CustomerDebt::class);
    }

    /** Công việc liên quan. */
    public function tasks(): HasMany
    {
        return $this->hasMany(SalesTask::class);
    }

    /** Loại trạng thái hiện tại. */
    public function currentStatusType(): BelongsTo
    {
        return $this->belongsTo(OrderStatusType::class, 'current_status_type_id');
    }

    /** Hồ sơ đổi trả, thu hồi và hoàn tiền của đơn. */
    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class, 'order_id');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Accessor: lấy customer trực tiếp từ lead (shortcut).
     */
    public function getCustomerAttribute(): ?Customer
    {
        return $this->lead?->customer;
    }

    /**
     * Tổng số tiền đã thanh toán.
     */
    public function getPaidAmountAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /**
     * Số tiền còn phải trả (công nợ).
     */
    public function getRemainAmountAttribute(): float
    {
        return max(0, (float) ($this->total_amount ?? 0) - $this->paid_amount);
    }

    /**
     * Tên trạng thái hiển thị theo nghiệp vụ.
     *
     * - Đã xuất kho + còn nợ → "Công nợ"
     * - Đã xuất kho + hết nợ → "Hoàn tất"
     * - Chưa xuất kho → hiển thị tên trạng thái hệ thống
     */
    public function getDisplayStatusNameAttribute(): string
    {
        if ($this->inventory_issued) {
            return $this->remain_amount > 0 ? 'Công nợ' : 'Hoàn tất';
        }

        return $this->currentStatusType->name ?? $this->getDepartmentLabel();
    }

    /**
     * Màu hiển thị trạng thái.
     */
    public function getDisplayStatusColorAttribute(): string
    {
        if ($this->inventory_issued) {
            return $this->remain_amount > 0 ? '#dc2626' : '#16a34a';
        }

        return $this->currentStatusType->color ?? '#2563eb';
    }

    // =========================================================================
    // BUSINESS METHODS
    // =========================================================================

    /**
     * Lấy màu Bootstrap badge theo department.
     *
     * Delegate sang Enum thay vì duplicate logic.
     */
    public function getStatusColor(): string
    {
        $dept = OrderDepartment::tryFrom($this->current_department);

        return $dept?->badgeColor() ?? 'dark';
    }

    /**
     * Lấy tên department hiển thị.
     *
     * Delegate sang Enum thay vì duplicate logic.
     */
    public function getDepartmentLabel(): string
    {
        $dept = OrderDepartment::tryFrom($this->current_department);

        return $dept?->label() ?? 'N/A';
    }

    /**
     * Lấy cấp duyệt đã approve cuối cùng.
     */
    public function latestApprovalLevel(): ?string
    {
        return $this->approvals()
            ->where('status', 'approved')
            ->orderByDesc('approved_at')
            ->value('level');
    }

    /**
     * Kiểm tra đơn đã hoàn tất chưa.
     */
    public function isCompleted(): bool
    {
        return $this->current_department === OrderDepartment::COMPLETED->value;
    }

    /**
     * Kiểm tra đơn đã xuất kho chưa.
     */
    public function isInventoryIssued(): bool
    {
        return (bool) $this->inventory_issued;
    }

    /**
     * Kiểm tra đơn đã bị hủy chưa.
     */
    public function isCancelled(): bool
    {
        return $this->current_department === OrderDepartment::CANCELLED->value;
    }
}
