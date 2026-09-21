<?php

/**
 * Web Routes - Refactored
 *
 * Áp dụng:
 * - Clean Code: Không có inline controller logic
 * - Single Responsibility: Routes chỉ định nghĩa URL mapping
 * - DRY: Sử dụng route groups để tránh lặp middleware
 * - RESTful: Sử dụng resource routes khi có thể
 *
 * @author Your Name
 *
 * @version 2.0
 */

use Illuminate\Support\Facades\Route;

/* EGO_FIX_BAO_GIA_ROUTE_TOP_START */
Route::middleware(['auth'])
    ->prefix('bao-gia')
    ->name('sales-quotations.')
    ->controller(\App\Http\Controllers\CRM\SalesQuotationController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/tao', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{salesQuotation}', 'show')->name('show');
        Route::get('/{salesQuotation}/sua', 'edit')->name('edit');
        Route::put('/{salesQuotation}', 'update')->name('update');
        Route::delete('/{salesQuotation}', 'destroy')->name('destroy');
        Route::post('/{salesQuotation}/da-gui', 'markSent')->name('sent');
        Route::get('/{salesQuotation}/pdf', 'pdf')->name('pdf');
        Route::get('/{salesQuotation}/pdf-download', 'downloadPdf')->name('pdf.download');
        Route::get('/{salesQuotation}/excel', 'excel')->name('excel');
    });
/* EGO_FIX_BAO_GIA_ROUTE_TOP_END */

/*
|--------------------------------------------------------------------------
| Controller Imports
|--------------------------------------------------------------------------
*/

// Auth
use App\Http\Controllers\Auth\LoginController;
// Core
use App\Http\Controllers\Inventory\BrandController;
use App\Http\Controllers\System\ChatController;
use App\Http\Controllers\System\CompanyController;
use App\Http\Controllers\CRM\CustomerController;
use App\Http\Controllers\Hr\DashboardController;
use App\Http\Controllers\System\DebugController;
use App\Http\Controllers\Finance\AccountController;
use App\Http\Controllers\Finance\BudgetController;
use App\Http\Controllers\Finance\CustomerDebtController;
// Orders & Payments
use App\Http\Controllers\Finance\FinanceDashboardController;
use App\Http\Controllers\Finance\FinanceReportController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\ReceiptController;
use App\Http\Controllers\Finance\SupplierDebtController;
use App\Http\Controllers\Marketing\ContentCalendarController;
// Products
use App\Http\Controllers\Marketing\KpiPayrollController;
use App\Http\Controllers\Marketing\MarketingDashboardController;
// Tasks & Chat
use App\Http\Controllers\Marketing\MarketingLeadController;
use App\Http\Controllers\Marketing\MarketingProgressController;
// Construction Sites & Material Requests (Refactored)
use App\Http\Controllers\Marketing\MarketingReportController;
use App\Http\Controllers\Marketing\WeeklyTaskController;
// Debug & Utils (Refactored)
use App\Http\Controllers\Projects\MaterialRequestController;
use App\Http\Controllers\System\MediaController;
// Marketing
use App\Http\Controllers\System\NotificationController;
use App\Http\Controllers\CRM\OrderController;
use App\Http\Controllers\Finance\PaymentAttachmentController;
use App\Http\Controllers\Finance\PaymentMethodController;
use App\Http\Controllers\Finance\PaymentRequestApprovalController;
use App\Http\Controllers\Finance\PaymentRequestController;
use App\Http\Controllers\Inventory\PriceTierController;
use App\Http\Controllers\Inventory\ProductCategoryController;
// Finance
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\System\PushSubscriptionController;
use App\Http\Controllers\CRM\SalesCommissionController;
use App\Http\Controllers\CRM\SalesCompensationV2Controller;
use App\Http\Controllers\Solar\SolarCalculatorController;
use App\Http\Controllers\Solar\SolarSettingController;
use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\System\UserController;
// Solar
use App\Http\Controllers\Inventory\WarehouseController;
use Illuminate\Support\Facades\Schema;

/* EGO_COMPANY_CONTEXT_ROUTES_START */
Route::middleware(['auth'])->group(function () {
    Route::get('/chon-cong-ty', \App\Http\Controllers\System\AutoCompanyContextController::class)
        ->name('company-context.select');
    Route::post('/chon-cong-ty', [\App\Http\Controllers\System\AutoCompanyContextController::class, 'store'])
        ->name('company-context.store');
    Route::post('/doi-cong-ty', [\App\Http\Controllers\System\EgoCompanyContextController::class, 'reset'])->name('company-context.reset');
});
/* EGO_COMPANY_CONTEXT_ROUTES_END */


/* EGO_WORKSPACE_APP_CENTER_ROUTES_START */
Route::middleware(['auth'])
    ->prefix('workspace')
    ->name('workspace.')
    ->controller(\App\Http\Controllers\System\WorkspaceController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
    });

Route::middleware(['auth', 'role:admin|management'])
    ->prefix('workspace/settings')
    ->name('workspace.settings.')
    ->controller(\App\Http\Controllers\Admin\WorkspaceSettingsController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::put('/', 'update')->name('update');
        Route::delete('/reset', 'reset')->name('reset');
    });
/* EGO_WORKSPACE_APP_CENTER_ROUTES_END */
/* EGO_WORKSPACE_CONTEXT_V1_START */
Route::middleware(['auth'])
    ->prefix('workspace')
    ->name('workspace.')
    ->controller(\App\Http\Controllers\System\WorkspaceContextController::class)
    ->group(function (): void {
        Route::post('/switch', 'switch')->name('context.switch');
        Route::delete('/switch', 'reset')->name('context.reset');
    });
/* EGO_WORKSPACE_CONTEXT_V1_END */
/*
|--------------------------------------------------------------------------
| Public / Auth Routes
|--------------------------------------------------------------------------
*/
Route::controller(LoginController::class)->group(function () {
    Route::get('/login', 'showLoginForm')->name('login');
    Route::post('/login', 'login');
    Route::post('/logout', 'logout')->name('logout');
});

// Đăng ký công khai đã bị vô hiệu hóa vì lý do bảo mật (CRM nội bộ):
// tài khoản nhân viên do admin tạo qua module Quản lý người dùng / phân quyền.
// Nếu cần mở lại có kiểm soát, gate bằng role:admin thay vì mở public.

/*
|--------------------------------------------------------------------------
| Sales
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    Route::get('/sales/commissions', [SalesCompensationV2Controller::class, 'index'])
        ->name('sales.commissions.index');

    Route::get('/sales/commissions/export/excel', [SalesCompensationV2Controller::class, 'exportExcel'])
        ->name('sales.commissions.export.excel');

    Route::get('/sales/commissions/export/pdf', [SalesCompensationV2Controller::class, 'exportPdf'])
        ->name('sales.commissions.export.pdf');

    Route::middleware(['role:admin|sales_manager|accounting'])->group(function () {
        Route::get('/sales/commissions/settings', [SalesCompensationV2Controller::class, 'settings'])
            ->name('sales.commissions.settings');

        Route::post('/sales/commissions/settings', [SalesCompensationV2Controller::class, 'savePolicy'])
            ->name('sales.commissions.settings.save');
    });

    /* EGO_SALES_COMPENSATION_V2_ROUTES_START */
    Route::post('/sales/commissions/policy/copy-previous', [SalesCompensationV2Controller::class, 'copyPrevious'])
        ->name('sales.commissions.policy.copy');
    Route::post('/sales/commissions/policy/submit', [SalesCompensationV2Controller::class, 'submit'])
        ->name('sales.commissions.policy.submit');
    Route::post('/sales/commissions/policy/approve', [SalesCompensationV2Controller::class, 'approve'])
        ->name('sales.commissions.policy.approve');
    Route::post('/sales/commissions/policy/reject', [SalesCompensationV2Controller::class, 'reject'])
        ->name('sales.commissions.policy.reject');
    Route::post('/sales/commissions/policy/lock', [SalesCompensationV2Controller::class, 'lock'])
        ->name('sales.commissions.policy.lock');
    Route::post('/sales/commissions/policy/unlock', [SalesCompensationV2Controller::class, 'unlock'])
        ->name('sales.commissions.policy.unlock');
    Route::post('/sales/commissions/staff', [SalesCompensationV2Controller::class, 'saveStaff'])
        ->name('sales.commissions.staff.save');
    Route::post('/sales/commissions/orders/{orderId}/override', [SalesCompensationV2Controller::class, 'saveOverride'])
        ->whereNumber('orderId')->name('sales.commissions.overrides.save');
    Route::delete('/sales/commissions/orders/{orderId}/override', [SalesCompensationV2Controller::class, 'deleteOverride'])
        ->whereNumber('orderId')->name('sales.commissions.overrides.delete');
    Route::post('/sales/commissions/adjustments', [SalesCompensationV2Controller::class, 'addAdjustment'])
        ->name('sales.commissions.adjustments.store');
    Route::delete('/sales/commissions/adjustments/{adjustmentId}', [SalesCompensationV2Controller::class, 'deleteAdjustment'])
        ->whereNumber('adjustmentId')->name('sales.commissions.adjustments.destroy');
    /* EGO_SALES_COMPENSATION_V2_ROUTES_END */
    Route::get('/sales/kpi', [SalesCommissionController::class, 'kpiDashboard'])
        ->name('sales.kpi.index');

    Route::get('/sales/kpi/my', [SalesCommissionController::class, 'kpiMyForm'])
        ->name('sales.kpi.my');

    Route::post('/sales/kpi/my', [SalesCommissionController::class, 'kpiMyStore'])
        ->name('sales.kpi.my.store');

    Route::middleware(['role:admin|sales_manager|accounting'])->group(function () {
        Route::get('/sales/kpi/settings', [SalesCommissionController::class, 'kpiSettings'])
            ->name('sales.kpi.settings');

        Route::post('/sales/kpi/settings', [SalesCommissionController::class, 'kpiSettingsSave'])
            ->name('sales.kpi.settings.save');
    });
});

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

/* EGO_COMPANY_MANAGEMENT_START */
Route::middleware(['auth'])->prefix('company-management')->name('company-management.')->group(function () {
    Route::get('/', [\App\Http\Controllers\System\CompanyManagementController::class, 'index'])->name('index');
    Route::get('/create', [\App\Http\Controllers\System\CompanyManagementController::class, 'create'])->name('create');
    Route::post('/', [\App\Http\Controllers\System\CompanyManagementController::class, 'store'])->name('store');
    Route::get('/{company}/edit', [\App\Http\Controllers\System\CompanyManagementController::class, 'edit'])->name('edit');
    Route::put('/{company}', [\App\Http\Controllers\System\CompanyManagementController::class, 'update'])->name('update');
    Route::delete('/{company}', [\App\Http\Controllers\System\CompanyManagementController::class, 'destroy'])->name('destroy');
});
/* EGO_COMPANY_MANAGEMENT_END */

Route::get('/dashboard', [\App\Http\Controllers\System\RoleHomeController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Debug Routes (Development Only)
|--------------------------------------------------------------------------
*/

// Debug routes: chỉ admin mới được truy cập (lộ thông tin schema nếu mở rộng hơn)
Route::middleware(['auth', 'role:admin'])
    ->prefix('debug')
    ->controller(DebugController::class)
    ->group(function () {
        Route::get('/ping-route', 'ping');
        Route::get('/product-tables', 'productTables');
        Route::get('/products-candidates', 'productsCandidates');
        Route::get('/inventory', 'inventoryTables');
        Route::get('/me', 'currentUser');
    });

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])
    ->prefix('notifications')
    ->name('notifications.')
    ->controller(NotificationController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/{id}/read', 'markAsRead')->name('mark-read');
        Route::post('/read-all', 'markAllAsRead')->name('mark-all-read');
        Route::get('/unread-count', 'getUnreadCount')->name('unread-count');
        Route::get('/json', 'json')->name('json');
    });

/*
|--------------------------------------------------------------------------
| Profile & Push Subscription
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    // Warehouses
    Route::resource('warehouses', WarehouseController::class)->except(['show']);

    Route::get('warehouses/{warehouse}/inventory', [WarehouseController::class, 'inventory'])
        ->middleware(['can:warehouse.manage', 'can:warehouse.stock_check'])
        ->name('warehouses.inventory');

    // ✅ Bulk actions (chọn nhiều kho: gán công ty / xoá)
    Route::post('warehouses/bulk', [WarehouseController::class, 'bulk'])
        ->name('warehouses.bulk');
    // Profile
    Route::middleware(['auth'])->controller(UserController::class)->group(function () {
        Route::get('/profile', 'profile')
            ->name('users.profile');

        Route::get('/profile/edit', 'editProfile')
            ->name('users.profile-edit');

        Route::match(['post', 'put'], '/profile/update', 'updateProfile')
            ->name('users.profile-update');

        Route::post('/profile/avatar', 'updateAvatar')
            ->name('users.profile.avatar');
    });

    // Push Notifications
    Route::controller(PushSubscriptionController::class)->group(function () {
        Route::post('/push/subscribe', 'subscribe')->name('push.subscribe');
        Route::post('/push/unsubscribe', 'unsubscribe')->name('push.unsubscribe');
    });
});

/*
|--------------------------------------------------------------------------
| Chat + Tasks
|--------------------------------------------------------------------------
*/

Route::middleware('auth')
    ->prefix('chat')
    ->group(function () {
        // Orders - Shipping
        Route::controller(OrderController::class)->group(function () {
            Route::post('/orders/{id}/shipping-info', 'updateShippingInfo')->name('orders.shippingInfo');
            Route::post('/orders/{id}/mark-shipped', 'markShipped')->name('orders.markShipped');
            Route::get('/orders/{id}/ship-serials', [OrderController::class, 'getShipSerials'])
                ->name('orders.ship-serials');

        });

        // Tasks
        Route::controller(TaskController::class)->group(function () {
            Route::get('/tasks/my', 'my')->name('tasks.my');
            Route::get('/tasks', 'index')->name('tasks.index');
            Route::get('/tasks/create', 'create')->name('tasks.create');
            Route::post('/tasks', 'store')->name('tasks.store');
            Route::post('/tasks/projects/quick', 'quickProjectStore')->name('tasks.projects.quick');
            Route::match(['get', 'post'], '/tasks/{task}/submit', 'submitResult')->name('tasks.submit');
            Route::post('/tasks/{task}/attachments/{attachment}/replace', 'replaceAttachment')->whereNumber('attachment')->name('tasks.attachments.replace');
            Route::match(['post', 'delete'], '/tasks/{task}/attachments/{attachment}', 'destroyAttachment')->whereNumber('attachment')->name('tasks.attachments.destroy');
            Route::post('/tasks/{task}/return-revision', 'returnRevision')->name('tasks.return-revision');
            Route::post('/tasks/{task}/approve', 'approve')->name('tasks.approve');
            Route::patch('/tasks/{task}/status', 'updateStatus')->name('tasks.status');
            Route::get('/tasks/{task}/edit', 'edit')->name('tasks.edit');
            Route::put('/tasks/{task}', 'update')->name('tasks.update');
            Route::delete('/tasks/{task}', 'destroy')->name('tasks.destroy');
            Route::get('/tasks/{task}', 'show')->name('tasks.show');
        });

        // Chat
        Route::controller(ChatController::class)->group(function () {
            Route::get('/conversations/json', 'conversationsJson')->name('chat.conversations.json');
            Route::post('/{conversation}/read/json', 'markReadJson')->name('chat.read.json');
            Route::get('/', 'inbox')->name('chat.inbox');
            Route::get('/users', 'users')->name('chat.users');
            Route::post('/direct', 'direct')->name('chat.direct');
            Route::get('/departments/json', 'departmentsJson')->name('chat.departments.json');
            Route::post('/department/json', 'departmentJson')->name('chat.department.json');
            Route::post('/group/json', 'groupJson')->name('chat.group.json');
            Route::get('/messages/{message}/download', 'downloadAttachment')->name('chat.messages.download');
            Route::get('/users/json', 'usersJson')->name('chat.users.json');
            Route::post('/direct/json', 'directJson')->name('chat.direct.json');
            Route::post('/{conversation}/tasks/quick', 'quickTaskStore')->name('chat.tasks.quick');
            Route::get('/{conversation}/messages/json', 'messagesJson')->name('chat.messages.json');
            Route::post('/{conversation}/send/json', 'sendJson')->name('chat.send.json');
            Route::get('/{conversation}', 'show')->name('chat.show');
            Route::post('/{conversation}/send', 'send')->name('chat.send');
        });
    });
