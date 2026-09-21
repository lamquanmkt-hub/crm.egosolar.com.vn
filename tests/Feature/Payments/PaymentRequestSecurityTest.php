<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Services\Payments\PaymentRequestAuditLogger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kiểm chứng lớp bảo mật P0 của module Đề nghị thanh toán (ĐNTT)
 * sau khi gỡ bỏ cơ chế hardcode email.
 *
 * Điểm mấu chốt: các test dưới đây chứng minh rằng quyền đến từ ROLE `admin`
 * chứ KHÔNG phải từ một địa chỉ email cụ thể — không test nào hardcode
 * `buibichthao@egosolar.vn`.
 *
 * Chạy trên DB egosolar_test, rollback sau từng test nhờ DatabaseTransactions.
 */
final class PaymentRequestSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $role, array $attributes = []): User
    {
        return $this->userWithRole($role, $attributes);
    }

    /** Tạo phiếu ĐNTT trực tiếp trong DB, trả về id. */
    private function makePaymentRequest(User $creator, string $status = 'draft', array $overrides = []): int
    {
        $columns = Schema::getColumnListing('payment_requests');

        $row = array_merge([
            'code' => 'PR-TEST-'.Str::upper(Str::random(10)),
            'created_by' => $creator->id,
            'receiver_name' => 'Người nhận test',
            'department' => 'Kế toán',
            'reason' => 'Lý do thanh toán test',
            'amount' => 1000000,
            'bank_info' => '123456789 - VCB',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        if (in_array('company_id', $columns, true)) {
            $row['company_id'] = \App\Support\EgoCompanyLock::id();
        }

        if (in_array('company', $columns, true)) {
            $row['company'] = \App\Support\EgoCompanyLock::name();
        }

        if (in_array('payment_content', $columns, true)) {
            $row['payment_content'] = 'Nội dung thanh toán test';
        }

        // Bỏ các khóa không tồn tại trong schema hiện tại.
        $row = array_intersect_key($row, array_flip($columns));

        return (int) DB::table('payment_requests')->insertGetId($row);
    }

    private function statusOf(int $id): ?string
    {
        return DB::table('payment_requests')->where('id', $id)->value('status');
    }

    private function skipUnlessAuditTableExists(): void
    {
        if (! PaymentRequestAuditLogger::available()) {
            $this->markTestSkipped(
                'Bảng payment_request_edit_logs chưa được migrate trên DB test. '
                .'Chạy migration 2026_09_19_120000_create_payment_request_edit_logs_table rồi test lại.'
            );
        }
    }

    private function auditLogs(int $paymentRequestId)
    {
        return DB::table('payment_request_edit_logs')
            ->where('payment_request_id', $paymentRequestId)
            ->get();
    }

    /* =====================================================================
     * 1-3. Luồng duyệt: tự duyệt và duyệt phiếu người khác
     * ===================================================================== */

    /** Admin/Giám đốc được tự duyệt phiếu do chính mình tạo (yêu cầu nghiệp vụ). */
    public function test_admin_can_self_approve_own_payment_request(): void
    {
        $admin = $this->makeUser('admin');
        $id = $this->makePaymentRequest($admin, 'submitted');

        $this->actingAs($admin)
            ->post('/payment-requests/'.$id.'/admin-approve', ['note' => 'Tự duyệt theo thẩm quyền Giám đốc']);

        $this->assertSame('admin_approved', $this->statusOf($id));
    }

    /** Nhân sự thường (dù có role duyệt cấp quản lý) KHÔNG được tự duyệt phiếu của mình. */
    public function test_non_admin_cannot_self_approve_own_payment_request(): void
    {
        $manager = $this->makeUser('management');
        $id = $this->makePaymentRequest($manager, 'submitted');

        $this->actingAs($manager)
            ->post('/payment-requests/'.$id.'/admin-approve', ['note' => 'Tự duyệt'])
            ->assertForbidden();

        $this->assertSame('submitted', $this->statusOf($id));
    }

    /** Admin duyệt được phiếu của người khác. */
    public function test_admin_can_approve_another_users_payment_request(): void
    {
        $admin = $this->makeUser('admin');
        $staff = $this->makeUser('sales');
        $id = $this->makePaymentRequest($staff, 'submitted');

        $this->actingAs($admin)
            ->post('/payment-requests/'.$id.'/admin-approve', ['note' => 'Duyệt']);

        $this->assertSame('admin_approved', $this->statusOf($id));
    }

    /** Admin từ chối được phiếu của người khác. */
    public function test_admin_can_reject_another_users_payment_request(): void
    {
        $admin = $this->makeUser('admin');
        $staff = $this->makeUser('sales');
        $id = $this->makePaymentRequest($staff, 'submitted');

        $this->actingAs($admin)
            ->post('/payment-requests/'.$id.'/admin-reject', ['note' => 'Thiếu chứng từ gốc']);

        $this->assertSame('admin_rejected', $this->statusOf($id));
    }

    /* =====================================================================
     * 4-7. Sửa phiếu đã duyệt/đã chi + nhật ký
     * ===================================================================== */

    /** Admin sửa được phiếu đã chi khi có lý do. */
    public function test_admin_can_edit_completed_payment_request_with_reason(): void
    {
        $admin = $this->makeUser('admin');
        $staff = $this->makeUser('sales');
        $id = $this->makePaymentRequest($staff, 'accounting_approved');

        $this->actingAs($admin)->put('/payment-requests/'.$id, [
            'receiver_name' => 'Người nhận ĐÃ SỬA',
            'reason' => 'Lý do thanh toán test',
            'amount' => 1000000,
            'audit_reason' => 'Sửa tên người nhận theo công văn NCC ngày 19/09.',
        ]);

        $this->assertSame(
            'Người nhận ĐÃ SỬA',
            DB::table('payment_requests')->where('id', $id)->value('receiver_name')
        );
    }

    /** Nhân sự thường KHÔNG sửa được phiếu đã chi (dù là người tạo). */
    public function test_non_admin_cannot_edit_completed_payment_request(): void
    {
        $staff = $this->makeUser('sales');
        $id = $this->makePaymentRequest($staff, 'accounting_approved');

        $this->actingAs($staff)->put('/payment-requests/'.$id, [
            'receiver_name' => 'Cố tình sửa',
            'reason' => 'Lý do thanh toán test',
            'amount' => 1000000,
            'audit_reason' => 'Muốn sửa thôi',
        ]);

        $this->assertSame(
            'Người nhận test',
            DB::table('payment_requests')->where('id', $id)->value('receiver_name')
        );
    }

    /** Sửa phiếu đã chi mà không nhập lý do: bị chặn bằng lỗi validation. */
    public function test_editing_completed_payment_request_without_reason_is_rejected(): void
    {
        $admin = $this->makeUser('admin');
        $id = $this->makePaymentRequest($admin, 'accounting_approved');

        $this->actingAs($admin)
            ->from('/payment-requests/'.$id.'/edit')
            ->put('/payment-requests/'.$id, [
                'receiver_name' => 'Người nhận ĐÃ SỬA',
                'reason' => 'Lý do thanh toán test',
                'amount' => 1000000,
            ])
            ->assertSessionHasErrors('audit_reason');

        $this->assertSame(
            'Người nhận test',
            DB::table('payment_requests')->where('id', $id)->value('receiver_name')
        );
    }

    /** Nhật ký ghi đúng: ai / trường nào / giá trị cũ -> mới / lý do. */
    public function test_audit_log_records_who_what_and_why(): void
    {
        $this->skipUnlessAuditTableExists();

        $admin = $this->makeUser('admin');
        $id = $this->makePaymentRequest($admin, 'accounting_approved');

        $this->actingAs($admin)->put('/payment-requests/'.$id, [
            'receiver_name' => 'Người nhận ĐÃ SỬA',
            'reason' => 'Lý do thanh toán test',
            'amount' => 1000000,
            'audit_reason' => 'Sửa tên người nhận theo công văn NCC.',
        ]);

        $log = $this->auditLogs($id)->firstWhere('field_name', 'receiver_name');

        $this->assertNotNull($log, 'Phải có dòng nhật ký cho trường receiver_name.');
        $this->assertSame('edit', $log->action_type);
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertSame('Người nhận test', $log->old_value);
        $this->assertSame('Người nhận ĐÃ SỬA', $log->new_value);
        $this->assertSame('Sửa tên người nhận theo công văn NCC.', $log->reason);
        $this->assertSame('accounting_approved', $log->status_before);
        $this->assertNotNull($log->created_at);
    }

    /* =====================================================================
     * 8-9. Xóa phiếu: bắt buộc lý do, có nhật ký, không mất lịch sử
     * ===================================================================== */

    /** Admin xóa phiếu mà không nhập lý do: bị chặn, phiếu còn nguyên. */
    public function test_admin_delete_requires_reason(): void
    {
        $admin = $this->makeUser('admin');
        $id = $this->makePaymentRequest($admin, 'accounting_approved');

        $this->actingAs($admin)
            ->from('/payment-requests/'.$id)
            ->post('/payment-requests/'.$id.'/xoa-full-thao', [])
            ->assertSessionHasErrors('audit_reason');

        $this->assertNotNull(DB::table('payment_requests')->where('id', $id)->first());
    }

    /** Admin xóa phiếu kèm lý do: được phép và có dòng nhật ký. */
    public function test_admin_delete_with_reason_is_logged(): void
    {
        $this->skipUnlessAuditTableExists();

        $admin = $this->makeUser('admin');
        $id = $this->makePaymentRequest($admin, 'accounting_approved');

        $this->actingAs($admin)->post('/payment-requests/'.$id.'/xoa-full-thao', [
            'audit_reason' => 'Phiếu lập trùng với ĐNTT PR-2026-00042.',
        ]);

        $log = $this->auditLogs($id)
            ->first(fn ($row) => in_array($row->action_type, ['delete', 'force_delete'], true));

        $this->assertNotNull($log, 'Phải ghi nhật ký trước khi xóa phiếu.');
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertSame('Phiếu lập trùng với ĐNTT PR-2026-00042.', $log->reason);
        $this->assertSame('accounting_approved', $log->status_before);
    }

    /**
     * Xóa MỀM phiếu không được xóa kèm chứng từ và lịch sử duyệt.
     *
     * Nếu bảng chưa có cột `deleted_at` (chỉ xóa cứng được) thì test skip và
     * nêu rõ — chuyển sang soft-delete là hạng mục giai đoạn sau.
     */
    public function test_soft_deleting_preserves_attachments_and_approval_history(): void
    {
        if (! Schema::hasColumn('payment_requests', 'deleted_at')) {
            $this->markTestSkipped(
                'payment_requests chưa có cột deleted_at nên chỉ xóa cứng được — '
                .'cần chuyển module sang soft-delete ở giai đoạn sau.'
            );
        }

        $admin = $this->makeUser('admin');
        $id = $this->makePaymentRequest($admin, 'accounting_approved');

        DB::table('payment_attachments')->insert([
            'payment_request_id' => $id,
            'original_name' => 'chung-tu.pdf',
            'path' => 'payment_requests/'.$id.'/chung-tu.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_request_approvals')->insert([
            'payment_request_id' => $id,
            'actor_id' => $admin->id,
            'step' => 'admin',
            'action' => 'approved',
            'note' => 'Duyệt',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post('/payment-requests/'.$id.'/xoa-full-thao', [
            'audit_reason' => 'Xóa theo yêu cầu Ban Giám đốc ngày 19/09.',
        ]);

        $this->assertSame(
            1,
            DB::table('payment_attachments')->where('payment_request_id', $id)->count(),
            'Chứng từ phải còn nguyên sau khi xóa mềm phiếu.'
        );

        $this->assertSame(
            1,
            DB::table('payment_request_approvals')->where('payment_request_id', $id)->count(),
            'Lịch sử duyệt phải còn nguyên sau khi xóa mềm phiếu.'
        );
    }

    /* =====================================================================
     * 10-11. Phân quyền route nhạy cảm
     * ===================================================================== */

    /**
     * Nhân sự thường không copy được phiếu của người khác.
     *
     * Dùng phiếu 'draft' để middleware LockCompletedFinanceRecords không chặn
     * trước — nhờ vậy test này kiểm đúng lớp phân quyền MỚI thêm ở route
     * `payment_requests.copy` (trả 403), chứ không phải lớp khóa phiếu đã chi.
     */
    public function test_staff_cannot_copy_another_users_payment_request(): void
    {
        $owner = $this->makeUser('sales');
        $other = $this->makeUser('sales');
        $id = $this->makePaymentRequest($owner, 'draft');

        $before = DB::table('payment_requests')->count();

        $this->actingAs($other)
            ->post('/payment-requests/'.$id.'/copy')
            ->assertForbidden();

        $this->assertSame($before, DB::table('payment_requests')->count());
    }

    /** Người tạo copy được phiếu của chính mình. */
    public function test_owner_can_copy_own_payment_request(): void
    {
        $owner = $this->makeUser('sales');
        $id = $this->makePaymentRequest($owner, 'draft');

        $before = DB::table('payment_requests')->count();

        $this->actingAs($owner)
            ->post('/payment-requests/'.$id.'/copy')
            ->assertRedirect();

        $this->assertSame($before + 1, DB::table('payment_requests')->count());
    }

    /** Admin copy được phiếu của bất kỳ ai. */
    public function test_admin_can_copy_any_payment_request(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('sales');
        $id = $this->makePaymentRequest($owner, 'accounting_approved');

        $before = DB::table('payment_requests')->count();

        $this->actingAs($admin)
            ->post('/payment-requests/'.$id.'/copy')
            ->assertRedirect();

        $this->assertSame($before + 1, DB::table('payment_requests')->count());
    }

    /**
     * Truy cập thẳng URL xóa quyền-cao khi không phải Admin: route trả 403.
     *
     * Dùng phiếu 'draft' để kiểm đúng lớp abort_unless() ở route, không bị
     * middleware khóa-phiếu-đã-chi chặn trước.
     */
    public function test_direct_url_access_to_admin_only_delete_is_blocked(): void
    {
        $staff = $this->makeUser('sales');
        $id = $this->makePaymentRequest($staff, 'draft');

        $this->actingAs($staff)
            ->post('/payment-requests/'.$id.'/force-delete-by-thao', ['audit_reason' => 'Thử vượt quyền'])
            ->assertForbidden();

        $this->assertNotNull(DB::table('payment_requests')->where('id', $id)->first());
    }

    /**
     * Phiếu ĐÃ CHI: nhân sự thường bị chặn ở tầng middleware
     * LockCompletedFinanceRecords (redirect kèm lỗi, không phải 403).
     *
     * Điều quan trọng là KẾT QUẢ bảo mật: request không thành công và phiếu
     * vẫn còn nguyên — nên test khẳng định theo hiệu lực thực tế, không phụ
     * thuộc vào việc chặn ở tầng nào.
     */
    public function test_staff_cannot_force_delete_completed_request(): void
    {
        $staff = $this->makeUser('sales');
        $id = $this->makePaymentRequest($staff, 'accounting_approved');

        $response = $this->actingAs($staff)
            ->from('/payment-requests/'.$id)
            ->post('/payment-requests/'.$id.'/force-delete-by-thao', ['audit_reason' => 'Thử vượt quyền']);

        $this->assertContains(
            $response->getStatusCode(),
            [302, 403],
            'Request phải bị chặn (403 hoặc redirect kèm lỗi), tuyệt đối không được 2xx.'
        );

        // Bằng chứng quan trọng nhất: phiếu KHÔNG bị xóa.
        $this->assertNotNull(
            DB::table('payment_requests')->where('id', $id)->first(),
            'Phiếu đã chi không được phép bị nhân sự thường xóa.'
        );
    }

    /** Khách chưa đăng nhập bị đẩy về trang login. */
    public function test_guest_is_redirected_from_payment_requests(): void
    {
        $this->post('/payment-requests/1/copy')->assertRedirect('/login');
    }

    /* =====================================================================
     * 12. Quyền đến từ ROLE, không phải từ email
     * ===================================================================== */

    /**
     * Chứng minh quyền đặc biệt gắn với ROLE `admin`: cùng một tài khoản,
     * có role thì toàn quyền, gỡ role ra thì mất quyền ngay — bất kể email.
     */
    public function test_elevated_access_comes_from_admin_role_not_from_email(): void
    {
        $user = $this->makeUser('admin');
        $staff = $this->makeUser('sales');
        $id = $this->makePaymentRequest($staff, 'accounting_approved');

        $this->assertTrue($user->fresh()->canOverrideLockedFinanceRecords());

        // CÓ role admin -> xóa được phiếu đã chi của người khác.
        $this->actingAs($user->fresh())
            ->post('/payment-requests/'.$id.'/force-delete-by-thao', [
                'audit_reason' => 'Xóa theo phê duyệt Ban Giám đốc.',
            ])
            ->assertRedirect();

        $this->assertNull(
            DB::table('payment_requests')->where('id', $id)->first(),
            'Admin phải xóa được phiếu đã chi.'
        );

        // Gỡ role admin: CÙNG tài khoản, CÙNG email, nhưng mất quyền ngay.
        $user->syncRoles([]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        $this->assertFalse($user->canOverrideLockedFinanceRecords());

        $id2 = $this->makePaymentRequest($staff, 'accounting_approved');

        $this->actingAs($user)
            ->from('/payment-requests/'.$id2)
            ->post('/payment-requests/'.$id2.'/force-delete-by-thao', [
                'audit_reason' => 'Xóa theo phê duyệt Ban Giám đốc.',
            ]);

        /*
         * Khẳng định theo HIỆU LỰC: khi mất role, phiếu phải còn nguyên.
         * Đây là bằng chứng mạnh hơn mã HTTP, vì cùng là 302 nhưng một bên
         * là "đã xóa xong, redirect về danh sách" còn một bên là "bị chặn".
         */
        $this->assertNotNull(
            DB::table('payment_requests')->where('id', $id2)->first(),
            'Mất role admin thì không được xóa phiếu nữa — quyền phải đến từ ROLE, không từ email.'
        );
    }

    /**
     * Nhân sự không phải Admin nhưng được gán riêng permission
     * `payment_requests.override_locked` thì cũng được sửa bản ghi đã chi.
     */
    public function test_specific_permission_grants_override_without_admin_role(): void
    {
        $user = $this->makeUser('sales');

        \Spatie\Permission\Models\Permission::findOrCreate(
            User::PERMISSION_OVERRIDE_LOCKED_FINANCE,
            'web'
        );

        $user->givePermissionTo(User::PERMISSION_OVERRIDE_LOCKED_FINANCE);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->canOverrideLockedFinanceRecords());
    }
}
