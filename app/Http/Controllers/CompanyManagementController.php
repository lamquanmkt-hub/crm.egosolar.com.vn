<?php

namespace App\Http\Controllers;

use App\Models\Core\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyManagementController extends Controller
{
    public function index(): View
    {
        $companies = Company::query()
            ->orderBy('id')
            ->paginate(20);

        return view('company_management.index', compact('companies'));
    }

    public function create(): View
    {
        $company = new Company();

        return view('company_management.create', compact('company'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCompany($request);
        $data['is_active'] = $request->boolean('is_active', true);

        $company = new Company();
        $company->forceFill($data);
        $company->save();

        return redirect()
            ->route('company-management.index')
            ->with('success', 'Đã thêm công ty mới.');
    }

    public function edit(Company $company): View
    {
        return view('company_management.edit', compact('company'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $this->validateCompany($request, $company->id);
        $data['is_active'] = $request->boolean('is_active');

        $company->forceFill($data);
        $company->save();

        return redirect()
            ->route('company-management.index')
            ->with('success', 'Đã cập nhật công ty.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $hasOrder = false;
        $hasWarehouse = false;
        $hasPivot = false;

        if (DB::getSchemaBuilder()->hasTable('crm_orders') && DB::getSchemaBuilder()->hasColumn('crm_orders', 'company_id')) {
            $hasOrder = DB::table('crm_orders')->where('company_id', $company->id)->exists();
        }

        if (DB::getSchemaBuilder()->hasTable('crm_warehouses') && DB::getSchemaBuilder()->hasColumn('crm_warehouses', 'company_id')) {
            $hasWarehouse = DB::table('crm_warehouses')->where('company_id', $company->id)->exists();
        }

        if (DB::getSchemaBuilder()->hasTable('company_warehouse')) {
            $hasPivot = DB::table('company_warehouse')->where('company_id', $company->id)->exists();
        }

        if ($hasOrder || $hasWarehouse || $hasPivot) {
            $company->forceFill(['is_active' => false]);
            $company->save();

            return redirect()
                ->route('company-management.index')
                ->with('success', 'Công ty đã có đơn/kho liên quan nên không xóa hẳn. Hệ thống đã tắt hoạt động công ty này.');
        }

        $company->delete();

        return redirect()
            ->route('company-management.index')
            ->with('success', 'Đã xóa công ty.');
    }

    private function validateCompany(Request $request, ?int $ignoreId = null): array
    {
        $uniqueCode = 'unique:companies,code';

        if ($ignoreId) {
            $uniqueCode .= ',' . $ignoreId;
        }

        return $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'code'         => ['required', 'string', 'max:50', $uniqueCode],
            'tax_code'     => ['nullable', 'string', 'max:100'],
            'phone'        => ['nullable', 'string', 'max:100'],
            'email'        => ['nullable', 'email', 'max:255'],
            'address'      => ['nullable', 'string', 'max:2000'],
            'bank_account' => ['nullable', 'string', 'max:255'],
            'bank_name'    => ['nullable', 'string', 'max:255'],
            'bank_holder'  => ['nullable', 'string', 'max:255'],
            'is_active'    => ['nullable', 'boolean'],
        ]);
    }
}