/* EGO_MATERIAL_REQUEST_LEGACY_ROUTES_DISABLED_START */
if (false) {
/* EGO_MR_ADMIN_DELETE_COMPLETED_ROUTE_START */
Route::match(['post', 'delete'], '/don-vat-tu/{materialRequest}/xoa', function ($materialRequest) {
    $id = (int) $materialRequest;

    $db = \Illuminate\Support\Facades\DB::class;
    $schema = \Illuminate\Support\Facades\Schema::class;

    $user = auth()->user();

    $hasRole = function (array $roles) use ($user) {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }

        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        if (isset($user->role)) {
            return in_array((string) $user->role, $roles, true);
        }

        return false;
    };

    $isAdmin = $hasRole(['admin']);
    $isKyThuat = $hasRole(['ky_thuat']);

    abort_unless($isAdmin || $isKyThuat, 403);
    abort_unless($schema::hasTable('material_requests'), 404);

    $row = $db::table('material_requests')->where('id', $id)->first();

    abort_unless($row, 404);

    $status = strtoupper(trim((string) ($row->status ?? '')));

    $completedStatuses = [
        'EXPORTED',
        'COMPLETED',
        'COMPLETE',
        'DONE',
        'FINISHED',
        'DA_XUAT_KHO',
        'HOAN_THANH',
    ];

    $isCompleted = in_array($status, $completedStatuses, true);

    if ($isCompleted) {
        return back()->with('error', 'Đơn đã hoàn thành / đã xuất kho nên không được xóa để tránh lệch tồn kho. Hãy tạo phiếu hoàn/điều chỉnh kho nếu cần.');
    }

    $db::transaction(function () use ($db, $schema, $id) {
        if ($schema::hasTable('material_request_items')) {
            $db::table('material_request_items')
                ->where('material_request_id', $id)
                ->delete();
        }

        if ($schema::hasTable('material_request_edit_histories')) {
            $db::table('material_request_edit_histories')
                ->where('material_request_id', $id)
                ->delete();
        }

        $db::table('material_requests')
            ->where('id', $id)
            ->delete();
    });

    return redirect('/don-vat-tu')->with('success', 'Đã xóa đơn vật tư #'.$id.'.');
})
    ->middleware(['auth', 'role:ky_thuat|admin'])
    ->whereNumber('materialRequest');
/* EGO_MR_ADMIN_DELETE_COMPLETED_ROUTE_END */

/*
|--------------------------------------------------------------------------
| Đơn vật tư (Material Requests) - Refactored
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:ky_thuat|accounting|admin|warehouse|kho|sales'])
    ->prefix('don-vat-tu')
    ->name('material-requests.')
    ->group(function () {

        Route::controller(MaterialRequestController::class)->group(function () {
            Route::get('/site-info', 'siteInfo')->name('siteInfo');

            // Static routes phải để TRƯỚC route động
            Route::get('/', 'index')->name('index');

            Route::get('/tao', 'create')
                ->name('create')
                ->middleware('role:ky_thuat|admin|sales|warehouse|kho');

            Route::post('/', 'store')
                ->name('store')
                ->middleware('role:ky_thuat|admin|sales|warehouse|kho');

            Route::get('/{materialRequest}/sua', 'edit')
                ->name('edit')
                ->middleware('role:ky_thuat|admin|warehouse|kho|sales');

            Route::match(['post', 'put', 'patch'], '/{materialRequest}/cap-nhat', 'update')
                ->name('update')
                ->middleware('role:ky_thuat|admin|warehouse|kho|sales');

            Route::match(['post', 'delete'], '/{materialRequest}/xoa', 'destroy')
                ->name('destroy')
                ->middleware('role:ky_thuat|admin');

            Route::post('/{materialRequest}/gui-duyet', 'submit')
                ->name('submit')
                ->middleware('role:ky_thuat|admin');

            Route::post('/{materialRequest}/admin-duyet', 'adminApprove')
                ->name('admin-approve')
                ->middleware('role:admin');

            Route::post('/{materialRequest}/kho-duyet', 'warehouseApprove')
                ->name('warehouse-approve')
                ->middleware('role:warehouse|kho|admin');

            Route::get('/{materialRequest}/export/excel', 'exportExcel')
                ->name('export.excel');

            // Route động để CUỐI
            Route::get('/{materialRequest}', 'show')->name('show');
        });
    });

// Status tracking
Route::get('/theo-doi-trang-thai', fn () => 'Theo dõi trạng thái - OK')
    ->middleware(['auth', 'role:ky_thuat|accounting|admin|warehouse|kho|sales|sales']);
}
/* EGO_MATERIAL_REQUEST_LEGACY_ROUTES_DISABLED_END */

/* EGO_SYNC_VN_MATERIAL_REQUEST_ROUTES_START */
require __DIR__.'/material_requests_synced.php';
/* EGO_SYNC_VN_MATERIAL_REQUEST_ROUTES_END */

/*
|--------------------------------------------------------------------------
| Main Resources
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    // Solar Settings
    Route::get('/solar/settings', [SolarSettingController::class, 'index'])
        ->name('solar.settings');

    Route::post('/solar/settings', [SolarSettingController::class, 'update'])
        ->name('solar.settings.update');

    Route::get('/solar/calculator', [SolarCalculatorController::class, 'index'])
        ->name('solar.calculator');

    Route::post('/solar/calculator/calculate', [SolarCalculatorController::class, 'calculate'])
        ->name('solar.calculator.calculate');
    // Users
    Route::resource('users', UserController::class)->except(['show']);

    // Companies - thông tin pháp nhân dùng để in PDF đơn hàng
    Route::resource('companies', CompanyController::class)->only(['index', 'edit', 'update']);

    // Customers
    Route::get('/customers/popup/form/{id?}', [CustomerController::class, 'ajaxForm'])
        ->name('customers.popup-form');
    Route::get('/customers/{customer}/invoice-info', [CustomerController::class, 'invoiceInfo'])
        ->name('customers.invoice-info');

    Route::post('/customers/{customer}/billing-info', [CustomerController::class, 'updateBillingInfo'])
        ->name('customers.billing-info.update');
    /* EGO_CUSTOMER_PROMAX_V3_ROUTES_START */
    Route::get(
        '/customers/duplicate-check',
        [CustomerController::class, 'duplicateCheck']
    )->name('customers.duplicate-check');

    Route::post(
        '/customers/{customer}/interactions',
        [CustomerController::class, 'storeInteraction']
    )
        ->whereNumber('customer')
        ->name('customers.interactions.store');
    /* EGO_CUSTOMER_PROMAX_V3_ROUTES_END */

    /* EGO_CUSTOMER_HANDOVER_V32_START */
    Route::post(
        '/customers/{customer}/handover',
        [CustomerController::class, 'handover']
    )
        ->whereNumber('customer')
        ->name('customers.handover');
    /* EGO_CUSTOMER_HANDOVER_V32_END */

    /* EGO_CUSTOMER_MODULE_TABS_ROUTES_START */
    Route::get('/customers/pipeline', [\App\Http\Controllers\CRM\SalesWorkReportController::class, 'index'])
        ->name('customers.pipeline');
    Route::get('/customers/overview', [CustomerController::class, 'overview'])
        ->name('customers.overview');
    /* EGO_CUSTOMER_MODULE_TABS_ROUTES_END */
    Route::resource('customers', CustomerController::class);

    /* EGO_CUSTOMER_PROFILES_ROUTES_START */
    Route::middleware(['auth'])
        ->prefix('customer-profiles')
        ->name('customer-profiles.')
        ->controller(\App\Http\Controllers\CRM\CustomerProfileController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::post('/sync-customers', 'syncCustomers')->name('sync-customers');
            Route::get('/export/csv', 'export')->name('export');
            Route::get('/shipping', 'shippingIndex')->name('shipping.index');
            Route::post('/shipping', 'storeShipping')->name('shipping.store');
            Route::put('/shipping/{shipping}', 'updateShipping')->whereNumber('shipping')->name('shipping.update');
            Route::delete('/shipping/{shipping}', 'destroyShipping')->whereNumber('shipping')->name('shipping.destroy');
            Route::post('/shipping', 'storeShipping')->name('shipping.store');
            Route::put('/shipping/{shipping}', 'updateShipping')->whereNumber('shipping')->name('shipping.update');
            Route::delete('/shipping/{shipping}', 'destroyShipping')->whereNumber('shipping')->name('shipping.destroy');
            Route::get('/{customerProfile}', 'show')->whereNumber('customerProfile')->name('show');
            Route::get('/{customerProfile}/edit', 'edit')->whereNumber('customerProfile')->name('edit');
            Route::put('/{customerProfile}', 'update')->whereNumber('customerProfile')->name('update');
            Route::delete('/{customerProfile}', 'destroy')->whereNumber('customerProfile')->name('destroy');
            Route::post('/{customerProfile}/documents', 'storeDocument')->whereNumber('customerProfile')->name('documents.store');
            Route::get('/{customerProfile}/documents/{document}', 'downloadDocument')->whereNumber('customerProfile')->whereNumber('document')->name('documents.download');
            Route::delete('/{customerProfile}/documents/{document}', 'destroyDocument')->whereNumber('customerProfile')->whereNumber('document')->name('documents.destroy');
        });
    /* EGO_CUSTOMER_PROFILES_ROUTES_END */

    // Product Categories, Brands, Price Tiers
    Route::resource('categories', ProductCategoryController::class);
    Route::resource('brands', BrandController::class);
    Route::resource('price-tiers', PriceTierController::class);

    // Warehouses
    Route::resource('warehouses', WarehouseController::class)->except(['show']);
    Route::get('warehouses/{warehouse}/inventory', [WarehouseController::class, 'inventory'])
        ->middleware(['can:warehouse.manage', 'can:warehouse.stock_check'])
        ->name('warehouses.inventory');
    // ✅ Products Input/Output (PHẢI đặt trước Route::resource('products', ...))
    Route::get('/products/input', [ProductController::class, 'input'])->name('products.input');
    Route::get('/products/input/export/excel', [ProductController::class, 'exportInputExcel'])->name('products.input.export.excel');
    Route::get('/products/output', [ProductController::class, 'output'])->name('products.output');
    Route::get('/products/history', [ProductController::class, 'history'])->name('products.history');

    // Products

    /* EGO_PRODUCT_SERIAL_MANAGEMENT_ROUTES_START */
    Route::middleware(['auth'])
        ->prefix('products/serials')
        ->name('products.serials.')
        ->controller(\App\Http\Controllers\Inventory\ProductSerialManagementController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/export', 'export')->name('export');
        });
    /* EGO_PRODUCT_SERIAL_MANAGEMENT_ROUTES_END */

    /* EGO_PRODUCT_GOODS_RECEIPTS_ROUTES_START */
    Route::middleware(['auth', 'role:admin|warehouse|accounting'])
        ->prefix('products/goods-receipts')
        ->name('product-goods-receipts.')
        ->controller(\App\Http\Controllers\Inventory\ProductGoodsReceiptController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/suppliers', 'storeSupplier')->name('suppliers.store');
            Route::get('/{id}', 'show')->whereNumber('id')->name('show');
            Route::post('/', 'store')->name('store');
            Route::post('/{id}/nhap-kho', 'post')->whereNumber('id')->name('post');
            Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
        });
    /* EGO_PRODUCT_GOODS_RECEIPTS_ROUTES_END */

    // Hồ sơ sản phẩm: mở trực tiếp từ tên, SKU hoặc toàn bộ dòng sản phẩm.
    Route::get('/products/{product}', [ProductController::class, 'show'])
        ->whereNumber('product')
        ->name('products.show');

    Route::resource('products', ProductController::class)->except(['show']);

    // Media
    Route::controller(MediaController::class)->group(function () {
        Route::get('/media/list', 'list')->name('media.list');
        Route::post('/media/upload', 'upload')->name('media.upload');
        Route::delete('/media/{media}', 'destroy')
            ->name('media.destroy')
            ->middleware('permission:products.manage');
    });
});

