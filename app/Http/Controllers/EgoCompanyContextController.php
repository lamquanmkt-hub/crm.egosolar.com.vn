<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chọn công ty làm việc cho phiên đăng nhập (context công ty của CRM).
 */
class EgoCompanyContextController extends Controller
{
    /**
     * Trang chọn công ty: tự chọn nếu chỉ có 1, render HTML fallback nếu thiếu view.
     */
    public function select(Request $request)
    {
        $companies = collect();

        if (Schema::hasTable('companies')) {
            $q = DB::table('companies');

            if (Schema::hasColumn('companies', 'deleted_at')) {
                $q->whereNull('deleted_at');
            }

            $companies = $q->orderBy('id')->get();
        }

        // Nếu chỉ có 1 công ty thì tự chọn luôn, tránh vòng lặp
        if ($companies->count() === 1) {
            $this->putCompanySession($request, $companies->first()->id);

            return redirect('/');
        }

        // Nếu chưa có view thì render thẳng HTML, không redirect vòng lặp
        if (! view()->exists('company-context.select')) {
            $html = '<!doctype html><html><head><meta charset="utf-8"><title>Chọn công ty</title>
            <style>
                body{font-family:Arial;background:#f4f7fb;padding:40px}
                .box{max-width:520px;margin:80px auto;background:white;border-radius:16px;padding:28px;box-shadow:0 16px 45px rgba(15,23,42,.12)}
                h2{margin:0 0 10px;color:#0f172a}
                p{color:#64748b}
                button{width:100%;padding:14px;margin:8px 0;border:0;border-radius:12px;background:#06b6d4;color:#fff;font-weight:700;cursor:pointer}
                .muted{font-size:13px;color:#94a3b8}
            </style></head><body><div class="box"><h2>Chọn công ty làm việc</h2><p>Vui lòng chọn công ty để tiếp tục vào CRM.</p>';

            foreach ($companies as $company) {
                $name = $company->name ?? $company->company_name ?? ('Công ty #'.$company->id);
                $html .= '<form method="POST" action="/chon-cong-ty">'
                    .csrf_field()
                    .'<input type="hidden" name="company_id" value="'.(int) $company->id.'">'
                    .'<button type="submit">'.e($name).'</button></form>';
            }

            if ($companies->isEmpty()) {
                $html .= '<p class="muted">Không tìm thấy bảng companies hoặc chưa có dữ liệu công ty.</p>';
                $html .= '<a href="/">Vào trang chủ</a>';
            }

            $html .= '</div></body></html>';

            return response($html);
        }

        return view('company-context.select', compact('companies'));
    }

    /**
     * Lưu công ty được chọn vào session rồi chuyển về trang chủ.
     */
    public function store(Request $request)
    {
        $companyId = (int) $request->input('company_id');

        if ($companyId <= 0 && Schema::hasTable('companies')) {
            $companyId = (int) DB::table('companies')->orderBy('id')->value('id');
        }

        if ($companyId > 0) {
            $this->putCompanySession($request, $companyId);
        }

        return redirect('/');
    }

    /**
     * Xóa công ty đang chọn khỏi session và quay lại trang chọn công ty.
     */
    public function reset(Request $request)
    {
        foreach ($this->sessionKeys() as $key) {
            $request->session()->forget($key);
        }

        return redirect('/chon-cong-ty');
    }

    /**
     * Ghi id công ty vào tất cả các khóa session tương thích và lưu ngay.
     */
    private function putCompanySession(Request $request, int $companyId): void
    {
        foreach ($this->sessionKeys() as $key) {
            $request->session()->put($key, $companyId);
        }

        $request->session()->save();
    }

    /**
     * Danh sách các khóa session dùng để lưu công ty đang chọn.
     */
    private function sessionKeys(): array
    {
        return [
            'company_id',
            'selected_company_id',
            'current_company_id',
            'ego_company_id',
            'active_company_id',
        ];
    }
}
