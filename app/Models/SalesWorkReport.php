<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesWorkReport extends Model
{
    protected $table = 'sales_work_reports';

    protected $fillable = [
        'customer_id',
        'lead_id',
        'assigned_to',
        'created_by',
        'data_source_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_company',
        'customer_address',
        'facebook_name',
        'facebook_link',
        'zalo_id',
        'customer_type',
        'region_text',
        'data_received_at',
        'first_call_at',
        'last_contact_at',
        'contact_channel',
        'customer_need',
        'system_size_kw',
        'budget_range',
        'project_timeline',
        'consultation_summary',
        'quoted_products',
        'customer_feedback',
        'status',
        'priority',
        'outcome',
        'next_followup_at',
        'next_action',
        'revenue_expectation',
        'lost_reason',
        'proof_links',
        'manager_note',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'data_received_at' => 'datetime',
        'first_call_at' => 'datetime',
        'last_contact_at' => 'datetime',
        'next_followup_at' => 'datetime',
        'approved_at' => 'datetime',
        'proof_links' => 'array',
        'system_size_kw' => 'decimal:2',
        'revenue_expectation' => 'decimal:2',
    ];

    public const STATUSES = [
        'new' => 'Data mới',
        'contacted' => 'Đã liên hệ',
        'consulting' => 'Đang tư vấn',
        'quoted' => 'Đã báo giá',
        'follow_up' => 'Cần chăm sóc lại',
        'won' => 'Chốt đơn',
        'lost' => 'Thất bại',
        'no_answer' => 'Không nghe máy',
        'invalid' => 'Data lỗi',
    ];

    public const PRIORITIES = [
        'low' => 'Thấp',
        'normal' => 'Bình thường',
        'high' => 'Cao',
        'hot' => 'Rất nóng',
    ];

    public const CHANNELS = [
        'call' => 'Gọi điện',
        'zalo' => 'Zalo',
        'facebook' => 'Facebook',
        'email' => 'Email',
        'meeting' => 'Gặp trực tiếp',
        'other' => 'Khác',
    ];

    public const OUTCOMES = [
        'positive' => 'Tích cực',
        'neutral' => 'Trung lập',
        'waiting' => 'Đang cân nhắc',
        'no_answer' => 'Không nghe máy',
        'negative' => 'Không phù hợp',
    ];

    public const CUSTOMER_TYPES = [
        'personal' => 'Cá nhân / hộ gia đình',
        'business' => 'Doanh nghiệp',
        'dealer' => 'Đại lý / thương mại',
        'contractor' => 'Nhà thầu',
        'factory' => 'Nhà xưởng / C&I',
        'other' => 'Khác',
    ];
}