/*
|--------------------------------------------------------------------------
| Orders
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])
    ->prefix('orders')
    ->name('orders.')
    ->controller(\App\Http\Controllers\CRM\OrderController::class)
    ->group(function () {

        // ✅ NEW: Search khách hàng cho dropdown (Tom Select)
        Route::get('/customers/search', 'searchCustomers')->name('customers.search');

        // ✅ FIX: bỏ double /orders (trước bạn bị /orders/orders/..)
        Route::post('/{order}/send-completed-mail', 'sendCompletedMail')
            ->whereNumber('order')
            ->name('sendCompletedMail');

        Route::post('/{id}/warehouse-issue', 'approveWarehouseIssue')
            ->whereNumber('id')
            ->name('warehouse.issue');
        Route::post('/{order}/invoice', 'updateInvoice')
            ->whereNumber('order')
            ->name('updateInvoice');
        Route::get('/{order}/returns/create', [\App\Http\Controllers\CRM\OrderReturnController::class, 'create'])
            ->whereNumber('order')
            ->name('returns.create');

        Route::post('/{order}/returns', [\App\Http\Controllers\CRM\OrderReturnController::class, 'store'])
            ->whereNumber('order')
            ->name('returns.store');

        // API endpoints
        Route::get('/product-catalog', 'getProductCatalog')->name('product-catalog');
        Route::get('/product-warehouses/{productId}', 'getProductWarehouses')
            ->whereNumber('productId')
            ->name('product-warehouses');
        Route::get('/products-by-warehouse', 'getProductsByWarehouse')->name('products-by-warehouse');
        Route::get('/warehouses-by-company', 'getWarehousesByCompany')->name('warehouses-by-company');
        Route::get('/customer-info/{customerId}', 'getCustomerInfo')->name('customer-info');
        Route::get('/product-price/{productId}', 'getProductPrice')->name('product-price');

        // PDF
        Route::get('/{order}/pdf', 'pdf')->whereNumber('order')->name('pdf');
        Route::get('/{order}/pdf-preview', 'pdfPreview')->whereNumber('order')->name('pdf.preview');
        // CRUD
        Route::get('/', 'index')->name('index');
        Route::get('/export/excel', 'exportExcel')->name('export.excel');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{id}', 'show')->whereNumber('id')->name('show');
        Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
        Route::put('/{id}', 'update')->name('update');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');

        // Workflow
        Route::post('/{id}/submit', 'submitForApproval')->name('submit');
        Route::get('/{id}/approval', 'approvalForm')->name('approval-form');
        Route::post('/{id}/approval', 'processApproval')->name('process-approval');
        Route::post('/{id}/cancel', 'cancelOrder')->name('cancel');
        Route::post('/{id}/ship', 'shipOrder')->name('ship');
        Route::post('/{id}/payment', 'recordPayment')->name('record-payment');
        Route::get('/payments/{paymentId}/edit', 'editPayment')->name('payments.edit');
        Route::put('/payments/{paymentId}', 'updatePayment')->name('payments.update');
        Route::delete('/payments/{paymentId}', 'destroyPayment')->name('payments.destroy');

        // Dashboard
        Route::get('/my/dashboard', 'myOrders')->name('my-orders');
    });

/* EGO_ORDER_AFTER_SALES_ROUTES_START */
Route::middleware(['auth'])->group(function () {
    Route::get('/order-returns', [\App\Http\Controllers\CRM\OrderReturnController::class, 'dashboard'])->name('order-returns.dashboard');
    Route::get('/orders/{order}/returns', [\App\Http\Controllers\CRM\OrderReturnController::class, 'index'])->name('orders.returns.index');
    Route::post('/order-returns/{orderReturn}/submit', [\App\Http\Controllers\CRM\OrderReturnController::class, 'submit'])->name('order-returns.submit');
    Route::post('/order-returns/{orderReturn}/approve', [\App\Http\Controllers\CRM\OrderReturnController::class, 'approve'])->name('order-returns.approve');
    Route::post('/order-returns/{orderReturn}/reject', [\App\Http\Controllers\CRM\OrderReturnController::class, 'reject'])->name('order-returns.reject');
    Route::post('/order-returns/{orderReturn}/revision', [\App\Http\Controllers\CRM\OrderReturnController::class, 'requestRevision'])->name('order-returns.revision');
    Route::post('/order-returns/{orderReturn}/in-transit', [\App\Http\Controllers\CRM\OrderReturnController::class, 'markInTransit'])->name('order-returns.in-transit');
    Route::post('/order-returns/{orderReturn}/receive', [\App\Http\Controllers\CRM\OrderReturnController::class, 'receive'])->name('order-returns.receive');
    Route::post('/order-returns/{orderReturn}/inspect', [\App\Http\Controllers\CRM\OrderReturnController::class, 'inspect'])->name('order-returns.inspect');
    Route::post('/order-returns/{orderReturn}/stock-in', [\App\Http\Controllers\CRM\OrderReturnController::class, 'stockIn'])->name('order-returns.stock-in');
    Route::post('/order-returns/{orderReturn}/attachments', [\App\Http\Controllers\CRM\OrderReturnController::class, 'upload'])->name('order-returns.upload');
    Route::get('/order-return-attachments/{attachment}/download', [\App\Http\Controllers\CRM\OrderReturnController::class, 'download'])->name('order-returns.attachments.download');
    Route::post('/order-returns/{orderReturn}/refunds', [\App\Http\Controllers\CRM\OrderReturnController::class, 'createRefund'])->name('order-returns.refunds.store');
    Route::post('/order-refunds/{refund}/approve', [\App\Http\Controllers\CRM\OrderReturnController::class, 'approveRefund'])->name('order-refunds.approve');
    Route::post('/order-refunds/{refund}/process', [\App\Http\Controllers\CRM\OrderReturnController::class, 'processRefund'])->name('order-refunds.process');
    Route::get('/order-returns/{orderReturn}', [\App\Http\Controllers\CRM\OrderReturnController::class, 'show'])->name('order-returns.show');
});
/* EGO_ORDER_AFTER_SALES_ROUTES_END */

/*
|--------------------------------------------------------------------------
| Payment Methods
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])
    ->prefix('payment-methods')
    ->name('payment-methods.')
    ->controller(PaymentMethodController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
        Route::put('/{id}', 'update')->whereNumber('id')->name('update');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
    });

/* EGO_THAO_FULL_PAYMENT_REQUESTS_START */
Route::middleware(['auth'])->group(function () {
    /*
     * Toàn quyền thao tác ĐNTT ở mọi trạng thái.
     * Trước đây khối này hardcode email `buibichthao@egosolar.vn`; nay dùng
     * đúng cơ chế phân quyền sẵn có: role `admin` (Giám đốc) hoặc permission
     * chuyên biệt `payment_requests.override_locked`.
     */
    $egoThaoCanFullPaymentRequest = function () {
        $user = auth()->user();

        return $user !== null
            && method_exists($user, 'canOverrideLockedFinanceRecords')
            && $user->canOverrideLockedFinanceRecords();
    };

    $egoMoneyToNumber = function ($value) {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        $value = str_replace(['đ', ' ', ','], ['', '', ''], $value);
        $value = preg_replace('/[^0-9.]/', '', $value);

        return $value === '' ? 0 : (float) $value;
    };

    Route::match(['put', 'patch'], '/payment-requests/{id}', function (\Illuminate\Http\Request $request, $id) use ($egoThaoCanFullPaymentRequest, $egoMoneyToNumber) {
        if (! $egoThaoCanFullPaymentRequest()) {
            return app(\App\Http\Controllers\Finance\PaymentRequestController::class)->update($request, $id);
        }

        $id = (int) $id;

        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('payment_requests'), 404);

        $old = \Illuminate\Support\Facades\DB::table('payment_requests')->where('company_id', \App\Support\EgoCompanyLock::id())->where('id', $id)->first();

        abort_unless($old, 404);

        $statusBefore = (string) ($old->status ?? '');
        $auditReason = trim((string) $request->input('audit_reason', ''));

        // Sửa phiếu đã duyệt/đã chi: bắt buộc có lý do và phải ghi nhật ký.
        if (\App\Services\Payments\PaymentRequestAuditLogger::isLockedStatus($statusBefore)) {
            $request->validate(
                ['audit_reason' => \App\Services\Payments\PaymentRequestAuditLogger::reasonRules()],
                \App\Services\Payments\PaymentRequestAuditLogger::reasonMessages(),
            );

            $auditReason = trim((string) $request->input('audit_reason'));
        }

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('payment_requests');
        $blocked = [
            '_token',
            '_method',
            'audit_reason',
            'id',
            'created_at',
            'created_by',
            'deleted_at',
            'approved_at',
            'approved_by',
            'admin_approved_at',
            'admin_approved_by',
            'accounting_approved_at',
            'accounting_approved_by',
        ];

        $data = [];

        foreach ($request->except($blocked) as $key => $value) {
            if (! in_array($key, $columns, true)) {
                continue;
            }

            if (in_array($key, ['amount', 'total_amount', 'paid_amount'], true)) {
                $value = $egoMoneyToNumber($value);
            }

            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $data[$key] = $value;
        }

        $aliases = [
            'recipient' => ['recipient_name', 'payee_name', 'receiver_name'],
            'receiver' => ['recipient_name', 'payee_name', 'receiver_name'],
            'payment_content' => ['content', 'description', 'payment_reason'],
            'content' => ['payment_content', 'description', 'payment_reason'],
            'reason' => ['payment_content', 'content', 'description'],
            'due_date' => ['payment_due_date', 'payment_date', 'expected_payment_date'],
            'payment_due_date' => ['due_date', 'payment_date', 'expected_payment_date'],
            'bank_info' => ['bank_account', 'bank_information', 'transfer_info'],
            'department' => ['department_name', 'unit', 'division'],
            'company_id' => ['company'],
        ];

        foreach ($aliases as $from => $tos) {
            if (! $request->has($from)) {
                continue;
            }

            foreach ($tos as $to) {
                if (in_array($to, $columns, true) && ! array_key_exists($to, $data)) {
                    $value = $request->input($from);

                    if (is_array($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                    }

                    $data[$to] = $value;
                }
            }
        }

        if (in_array('updated_at', $columns, true)) {
            $data['updated_at'] = now();
        }

        if (in_array('company_id', $columns, true)) {
            $data['company_id'] = \App\Support\EgoCompanyLock::id();
        }

        if (in_array('company', $columns, true)) {
            $data['company'] = \App\Support\EgoCompanyLock::name();
        }

        if (empty($data)) {
            return back()->with('success', 'Không có dữ liệu cần cập nhật.');
        }

        /*
         * NGUYÊN TỬ: sửa dữ liệu và ghi nhật ký cùng một transaction. Nếu ghi
         * nhật ký lỗi thì thay đổi trên payment_requests cũng bị rollback —
         * không để tồn tại thay đổi tài chính mà không có dấu vết.
         */
        \Illuminate\Support\Facades\DB::transaction(function () use ($id, $data, $old, $auditReason, $statusBefore): void {
            \Illuminate\Support\Facades\DB::table('payment_requests')
                ->where('company_id', \App\Support\EgoCompanyLock::id())
                ->where('id', $id)
                ->update($data);

            // Nhật ký: mỗi trường thay đổi một dòng (ai / khi nào / cũ -> mới / lý do).
            \App\Services\Payments\PaymentRequestAuditLogger::logFieldChanges(
                $id,
                (string) ($old->code ?? ''),
                (array) $old,
                \Illuminate\Support\Arr::except($data, ['updated_at']),
                $auditReason !== '' ? $auditReason : null,
                $statusBefore,
                (string) ($data['status'] ?? $statusBefore),
            );
        });

        return redirect('/payment-requests/'.$id)->with('success', 'Đã cập nhật ĐNTT #'.$id.' ở mọi trạng thái (quyền Admin).');
    })
        ->whereNumber('id')
        ->name('payment_requests.thao_full_update');

    Route::match(['post', 'delete'], '/payment-requests/{id}/xoa-full-thao', function (\Illuminate\Http\Request $request, $id) use ($egoThaoCanFullPaymentRequest) {
        abort_unless($egoThaoCanFullPaymentRequest(), 403);

        $id = (int) $id;

        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('payment_requests'), 404);

        $row = \Illuminate\Support\Facades\DB::table('payment_requests')->where('company_id', \App\Support\EgoCompanyLock::id())->where('id', $id)->first();

        abort_unless($row, 404);

        // Xóa phiếu luôn phải có lý do và được ghi nhật ký trước khi xóa.
        $request->validate(
            ['audit_reason' => \App\Services\Payments\PaymentRequestAuditLogger::reasonRules()],
            \App\Services\Payments\PaymentRequestAuditLogger::reasonMessages(),
        );

        $auditReason = trim((string) $request->input('audit_reason'));

        /*
         * NGUYÊN TỬ: ghi nhật ký nằm TRONG cùng transaction với thao tác xóa.
         * Nếu ghi nhật ký thất bại thì toàn bộ việc xóa bị rollback — không
         * bao giờ xóa phiếu tài chính mà thiếu dấu vết kiểm toán.
         */
        \Illuminate\Support\Facades\DB::transaction(function () use ($id, $row, $auditReason) {
            $db = \Illuminate\Support\Facades\DB::class;
            $schema = \Illuminate\Support\Facades\Schema::class;

            \App\Services\Payments\PaymentRequestAuditLogger::logAction(
                $id,
                (string) ($row->code ?? ''),
                $schema::hasColumn('payment_requests', 'deleted_at')
                    ? \App\Models\Payments\PaymentRequestEditLog::ACTION_DELETE
                    : \App\Models\Payments\PaymentRequestEditLog::ACTION_FORCE_DELETE,
                (string) ($row->status ?? ''),
                null,
                $auditReason,
            );

            if (
                $schema::hasTable('finance_supplier_debt_payments') &&
                $schema::hasColumn('finance_supplier_debt_payments', 'payment_request_id')
            ) {
                $db::table('finance_supplier_debt_payments')
                    ->where('payment_request_id', $id)
                    ->update([
                        'payment_request_id' => null,
                        'status' => 'planned',
                        'updated_at' => now(),
                    ]);
            }

            /*
             * BẢO TOÀN LỊCH SỬ: khi phiếu được XÓA MỀM (bảng có cột
             * `deleted_at`), tuyệt đối KHÔNG xóa chứng từ và lịch sử duyệt —
             * trước đây khối này xóa sạch kèm theo, làm mất dấu vết kiểm toán.
             * Chỉ khi buộc phải xóa cứng (bảng không có `deleted_at`) mới dọn
             * các bảng con để không để lại bản ghi mồ côi.
             */
            if (! $schema::hasColumn('payment_requests', 'deleted_at')) {
                foreach ([
                    'payment_request_attachments',
                    'payment_attachments',
                    'payment_request_files',
                    'payment_request_approvals',
                    'payment_request_histories',
                    'payment_request_logs',
                ] as $table) {
                    if ($schema::hasTable($table) && $schema::hasColumn($table, 'payment_request_id')) {
                        $db::table($table)->where('payment_request_id', $id)->delete();
                    }
                }
            }

            if ($schema::hasColumn('payment_requests', 'deleted_at')) {
                $db::table('payment_requests')
                    ->where('company_id', \App\Support\EgoCompanyLock::id())
                    ->where('id', $id)
                    ->update([
                        'deleted_at' => now(),
                        'updated_at' => now(),
                    ]);
            } else {
                $db::table('payment_requests')->where('company_id', \App\Support\EgoCompanyLock::id())->where('id', $id)->delete();
            }
        });

        return redirect('/payment-requests')->with('success', 'Đã xóa ĐNTT #'.$id.' ở mọi trạng thái (quyền Admin).');
    })
        ->whereNumber('id')
        ->name('payment_requests.thao_full_delete');

    Route::match(['post', 'delete'], '/payment-requests/{id}', function (\Illuminate\Http\Request $request, $id) use ($egoThaoCanFullPaymentRequest) {
        if (! $egoThaoCanFullPaymentRequest()) {
            return app(\App\Http\Controllers\Finance\PaymentRequestController::class)->destroy($request, $id);
        }

        $id = (int) $id;

        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('payment_requests'), 404);

        $row = \Illuminate\Support\Facades\DB::table('payment_requests')->where('company_id', \App\Support\EgoCompanyLock::id())->where('id', $id)->first();

        abort_unless($row, 404);

        // Xóa phiếu luôn phải có lý do và được ghi nhật ký trước khi xóa.
        $request->validate(
            ['audit_reason' => \App\Services\Payments\PaymentRequestAuditLogger::reasonRules()],
            \App\Services\Payments\PaymentRequestAuditLogger::reasonMessages(),
        );

        $auditReason = trim((string) $request->input('audit_reason'));

        /*
         * NGUYÊN TỬ: ghi nhật ký nằm TRONG cùng transaction với thao tác xóa.
         * Nếu ghi nhật ký thất bại thì toàn bộ việc xóa bị rollback — không
         * bao giờ xóa phiếu tài chính mà thiếu dấu vết kiểm toán.
         */
        \Illuminate\Support\Facades\DB::transaction(function () use ($id, $row, $auditReason) {
            $db = \Illuminate\Support\Facades\DB::class;
            $schema = \Illuminate\Support\Facades\Schema::class;

            \App\Services\Payments\PaymentRequestAuditLogger::logAction(
                $id,
                (string) ($row->code ?? ''),
                $schema::hasColumn('payment_requests', 'deleted_at')
                    ? \App\Models\Payments\PaymentRequestEditLog::ACTION_DELETE
                    : \App\Models\Payments\PaymentRequestEditLog::ACTION_FORCE_DELETE,
                (string) ($row->status ?? ''),
                null,
                $auditReason,
            );

            if (
                $schema::hasTable('finance_supplier_debt_payments') &&
                $schema::hasColumn('finance_supplier_debt_payments', 'payment_request_id')
            ) {
                $db::table('finance_supplier_debt_payments')
                    ->where('payment_request_id', $id)
                    ->update([
                        'payment_request_id' => null,
                        'status' => 'planned',
                        'updated_at' => now(),
                    ]);
            }

            /*
             * BẢO TOÀN LỊCH SỬ: khi phiếu được XÓA MỀM (bảng có cột
             * `deleted_at`), tuyệt đối KHÔNG xóa chứng từ và lịch sử duyệt —
             * trước đây khối này xóa sạch kèm theo, làm mất dấu vết kiểm toán.
             * Chỉ khi buộc phải xóa cứng (bảng không có `deleted_at`) mới dọn
             * các bảng con để không để lại bản ghi mồ côi.
             */
            if (! $schema::hasColumn('payment_requests', 'deleted_at')) {
                foreach ([
                    'payment_request_attachments',
                    'payment_attachments',
                    'payment_request_files',
                    'payment_request_approvals',
                    'payment_request_histories',
                    'payment_request_logs',
                ] as $table) {
                    if ($schema::hasTable($table) && $schema::hasColumn($table, 'payment_request_id')) {
                        $db::table($table)->where('payment_request_id', $id)->delete();
                    }
                }
            }

            if ($schema::hasColumn('payment_requests', 'deleted_at')) {
                $db::table('payment_requests')
                    ->where('company_id', \App\Support\EgoCompanyLock::id())
                    ->where('id', $id)
                    ->update([
                        'deleted_at' => now(),
                        'updated_at' => now(),
                    ]);
            } else {
                $db::table('payment_requests')->where('company_id', \App\Support\EgoCompanyLock::id())->where('id', $id)->delete();
            }
        });

        return redirect('/payment-requests')->with('success', 'Đã xóa ĐNTT #'.$id.' ở mọi trạng thái (quyền Admin).');
    })
        ->whereNumber('id')
        ->name('payment_requests.thao_destroy_any_status');
});
/* EGO_THAO_FULL_PAYMENT_REQUESTS_END */

