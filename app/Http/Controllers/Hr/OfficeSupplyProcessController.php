<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OfficeSupplyProcessController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureTables();

        $productQuery = DB::table('hr_vpp_products');

        if ($request->filled('q')) {
            $q = trim($request->q);
            $productQuery->where(function ($x) use ($q) {
                $x->where('name', 'like', "%{$q}%")
                  ->orWhere('sku', 'like', "%{$q}%")
                  ->orWhere('note', 'like', "%{$q}%");
            });
        }

        $products = $productQuery
            ->orderBy('name')
            ->get();

        $requests = DB::table('hr_vpp_requests as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.created_by')
            ->select('r.*', 'u.name as creator_name')
            ->selectSub(function ($q) {
                $q->from('hr_vpp_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('hr_vpp_items.request_id', 'r.id');
            }, 'items_count')
            ->selectSub(function ($q) {
                $q->from('hr_vpp_items')
                    ->selectRaw('COALESCE(SUM(issued_qty), 0)')
                    ->whereColumn('hr_vpp_items.request_id', 'r.id');
            }, 'total_issued')
            ->orderByDesc('r.created_at')
            ->limit(20)
            ->get();

        $movements = DB::table('hr_vpp_movements as m')
            ->leftJoin('hr_vpp_products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->select('m.*', 'p.name as product_name', 'p.unit', 'u.name as user_name')
            ->orderByDesc('m.moved_at')
            ->orderByDesc('m.id')
            ->limit(30)
            ->get();

        $startMonth = now()->startOfMonth();

        $stats = [
            'products' => DB::table('hr_vpp_products')->count(),
            'stock' => DB::table('hr_vpp_products')->sum('current_stock'),
            'in_month' => DB::table('hr_vpp_movements')->where('type', 'in')->where('moved_at', '>=', $startMonth)->sum('qty'),
            'out_month' => DB::table('hr_vpp_movements')->where('type', 'out')->where('moved_at', '>=', $startMonth)->sum('qty'),
            'low_stock' => DB::table('hr_vpp_products')->whereColumn('current_stock', '<=', 'min_stock')->count(),
        ];

        return view('hr.office-supply-process.index', compact(
            'products',
            'requests',
            'movements',
            'stats'
        ));
    }

    public function productStore(Request $request)
    {
        $this->ensureTables();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:50'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'initial_stock' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data) {
            $name = trim($data['name']);
            $unit = trim($data['unit'] ?: 'cái');
            $existing = $this->findSameVppProduct($name, $unit);

            if ($existing) {
                $id = $existing->id;

                DB::table('hr_vpp_products')->where('id', $id)->update([
                    'sku' => $data['sku'] ?: $existing->sku,
                    'min_stock' => $data['min_stock'] ?? $existing->min_stock,
                    'note' => $data['note'] ?? $existing->note,
                    'updated_at' => now(),
                ]);
            } else {
                $id = DB::table('hr_vpp_products')->insertGetId([
                    'name' => $name,
                    'sku' => $data['sku'] ?? null,
                    'unit' => $unit,
                    'current_stock' => 0,
                    'min_stock' => $data['min_stock'] ?? 0,
                    'note' => $data['note'] ?? null,
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $initial = (float) ($data['initial_stock'] ?? 0);

            if ($initial > 0) {
                $this->moveStock($id, 'in', $initial, [
                    'reason' => $existing ? 'Cộng tồn vật phẩm đã có' : 'Tồn đầu kỳ',
                    'note' => $existing ? 'Vật phẩm trùng tên, hệ thống cộng vào mã cũ' : 'Khởi tạo vật phẩm mới',
                    'receiver_name' => null,
                    'department_name' => null,
                    'request_id' => null,
                ]);
            }
        });

        return back()->with('success', 'Đã lưu vật phẩm VPP. Nếu trùng tên, hệ thống đã cộng vào mã cũ.');
    }

    public function importStock(Request $request)
    {
        $this->ensureTables();

        $data = $request->validate([
            'product_id' => ['nullable', 'integer'],
            'new_product_name' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'qty' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data) {
            $productId = $data['product_id'] ?? null;

            if (!$productId && !empty($data['new_product_name'])) {
                $name = trim($data['new_product_name']);
                $unit = trim($data['unit'] ?? 'cái');

                $existing = $this->findSameVppProduct($name, $unit);

                if ($existing) {
                    $productId = $existing->id;
                } else {
                    $productId = DB::table('hr_vpp_products')->insertGetId([
                        'name' => $name,
                        'unit' => $unit,
                        'current_stock' => 0,
                        'min_stock' => 0,
                        'created_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            abort_if(!$productId, 422, 'Vui lòng chọn vật phẩm hoặc nhập tên vật phẩm mới.');

            $this->moveStock($productId, 'in', (float) $data['qty'], [
                'reason' => $data['reason'] ?? 'Nhập VPP',
                'note' => $data['note'] ?? null,
                'receiver_name' => null,
                'department_name' => null,
                'request_id' => null,
            ]);
        });

        return back()->with('success', 'Đã lưu nhập VPP. Nếu trùng tên, hệ thống đã cộng vào vật phẩm cũ.');
    }

    public function allocateStock(Request $request)
    {
        $this->ensureTables();

        $data = $request->validate([
            'department_name' => ['required', 'string', 'max:255'],
            'receiver_name' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        $items = $this->normalizeAllocateItems($request);

        if (count($items) === 0) {
            return back()->withInput()->withErrors(['items' => 'Cần chọn ít nhất 1 vật phẩm để phân bổ.']);
        }

        $stockErrors = [];

        foreach ($items as $item) {
            $product = DB::table('hr_vpp_products')->where('id', $item['product_id'])->first();

            if (!$product) {
                $stockErrors[] = 'Không tìm thấy vật phẩm ID ' . $item['product_id'];
                continue;
            }

            if ((float) $product->current_stock < (float) $item['qty']) {
                $stockErrors[] = $product->name . ' chỉ còn ' . $product->current_stock . ' ' . $product->unit . ', không đủ xuất ' . $item['qty'];
            }
        }

        if ($stockErrors) {
            return back()->withInput()->withErrors(['stock' => implode(' | ', $stockErrors)]);
        }

        DB::transaction(function () use ($data, $items) {
            $requestId = DB::table('hr_vpp_requests')->insertGetId([
                'code' => $this->makeCode(),
                'department_name' => $data['department_name'],
                'requester_name' => $data['receiver_name'],
                'requested_month' => now()->format('Y-m'),
                'purpose' => $data['purpose'] ?? null,
                'note' => $data['note'] ?? null,
                'status' => 'completed',
                'receiver_name' => $data['receiver_name'],
                'received_note' => 'Đã phân bổ và trừ kho',
                'received_by' => Auth::id(),
                'received_at' => now(),
                'completed_note' => 'Hoàn tất phân bổ VPP',
                'completed_at' => now(),
                'created_by' => Auth::id(),
                'submitted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($items as $item) {
                $product = DB::table('hr_vpp_products')->where('id', $item['product_id'])->first();

                DB::table('hr_vpp_items')->insert([
                    'request_id' => $requestId,
                    'product_id' => $item['product_id'],
                    'item_name' => $product->name,
                    'unit' => $product->unit,
                    'requested_qty' => $item['qty'],
                    'hr_qty' => $item['qty'],
                    'issued_qty' => $item['qty'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->moveStock($item['product_id'], 'out', (float) $item['qty'], [
                    'reason' => 'Phân bổ VPP',
                    'note' => $data['note'] ?? null,
                    'receiver_name' => $data['receiver_name'],
                    'department_name' => $data['department_name'],
                    'request_id' => $requestId,
                ]);
            }

            $this->log($requestId, null, 'completed', 'Phân bổ VPP và trừ tồn kho', $data['note'] ?? null);
        });

        return back()->with('success', 'Đã phân bổ VPP. Tồn kho đã tự động trừ.');
    }


    public function detailJson($id)
    {
        $this->ensureTables();

        $requestRow = DB::table('hr_vpp_requests as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.created_by')
            ->select('r.*', 'u.name as creator_name')
            ->where('r.id', $id)
            ->first();

        abort_unless($requestRow, 404);

        $items = DB::table('hr_vpp_items as i')
            ->leftJoin('hr_vpp_products as p', 'p.id', '=', 'i.product_id')
            ->select('i.*', 'p.current_stock')
            ->where('i.request_id', $id)
            ->orderBy('i.id')
            ->get();

        $movements = DB::table('hr_vpp_movements as m')
            ->leftJoin('hr_vpp_products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->select('m.*', 'p.name as product_name', 'p.unit', 'u.name as user_name')
            ->where('m.request_id', $id)
            ->orderByDesc('m.id')
            ->get();

        return response()->json([
            'request' => $requestRow,
            'items' => $items,
            'movements' => $movements,
        ]);
    }

    public function show($id)
    {
        $this->ensureTables();

        $requestRow = DB::table('hr_vpp_requests as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.created_by')
            ->select('r.*', 'u.name as creator_name')
            ->where('r.id', $id)
            ->first();

        abort_unless($requestRow, 404);

        $items = DB::table('hr_vpp_items as i')
            ->leftJoin('hr_vpp_products as p', 'p.id', '=', 'i.product_id')
            ->select('i.*', 'p.current_stock')
            ->where('i.request_id', $id)
            ->orderBy('i.id')
            ->get();

        $movements = DB::table('hr_vpp_movements as m')
            ->leftJoin('hr_vpp_products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->select('m.*', 'p.name as product_name', 'p.unit', 'u.name as user_name')
            ->where('m.request_id', $id)
            ->orderByDesc('m.id')
            ->get();

        return view('hr.office-supply-process.show', compact('requestRow', 'items', 'movements'));
    }

    public function destroy($id)
    {
        $this->ensureTables();

        $requestRow = DB::table('hr_vpp_requests')->where('id', $id)->first();
        abort_unless($requestRow, 404);

        DB::transaction(function () use ($id) {
            $outs = DB::table('hr_vpp_movements')
                ->where('request_id', $id)
                ->where('type', 'out')
                ->get();

            foreach ($outs as $out) {
                $this->moveStock($out->product_id, 'in', (float) $out->qty, [
                    'reason' => 'Hoàn kho do huỷ phiếu phân bổ',
                    'note' => 'Tự động cộng lại tồn kho khi xoá phiếu',
                    'receiver_name' => $out->receiver_name,
                    'department_name' => $out->department_name,
                    'request_id' => null,
                ]);
            }

            DB::table('hr_vpp_movements')->where('request_id', $id)->delete();
            DB::table('hr_vpp_logs')->where('request_id', $id)->delete();
            DB::table('hr_vpp_items')->where('request_id', $id)->delete();
            DB::table('hr_vpp_requests')->where('id', $id)->delete();
        });

        return redirect()->route('hr.office-supply-process.index')
            ->with('success', 'Đã xoá phiếu và hoàn lại tồn kho.');
    }


    public function productUpdate(Request $request, $id)
    {
        $this->ensureTables();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:50'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        DB::table('hr_vpp_products')->where('id', $id)->update([
            'name' => trim($data['name']),
            'sku' => $data['sku'] ?? null,
            'unit' => trim($data['unit']),
            'min_stock' => $data['min_stock'] ?? 0,
            'note' => $data['note'] ?? null,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã cập nhật vật phẩm VPP.');
    }


    public function productDestroy($id)
    {
        $this->ensureTables();

        $product = DB::table('hr_vpp_products')->where('id', $id)->first();
        abort_unless($product, 404);

        DB::transaction(function () use ($id) {
            if (Schema::hasTable('hr_vpp_items') && Schema::hasColumn('hr_vpp_items', 'product_id')) {
                DB::table('hr_vpp_items')->where('product_id', $id)->delete();
            }

            if (Schema::hasTable('hr_vpp_movements')) {
                DB::table('hr_vpp_movements')->where('product_id', $id)->delete();
            }

            DB::table('hr_vpp_products')->where('id', $id)->delete();
        });

        return back()->with('success', 'Đã xoá vật phẩm VPP.');
    }

    public function history()
    {
        $this->ensureTables();

        $movements = DB::table('hr_vpp_movements as m')
            ->leftJoin('hr_vpp_products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->select('m.*', 'p.name as product_name', 'p.unit', 'u.name as user_name')
            ->orderByDesc('m.moved_at')
            ->paginate(50);

        return view('hr.office-supply-process.history', compact('movements'));
    }

    public function store(Request $request)
    {
        return $this->allocateStock($request);
    }

    public function update(Request $request, $id)
    {
        return back()->with('success', 'Module mới dùng phân bổ trực tiếp từ kho, không cần sửa quy trình chữ.');
    }

    public function hrReview(Request $request, $id)
    {
        return back()->with('success', 'Module mới đã chuyển sang quản lý kho và phân bổ trực tiếp.');
    }

    public function approve(Request $request, $id)
    {
        return back()->with('success', 'Module mới đã chuyển sang quản lý kho và phân bổ trực tiếp.');
    }

    public function reject(Request $request, $id)
    {
        return back()->with('success', 'Module mới đã chuyển sang quản lý kho và phân bổ trực tiếp.');
    }

    public function issue(Request $request, $id)
    {
        return back()->with('success', 'Module mới đã chuyển sang quản lý kho và phân bổ trực tiếp.');
    }

    public function receive(Request $request, $id)
    {
        return back()->with('success', 'Module mới đã chuyển sang quản lý kho và phân bổ trực tiếp.');
    }

    public function complete(Request $request, $id)
    {
        return back()->with('success', 'Module mới đã chuyển sang quản lý kho và phân bổ trực tiếp.');
    }

    private function normalizeAllocateItems(Request $request): array
    {
        $productIds = $request->input('product_id', []);
        $qtys = $request->input('qty', []);

        $items = [];

        foreach ($productIds as $i => $productId) {
            if (!$productId) {
                continue;
            }

            $qty = (float) ($qtys[$i] ?? 0);

            if ($qty <= 0) {
                continue;
            }

            $items[] = [
                'product_id' => (int) $productId,
                'qty' => $qty,
            ];
        }

        return $items;
    }

    private function moveStock(int $productId, string $type, float $qty, array $meta): void
    {
        $product = DB::table('hr_vpp_products')
            ->where('id', $productId)
            ->lockForUpdate()
            ->first();

        abort_unless($product, 404, 'Không tìm thấy vật phẩm trong kho.');

        $before = (float) $product->current_stock;
        $after = $type === 'in' ? $before + $qty : $before - $qty;

        if ($after < 0) {
            abort(422, 'Tồn kho không đủ để xuất.');
        }

        DB::table('hr_vpp_products')
            ->where('id', $productId)
            ->update([
                'current_stock' => $after,
                'updated_at' => now(),
            ]);

        DB::table('hr_vpp_movements')->insert([
            'product_id' => $productId,
            'request_id' => $meta['request_id'] ?? null,
            'type' => $type,
            'qty' => $qty,
            'before_qty' => $before,
            'after_qty' => $after,
            'department_name' => $meta['department_name'] ?? null,
            'receiver_name' => $meta['receiver_name'] ?? null,
            'reason' => $meta['reason'] ?? null,
            'note' => $meta['note'] ?? null,
            'user_id' => Auth::id(),
            'moved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function log(int $requestId, ?string $from, string $to, string $action, ?string $note = null): void
    {
        if (!Schema::hasTable('hr_vpp_logs')) {
            return;
        }

        DB::table('hr_vpp_logs')->insert([
            'request_id' => $requestId,
            'from_status' => $from,
            'to_status' => $to,
            'action' => $action,
            'note' => $note,
            'user_id' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeCode(): string
    {
        $next = ((int) DB::table('hr_vpp_requests')->max('id')) + 1;

        return 'VPP-' . now()->format('Y') . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }


    private function findSameVppProduct(?string $name, ?string $unit = null)
    {
        $name = trim((string) $name);
        $unit = trim((string) ($unit ?: 'cái'));

        if ($name === '') {
            return null;
        }

        return DB::table('hr_vpp_products')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
            ->whereRaw('LOWER(TRIM(unit)) = ?', [mb_strtolower($unit)])
            ->first();
    }

    private function ensureTables(): void
    {
        if (!Schema::hasTable('hr_vpp_products')) {
            Schema::create('hr_vpp_products', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('sku', 100)->nullable();
                $table->string('unit', 50)->default('cái');
                $table->decimal('current_stock', 12, 2)->default(0);
                $table->decimal('min_stock', 12, 2)->default(0);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hr_vpp_requests')) {
            Schema::create('hr_vpp_requests', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('department_name');
                $table->string('requester_name')->nullable();
                $table->string('requested_month', 20)->nullable();
                $table->text('purpose')->nullable();
                $table->text('note')->nullable();
                $table->string('status', 50)->default('completed')->index();
                $table->string('receiver_name')->nullable();
                $table->text('received_note')->nullable();
                $table->unsignedBigInteger('received_by')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->text('completed_note')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('hr_vpp_requests', 'receiver_name')) {
            Schema::table('hr_vpp_requests', function (Blueprint $table) {
                $table->string('receiver_name')->nullable()->after('status');
            });
        }

        if (!Schema::hasColumn('hr_vpp_requests', 'received_note')) {
            Schema::table('hr_vpp_requests', function (Blueprint $table) {
                $table->text('received_note')->nullable()->after('receiver_name');
            });
        }

        if (!Schema::hasColumn('hr_vpp_requests', 'received_by')) {
            Schema::table('hr_vpp_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('received_by')->nullable()->after('received_note');
            });
        }

        if (!Schema::hasColumn('hr_vpp_requests', 'received_at')) {
            Schema::table('hr_vpp_requests', function (Blueprint $table) {
                $table->timestamp('received_at')->nullable()->after('received_by');
            });
        }

        if (!Schema::hasColumn('hr_vpp_requests', 'completed_note')) {
            Schema::table('hr_vpp_requests', function (Blueprint $table) {
                $table->text('completed_note')->nullable()->after('received_at');
            });
        }

        if (!Schema::hasColumn('hr_vpp_requests', 'completed_at')) {
            Schema::table('hr_vpp_requests', function (Blueprint $table) {
                $table->timestamp('completed_at')->nullable()->after('completed_note');
            });
        }

        if (!Schema::hasTable('hr_vpp_items')) {
            Schema::create('hr_vpp_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id')->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('item_name');
                $table->string('unit', 50)->nullable();
                $table->decimal('requested_qty', 12, 2)->default(0);
                $table->decimal('hr_qty', 12, 2)->nullable();
                $table->decimal('issued_qty', 12, 2)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('hr_vpp_items', 'product_id')) {
            Schema::table('hr_vpp_items', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable()->index()->after('request_id');
            });
        }

        if (!Schema::hasTable('hr_vpp_movements')) {
            Schema::create('hr_vpp_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('request_id')->nullable()->index();
                $table->string('type', 20)->index();
                $table->decimal('qty', 12, 2)->default(0);
                $table->decimal('before_qty', 12, 2)->default(0);
                $table->decimal('after_qty', 12, 2)->default(0);
                $table->string('department_name')->nullable();
                $table->string('receiver_name')->nullable();
                $table->string('reason')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('moved_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hr_vpp_logs')) {
            Schema::create('hr_vpp_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id')->index();
                $table->string('from_status', 50)->nullable();
                $table->string('to_status', 50)->nullable();
                $table->string('action')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }
    }
}
