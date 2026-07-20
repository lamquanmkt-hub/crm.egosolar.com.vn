<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Controller quản lý chi phí văn phòng và các hạng mục chi phí.
 */
class OfficeExpenseController extends Controller
{
    /**
     * Hiển thị danh sách chi phí văn phòng theo tháng / hạng mục kèm thống kê tổng hợp.
     */
    public function index(Request $request)
    {
        $this->ensureDefaultCategories();

        $month = $request->input('month', now()->format('Y-m'));
        $category = $request->input('category');

        $categories = $this->categories();

        $query = DB::table('hr_office_expenses');

        if ($month) {
            $query->whereRaw("DATE_FORMAT(expense_date, '%Y-%m') = ?", [$month]);
        }

        if ($category) {
            $query->where('category', $category);
        }

        $expenses = $query->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $total = (float) $expenses->sum('amount');

        $categoryStats = collect($categories)->map(function ($label, $key) use ($expenses) {
            return [
                'key' => $key,
                'label' => $label,
                'total' => (float) $expenses->where('category', $key)->sum('amount'),
                'count' => $expenses->where('category', $key)->count(),
            ];
        })->values();

        $topCategory = $categoryStats->sortByDesc('total')->first();

        return view('hr.office-expenses.index', compact(
            'expenses',
            'total',
            'month',
            'category',
            'categories',
            'categoryStats',
            'topCategory'
        ));
    }

    /**
     * Thêm mới một khoản chi phí văn phòng.
     */
    public function store(Request $request)
    {
        $this->ensureDefaultCategories();

        $data = $request->validate([
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:190'],
            'amount' => ['required'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless(array_key_exists($data['category'], $this->categories()), 422);

        DB::table('hr_office_expenses')->insert([
            'expense_date' => $data['expense_date'],
            'category' => $data['category'],
            'title' => trim($data['title']),
            'amount' => $this->moneyToNumber($data['amount']),
            'note' => $data['note'] ?? null,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã thêm chi phí văn phòng.');
    }

    /**
     * Thêm hạng mục chi phí mới với slug duy nhất.
     */
    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $name = trim($data['name']);
        $baseSlug = Str::slug($name, '_') ?: 'hang_muc';
        $slug = $baseSlug;
        $i = 2;

        while (DB::table('hr_office_expense_categories')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '_' . $i;
            $i++;
        }

        DB::table('hr_office_expense_categories')->insert([
            'name' => $name,
            'slug' => $slug,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã thêm hạng mục chi phí.');
    }

    /**
     * Xoá hạng mục chi phí nếu chưa có khoản chi nào sử dụng.
     *
     * @param int|string $id ID hạng mục
     */
    public function destroyCategory($id)
    {
        $category = DB::table('hr_office_expense_categories')->where('id', (int) $id)->first();

        abort_unless($category, 404);

        $used = DB::table('hr_office_expenses')->where('category', $category->slug)->exists();

        if ($used) {
            return back()->withErrors(['category' => 'Hạng mục này đã có chi phí, không thể xóa.']);
        }

        DB::table('hr_office_expense_categories')->where('id', (int) $id)->delete();

        return back()->with('success', 'Đã xóa hạng mục chi phí.');
    }

    /**
     * Xoá một khoản chi phí văn phòng theo ID.
     *
     * @param int|string $id ID khoản chi
     */
    public function destroy($id)
    {
        DB::table('hr_office_expenses')->where('id', (int) $id)->delete();

        return back()->with('success', 'Đã xóa chi phí văn phòng.');
    }

    /**
     * Lấy danh sách hạng mục chi phí (slug => tên); dùng danh sách mặc định nếu chưa có bảng.
     *
     * @return array<string, string>
     */
    private function categories(): array
    {
        if (!Schema::hasTable('hr_office_expense_categories')) {
            return [
                'van_phong_pham' => 'Văn phòng phẩm',
                'nuoc_uong_tiep_khach' => 'Nước uống / tiếp khách',
                'ship_gui_hang' => 'Ship / gửi hàng',
                've_sinh_tap_vu' => 'Vệ sinh / tạp vụ',
                'thiet_bi_van_phong' => 'Thiết bị văn phòng',
                'bao_tri_sua_chua' => 'Bảo trì / sửa chữa',
                'khac' => 'Khác',
            ];
        }

        return DB::table('hr_office_expense_categories')
            ->orderBy('id')
            ->pluck('name', 'slug')
            ->toArray();
    }

    /**
     * Tạo sẵn các hạng mục chi phí mặc định nếu bảng đang trống.
     */
    private function ensureDefaultCategories(): void
    {
        if (!Schema::hasTable('hr_office_expense_categories')) {
            return;
        }

        if (DB::table('hr_office_expense_categories')->count() > 0) {
            return;
        }

        foreach ([
            'Văn phòng phẩm',
            'Nước uống / tiếp khách',
            'Ship / gửi hàng',
            'Vệ sinh / tạp vụ',
            'Thiết bị văn phòng',
            'Bảo trì / sửa chữa',
            'Khác',
        ] as $name) {
            DB::table('hr_office_expense_categories')->insert([
                'name' => $name,
                'slug' => Str::slug($name, '_'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Chuyển chuỗi tiền tệ (có đ, dấu phẩy, khoảng trắng) về số float.
     *
     * @param mixed $value Giá trị tiền nhập vào
     */
    private function moneyToNumber($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        $value = str_replace(['đ', ' ', ','], ['', '', ''], $value);
        $value = preg_replace('/[^0-9.]/', '', $value);

        return $value === '' ? 0 : (float) $value;
    }
}