/* EGO_THAO_PR_ATTACHMENTS_START */
Route::middleware(['auth'])->group(function () {
    Route::get('/payment-requests/{paymentRequest}/attachments-thao/{attachment}/download', [\App\Http\Controllers\Finance\EgoPaymentRequestAttachmentController::class, 'download'])
        ->whereNumber('paymentRequest')
        ->whereNumber('attachment')
        ->name('payment-requests.attachments-thao.download');

    Route::get('/payment-requests/{paymentRequest}/attachments-thao/{attachment}/preview', [\App\Http\Controllers\Finance\EgoPaymentRequestAttachmentPreviewController::class, 'show'])
        ->whereNumber('paymentRequest')
        ->whereNumber('attachment')
        ->name('payment-requests.attachments-thao.preview');

    Route::post('/payment-requests/{paymentRequest}/attachments-thao', [\App\Http\Controllers\Finance\EgoPaymentRequestAttachmentController::class, 'upload'])
        ->whereNumber('paymentRequest')
        ->name('payment-requests.attachments-thao.upload');

    Route::post('/payment-requests/{paymentRequest}/attachments-thao/{attachment}/cap-nhat', [\App\Http\Controllers\Finance\EgoPaymentRequestAttachmentController::class, 'replace'])
        ->whereNumber('paymentRequest')
        ->whereNumber('attachment')
        ->name('payment-requests.attachments-thao.replace');

    Route::match(['post', 'delete'], '/payment-requests/{paymentRequest}/attachments-thao/{attachment}/xoa', [\App\Http\Controllers\Finance\EgoPaymentRequestAttachmentController::class, 'destroy'])
        ->whereNumber('paymentRequest')
        ->whereNumber('attachment')
        ->name('payment-requests.attachments-thao.destroy');
});
/* EGO_THAO_PR_ATTACHMENTS_END */

/* EGO_COPY_PAYMENT_REQUEST_START */
Route::post('/payment-requests/{id}/copy', function ($id) {
    $id = (int) $id;

    abort_unless(\Illuminate\Support\Facades\Schema::hasTable('payment_requests'), 404);

    $old = \Illuminate\Support\Facades\DB::table('payment_requests')->where('company_id', \App\Support\EgoCompanyLock::id())->where('id', $id)->first();
    abort_unless($old, 404);

    /*
     * PHÂN QUYỀN SAO CHÉP (trước đây route này chỉ có `auth` — bất kỳ ai đăng
     * nhập cũng copy được phiếu của người khác).
     * Theo đúng cách các action ĐNTT khác đang kiểm tra (ở tầng controller):
     *  - Admin/Giám đốc, Kế toán, Nhân sự (HR): copy được mọi phiếu — đúng
     *    bằng phạm vi `canViewAllPaymentRequests()` của PaymentRequestController,
     *    để không ai copy được phiếu mà họ vốn không được xem.
     *  - Nhân sự khác: chỉ copy được phiếu do chính mình tạo.
     */
    $actor = auth()->user();
    abort_unless($actor !== null, 403);

    $actorCanViewAll = (method_exists($actor, 'isAdmin') && $actor->isAdmin())
        || (method_exists($actor, 'hasAnyRole')
            && $actor->hasAnyRole(['accounting', 'ketoan', 'ke_toan', 'hr']));

    abort_unless(
        $actorCanViewAll || (int) ($old->created_by ?? 0) === (int) $actor->id,
        403,
        'Bạn chỉ được sao chép phiếu đề nghị thanh toán do chính mình tạo.'
    );

    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('payment_requests');
    $data = (array) $old;

    unset($data['id']);

    // Tạo mã phiếu mới dạng PR-2026-00072
    $year = now()->format('Y');
    $prefix = 'PR-'.$year.'-';
    $startPos = strlen($prefix) + 1;

    $maxNo = \Illuminate\Support\Facades\DB::table('payment_requests')
        ->where('company_id', \App\Support\EgoCompanyLock::id())
        ->where('code', 'like', $prefix.'%')
        ->selectRaw("MAX(CAST(SUBSTRING(code, {$startPos}) AS UNSIGNED)) as max_no")
        ->value('max_no');

    if (in_array('code', $columns, true)) {
        $data['code'] = $prefix.str_pad(((int) $maxNo) + 1, 5, '0', STR_PAD_LEFT);
    }

    /* EGO_COPY_ENSURE_REASON */
    if (in_array('reason', $columns, true) && empty($data['reason'])) {
        $data['reason'] = $data['payment_content'] ?? $data['receiver_name'] ?? 'Thanh toán theo đề nghị';
    }

    if (in_array('payment_content', $columns, true) && empty($data['payment_content'])) {
        $data['payment_content'] = $data['reason'] ?? 'Thanh toán theo đề nghị';
    }

    // Người tạo là người đang đăng nhập
    if (in_array('created_by', $columns, true)) {
        $data['created_by'] = auth()->id();
    }

    // Đơn copy quay về nháp để sửa lại
    if (in_array('status', $columns, true)) {
        $data['status'] = 'draft';
    }

    // Xoá các thông tin duyệt / kế toán của đơn cũ
    foreach ([
        'approved_by',
        'approved_at',
        'admin_approved_by',
        'admin_approved_at',
        'accounting_approved_by',
        'accounting_approved_at',
        'rejected_by',
        'rejected_at',
        'admin_note',
        'accounting_note',
        'reject_reason',
        'paid_at',
        'paid_by',
        'deleted_at',
    ] as $field) {
        if (in_array($field, $columns, true)) {
            $data[$field] = null;
        }
    }

    if (in_array('created_at', $columns, true)) {
        $data['created_at'] = now();
    }

    if (in_array('updated_at', $columns, true)) {
        $data['updated_at'] = now();
    }

    if (in_array('company_id', $columns, true)) {
        $data['company_id'] = \App\Support\EgoCompanyLock::id();
    }

    if (in_array('company', $columns, true)) {
        $data['company'] = \App\Support\EgoCompanyLock::name();
    }

    /*
     * NGUYÊN TỬ: tạo phiếu mới + nhân bản chứng từ + ghi nhật ký cùng một
     * transaction, để không bao giờ sinh ra phiếu nửa vời (có phiếu nhưng
     * thiếu chứng từ, hoặc có phiếu nhưng không có dấu vết ai đã sao chép).
     */
    $newId = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $id, $old) {
    $newId = \Illuminate\Support\Facades\DB::table('payment_requests')->insertGetId($data);

    // Copy file đính kèm nếu bảng có
    foreach ([
        'payment_request_attachments',
        'payment_attachments',
        'payment_request_files',
    ] as $table) {
        if (
            \Illuminate\Support\Facades\Schema::hasTable($table) &&
            \Illuminate\Support\Facades\Schema::hasColumn($table, 'payment_request_id')
        ) {
            $rows = \Illuminate\Support\Facades\DB::table($table)
                ->where('payment_request_id', $id)
                ->get();

            foreach ($rows as $row) {
                $fileData = (array) $row;
                unset($fileData['id']);

                $fileData['payment_request_id'] = $newId;

                if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'created_at')) {
                    $fileData['created_at'] = now();
                }

                if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'updated_at')) {
                    $fileData['updated_at'] = now();
                }

                \Illuminate\Support\Facades\DB::table($table)->insert($fileData);
            }
        }
    }

    \App\Services\Payments\PaymentRequestAuditLogger::logAction(
        (int) $newId,
        (string) ($data['code'] ?? ''),
        \App\Models\Payments\PaymentRequestEditLog::ACTION_COPY,
        null,
        (string) ($data['status'] ?? ''),
        'Sao chép từ phiếu #'.$id.' ('.(string) ($old->code ?? '').').'
    );

        return $newId;
    });

    return redirect('/payment-requests/'.$newId.'/edit')
        ->with('success', 'Đã sao chép phiếu mới thành công.');
})
    ->middleware('auth')
    ->whereNumber('id')
    ->name('payment_requests.copy');
/* EGO_COPY_PAYMENT_REQUEST_END */

