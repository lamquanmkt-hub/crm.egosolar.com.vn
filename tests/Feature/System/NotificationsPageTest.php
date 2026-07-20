<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/**
 * Trang danh sách thông báo (/notifications).
 *
 * Bug đã gặp trên production (phát hiện 2026-07-20 khi verify deploy): view
 * dùng `$notifications->data ?? $notifications`, nhưng controller luôn truyền
 * Collection — truy cập `->data` trên Collection NÉM exception chứ không trả
 * null, nên toán tử `??` không đỡ được và trang trả 500.
 *
 * Chỉ vỡ khi CÓ thông báo, vì dòng lỗi nằm trong nhánh @else của view.
 * Đó là lý do test này phải seed dữ liệu — smoke test trên DB rỗng không bắt được.
 */
final class NotificationsPageTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOrderFixture;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->userWithRole('admin');
    }

    /** Id user thao tác trong fixture đơn hàng. */
    protected function seedActorId(): int
    {
        return (int) $this->user->id;
    }

    /** Không có thông báo nào: trang vẫn render (nhánh rỗng). */
    public function test_page_renders_when_there_are_no_notifications(): void
    {
        $this->actingAs($this->user)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('Chưa có thông báo');
    }

    /** CÓ thông báo: trang phải render được — đây là ca gây 500 trên production. */
    public function test_page_renders_when_user_has_notifications(): void
    {
        $this->seedShippableOrder();

        DB::table('crm_order_notifications')->insert([
            'order_id' => $this->seed['orderId'],
            'user_id' => $this->user->id,
            'type' => 'shipped',
            'title' => 'Đơn hàng đã xuất kho',
            'message' => 'Đơn TEST-WH-001 đã được xuất kho.',
            'is_read' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('Đơn hàng đã xuất kho');
    }

    /** Nhiều thông báo cùng lúc vẫn render bình thường. */
    public function test_page_renders_with_multiple_notifications(): void
    {
        $this->seedShippableOrder();

        $rows = [];
        foreach (range(1, 5) as $i) {
            $rows[] = [
                'order_id' => $this->seed['orderId'],
                'user_id' => $this->user->id,
                'type' => 'status_change',
                'title' => "Thông báo số {$i}",
                'message' => "Nội dung thông báo {$i}",
                'is_read' => 0,
                'created_at' => now()->subMinutes($i),
                'updated_at' => now()->subMinutes($i),
            ];
        }
        DB::table('crm_order_notifications')->insert($rows);

        $this->actingAs($this->user)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('Thông báo số 1');
    }
}
