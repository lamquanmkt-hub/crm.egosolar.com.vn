<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Services\Debug\SchemaInspector;
use Illuminate\Http\JsonResponse;
/**
 * Các endpoint debug: xem cấu trúc bảng, user hiện tại, ping, xóa opcache.
 */
final class DebugController extends Controller
{
    /**
     * Khởi tạo controller với service kiểm tra schema.
     */
    public function __construct(private readonly SchemaInspector $inspector)
    {
        // Chặn debug ngoài local/dev (thêm middleware ở mục 5)
    }
    /**
     * Thông tin chi tiết các bảng thuộc nhóm product dạng JSON.
     */
    public function productTables(): JsonResponse
    {
        return response()->json(
            $this->inspector->tablesInfoByGroup('product')
        );
    }
    /**
     * Liệt kê các bảng có tên chứa "product".
     */
    public function productsCandidates(): JsonResponse
    {
        return response()->json([
            'candidates' => $this->inspector->tablesLike('product'),
        ]);
    }
    /**
     * Tóm tắt các bảng thuộc nhóm inventory dạng JSON.
     */
    public function inventoryTables(): JsonResponse
    {
        return response()->json(
            $this->inspector->tablesSummaryByGroup('inventory')
        );
    }
    /**
     * Thông tin user đang đăng nhập (id, email, roles) dạng JSON.
     */
    public function currentUser(): JsonResponse
    {
        $user = auth()->user();
        return response()->json([
            'id' => $user?->id,
            'email' => $user?->email,
            'roles' => $user?->getRoleNames() ?? [],
        ]);
    }
    /**
     * Kiểm tra route hoạt động, trả chuỗi OK.
     */
    public function ping(): string
    {
        return 'OK PING ROUTE';
    }
    /**
     * Liệt kê toàn bộ bảng trong database hiện tại dạng JSON.
     */
    public function allTables(): JsonResponse
    {
        $tables = $this->inspector->allTables();
        return response()->json([
            'db' => DB::getDatabaseName(),
            'tables_count' => count($tables),
            'tables' => $tables,
        ]);
    }
    /**
     * Xóa PHP opcache nếu đang bật.
     */
    public function clearOpcache(){
        if (function_exists('opcache_reset')) {
            opcache_reset();
            return 'Opcache cleared!';
        }
        return 'Opcache not enabled';
    }
}