/*
|--------------------------------------------------------------------------
| Payment Requests
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    // CRUD
    Route::controller(PaymentRequestController::class)->group(function () {
        Route::get('/payment-requests', 'index')->name('payment_requests.index');
        Route::get('/payment-requests/export/excel', 'exportExcel')->name('payment_requests.export_excel');
        Route::get('/payment-requests/export/pdf', 'exportPdf')->name('payment_requests.export_pdf');
        // Route::get('/payment-requests/create', 'create')->name('payment_requests.create');
        Route::get('/payment-requests/new', 'create');
        Route::post(
            '/payment-requests',
            [PaymentRequestController::class, 'store']
        )->name('payment_requests.store');

        Route::get('/payment-requests/{id}', 'show')->whereNumber('id')->name('payment_requests.show');
        Route::get('/payment-requests/{id}/edit', 'edit')->whereNumber('id')->name('payment_requests.edit');
        Route::put('/payment-requests/{id}', 'update')->whereNumber('id')->name('payment_requests.update');
        Route::delete('/payment-requests/{id}', 'destroy')->whereNumber('id')->name('payment_requests.destroy');
        Route::get('/payment-requests/{id}/invoice', 'invoice')->whereNumber('id')->name('payment_requests.invoice');
        Route::get('/payment-requests/demo-create', 'demoCreate')->name('payment_requests.demo_create');
    });

    // Approval Workflow
    Route::controller(PaymentRequestApprovalController::class)->group(function () {
        // Phải đặt trước các route động {id}
        Route::post('/payment-requests/bulk-approve', 'bulkApprove')
            ->name('payment_requests.bulk_approve');

        Route::post('/payment-requests/{id}/submit', 'submit')->name('payment_requests.submit');
        Route::post('/payment-requests/{id}/admin-approve', 'adminApprove')->name('payment_requests.admin_approve');
        Route::post('/payment-requests/{id}/admin-reject', 'adminReject')->name('payment_requests.admin_reject');
        Route::post('/payment-requests/{id}/acc-approve', 'accApprove')->name('payment_requests.acc_approve');
        Route::post('/payment-requests/{id}/acc-reject', 'accReject')->name('payment_requests.acc_reject');
    });

    // Attachments
    Route::post('/payment-requests/{paymentRequest}/attachments', [PaymentAttachmentController::class, 'store'])
        ->name('payment-attachments.store');
});
/*
|--------------------------------------------------------------------------
| Marketing
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:marketing|marketing_manager|admin'])
    ->prefix('marketing')
    ->name('marketing.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */
        Route::get('/dashboard', [MarketingDashboardController::class, 'index'])
            ->name('dashboard');

        /*
|--------------------------------------------------------------------------
| ================== KẾ HOẠCH (PLAN) ==================
|--------------------------------------------------------------------------
*/
        Route::prefix('plan')
            ->name('plan.')
            ->controller(\App\Http\Controllers\Marketing\MarketingPlanController::class)
            ->group(function () {

                Route::get('/', 'index')->name('overview');

                // CREATE
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');

                // EDIT
                Route::get('/{id}/edit', 'edit')
                    ->whereNumber('id')
                    ->name('edit');

                // SHOW
                Route::get('/{id}', 'show')
                    ->whereNumber('id')
                    ->name('show');

                // UPDATE
                Route::put('/{id}', 'update')
                    ->whereNumber('id')
                    ->name('update');

                // DELETE
                Route::delete('/{id}', 'destroy')
                    ->whereNumber('id')
                    ->name('delete');

                // APPROVE
                Route::post('/{id}/approve', 'approve')
                    ->whereNumber('id')
                    ->middleware(['role:marketing_manager|admin'])
                    ->name('approve');
            });
        /*
        |--------------------------------------------------------------------------
        | ================== TIẾN ĐỘ ==================
        |--------------------------------------------------------------------------
        */
        Route::prefix('progress')
            ->name('progress.')
            ->controller(MarketingProgressController::class)
            ->group(function () {

                Route::get('/', 'index')->name('index');
                Route::get('/monthly', 'monthly')->name('monthly');

                Route::post('/update', 'update')->name('update');

                Route::post('/tasks/{id}/status', 'updateStatus')
                    ->whereNumber('id')
                    ->name('tasks.status');
            });

        /*
        |--------------------------------------------------------------------------
        | ================== BÁO CÁO (REPORT)
        |--------------------------------------------------------------------------
        */
        Route::prefix('report')
            ->name('report.')
            ->controller(MarketingReportController::class)
            ->group(function () {

                Route::get('/ads', 'ads')->name('ads');
                Route::get('/seo', 'seo')->name('seo');
                Route::get('/overview', 'overview')->name('overview');
                // ✅ ADS INPUT
                Route::get('/ads/input', 'adsInput')->name('ads.input');
                Route::post('/ads/input', 'adsStore')->name('ads.store');
                Route::post('/ads/import', 'adsImport')->name('ads.import');
                Route::post('/ads/delete', 'adsDelete')->name('ads.delete');

                Route::get('/seo', 'seo')->name('seo');
                Route::get('/overview', 'overview')->name('overview');
            });

        /*
        |--------------------------------------------------------------------------
        | ================== LEADS
        |--------------------------------------------------------------------------
        */
        Route::prefix('leads')
            ->name('leads.')
            ->controller(MarketingLeadController::class)
            ->group(function () {

                Route::get('/', 'index')->name('index');
                Route::get('/upload', 'upload')->name('upload');
                Route::post('/import', 'import')->name('import');
            });

        /*
        |--------------------------------------------------------------------------
        | ================== KPI & PAYROLL
        |--------------------------------------------------------------------------
        */
        Route::prefix('kpi-payroll')
            ->name('kpi-payroll.')
            ->group(function () {

                Route::get('/', [KpiPayrollController::class, 'index'])
                    ->name('index');

                Route::get('/my', [KpiPayrollController::class, 'my'])
                    ->name('my');

                Route::post('/my', [KpiPayrollController::class, 'saveMy'])
                    ->name('my.save');

                Route::middleware(['role:marketing_manager|admin'])
                    ->group(function () {

                        Route::get('/settings', [KpiPayrollController::class, 'settings'])
                            ->name('settings');

                        Route::post('/settings', [KpiPayrollController::class, 'saveSettings'])
                            ->name('settings.save');
                    });
            });

        /*
        |--------------------------------------------------------------------------
        | ================== REPORTS (CONTENT)
        |--------------------------------------------------------------------------
        */
        Route::prefix('reports')
            ->name('reports.')
            ->group(function () {

                /*
                |--------------------------------------------------------------------------
                | Content Calendar
                |--------------------------------------------------------------------------
                */
                Route::controller(ContentCalendarController::class)
                    ->group(function () {

                        Route::get('/content-calendar', 'index')
                            ->name('content-calendar');

                        Route::post('/content-calendar', 'store')
                            ->name('content-calendar.store');

                        Route::get('/content-calendar/{id}', 'show')
                            ->whereNumber('id')
                            ->name('content-calendar.show');

                        Route::put('/content-calendar/{id}', 'update')
                            ->whereNumber('id')
                            ->name('content-calendar.update');

                        Route::delete('/content-calendar/{id}', 'destroy')
                            ->whereNumber('id')
                            ->name('content-calendar.destroy');

                        Route::post('/content-calendar/{id}/files', 'uploadFile')
                            ->whereNumber('id')
                            ->name('content-calendar.files');

                        Route::delete('/content-calendar/{id}/files/{fileId}', 'deleteFile')
                            ->whereNumber('id')
                            ->whereNumber('fileId')
                            ->name('content-calendar.files.delete');

                        Route::post('/content-calendar/{id}/submit', 'submit')
                            ->whereNumber('id')
                            ->name('content-calendar.submit');

                        Route::post('/content-calendar/{id}/approve', 'approve')
                            ->whereNumber('id')
                            ->middleware(['role:marketing_manager|admin'])
                            ->name('content-calendar.approve');

                        Route::get('/content-calendar/{id}/weekly-metrics', 'getWeeklyMetrics')
                            ->whereNumber('id')
                            ->name('content-calendar.weekly-metrics.get');

                        Route::post('/content-calendar/{id}/weekly-metrics', 'saveWeeklyMetrics')
                            ->whereNumber('id')
                            ->name('content-calendar.weekly-metrics');

                        Route::get('/content-calendar/weekly-dashboard', 'weeklyDashboard')
                            ->name('content-calendar.weekly-dashboard');

                        Route::post('/content-calendar/{id}/feedback', 'storeFeedback')
                            ->whereNumber('id')
                            ->name('content-calendar.feedback.store');

                        Route::put('/content-calendar/{id}/feedback/{feedbackId}', 'updateFeedback')
                            ->whereNumber('id')
                            ->whereNumber('feedbackId')
                            ->name('content-calendar.feedback.update');

                        Route::delete('/content-calendar/{id}/feedback/{feedbackId}', 'deleteFeedback')
                            ->whereNumber('id')
                            ->name('content-calendar.feedback.delete');
                    });

                /*
                |--------------------------------------------------------------------------
                | Weekly Tasks
                |--------------------------------------------------------------------------
                */
                Route::controller(WeeklyTaskController::class)
                    ->group(function () {

                        Route::get('/weekly-tasks', 'index')
                            ->name('weekly-tasks');

                        Route::get('/weekly-tasks/create', 'create')
                            ->name('weekly-tasks.create');

                        Route::post('/weekly-tasks', 'store')
                            ->name('weekly-tasks.store');

                        Route::get('/weekly-tasks/{id}', 'show')
                            ->whereNumber('id')
                            ->name('weekly-tasks.show');

                        Route::get('/weekly-tasks/{id}/edit', 'edit')
                            ->whereNumber('id')
                            ->name('weekly-tasks.edit');

                        Route::put('/weekly-tasks/{id}', 'update')
                            ->whereNumber('id')
                            ->name('weekly-tasks.update');

                        Route::delete('/weekly-tasks/{id}', 'destroy')
                            ->whereNumber('id')
                            ->name('weekly-tasks.destroy');
                    });
            });

    });
/*
|--------------------------------------------------------------------------
| Finance
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin|accounting'])
    ->prefix('finance')
    ->name('finance.')
    ->group(function () {
        Route::get('/', [FinanceDashboardController::class, 'index'])->name('index');

        /* EGO_FINANCE_ASSETS_ROUTES_START */
        Route::prefix('assets')
            ->name('assets.')
            ->controller(\App\Http\Controllers\Finance\AssetController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/export/csv', 'exportCsv')->name('export');
                Route::post('/', 'store')->name('store');
                Route::put('/{id}', 'update')->whereNumber('id')->name('update');
                Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
                Route::post('/categories', 'storeCategory')->name('categories.store');
                Route::post('/{assetId}/events', 'storeEvent')->whereNumber('assetId')->name('events.store');
                Route::delete('/events/{eventId}', 'destroyEvent')->whereNumber('eventId')->name('events.destroy');
                Route::get('/files/{fileId}/download', 'downloadFile')->whereNumber('fileId')->name('files.download');
            });
        /* EGO_FINANCE_ASSETS_ROUTES_END */

        Route::prefix('customer-debts')->name('customer-debts.')->group(function () {
            Route::get('/', [CustomerDebtController::class, 'index'])->name('index');
            Route::get('/by-customer', [CustomerDebtController::class, 'byCustomer'])->name('by-customer');
            Route::get('/payment-history', [CustomerDebtController::class, 'paymentHistory'])->name('payment-history');
        });

        Route::prefix('supplier-debts')->name('supplier-debts.')->group(function () {
            Route::get('/', [SupplierDebtController::class, 'index'])->name('index');

            Route::post('/', [SupplierDebtController::class, 'store'])
                ->name('store');

            Route::post('/{id}/files', [SupplierDebtController::class, 'storeDebtFileOnly'])
                ->whereNumber('id')
                ->name('files.store');

            Route::get('/files/{fileId}/download', [SupplierDebtController::class, 'downloadDebtFile'])
                ->whereNumber('fileId')
                ->name('files.download');

            Route::delete('/files/{fileId}', [SupplierDebtController::class, 'destroyDebtFile'])
                ->whereNumber('fileId')
                ->name('files.destroy');

            Route::put('/{id}', [SupplierDebtController::class, 'update'])
                ->whereNumber('id')
                ->name('update');

            Route::delete('/{id}', [SupplierDebtController::class, 'destroy'])
                ->whereNumber('id')
                ->name('destroy');

            Route::post('/{id}/payment-rounds', [SupplierDebtController::class, 'storePaymentRound'])
                ->whereNumber('id')
                ->name('payment-rounds.store');

            Route::put('/payment-rounds/{paymentRoundId}', [SupplierDebtController::class, 'updatePaymentRound'])
                ->whereNumber('paymentRoundId')
                ->name('payment-rounds.update');

            Route::delete('/payment-rounds/{paymentRoundId}', [SupplierDebtController::class, 'destroyPaymentRound'])
                ->whereNumber('paymentRoundId')
                ->name('payment-rounds.destroy');

            Route::post('/payment-rounds/{paymentRoundId}/payment-request', [SupplierDebtController::class, 'createPaymentRequestFromRound'])
                ->whereNumber('paymentRoundId')
                ->name('payment-rounds.create-payment-request');
            Route::post('/payment-rounds/{paymentRoundId}/create-remaining-round', [SupplierDebtController::class, 'createRemainingRoundFromLinkedPayment'])
                ->whereNumber('paymentRoundId')
                ->name('payment-rounds.create-remaining-round');

        });

        Route::prefix('receipts')->name('receipts.')->group(function () {
            Route::get('/', [ReceiptController::class, 'index'])->name('index');
            Route::get('/create', [ReceiptController::class, 'create'])->name('create');
            Route::post('/', [ReceiptController::class, 'store'])->name('store');
            Route::delete('/{receipt}', [ReceiptController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/', [PaymentController::class, 'index'])->name('index');
            Route::get('/create', [PaymentController::class, 'create'])->name('create');
            Route::post('/', [PaymentController::class, 'store'])->name('store');
            Route::delete('/{payment}', [PaymentController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('accounts')->name('accounts.')->group(function () {
            Route::get('/', [AccountController::class, 'index'])->name('index');
            Route::get('/create', [AccountController::class, 'create'])->name('create');
            Route::post('/', [AccountController::class, 'store'])->name('store');
            Route::get('/{account}/edit', [AccountController::class, 'edit'])->name('edit');
            Route::put('/{account}', [AccountController::class, 'update'])->name('update');
            Route::delete('/{account}', [AccountController::class, 'destroy'])->name('destroy');
        });

        Route::get('/payment-request', [FinanceDashboardController::class, 'paymentRequest'])->name('payment-request');
        Route::get('/salary', [FinanceDashboardController::class, 'salary'])->name('salary');
        Route::get('/salary/export/excel', [FinanceDashboardController::class, 'exportSalaryExcel'])->name('salary.export.excel');
        Route::get('/salary/my', [FinanceDashboardController::class, 'mySalary'])->name('salary.my');
        Route::get('/salary/{user}', [FinanceDashboardController::class, 'salaryDetail'])->name('salary.detail');
        Route::get('/budget', [BudgetController::class, 'index'])->name('budget');

        Route::post('/budget', [BudgetController::class, 'store'])
            ->name('budget.store');

        Route::put('/budget/{id}', [BudgetController::class, 'update'])
            ->whereNumber('id')
            ->name('budget.update');

        Route::delete('/budget/{id}', [BudgetController::class, 'destroy'])
            ->whereNumber('id')
            ->name('budget.destroy');
        Route::post('/salary/save', [FinanceDashboardController::class, 'saveSalary'])->name('salary.save');
        Route::get('/reports', [FinanceReportController::class, 'index'])->name('reports');
    });
/*
|--------------------------------------------------------------------------
| Kỹ thuật - Tính lương
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| Kỹ thuật - Tính lương KPI
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| Kỹ thuật - Tính lương KPI
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| Kỹ thuật - Lương KPI
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:ky_thuat|accounting|admin|manager'])
    ->prefix('ky-thuat/luong')
    ->name('ky-thuat.luong.')
    ->group(function () {

        Route::get('/', [\App\Http\Controllers\TechnicalKpi\TechnicalPayrollController::class, 'index'])
            ->name('index');

        Route::post('/luu', [\App\Http\Controllers\TechnicalKpi\TechnicalPayrollController::class, 'store'])
            ->name('store');

        Route::get('/settings', [\App\Http\Controllers\TechnicalKpi\TechnicalPayrollController::class, 'settings'])
            ->name('settings');

        Route::post('/settings', [\App\Http\Controllers\TechnicalKpi\TechnicalPayrollController::class, 'saveSettings'])
            ->name('settings.save');

        Route::get('/{id}', [\App\Http\Controllers\TechnicalKpi\TechnicalPayrollController::class, 'show'])
            ->whereNumber('id')
            ->name('show');

        Route::get('/{id}/edit', [\App\Http\Controllers\TechnicalKpi\TechnicalPayrollController::class, 'edit'])
            ->whereNumber('id')
            ->name('edit');

        Route::post('/{id}/update', [\App\Http\Controllers\TechnicalKpi\TechnicalPayrollController::class, 'update'])
            ->whereNumber('id')
            ->name('update');

        Route::post('/{id}/approve', [\App\Http\Controllers\TechnicalKpi\TechnicalPayrollController::class, 'approve'])
            ->whereNumber('id')
            ->name('approve');

    });

/*
|--------------------------------------------------------------------------
| Kỹ thuật - Lịch bảo trì / bảo hành điện mặt trời
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:ky_thuat|technical|technician|technical_staff|technical_leader|technical_manager|accounting|admin|manager|warehouse|kho|sales|sales_manager|cskh'])
    ->prefix('ky-thuat/bao-tri-bao-hanh')
    ->name('ky-thuat.maintenance.')
    ->group(function () {
        // Đường dẫn O&M cũ chỉ giữ để tương thích bookmark/menu cache.
        // Giao diện chuẩn đã được đồng bộ tại /du-an/bao-tri-bao-hanh.
        Route::get('/', fn () => redirect()->route('projects-unified.maintenance.index'))->name('index');
        Route::get('/sites/search', [\App\Http\Controllers\Technical\SolarMaintenanceController::class, 'sitesSearch'])->name('sites-search');
        Route::post('/', [\App\Http\Controllers\Technical\SolarMaintenanceController::class, 'store'])->name('store');
        Route::get('/cong-trinh/{site}', fn ($site) => redirect()->route('projects-unified.maintenance.site', ['site' => $site]))->whereNumber('site')->name('site');
        Route::post('/cong-trinh/{site}/files', [\App\Http\Controllers\Technical\SolarMaintenanceAttachmentController::class, 'storeSite'])->whereNumber('site')->name('site-files.store');
        Route::get('/site-files/{document}/preview', [\App\Http\Controllers\Technical\SolarMaintenanceAttachmentController::class, 'previewSite'])->whereNumber('document')->name('site-files.preview');
        Route::get('/site-files/{document}/download', [\App\Http\Controllers\Technical\SolarMaintenanceAttachmentController::class, 'downloadSite'])->whereNumber('document')->name('site-files.download');
        Route::delete('/site-files/{document}', [\App\Http\Controllers\Technical\SolarMaintenanceAttachmentController::class, 'destroySite'])->whereNumber('document')->name('site-files.destroy');
        Route::post('/{schedule}/files', [\App\Http\Controllers\Technical\SolarMaintenanceAttachmentController::class, 'storeSchedule'])->whereNumber('schedule')->name('schedule-files.store');
        Route::get('/schedule-files/{attachment}/preview', [\App\Http\Controllers\Technical\SolarMaintenanceAttachmentController::class, 'previewSchedule'])->whereNumber('attachment')->name('schedule-files.preview');
        Route::get('/schedule-files/{attachment}/download', [\App\Http\Controllers\Technical\SolarMaintenanceAttachmentController::class, 'downloadSchedule'])->whereNumber('attachment')->name('schedule-files.download');
        Route::delete('/schedule-files/{attachment}', [\App\Http\Controllers\Technical\SolarMaintenanceAttachmentController::class, 'destroySchedule'])->whereNumber('attachment')->name('schedule-files.destroy');
        Route::post('/{schedule}/gui-duyet', [\App\Http\Controllers\Technical\SolarMaintenanceApprovalController::class, 'submit'])->whereNumber('schedule')->name('approval.submit');
        Route::post('/{schedule}/phe-duyet', [\App\Http\Controllers\Technical\SolarMaintenanceApprovalController::class, 'approve'])->whereNumber('schedule')->name('approval.approve');
        Route::post('/{schedule}/yeu-cau-chinh-sua', [\App\Http\Controllers\Technical\SolarMaintenanceApprovalController::class, 'requestRevision'])->whereNumber('schedule')->name('approval.revision');
        Route::post('/{schedule}/tu-choi', [\App\Http\Controllers\Technical\SolarMaintenanceApprovalController::class, 'reject'])->whereNumber('schedule')->name('approval.reject');
        Route::post('/{schedule}/mo-lai', [\App\Http\Controllers\Technical\SolarMaintenanceApprovalController::class, 'reopen'])->whereNumber('schedule')->name('approval.reopen');
        Route::get('/{schedule}/json', [\App\Http\Controllers\Technical\SolarMaintenanceController::class, 'showJson'])->whereNumber('schedule')->name('json');
        Route::put('/{schedule}', [\App\Http\Controllers\Technical\SolarMaintenanceController::class, 'update'])->whereNumber('schedule')->name('update');
        Route::post('/{schedule}/status', [\App\Http\Controllers\Technical\SolarMaintenanceController::class, 'updateStatus'])->whereNumber('schedule')->name('status');
        Route::delete('/{schedule}', [\App\Http\Controllers\Technical\SolarMaintenanceController::class, 'destroy'])->whereNumber('schedule')->name('destroy');
        Route::get('/{schedule}', fn ($schedule) => redirect()->route('projects-unified.maintenance.show', ['schedule' => $schedule]))->whereNumber('schedule')->name('show');
    });

/*
|--------------------------------------------------------------------------
| Đề xuất chung
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])
    ->prefix('de-xuat')
    ->name('de-xuat.')
    ->group(function () {

        Route::get('/', [\App\Http\Controllers\Projects\ProposalController::class, 'index'])
            ->name('index');

        Route::get('/tao', [\App\Http\Controllers\Projects\ProposalController::class, 'create'])
            ->name('create');

        Route::post('/luu', [\App\Http\Controllers\Projects\ProposalController::class, 'store'])
            ->name('store');

        Route::get('/{id}', [\App\Http\Controllers\Projects\ProposalController::class, 'show'])
            ->whereNumber('id')
            ->name('show');

        Route::post('/{id}/duyet', [\App\Http\Controllers\Projects\ProposalController::class, 'approve'])
            ->whereNumber('id')
            ->name('approve');

        Route::post('/{id}/tu-choi', [\App\Http\Controllers\Projects\ProposalController::class, 'reject'])
            ->whereNumber('id')
            ->name('reject');

        Route::post('/{id}/xoa', [\App\Http\Controllers\Projects\ProposalController::class, 'destroy'])
            ->whereNumber('id')
            ->name('destroy');
    });
Route::middleware('auth')->get('/sidebar/status', function () {
    return response()->json(
        \App\Support\EgoSidebarStatus::data(request()->boolean('debug'))
    );
})->name('sidebar.status');

/* EGO_HR_ANNOUNCEMENTS_ROUTES_START */
Route::middleware(['auth'])
    ->prefix('hr/announcements')
    ->name('hr.announcements.')
    ->controller(\App\Http\Controllers\Hr\AnnouncementController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/json/unread-count', 'unreadCount')->name('unread-count');
        Route::get('/json/list', 'jsonList')->name('json');
        Route::post('/read-all', 'markAllRead')->name('read-all');
        Route::get('/{announcement}', 'show')->name('show');
        Route::put('/{announcement}', 'update')->name('update');
        Route::delete('/{announcement}', 'destroy')->name('destroy');
        Route::post('/{announcement}/read', 'markRead')->name('read');
    });
/* EGO_HR_ANNOUNCEMENTS_ROUTES_END */

/* EGO_SITE_ASSEMBLY_ROUTES_START */
Route::middleware(['auth', 'role:ky_thuat|accounting|admin|warehouse|kho|sales|management|manager'])
    ->prefix('cong-trinh/lap-rap-san-xuat')
    ->name('site-assemblies.')
    ->controller(\App\Http\Controllers\Projects\SiteAssemblyController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/{id}/hoan-thanh', 'complete')->whereNumber('id')->name('complete');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
    });
/* EGO_SITE_ASSEMBLY_ROUTES_END */

require __DIR__.'/hr.php';
// Route::get('/clear-opcache', function () {
//    if (function_exists('opcache_reset')) {
//        opcache_reset();
//        return 'Opcache cleared!';
//    }
//    return 'Opcache not enabled';
// });

Route::post('/ky-thuat/luong/settings/kpi-items', [\App\Http\Controllers\TechnicalKpi\TechnicalPayrollController::class, 'saveKpiItems'])
    ->middleware(['auth', 'role:admin|accounting|manager'])
    ->name('ky-thuat.luong.settings.kpi-items');

Route::delete('/ky-thuat/luong/settings/kpi-items/{id}', [\App\Http\Controllers\TechnicalKpi\TechnicalPayrollController::class, 'destroyKpiItem'])
    ->middleware(['auth', 'role:admin|accounting|manager'])
    ->name('ky-thuat.luong.settings.kpi-items.destroy');

/* EGO_SALES_WORK_REPORTS_START */
Route::middleware(['auth'])
    ->prefix('sales/work-reports')
    ->name('sales.work-reports.')
    ->controller(\App\Http\Controllers\CRM\SalesWorkReportController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/tao', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/export/csv', 'exportCsv')->name('export');
        Route::get('/{id}', 'show')->whereNumber('id')->name('show');
        Route::get('/{id}/sua', 'edit')->whereNumber('id')->name('edit');
        Route::put('/{id}', 'update')->whereNumber('id')->name('update');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
        Route::post('/{id}/duyet', 'approve')
            ->whereNumber('id')
            ->middleware('role:admin|sales_manager|accounting')
            ->name('approve');
    });
