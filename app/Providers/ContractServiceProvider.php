<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Services\BrandServiceInterface;
use App\Contracts\Services\CallioServiceInterface;
use App\Contracts\Services\CommissionEngineServiceInterface;
use App\Contracts\Services\CustomerServiceInterface;
use App\Contracts\Services\MediaUploadServiceInterface;
use App\Contracts\Services\NotificationServiceInterface;
use App\Contracts\Services\OrderReturnServiceInterface;
use App\Contracts\Services\OrderServiceInterface;
use App\Contracts\Services\PageAccessServiceInterface;
use App\Contracts\Services\PaymentMethodServiceInterface;
use App\Contracts\Services\PriceTierServiceInterface;
use App\Contracts\Services\PricingServiceInterface;
use App\Contracts\Services\ProductCategoryServiceInterface;
use App\Contracts\Services\ProductServiceInterface;
use App\Contracts\Services\ProductStockServiceInterface;
use App\Contracts\Services\StockLotServiceInterface;
use App\Contracts\Services\SupplierDebtServiceInterface;
use App\Contracts\Services\WarehouseServiceInterface;
use App\Services\BrandService;
use App\Services\CallioService;
use App\Services\CommissionEngineService;
use App\Services\CustomerService;
use App\Services\Finance\SupplierDebtService;
use App\Services\MediaUploadService;
use App\Services\NotificationService;
use App\Services\OrderReturnService;
use App\Services\OrderService;
use App\Services\PaymentMethodService;
use App\Services\PriceTierService;
use App\Services\PricingService;
use App\Services\ProductCategoryService;
use App\Services\ProductService;
use App\Services\ProductStockService;
use App\Services\RolePermission\PageAccessService;
use App\Services\StockLotService;
use App\Services\WarehouseService;
use Illuminate\Support\ServiceProvider;

/**
 * Đăng ký binding Service Interface → Service cụ thể (Controller chỉ phụ thuộc interface).
 */
class ContractServiceProvider extends ServiceProvider
{
    /**
     * Các binding hợp đồng service.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        // Danh mục sản phẩm (catalog)
        BrandServiceInterface::class => BrandService::class,
        ProductCategoryServiceInterface::class => ProductCategoryService::class,
        ProductServiceInterface::class => ProductService::class,

        // Giá bán
        PriceTierServiceInterface::class => PriceTierService::class,
        PricingServiceInterface::class => PricingService::class,

        // Kho & tồn kho
        WarehouseServiceInterface::class => WarehouseService::class,
        ProductStockServiceInterface::class => ProductStockService::class,
        StockLotServiceInterface::class => StockLotService::class,

        // Bán hàng (CRM)
        CustomerServiceInterface::class => CustomerService::class,
        OrderServiceInterface::class => OrderService::class,
        PaymentMethodServiceInterface::class => PaymentMethodService::class,
        OrderReturnServiceInterface::class => OrderReturnService::class,
        CommissionEngineServiceInterface::class => CommissionEngineService::class,

        // Tài chính
        SupplierDebtServiceInterface::class => SupplierDebtService::class,

        // Phân quyền truy cập trang (dùng bởi middleware EnforcePageAccess)
        PageAccessServiceInterface::class => PageAccessService::class,

        /*
         * Seam ra hệ thống ngoài — tách interface để test thay được bằng fake,
         * không gọi thật ra API/filesystem.
         */
        CallioServiceInterface::class => CallioService::class,
        MediaUploadServiceInterface::class => MediaUploadService::class,
        NotificationServiceInterface::class => NotificationService::class,
    ];
}
