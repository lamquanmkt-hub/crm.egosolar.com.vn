<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Services\Debug\SchemaInspector;
use Illuminate\Http\JsonResponse;
final class DebugController extends Controller
{
    public function __construct(private readonly SchemaInspector $inspector)
    {
        // Chặn debug ngoài local/dev (thêm middleware ở mục 5)
    }
    public function productTables(): JsonResponse
    {
        return response()->json(
            $this->inspector->tablesInfoByGroup('product')
        );
    }
    public function productsCandidates(): JsonResponse
    {
        return response()->json([
            'candidates' => $this->inspector->tablesLike('product'),
        ]);
    }
    public function inventoryTables(): JsonResponse
    {
        return response()->json(
            $this->inspector->tablesSummaryByGroup('inventory')
        );
    }
    public function currentUser(): JsonResponse
    {
        $user = auth()->user();
        return response()->json([
            'id' => $user?->id,
            'email' => $user?->email,
            'roles' => $user?->getRoleNames() ?? [],
        ]);
    }
    public function ping(): string
    {
        return 'OK PING ROUTE';
    }
    public function allTables(): JsonResponse
    {
        $tables = $this->inspector->allTables();
        return response()->json([
            'db' => DB::getDatabaseName(),
            'tables_count' => count($tables),
            'tables' => $tables,
        ]);
    }
    public function clearOpcache(){
        if (function_exists('opcache_reset')) {
            opcache_reset();
            return 'Opcache cleared!';
        }
        return 'Opcache not enabled';
    }
}
