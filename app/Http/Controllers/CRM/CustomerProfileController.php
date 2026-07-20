<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\CustomerProfileDocument;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Quản lý hồ sơ đại lý/khách hàng: CRUD, giấy tờ đính kèm, đơn vị vận chuyển, xuất CSV.
 */
class CustomerProfileController extends Controller
{
    private array $statuses = [
        'draft' => 'Mới tạo',
        'deposit_pending' => 'Chờ đặt cọc',
        'deposited' => 'Đã đặt cọc',
        'deposit_refunded' => 'Hoàn cọc',
        'active' => 'Đang hợp tác',
        'need_documents' => 'Thiếu hồ sơ',
        'reviewing' => 'Đang rà soát',
        'paused' => 'Tạm dừng',
        'cancelled' => 'Hủy',
    ];

    private array $documentTypes = [
        'business_license' => 'ĐKKD / GPKD',
        'tax' => 'MST / Thuế',
        'id_card' => 'CCCD / Người đại diện',
        'deposit_receipt' => 'Phiếu đặt cọc',
        'contract' => 'Hợp đồng đại lý',
        'bank' => 'Tài khoản ngân hàng',
        'policy' => 'Chính sách / Cam kết',
        'other' => 'Khác',
    ];

    /**
     * Danh sách hồ sơ khách hàng với bộ lọc từ khóa, trạng thái, cấp đại lý, khoảng ngày cọc.
     */
    public function index(Request $request)
    {
        $this->ensureTables();

        $query = CustomerProfile::query()->orderByDesc('id');

        if ($q = trim((string) $request->get('q'))) {
            $query->where(function ($x) use ($q) {
                $x->where('agent_name', 'like', '%'.$q.'%')
                    ->orWhere('phone', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%')
                    ->orWhere('tax_code', 'like', '%'.$q.'%')
                    ->orWhere('contract_code', 'like', '%'.$q.'%')
                    ->orWhere('note', 'like', '%'.$q.'%');
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($level = $request->get('agent_level')) {
            $query->where('agent_level', $level);
        }

        if ($from = $request->get('from')) {
            $query->whereDate('deposit_date', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->whereDate('deposit_date', '<=', $to);
        }

        $profiles = $query->paginate(20)->withQueryString();
        $this->decorateProfiles($profiles->getCollection());

        $shippingUnits = $this->shippingUnits();

        $summary = $this->summary();
        $customers = $this->customersForSelect();
        $priceTiers = $this->priceTiers();
        $levels = CustomerProfile::query()
            ->whereNotNull('agent_level')
            ->where('agent_level', '!=', '')
            ->distinct()
            ->orderBy('agent_level')
            ->pluck('agent_level');

        return view('customer-profiles.index', [
            'profiles' => $profiles,
            'summary' => $summary,
            'customers' => $customers,
            'priceTiers' => $priceTiers,
            'levels' => $levels,
            'statuses' => $this->statuses,
            'documentTypes' => $this->documentTypes,
            'shippingUnits' => $shippingUnits,
            'filters' => $request->only(['q', 'status', 'agent_level', 'from', 'to']),
        ]);
    }

    /**
     * Form tạo hồ sơ mới, tự đổ sẵn thông tin nếu chọn từ khách hàng CRM.
     */
    public function create(Request $request)
    {
        $this->ensureTables();

        $profile = new CustomerProfile([
            'status' => 'draft',
            'deposit_amount' => 0,
            'deposit_date' => now()->toDateString(),
        ]);

        if ($request->filled('customer_id')) {
            $customer = $this->customerById((int) $request->get('customer_id'));
            if ($customer) {
                $profile->customer_id = $customer->id;
                $profile->agent_name = $customer->display_name;
                $profile->price_tier_id = $customer->price_tier_id ?? null;
                $profile->agent_level = $this->tierName($profile->price_tier_id) ?: '';
                $profile->phone = $customer->phone ?? null;
                $profile->email = $customer->email ?? null;
                $profile->address = $customer->address ?? null;
                $profile->tax_code = $customer->tax_code ?? null;
            }
        }

        return view('customer-profiles.create', [
            'profile' => $profile,
            'customers' => $this->customersForSelect(),
            'priceTiers' => $this->priceTiers(),
            'statuses' => $this->statuses,
            'documentTypes' => $this->documentTypes,
        ]);
    }

    /**
     * Tạo hồ sơ khách hàng mới kèm giấy tờ; nếu khách đã có hồ sơ thì chuyển tới hồ sơ đó.
     */
    public function store(Request $request)
    {
        $this->ensureTables();

        $data = $this->validatedData($request);
        $data['deposit_amount'] = $this->money($data['deposit_amount'] ?? 0);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        if (! empty($data['price_tier_id']) && empty($data['agent_level'])) {
            $data['agent_level'] = $this->tierName((int) $data['price_tier_id']);
        }

        if (! empty($data['customer_id'])) {
            $existing = CustomerProfile::where('customer_id', $data['customer_id'])->first();
            if ($existing) {
                return redirect()
                    ->route('customer-profiles.edit', $existing->id)
                    ->withErrors(['customer_id' => 'Khách hàng này đã có hồ sơ. Hệ thống đã mở hồ sơ đang có.']);
            }
        }

        $profile = CustomerProfile::create($data);
        $this->storeDocuments($profile, $request);

        return redirect()->route('customer-profiles.show', $profile)->with('success', 'Đã tạo hồ sơ khách hàng.');
    }

    /**
     * Trang chi tiết một hồ sơ khách hàng và danh sách giấy tờ.
     */
    public function show(CustomerProfile $customerProfile)
    {
        $this->decorateProfiles(collect([$customerProfile]));
        $documents = $customerProfile->documents()->orderByDesc('id')->get();

        return view('customer-profiles.show', [
            'profile' => $customerProfile,
            'documents' => $documents,
            'statuses' => $this->statuses,
            'documentTypes' => $this->documentTypes,
        ]);
    }

    /**
     * Form chỉnh sửa hồ sơ khách hàng.
     */
    public function edit(CustomerProfile $customerProfile)
    {
        $this->decorateProfiles(collect([$customerProfile]));

        return view('customer-profiles.edit', [
            'profile' => $customerProfile,
            'customers' => $this->customersForSelect(),
            'priceTiers' => $this->priceTiers(),
            'statuses' => $this->statuses,
            'documentTypes' => $this->documentTypes,
            'documents' => $customerProfile->documents()->orderByDesc('id')->get(),
        ]);
    }

    /**
     * Cập nhật hồ sơ khách hàng và lưu thêm giấy tờ mới nếu có.
     */
    public function update(Request $request, CustomerProfile $customerProfile)
    {
        $data = $this->validatedData($request, $customerProfile->id);
        $data['deposit_amount'] = $this->money($data['deposit_amount'] ?? 0);
        $data['updated_by'] = auth()->id();

        if (! empty($data['price_tier_id']) && empty($data['agent_level'])) {
            $data['agent_level'] = $this->tierName((int) $data['price_tier_id']);
        }

        $customerProfile->update($data);
        $this->storeDocuments($customerProfile, $request);

        return redirect()->route('customer-profiles.show', $customerProfile)->with('success', 'Đã cập nhật hồ sơ khách hàng.');
    }

    /**
     * Xóa hồ sơ khách hàng kèm toàn bộ giấy tờ và file vật lý.
     */
    public function destroy(CustomerProfile $customerProfile)
    {
        foreach ($customerProfile->documents as $document) {
            if ($document->file_path) {
                Storage::disk('public')->delete($document->file_path);
            }
            $document->delete();
        }

        $customerProfile->delete();

        return redirect()->route('customer-profiles.index')->with('success', 'Đã xóa hồ sơ khách hàng.');
    }

    /**
     * Tải thêm giấy tờ lên hồ sơ khách hàng.
     */
    public function storeDocument(Request $request, CustomerProfile $customerProfile)
    {
        $this->storeDocuments($customerProfile, $request);

        return back()->with('success', 'Đã tải giấy tờ lên hồ sơ.');
    }

    /**
     * Tải xuống một giấy tờ của hồ sơ (kiểm tra giấy tờ thuộc đúng hồ sơ).
     */
    public function downloadDocument(CustomerProfile $customerProfile, CustomerProfileDocument $document)
    {
        abort_unless((int) $document->customer_profile_id === (int) $customerProfile->id, 404);
        abort_unless(Storage::disk('public')->exists($document->file_path), 404);

        return Storage::disk('public')->download($document->file_path, $document->original_name);
    }

    /**
     * Xóa một giấy tờ khỏi hồ sơ (cả file vật lý).
     */
    public function destroyDocument(CustomerProfile $customerProfile, CustomerProfileDocument $document)
    {
        abort_unless((int) $document->customer_profile_id === (int) $customerProfile->id, 404);

        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return back()->with('success', 'Đã xóa giấy tờ.');
    }

    /**
     * Chuyển hàng loạt khách hàng CRM được chọn sang hồ sơ đại lý (bỏ qua khách đã có hồ sơ).
     */
    public function syncCustomers(Request $request)
    {
        $this->ensureTables();

        $selectedIds = collect((array) $request->input('customer_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            return back()->withErrors(['customer_ids' => 'Vui lòng tích chọn ít nhất 1 khách hàng để chuyển sang hồ sơ đại lý.']);
        }

        $customers = $this->customersForSelect(10000)
            ->whereIn('id', $selectedIds)
            ->values();

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($customers, &$created, &$skipped) {
            foreach ($customers as $customer) {
                if (! $customer->id) {
                    continue;
                }

                $exists = CustomerProfile::where('customer_id', $customer->id)->exists();
                if ($exists) {
                    $skipped++;

                    continue;
                }

                $tierId = $customer->price_tier_id ?? null;

                CustomerProfile::create([
                    'customer_id' => $customer->id,
                    'agent_name' => $customer->display_name,
                    'agent_level' => $this->tierName($tierId) ?: 'Chưa phân cấp',
                    'price_tier_id' => $tierId,
                    'deposit_amount' => 0,
                    'deposit_date' => null,
                    'status' => 'draft',
                    'phone' => $customer->phone ?? null,
                    'email' => $customer->email ?? null,
                    'address' => $customer->address ?? null,
                    'tax_code' => $customer->tax_code ?? null,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                $created++;
            }
        });

        return back()->with('success', 'Đã chuyển sang hồ sơ đại lý: tạo mới '.$created.' hồ sơ, bỏ qua '.$skipped.' khách đã có hồ sơ.');
    }

    /**
     * Danh sách đơn vị vận chuyển với tìm kiếm theo tên, SĐT, địa chỉ, tuyến.
     */
    public function shippingIndex(Request $request)
    {
        $this->ensureTables();

        $q = trim((string) $request->input('q', ''));

        $query = DB::table('customer_profile_shippings')->orderByDesc('id');

        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';

            $query->where(function ($x) use ($like) {
                $x->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('address', 'like', $like)
                    ->orWhere('route', 'like', $like)
                    ->orWhere('note', 'like', $like);
            });
        }

        $shippingUnits = $query->paginate(30)->withQueryString();

        return view('customer-profiles.shipping', [
            'shippingUnits' => $shippingUnits,
            'q' => $q,
        ]);
    }

    /**
     * Thêm đơn vị vận chuyển mới.
     */
    public function storeShipping(Request $request)
    {
        $this->ensureTables();

        $data = $this->validatedShippingData($request);

        DB::table('customer_profile_shippings')->insert([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'route' => $data['route'] ?? null,
            'note' => $data['note'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('customer-profiles.shipping.index')->with('success', 'Đã thêm đơn vị vận chuyển.');
    }

    /**
     * Cập nhật thông tin đơn vị vận chuyển.
     */
    public function updateShipping(Request $request, int $shipping)
    {
        $this->ensureTables();

        $data = $this->validatedShippingData($request);

        $exists = DB::table('customer_profile_shippings')->where('id', $shipping)->exists();
        abort_unless($exists, 404);

        DB::table('customer_profile_shippings')
            ->where('id', $shipping)
            ->update([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'route' => $data['route'] ?? null,
                'note' => $data['note'] ?? null,
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]);

        return redirect()->route('customer-profiles.shipping.index')->with('success', 'Đã cập nhật đơn vị vận chuyển.');
    }

    /**
     * Xóa đơn vị vận chuyển.
     */
    public function destroyShipping(int $shipping)
    {
        $this->ensureTables();

        DB::table('customer_profile_shippings')->where('id', $shipping)->delete();

        return redirect()->route('customer-profiles.shipping.index')->with('success', 'Đã xóa đơn vị vận chuyển.');
    }

    /**
     * Xuất toàn bộ hồ sơ khách hàng ra file CSV (có BOM UTF-8).
     */
    public function export(Request $request): StreamedResponse
    {
        $this->ensureTables();

        $profiles = CustomerProfile::query()->orderByDesc('id')->get();
        $this->decorateProfiles($profiles);

        $fileName = 'ho-so-khach-hang-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($profiles) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, [
                'ID',
                'Tên khách hàng',
                'Tên đại lý',
                'Cấp đại lý',
                'Tiền đặt cọc',
                'Ngày đặt cọc',
                'Trạng thái',
                'SĐT',
                'Email',
                'Mã HĐ',
                'Số giấy tờ',
                'Ghi chú',
            ]);

            foreach ($profiles as $profile) {
                fputcsv($out, [
                    $profile->id,
                    $profile->customer_name ?? '',
                    $profile->agent_name,
                    $profile->agent_level,
                    $profile->deposit_amount,
                    optional($profile->deposit_date)->format('Y-m-d'),
                    $this->statuses[$profile->status] ?? $profile->status,
                    $profile->phone,
                    $profile->email,
                    $profile->contract_code,
                    $profile->documents_count ?? 0,
                    $profile->note,
                ]);
            }

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Lấy danh sách đơn vị vận chuyển (trả rỗng nếu chưa có bảng).
     */
    private function shippingUnits()
    {
        if (! Schema::hasTable('customer_profile_shippings')) {
            return collect();
        }

        return DB::table('customer_profile_shippings')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Validate dữ liệu đơn vị vận chuyển.
     */
    private function validatedShippingData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
            'route' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * Validate dữ liệu hồ sơ khách hàng (dùng chung tạo mới và cập nhật).
     */
    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'agent_name' => ['required', 'string', 'max:255'],
            'agent_level' => ['nullable', 'string', 'max:255'],
            'price_tier_id' => ['nullable', 'integer'],
            'deposit_amount' => ['nullable', 'string', 'max:50'],
            'deposit_date' => ['nullable', 'date'],
            'status' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'tax_code' => ['nullable', 'string', 'max:120'],
            'representative_name' => ['nullable', 'string', 'max:255'],
            'representative_position' => ['nullable', 'string', 'max:255'],
            'contract_code' => ['nullable', 'string', 'max:255'],
            'contract_date' => ['nullable', 'date'],
            'next_followup_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,zip,rar'],
            'document_type' => ['nullable', 'string', 'max:80'],
            'document_note' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * Lưu các file giấy tờ upload kèm request vào hồ sơ (đặt tên an toàn, ghi bản ghi document).
     */
    private function storeDocuments(CustomerProfile $profile, Request $request): void
    {
        if (! $request->hasFile('documents')) {
            return;
        }

        foreach ((array) $request->file('documents') as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $safeBase = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $ext = $file->getClientOriginalExtension();
            $name = now()->format('YmdHis').'-'.Str::random(8).'-'.($safeBase ?: 'file').($ext ? '.'.$ext : '');
            $path = $file->storeAs('customer-profile-documents/'.$profile->id, $name, 'public');

            CustomerProfileDocument::create([
                'customer_profile_id' => $profile->id,
                'document_type' => $request->input('document_type', 'other') ?: 'other',
                'title' => $file->getClientOriginalName(),
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize() ?: 0,
                'note' => $request->input('document_note'),
                'uploaded_by' => auth()->id(),
            ]);
        }
    }

    /**
     * Kiểm tra các bảng bắt buộc và tự tạo bảng customer_profile_shippings nếu chưa có.
     */
    private function ensureTables(): void
    {
        abort_unless(Schema::hasTable('customer_profiles'), 500, 'Chưa có bảng customer_profiles. Vui lòng chạy php artisan migrate.');
        abort_unless(Schema::hasTable('customer_profile_documents'), 500, 'Chưa có bảng customer_profile_documents. Vui lòng chạy php artisan migrate.');

        if (! Schema::hasTable('customer_profile_shippings')) {
            Schema::create('customer_profile_shippings', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone', 80)->nullable();
                $table->string('address', 500)->nullable();
                $table->string('route', 255)->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->index('name');
                $table->index('phone');
                $table->index('route');
            });
        }
    }

    /**
     * Tìm tên bảng khách hàng đang tồn tại trong hệ thống (crm_customers/customers/clients).
     */
    private function customerTable(): ?string
    {
        foreach (['crm_customers', 'customers', 'clients'] as $table) {
            if (Schema::hasTable($table)) {
                return $table;
            }
        }

        return null;
    }

    /**
     * Lấy danh sách khách hàng cho ô chọn, tự dò tên cột (tên, SĐT, email, địa chỉ, MST, bậc giá).
     */
    private function customersForSelect(int $limit = 2000)
    {
        $table = $this->customerTable();
        if (! $table) {
            return collect();
        }

        $cols = Schema::getColumnListing($table);
        $nameCol = $this->firstColumn($cols, ['name', 'company_name', 'customer_name', 'full_name', 'contact_name']);
        $phoneCol = $this->firstColumn($cols, ['phone', 'mobile', 'tel', 'telephone']);
        $emailCol = $this->firstColumn($cols, ['email']);
        $addressCol = $this->firstColumn($cols, ['address', 'billing_address', 'company_address']);
        $taxCol = $this->firstColumn($cols, ['tax_code', 'tax_number', 'mst']);
        $priceTierCol = $this->firstColumn($cols, ['price_tier_id', 'tier_id']);

        return DB::table($table)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function ($row) use ($nameCol, $phoneCol, $emailCol, $addressCol, $taxCol, $priceTierCol) {
                $row->display_name = $nameCol ? (string) ($row->{$nameCol} ?? '') : ('Khách hàng #'.($row->id ?? ''));
                $row->phone = $phoneCol ? ($row->{$phoneCol} ?? null) : null;
                $row->email = $emailCol ? ($row->{$emailCol} ?? null) : null;
                $row->address = $addressCol ? ($row->{$addressCol} ?? null) : null;
                $row->tax_code = $taxCol ? ($row->{$taxCol} ?? null) : null;
                $row->price_tier_id = $priceTierCol ? ($row->{$priceTierCol} ?? null) : null;

                return $row;
            });
    }

    /**
     * Lấy một khách hàng theo id từ danh sách khách đã chuẩn hóa.
     */
    private function customerById(int $id): ?object
    {
        return $this->customersForSelect(10000)->firstWhere('id', $id);
    }

    /**
     * Danh sách bậc giá đang hoạt động (trả rỗng nếu chưa có bảng crm_price_tiers).
     */
    private function priceTiers()
    {
        if (! Schema::hasTable('crm_price_tiers')) {
            return collect();
        }

        return DB::table('crm_price_tiers')
            ->when(Schema::hasColumn('crm_price_tiers', 'is_active'), fn ($q) => $q->where('is_active', 1))
            ->orderBy(Schema::hasColumn('crm_price_tiers', 'priority') ? 'priority' : 'id')
            ->get();
    }

    /**
     * Lấy tên bậc giá theo id (null nếu không có).
     */
    private function tierName($tierId): ?string
    {
        if (! $tierId || ! Schema::hasTable('crm_price_tiers')) {
            return null;
        }

        return DB::table('crm_price_tiers')->where('id', (int) $tierId)->value('name');
    }

    /**
     * Gắn thêm thông tin hiển thị cho hồ sơ: tên/SĐT/email khách, tên bậc giá, số giấy tờ.
     */
    private function decorateProfiles($profiles): void
    {
        $customerIds = collect($profiles)->pluck('customer_id')->filter()->unique()->values();
        $customers = $this->customersForSelect(10000)->whereIn('id', $customerIds)->keyBy('id');
        $tierNames = $this->priceTiers()->keyBy('id');
        $documentCounts = CustomerProfileDocument::query()
            ->select('customer_profile_id', DB::raw('COUNT(*) as total'))
            ->whereIn('customer_profile_id', collect($profiles)->pluck('id')->all())
            ->groupBy('customer_profile_id')
            ->pluck('total', 'customer_profile_id');

        foreach ($profiles as $profile) {
            $customer = $customers->get($profile->customer_id);
            $profile->customer_name = $customer->display_name ?? null;
            $profile->customer_phone = $customer->phone ?? null;
            $profile->customer_email = $customer->email ?? null;
            $profile->price_tier_name = $profile->price_tier_id && $tierNames->has($profile->price_tier_id)
                ? ($tierNames[$profile->price_tier_id]->name ?? null)
                : null;
            $profile->documents_count = (int) ($documentCounts[$profile->id] ?? 0);
        }
    }

    /**
     * Số liệu tổng quan: tổng hồ sơ, đang hợp tác, đã cọc, thiếu hồ sơ, tổng tiền cọc, số giấy tờ.
     */
    private function summary(): array
    {
        return [
            'total' => CustomerProfile::count(),
            'active' => CustomerProfile::where('status', 'active')->count(),
            'deposited' => CustomerProfile::whereIn('status', ['deposited', 'active'])->count(),
            'need_documents' => CustomerProfile::where('status', 'need_documents')->count(),
            'deposit_total' => CustomerProfile::sum('deposit_amount'),
            'documents' => CustomerProfileDocument::count(),
        ];
    }

    /**
     * Trả về cột đầu tiên trong danh sách ứng viên có tồn tại trong bảng.
     */
    private function firstColumn(array $cols, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $cols, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Chuẩn hóa chuỗi tiền tệ (bỏ ký hiệu đ, khoảng trắng, phân tách nghìn) thành số float.
     */
    private function money($value): float
    {
        $value = trim((string) ($value ?? ''));
        $value = str_replace(["\xc2\xa0", ' ', 'đ', 'Đ', '₫'], '', $value);

        if ($value === '') {
            return 0;
        }

        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');

        if ($hasComma && $hasDot) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, '.') > 1) {
            $value = str_replace('.', '', $value);
        } elseif ($hasComma) {
            $value = str_replace(',', '.', $value);
        }

        $value = preg_replace('/[^0-9.\-]/', '', $value);

        if ($value === '' || $value === '-' || $value === '.') {
            return 0;
        }

        return round((float) $value, 2);
    }
}