/* EGO_SALES_WORK_REPORTS_END */

/* EGO_FIX_MARKETING_PLAN_FILE_ROUTE_START */
Route::middleware(['auth', 'role:marketing|marketing_manager|admin|accounting'])
    ->get('/marketing/plan/file/{file}', [\App\Http\Controllers\Marketing\MarketingPlanController::class, 'file'])
    ->whereNumber('file')
    ->name('marketing.plan.file');
/* EGO_FIX_MARKETING_PLAN_FILE_ROUTE_END */

/* EGO_MARKETING_PLAN_FILE_PREVIEW_START */
Route::middleware(['auth', 'role:marketing|marketing_manager|admin|accounting'])
    ->get('/marketing/plan/file-preview/{file}', function ($file) {
        ini_set('memory_limit', '1024M');
        set_time_limit(180);

        $id = (int) $file;
        $desiredName = trim((string) request('name', ''));
        $planId = request('plan_id');
        $sheetIndex = max(0, (int) request('sheet', 0));

        $startsWith = function ($haystack, $needle) {
            return substr($haystack, 0, strlen($needle)) === $needle;
        };

        $resolveRealPath = function ($path) use ($startsWith) {
            $path = trim((string) $path);

            if ($path === '') {
                return null;
            }

            if (filter_var($path, FILTER_VALIDATE_URL)) {
                return ['url' => $path, 'real' => null];
            }

            $clean = ltrim($path, '/');
            $cleanNoStorage = $startsWith($clean, 'storage/') ? substr($clean, 8) : $clean;
            $cleanNoPublic = $startsWith($clean, 'public/') ? substr($clean, 7) : $clean;

            $candidates = [
                base_path($clean),
                storage_path('app/'.$clean),
                storage_path('app/public/'.$clean),
                storage_path('app/public/'.$cleanNoStorage),
                storage_path('app/public/'.$cleanNoPublic),
                public_path($clean),
                public_path('storage/'.$clean),
                public_path('storage/'.$cleanNoStorage),
                public_path('uploads/'.basename($clean)),
                public_path('marketing/'.basename($clean)),
                storage_path('app/public/uploads/'.basename($clean)),
                storage_path('app/public/marketing/'.basename($clean)),
            ];

            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    return ['url' => null, 'real' => $candidate];
                }
            }

            return null;
        };

        $findFile = function () use ($id, $desiredName, $planId, $resolveRealPath) {
            $tables = [];

            try {
                foreach (\Illuminate\Support\Facades\DB::select('SHOW TABLES') as $row) {
                    $arr = (array) $row;
                    $tables[] = reset($arr);
                }
            } catch (\Throwable $e) {
                $tables = [];
            }

            $priority = [
                'mkt_plan_attachments',
                'marketing_plan_attachments',
                'marketing_plan_files',
                'marketing_plan_uploads',
                'marketing_files',
                'plan_files',
                'attachments',
                'files',
                'media',
            ];

            $tables = array_values(array_unique(array_merge($priority, $tables)));

            $desiredBase = mb_strtolower(pathinfo($desiredName, PATHINFO_FILENAME), 'UTF-8');
            $desiredExt = mb_strtolower(pathinfo($desiredName, PATHINFO_EXTENSION), 'UTF-8');

            $found = [];

            foreach ($tables as $table) {
                try {
                    if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                        continue;
                    }

                    if (! \Illuminate\Support\Facades\Schema::hasColumn($table, 'id')) {
                        continue;
                    }

                    $row = \Illuminate\Support\Facades\DB::table($table)->where('id', $id)->first();

                    if (! $row) {
                        continue;
                    }

                    $values = (array) $row;
                    $allText = mb_strtolower(implode(' ', array_map('strval', $values)), 'UTF-8');

                    $score = 0;

                    if (strpos($table, 'mkt') !== false) {
                        $score += 180;
                    }

                    if (strpos($table, 'marketing') !== false) {
                        $score += 150;
                    }

                    if (strpos($table, 'plan') !== false) {
                        $score += 120;
                    }

                    if ($planId) {
                        foreach (['plan_id', 'marketing_plan_id', 'mkt_plan_id'] as $field) {
                            if (isset($values[$field]) && (string) $values[$field] === (string) $planId) {
                                $score += 500;
                            }
                        }
                    }

                    if ($desiredBase !== '' && strpos($allText, $desiredBase) !== false) {
                        $score += 700;
                    }

                    if ($desiredExt !== '' && strpos($allText, '.'.$desiredExt) !== false) {
                        $score += 500;
                    }

                    $paths = [];

                    foreach ([
                        'path',
                        'file_path',
                        'filepath',
                        'storage_path',
                        'stored_path',
                        'full_path',
                        'url',
                        'file',
                        'attachment',
                        'filename',
                        'file_name',
                        'original_name',
                        'name',
                    ] as $field) {
                        if (! empty($values[$field])) {
                            $paths[] = trim((string) $values[$field]);
                        }
                    }

                    foreach ($values as $value) {
                        $value = trim((string) $value);

                        if ($value !== '' && preg_match('/\.(xlsx|xls|ods|csv|pdf|png|jpg|jpeg|webp|gif|docx?|pptx?)($|\?)/i', $value)) {
                            $paths[] = $value;
                        }
                    }

                    $paths = array_values(array_unique($paths));

                    foreach ($paths as $path) {
                        $resolved = $resolveRealPath($path);

                        if (! $resolved) {
                            continue;
                        }

                        $name = $desiredName ?: basename($path);

                        foreach (['original_name', 'file_name', 'filename', 'name', 'title'] as $nf) {
                            if (! empty($values[$nf]) && preg_match('/\.(xlsx|xls|ods|csv|pdf|png|jpg|jpeg|webp|gif|docx?|pptx?)$/i', (string) $values[$nf])) {
                                $name = basename((string) $values[$nf]);
                                break;
                            }
                        }

                        $ext = mb_strtolower(pathinfo($name ?: $path, PATHINFO_EXTENSION), 'UTF-8');
                        $localScore = $score;

                        if ($desiredExt !== '') {
                            if ($ext === $desiredExt) {
                                $localScore += 1000;
                            }

                            if (in_array($desiredExt, ['xlsx', 'xls', 'ods', 'csv'], true) && in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                                $localScore -= 1000;
                            }
                        }

                        $found[] = [
                            'score' => $localScore,
                            'table' => $table,
                            'row' => $row,
                            'name' => $name,
                            'path' => $path,
                            'url' => $resolved['url'],
                            'real' => $resolved['real'],
                        ];
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }

            usort($found, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            return $found[0] ?? null;
        };

        $found = $findFile();

        $baseCss = '<style>
            *{box-sizing:border-box}
            html,body{margin:0;min-height:100%;font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a}
            .preview-top{position:sticky;top:0;z-index:9999;background:#fff;border-bottom:1px solid #e5e7eb}
            .preview-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px}
            .preview-title{font-size:14px;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .preview-source{padding:7px 10px;border:1px solid #dbe3ef;border-radius:999px;font-size:12px;font-weight:800;background:#f8fafc}
            .preview-tabs{display:flex;gap:8px;flex-wrap:wrap;padding:10px 14px;border-top:1px solid #eef2f7;background:#fff}
            .preview-tab{display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;border-radius:999px;border:1px solid #dbe3ef;background:#fff;color:#0f172a;text-decoration:none;font-size:12px;font-weight:900}
            .preview-tab.active{background:#2563eb;border-color:#2563eb;color:#fff}
            .preview-body{padding:14px;overflow:auto}
            .notice{padding:14px;border:1px solid #dbe3ef;border-radius:14px;background:#fff;color:#475569;line-height:1.5}
            iframe{width:100%;height:calc(100vh - 56px);border:0;background:#fff}
            img{display:block;max-width:100%;height:auto;margin:0 auto}
            .excel-wrap{background:#fff;border:1px solid #dbe3ef;border-radius:14px;overflow:auto;box-shadow:0 12px 30px rgba(15,23,42,.06)}
            .excel-wrap table{border-collapse:collapse !important}
            .excel-wrap td,.excel-wrap th{border:1px solid #d7dee8 !important}
        </style>';

        $page = function ($title, $body) use ($baseCss) {
            return response('<!doctype html><html><head><meta charset="utf-8">'.$baseCss.'</head><body>'.$body.'</body></html>');
        };

        if (! $found) {
            return $page('Không tìm thấy file', '<div class="preview-top"><div class="preview-bar"><div class="preview-title">Không tìm thấy file</div></div></div><div class="preview-body"><div class="notice">Không tìm thấy file ID #'.e($id).' trong database/storage.</div></div>');
        }

        $name = $found['name'] ?: $desiredName ?: ('File #'.$id);
        $source = $found['table'] ?? 'file';
        $real = $found['real'];
        $url = $found['url'];

        if ($url) {
            return $page($name, '<div class="preview-top"><div class="preview-bar"><div class="preview-title">'.e($name).'</div><div class="preview-source">'.e($source).'</div></div></div><iframe src="'.e($url).'"></iframe>');
        }

        $ext = strtolower(pathinfo($name ?: $real, PATHINFO_EXTENSION));

        $headerOnly = '<div class="preview-top"><div class="preview-bar"><div class="preview-title">'.e($name).'</div><div class="preview-source">'.e($source).'</div></div></div>';

        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
            $mime = @mime_content_type($real) ?: 'image/'.($ext === 'jpg' ? 'jpeg' : $ext);
            $data = base64_encode(file_get_contents($real));

            return $page($name, $headerOnly.'<div class="preview-body"><img src="data:'.e($mime).';base64,'.$data.'"></div>');
        }

        if ($ext === 'pdf') {
            $data = base64_encode(file_get_contents($real));

            return $page($name, $headerOnly.'<iframe src="data:application/pdf;base64,'.$data.'"></iframe>');
        }

        if (in_array($ext, ['xlsx', 'xls', 'ods'], true)) {
            if (! class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                return $page($name, $headerOnly.'<div class="preview-body"><div class="notice">Server chưa có PhpSpreadsheet. Chạy: <b>composer require phpoffice/phpspreadsheet</b></div></div>');
            }

            try {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($real);
                $reader->setReadDataOnly(false);
                $spreadsheet = $reader->load($real);

                $sheetCount = $spreadsheet->getSheetCount();

                if ($sheetIndex >= $sheetCount) {
                    $sheetIndex = 0;
                }

                $tabs = '<div class="preview-tabs">';

                for ($i = 0; $i < $sheetCount; $i++) {
                    $sheetName = $spreadsheet->getSheet($i)->getTitle();

                    $query = request()->query();
                    $query['sheet'] = $i;

                    $href = url('/marketing/plan/file-preview/'.$id).'?'.http_build_query($query);

                    $tabs .= '<a class="preview-tab '.($i === $sheetIndex ? 'active' : '').'" href="'.e($href).'">'.e($sheetName).'</a>';
                }

                $tabs .= '</div>';

                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Html($spreadsheet);
                $writer->setSheetIndex($sheetIndex);
                $writer->setUseInlineCss(true);

                ob_start();
                $writer->save('php://output');
                $excelHtml = ob_get_clean();

                $excelHtml = preg_replace('/<!DOCTYPE[^>]*>/i', '', $excelHtml);
                $excelHtml = preg_replace('/<html[^>]*>|<\/html>|<head[^>]*>.*?<\/head>|<body[^>]*>|<\/body>/is', '', $excelHtml);

                $spreadsheet->disconnectWorksheets();

                $top = '<div class="preview-top"><div class="preview-bar"><div class="preview-title">'.e($name).'</div><div class="preview-source">'.e($source).'</div></div>'.$tabs.'</div>';

                return $page($name, $top.'<div class="preview-body"><div class="excel-wrap">'.$excelHtml.'</div></div>');
            } catch (\Throwable $e) {
                return $page($name, $headerOnly.'<div class="preview-body"><div class="notice">Không đọc được Excel: '.e($e->getMessage()).'</div></div>');
            }
        }

        if ($ext === 'csv') {
            $rows = array_map('str_getcsv', file($real));
            $table = '<table style="border-collapse:collapse;width:100%;font-size:13px">';

            foreach ($rows as $r => $row) {
                $table .= '<tr>';

                foreach ($row as $cell) {
                    $tag = $r === 0 ? 'th' : 'td';
                    $table .= '<'.$tag.' style="border:1px solid #d7dee8;padding:7px 9px">'.e((string) $cell).'</'.$tag.'>';
                }

                $table .= '</tr>';
            }

            $table .= '</table>';

            return $page($name, $headerOnly.'<div class="preview-body"><div class="excel-wrap">'.$table.'</div></div>');
        }

        return $page($name, $headerOnly.'<div class="preview-body"><div class="notice">Định dạng chưa hỗ trợ xem nhanh: '.e($ext).'</div></div>');
    })
    ->whereNumber('file')
    ->name('marketing.plan.file.preview');
/* EGO_MARKETING_PLAN_FILE_PREVIEW_END */

/* EGO_SALES_WORK_REPORT_DETAIL_JSON_START */
Route::middleware(['auth'])
    ->get('/sales/work-reports/{id}/detail-json', function ($id) {
        $db = \Illuminate\Support\Facades\DB::class;
        $schema = \Illuminate\Support\Facades\Schema::class;
        $auth = \Illuminate\Support\Facades\Auth::class;

        $user = $auth::user();

        $canManage = false;
        if ($user) {
            if (method_exists($user, 'hasAnyRole')) {
                $canManage = $user->hasAnyRole(['admin', 'sales_manager', 'accounting']);
            } elseif (method_exists($user, 'hasRole')) {
                $canManage = $user->hasRole('admin') || $user->hasRole('sales_manager') || $user->hasRole('accounting');
            } elseif (isset($user->role)) {
                $canManage = in_array($user->role, ['admin', 'sales_manager', 'accounting'], true);
            }
        }

        $select = ['r.*'];
        $q = $db::table('sales_work_reports as r');

        if ($schema::hasTable('users')) {
            $q->leftJoin('users as u', 'u.id', '=', 'r.assigned_to')
                ->leftJoin('users as creator', 'creator.id', '=', 'r.created_by');
            $select[] = 'u.name as sales_name';
            $select[] = 'creator.name as creator_name';
        } else {
            $select[] = $db::raw('NULL as sales_name');
            $select[] = $db::raw('NULL as creator_name');
        }

        if ($schema::hasTable('crm_sources')) {
            $q->leftJoin('crm_sources as s', 's.id', '=', 'r.data_source_id');
            $select[] = 's.name as source_name';
        } else {
            $select[] = $db::raw('NULL as source_name');
        }

        $report = $q->select($select)->where('r.id', (int) $id)->first();

        abort_unless($report, 404);
        abort_if(! $canManage && (int) $report->assigned_to !== (int) $auth::id(), 403);

        $types = [
            'dealer' => 'Đại lý',
            'retail' => 'Mua lẻ',
            'turnkey' => 'Lắp đặt trọn gói',
            'personal' => 'Cá nhân / hộ gia đình',
            'business' => 'Doanh nghiệp',
            'contractor' => 'Nhà thầu',
            'factory' => 'Nhà xưởng / C&I',
            'other' => 'Khác',
        ];

        $stages = [
            'new_need_confirm' => 'Mới - cần xác nhận',
            'interested' => 'Quan tâm',
            'hot_need_quote' => 'Nóng - cần báo giá',
            'quoted_waiting' => 'Đã báo giá - chờ phản hồi',
            'comparing_price' => 'Đang so sánh giá',
            'need_follow' => 'Cần chăm sóc lại',
            'unreachable' => 'Chưa liên hệ được',
            'not_interested' => 'Không quan tâm',
            'closed_won' => 'Đã chốt',
            'closed_lost' => 'Đã mất',
        ];

        $statuses = [
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

        $priorities = [
            'low' => 'Thấp',
            'normal' => 'Bình thường',
            'high' => 'Cao',
            'hot' => 'Rất nóng',
        ];

        $calls = [
            'not_called' => 'Chưa gọi',
            'answered' => 'Nghe máy',
            'no_answer' => 'Không nghe',
            'busy' => 'Máy bận',
            'call_back' => 'Hẹn gọi lại',
        ];

        $quotes = [
            'not_sent' => 'Chưa gửi',
            'sent' => 'Đã gửi',
            'viewed' => 'Khách đã xem',
            'waiting' => 'Đang chờ phản hồi',
        ];

        $dateText = function ($value) {
            if (! $value) {
                return '—';
            }
            try {
                return \Carbon\Carbon::parse($value)->format('d/m/Y H:i');
            } catch (\Throwable $e) {
                return (string) $value;
            }
        };

        $moneyText = function ($value) {
            if ($value === null || $value === '') {
                return '—';
            }

            return number_format((float) $value, 0, ',', '.').' đ';
        };

        $row = (array) $report;
        $row['customer_type_label'] = $types[$report->customer_type] ?? ($report->customer_type ?: '—');
        $row['customer_stage_label'] = $stages[$report->customer_stage] ?? ($report->customer_stage ?: '—');
        $row['status_label'] = $statuses[$report->status] ?? ($report->status ?: '—');
        $row['priority_label'] = $priorities[$report->priority] ?? ($report->priority ?: '—');
        $row['call_1_result_label'] = $calls[$report->call_1_result] ?? ($report->call_1_result ?: '—');
        $row['call_2_result_label'] = $calls[$report->call_2_result] ?? ($report->call_2_result ?: '—');
        $row['quote_status_label'] = $quotes[$report->quote_status] ?? ($report->quote_status ?: '—');
        $row['created_at_text'] = $dateText($report->created_at ?? null);
        $row['updated_at_text'] = $dateText($report->updated_at ?? null);
        $row['data_received_at_text'] = $dateText($report->data_received_at ?? null);
        $row['call_1_at_text'] = $dateText($report->call_1_at ?? null);
        $row['call_2_at_text'] = $dateText($report->call_2_at ?? null);
        $row['last_contact_at_text'] = $dateText($report->last_contact_at ?? null);
        $row['quote_sent_at_text'] = $dateText($report->quote_sent_at ?? null);
        $row['next_followup_at_text'] = $dateText($report->next_followup_at ?? null);
        $row['revenue_expectation_text'] = $moneyText($report->revenue_expectation ?? null);

        $historyQ = $db::table('sales_work_reports as h')
            ->leftJoin('users as hu', 'hu.id', '=', 'h.assigned_to')
            ->select('h.id', 'h.customer_name', 'h.customer_phone', 'h.status', 'h.priority', 'h.customer_stage', 'h.call_1_result', 'h.quote_status', 'h.created_at', 'h.updated_at', 'hu.name as sales_name')
            ->where(function ($x) use ($report) {
                if (! empty($report->customer_phone)) {
                    $x->where('h.customer_phone', $report->customer_phone);
                } else {
                    $x->where('h.customer_name', $report->customer_name);
                }
            })
            ->orderByDesc('h.updated_at')
            ->limit(12);

        $history = $historyQ->get()->map(function ($h) use ($statuses, $priorities, $stages, $calls, $quotes, $dateText) {
            $a = (array) $h;
            $a['status_label'] = $statuses[$h->status] ?? ($h->status ?: '—');
            $a['priority_label'] = $priorities[$h->priority] ?? ($h->priority ?: '—');
            $a['customer_stage_label'] = $stages[$h->customer_stage] ?? ($h->customer_stage ?: '—');
            $a['call_1_result_label'] = $calls[$h->call_1_result] ?? ($h->call_1_result ?: '—');
            $a['quote_status_label'] = $quotes[$h->quote_status] ?? ($h->quote_status ?: '—');
            $a['updated_at_text'] = $dateText($h->updated_at ?? null);

            return $a;
        })->values();

        $followups = collect();

        if ($schema::hasTable('sales_customer_followups')) {
            $fq = $db::table('sales_customer_followups as f')
                ->leftJoin('users as fu', 'fu.id', '=', 'f.created_by')
                ->select('f.*', 'fu.name as creator_name')
                ->where(function ($x) use ($report) {
                    $x->where('f.sales_work_report_id', $report->id);
                    if (! empty($report->customer_phone)) {
                        $x->orWhere('f.customer_phone', $report->customer_phone);
                    }
                })
                ->orderByDesc('f.created_at')
                ->limit(10);

            $followups = $fq->get()->map(function ($f) use ($dateText) {
                $a = (array) $f;
                $a['created_at_text'] = $dateText($f->created_at ?? null);
                $a['followup_at_text'] = $dateText($f->followup_at ?? null);

                return $a;
            })->values();
        }

        return response()->json([
            'report' => $row,
            'history' => $history,
            'followups' => $followups,
        ]);
    })
    ->whereNumber('id')
    ->name('sales.work-reports.detail-json');
/* EGO_SALES_WORK_REPORT_DETAIL_JSON_END */

/* EGO_THAO_FORCE_DELETE_PAYMENT_REQUEST_ONLY_START */
Route::middleware(['auth'])
    ->post('/payment-requests/{paymentRequest}/force-delete-by-thao', function (\Illuminate\Http\Request $request, $paymentRequest) {
        // Trước đây: hardcode email. Nay: role admin (Giám đốc) hoặc
        // permission `payment_requests.override_locked`.
        $actor = auth()->user();

        abort_unless(
            $actor !== null
                && method_exists($actor, 'canOverrideLockedFinanceRecords')
                && $actor->canOverrideLockedFinanceRecords(),
            403
        );

        $id = (int) $paymentRequest;

        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('payment_requests'), 404);

        $pr = \Illuminate\Support\Facades\DB::table('payment_requests')->where('company_id', \App\Support\EgoCompanyLock::id())->where('id', $id)->first();

        abort_unless($pr, 404);

        // Bắt buộc lý do + ghi nhật ký trước khi xóa.
        $request->validate(
            ['audit_reason' => \App\Services\Payments\PaymentRequestAuditLogger::reasonRules()],
            \App\Services\Payments\PaymentRequestAuditLogger::reasonMessages(),
        );

        $auditReason = trim((string) $request->input('audit_reason'));

        /*
         * NGUYÊN TỬ: ghi nhật ký + gỡ liên kết công nợ + xóa phiếu + tính lại
         * công nợ nằm trong CÙNG một transaction. Ghi nhật ký lỗi -> rollback
         * toàn bộ, không xóa gì cả.
         */
        \Illuminate\Support\Facades\DB::transaction(function () use ($id, $pr, $auditReason): void {
        \App\Services\Payments\PaymentRequestAuditLogger::logAction(
            $id,
            (string) ($pr->code ?? ''),
            \Illuminate\Support\Facades\Schema::hasColumn('payment_requests', 'deleted_at')
                ? \App\Models\Payments\PaymentRequestEditLog::ACTION_DELETE
                : \App\Models\Payments\PaymentRequestEditLog::ACTION_FORCE_DELETE,
            (string) ($pr->status ?? ''),
            null,
            $auditReason,
        );

        $affectedDebtIds = [];

        if (
            \Illuminate\Support\Facades\Schema::hasTable('finance_supplier_debt_payments') &&
            \Illuminate\Support\Facades\Schema::hasColumn('finance_supplier_debt_payments', 'payment_request_id')
        ) {
            $affectedDebtIds = \Illuminate\Support\Facades\DB::table('finance_supplier_debt_payments')
                ->where('payment_request_id', $id)
                ->pluck('supplier_debt_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            \Illuminate\Support\Facades\DB::table('finance_supplier_debt_payments')
                ->where('payment_request_id', $id)
                ->update([
                    'payment_request_id' => null,
                    'status' => 'planned',
                    'updated_at' => now(),
                ]);
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('payment_requests', 'deleted_at')) {
            \Illuminate\Support\Facades\DB::table('payment_requests')
                ->where('company_id', \App\Support\EgoCompanyLock::id())
                ->where('id', $id)
                ->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
        } else {
            \Illuminate\Support\Facades\DB::table('payment_requests')->where('company_id', \App\Support\EgoCompanyLock::id())->where('id', $id)->delete();
        }

        if (
            count($affectedDebtIds) &&
            \Illuminate\Support\Facades\Schema::hasTable('finance_supplier_debts') &&
            \Illuminate\Support\Facades\Schema::hasTable('finance_supplier_debt_payments')
        ) {
            foreach ($affectedDebtIds as $debtId) {
                $debt = \Illuminate\Support\Facades\DB::table('finance_supplier_debts')->whereIn('company_name', \App\Support\EgoCompanyScope::companyNames())->where('id', (int) $debtId)->first();

                if (! $debt) {
                    continue;
                }

                $rounds = \Illuminate\Support\Facades\DB::table('finance_supplier_debt_payments')->where('supplier_debt_id', (int) $debtId)->get();
                $paid = 0.0;

                foreach ($rounds as $round) {
                    $roundAmount = (float) ($round->amount ?? 0);

                    if (! empty($round->payment_request_id)) {
                        $linked = \Illuminate\Support\Facades\DB::table('payment_requests')
                            ->where('company_id', \App\Support\EgoCompanyLock::id())
                            ->where('id', (int) $round->payment_request_id)
                            ->first();

                        if ($linked && in_array(strtolower((string) ($linked->status ?? '')), ['accounting_approved', 'paid', 'completed', 'complete', 'done', 'closed'], true)) {
                            $linkedAmount = (float) ($linked->amount ?? 0);
                            $paid += $linkedAmount > 0 ? min($roundAmount, $linkedAmount) : $roundAmount;
                        }

                        continue;
                    }

                    if (in_array(strtolower((string) ($round->status ?? '')), ['paid', 'accounting_approved'], true)) {
                        $paid += $roundAmount;
                    }
                }

                $total = (float) ($debt->total_amount ?? 0);
                $status = $total > 0 && $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

                \Illuminate\Support\Facades\DB::table('finance_supplier_debts')
                    ->whereIn('company_name', \App\Support\EgoCompanyScope::companyNames())
                    ->where('id', (int) $debtId)
                    ->update([
                        'paid_amount' => $paid,
                        'status' => $status,
                        'updated_at' => now(),
                    ]);
            }
        }
        });

        return redirect('/payment-requests')->with('success', 'Đã xóa phiếu ĐNTT #'.$id.'.');
    })
    ->whereNumber('paymentRequest')
    ->name('payment_requests.force-delete-by-thao');
/* EGO_THAO_FORCE_DELETE_PAYMENT_REQUEST_ONLY_END */

/* EGO_SERIAL_WARRANTY_ROUTES_START */
Route::middleware(['auth'])
    ->prefix('serial-warranty')
    ->name('serial-warranty.')
    ->controller(\App\Http\Controllers\Inventory\SerialWarrantyController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/lookup', 'lookup')->name('lookup');
        Route::post('/manual-add', 'manualAddSerialWarranty')->name('manual-add');
        Route::post('/receive', 'receive')->name('receive');
        Route::post('/issue', 'issue')->name('issue');
        Route::post('/transfer', 'transfer')->name('transfer');
        Route::post('/return-stock', 'returnStock')->name('return-stock');
        Route::post('/claim', 'claim')->name('claim');
        Route::post('/serial/{serialUnit}/remove-from-lookup', 'removeSerialFromLookup')->whereNumber('serialUnit')->name('serial.remove-from-lookup');
        Route::post('/serial/{serialUnit}/warranty', 'updateSerialWarranty')->whereNumber('serialUnit')->name('serial.warranty.update');
        Route::post('/product/{product}/serials', 'productAddSerials')->whereNumber('product')->name('product.serials.add');
        Route::post('/serial/{serialUnit}/update', 'productUpdateSerial')->whereNumber('serialUnit')->name('product.serials.update');
        Route::post('/serial/{serialUnit}/delete', 'productDeleteSerial')->whereNumber('serialUnit')->name('product.serials.delete');
    });
/* EGO_SERIAL_WARRANTY_ROUTES_END */

/* EGO_COMPANY_DOCUMENTS_ROUTES_START */
Route::middleware(['auth'])
    ->prefix('company-documents')
    ->name('company-documents.')
    ->controller(\App\Http\Controllers\System\CompanyDocumentController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/folders', 'storeFolder')->name('folders.store');
        Route::delete('/folders/{folder}', 'destroyFolder')->whereNumber('folder')->name('folders.destroy');
        Route::post('/files', 'upload')->name('files.upload');
        Route::post('/clipboard', 'setClipboard')->name('clipboard.set');
        Route::post('/clipboard/clear', 'clearClipboard')->name('clipboard.clear');
        Route::post('/paste', 'paste')->name('paste');
        Route::get('/files/{file}/preview', [\App\Http\Controllers\System\CompanyDocumentPreviewController::class, '__invoke'])->whereNumber('file')->name('files.preview');
        Route::get('/files/{file}/download', 'download')->whereNumber('file')->name('files.download');
        Route::delete('/files/{file}', 'destroyFile')->whereNumber('file')->name('files.destroy');
    });
/* EGO_COMPANY_DOCUMENTS_ROUTES_END */

/* EGO_ORDER_DOCUMENTS_PROFILE_ROUTES_START */
Route::middleware(['auth'])->group(function () {
    Route::post('/orders/{order}/documents-ego', [\App\Http\Controllers\CRM\EgoOrderDocumentController::class, 'store'])
        ->whereNumber('order')
        ->name('orders.documents-ego.store');

    Route::get('/orders/{order}/documents-ego/{document}/preview', [\App\Http\Controllers\CRM\EgoOrderDocumentController::class, 'preview'])
        ->whereNumber('order')
        ->whereNumber('document')
        ->name('orders.documents-ego.preview');

    Route::get('/orders/{order}/documents-ego/{document}/download', [\App\Http\Controllers\CRM\EgoOrderDocumentController::class, 'download'])
        ->whereNumber('order')
        ->whereNumber('document')
        ->name('orders.documents-ego.download');

    Route::match(['post', 'delete'], '/orders/{order}/documents-ego/{document}/xoa', [\App\Http\Controllers\CRM\EgoOrderDocumentController::class, 'destroy'])
        ->whereNumber('order')
        ->whereNumber('document')
        ->name('orders.documents-ego.destroy');
});
/* EGO_ORDER_DOCUMENTS_PROFILE_ROUTES_END */

/* EGO_CUSTOMER_PROFILE_DOCUMENT_PREVIEW_START */
Route::middleware(['auth'])->get(
    '/customer-profiles/{customerProfile}/documents/{document}/preview-ego',
    \App\Http\Controllers\CRM\EgoCustomerProfileDocumentPreviewController::class
)->whereNumber('customerProfile')->whereNumber('document')->name('customer-profiles.documents.preview-ego');
/* EGO_CUSTOMER_PROFILE_DOCUMENT_PREVIEW_END */
/* EGO_VPP_FUNCTION_ROUTES_START */
Route::middleware(['auth'])
    ->prefix('nhan-su/quy-trinh-phan-bo-vpp')
    ->name('hr.office-supply-process.')
    ->controller(\App\Http\Controllers\Hr\OfficeSupplyProcessController::class)
    ->group(function () {
        if (! Route::has('hr.office-supply-process.index')) {
            Route::get('/', 'index')->name('index');
        }

        if (! Route::has('hr.office-supply-process.store')) {
            Route::post('/', 'store')->name('store');
        }

        if (! Route::has('hr.office-supply-process.show')) {
            Route::get('/{id}', 'show')->whereNumber('id')->name('show');
        }

        if (! Route::has('hr.office-supply-process.update')) {
            Route::put('/{id}', 'update')->whereNumber('id')->name('update');
        }

        if (! Route::has('hr.office-supply-process.destroy')) {
            Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
        }

        if (! Route::has('hr.office-supply-process.hr-review')) {
            Route::post('/{id}/hr-kiem-tra', 'hrReview')->whereNumber('id')->name('hr-review');
        }

        if (! Route::has('hr.office-supply-process.approve')) {
            Route::post('/{id}/duyet', 'approve')->whereNumber('id')->name('approve');
        }

        if (! Route::has('hr.office-supply-process.reject')) {
            Route::post('/{id}/tu-choi', 'reject')->whereNumber('id')->name('reject');
        }

        if (! Route::has('hr.office-supply-process.issue')) {
            Route::post('/{id}/xuat-kho', 'issue')->whereNumber('id')->name('issue');
        }

        if (! Route::has('hr.office-supply-process.receive')) {
            Route::post('/{id}/ky-nhan', 'receive')->whereNumber('id')->name('receive');
        }

        if (! Route::has('hr.office-supply-process.complete')) {
            Route::post('/{id}/hoan-tat', 'complete')->whereNumber('id')->name('complete');
        }
    });
/* EGO_VPP_FUNCTION_ROUTES_END */

/* EGO_VPP_SAVE_ONLY_ROUTES_START */
// Route riêng cho module VPP HR.
// Không liên kết kho hàng chính, không trừ kho sản phẩm, chỉ lưu sổ VPP riêng.
Route::middleware(['auth'])
    ->prefix('nhan-su/quy-trinh-phan-bo-vpp')
    ->name('hr.office-supply-process.')
    ->controller(\App\Http\Controllers\Hr\OfficeSupplyProcessController::class)
    ->group(function () {
        Route::post('/them-vpp', 'productStore')->name('product.store');
        Route::post('/nhap-vpp', 'importStock')->name('stock.import');
        Route::post('/cap-phat-vpp', 'allocateStock')->name('stock.allocate');
    });
/* EGO_VPP_SAVE_ONLY_ROUTES_END */

/* EGO_VPP_POPUP_EDIT_ROUTE_START */
Route::middleware(['auth'])
    ->prefix('nhan-su/quy-trinh-phan-bo-vpp')
    ->name('hr.office-supply-process.')
    ->controller(\App\Http\Controllers\Hr\OfficeSupplyProcessController::class)
    ->group(function () {
        Route::put('/sua-vpp/{id}', 'productUpdate')->whereNumber('id')->name('product.update');
    });
/* EGO_VPP_POPUP_EDIT_ROUTE_END */

/* EGO_VPP_DELETE_ROUTE_START */
Route::middleware(['auth'])
    ->prefix('nhan-su/quy-trinh-phan-bo-vpp')
    ->name('hr.office-supply-process.')
    ->controller(\App\Http\Controllers\Hr\OfficeSupplyProcessController::class)
    ->group(function () {
        Route::delete('/xoa-vpp/{id}', 'productDestroy')->whereNumber('id')->name('product.destroy');
    });
/* EGO_VPP_DELETE_ROUTE_END */

/* EGO_VPP_DETAIL_POPUP_ROUTE_START */
Route::middleware(['auth'])
    ->prefix('nhan-su/quy-trinh-phan-bo-vpp')
    ->name('hr.office-supply-process.')
    ->controller(\App\Http\Controllers\Hr\OfficeSupplyProcessController::class)
    ->group(function () {
        Route::get('/popup-chi-tiet/{id}', 'detailJson')->whereNumber('id')->name('detail-json');
    });
/* EGO_VPP_DETAIL_POPUP_ROUTE_END */

/* EGO_BOOKING_ROOM_ROUTES_START */
require __DIR__.'/booking_room.php';
/* EGO_BOOKING_ROOM_ROUTES_END */

/*
|--------------------------------------------------------------------------
| Xóa mềm đơn hàng
|--------------------------------------------------------------------------
| Chỉ ẩn đơn khỏi danh sách. Không xóa thanh toán, tồn kho hoặc lịch sử.
*/
Route::delete(
    '/orders/{order}/soft-delete',
    [
        \App\Http\Controllers\CRM\OrderDeleteController::class,
        'destroy',
    ]
)
    ->middleware('auth')
    ->name('orders.soft-delete');

/* EGO_AI_COPILOT_ROUTES_START */
require __DIR__.'/ai.php';
/* EGO_AI_COPILOT_ROUTES_END */
/* EGO_ROLE_PERMISSION_SETTINGS_ROUTES */
require __DIR__.'/role_permissions.php';


/* EGO_MATERIAL_WORKFLOW_V71_DECISION_PAGE_ROUTE */
Route::middleware(['auth', \App\Http\Middleware\RetireLegacyProjectModule::class])->get(
    '/cong-trinh/{project}/vat-tu/{materialRequest}/xu-ly-quyet-dinh',
    [\App\Http\Controllers\Projects\ProjectMaterialDecisionPageController::class, 'show']
)->whereNumber('project')
  ->whereNumber('materialRequest')
  ->name('ego-material-decision.page');

/* EGO_PROJECT_TEST_NEW_ROUTES_START */
require __DIR__.'/project_test.php';
/* EGO_PROJECT_TEST_NEW_ROUTES_END */
/* EGO_TECHNICAL_WORKSPACE_V1_ROUTES_START */
require __DIR__.'/technical_workspace.php';
/* EGO_TECHNICAL_WORKSPACE_V1_ROUTES_END */

/* EGO_SYNC_VN_PROJECT_TECHNICAL_ROUTES_START */
require __DIR__.'/project_unified.php';
require __DIR__.'/project_workflow_document_settings.php';
require __DIR__.'/technical.php';
/* EGO_SYNC_VN_PROJECT_TECHNICAL_ROUTES_END */
/* EGO_SMART_SEARCH_ROUTES_START */
Route::middleware(['auth', 'throttle:90,1'])
    ->prefix('smart-search')
    ->name('smart-search.')
    ->controller(\App\Http\Controllers\System\SmartSearchController::class)
    ->group(function () {
        Route::get('/bootstrap', 'bootstrap')->name('bootstrap');
        Route::get('/query', 'query')->name('query');
    });
/* EGO_SMART_SEARCH_ROUTES_END */


/* EGO_CUSTOMER_CONSIGNMENT_ROUTES_START */
Route::middleware(['auth'])
    ->prefix('ky-gui-hang-hoa')
    ->name('customer-consignments.')
    ->controller(\App\Http\Controllers\CRM\CustomerConsignmentController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/quy-trinh', 'process')->name('process');
        Route::get('/tao', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{customerConsignment}/sua', 'edit')->whereNumber('customerConsignment')->name('edit');
        Route::put('/{customerConsignment}', 'update')->whereNumber('customerConsignment')->name('update');
        Route::post('/{customerConsignment}/gui-duyet', 'submit')->whereNumber('customerConsignment')->name('submit');
        Route::post('/{customerConsignment}/phe-duyet', 'approve')->whereNumber('customerConsignment')->name('approve');
        Route::post('/{customerConsignment}/yeu-cau-chinh-sua', 'requestRevision')->whereNumber('customerConsignment')->name('request-revision');
        Route::post('/{customerConsignment}/tu-choi', 'reject')->whereNumber('customerConsignment')->name('reject');
        Route::post('/{customerConsignment}/kho-xuat', 'warehouseIssue')->whereNumber('customerConsignment')->name('warehouse-issue');
        Route::post('/{customerConsignment}/huy', 'cancel')->whereNumber('customerConsignment')->name('cancel');
        Route::get('/{customerConsignment}', 'show')->whereNumber('customerConsignment')->name('show');
    });
/* EGO_CUSTOMER_CONSIGNMENT_ROUTES_END */
// EGO_WORKSPACE_DEFAULT_HOME_V2_START
// Trang gốc mở Workspace. Dashboard theo vai trò nằm tại /dashboard.
Route::get('/', function () {
    return redirect()->route('workspace.index');
})->name('home');
// EGO_WORKSPACE_DEFAULT_HOME_V2_END

/* EGO_PAYMENT_ADVANCE_ROUTE_LOADER_20260906_START */
require_once __DIR__.'/payment_advances.php';
/* EGO_PAYMENT_ADVANCE_ROUTE_LOADER_20260906_END */

/* EGO_BUSINESS_TRIP_ROUTES_V1_START */
require_once __DIR__.'/business_trips.php';
/* EGO_BUSINESS_TRIP_ROUTES_V1_END */
