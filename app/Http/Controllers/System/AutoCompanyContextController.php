<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final class AutoCompanyContextController extends Controller
{
    /**
     * Không hiển thị trang chọn công ty.
     *
     * Thứ tự ưu tiên:
     * 1. Công ty đã dùng gần nhất lưu trong cookie.
     * 2. Công ty mặc định trên tài khoản.
     * 3. Công ty được gán trực tiếp cho tài khoản.
     * 4. EGO Quốc Tế.
     * 5. Công ty hoạt động đầu tiên mà controller cũ chấp nhận.
     */
    public function __invoke(Request $request)
    {
        $legacy = $this->legacyController();

        foreach ($this->candidateCompanyIds($request) as $companyId) {
            try {
                $attempt = clone $request;

                $attempt->merge([
                    'company_id' => $companyId,
                ]);

                $response = $legacy->store($attempt);

                $this->rememberCompany($request, $companyId);

                return $response;
            } catch (Throwable $exception) {
                /*
                 * Công ty có thể không thuộc phạm vi tài khoản hiện tại.
                 * Thử công ty tiếp theo bằng chính logic kiểm tra của
                 * controller đang chạy trong hệ thống.
                 */
                continue;
            }
        }

        /*
         * Chỉ hiện trang cũ trong trường hợp không có công ty hợp lệ.
         * Trường hợp bình thường người dùng sẽ không thấy trang này.
         */
        return $legacy->select($request);
    }

    /**
     * Proxy cho thao tác đổi công ty trong sidebar.
     * Sau khi đổi sẽ lưu lại để lần đăng nhập sau tự mở đúng công ty.
     */
    public function store(Request $request)
    {
        $response = $this->legacyController()->store($request);

        $companyId = (int) $request->input('company_id');

        if ($companyId > 0) {
            $this->rememberCompany($request, $companyId);
        }

        return $response;
    }

    private function legacyController(): object
    {
        $classes = [
            \App\Http\Controllers\System\EgoCompanyContextController::class,
            \App\Http\Controllers\EgoCompanyContextController::class,
        ];

        foreach ($classes as $class) {
            if (class_exists($class)) {
                return app($class);
            }
        }

        abort(
            500,
            'Không tìm thấy EgoCompanyContextController hiện tại.'
        );
    }

    /**
     * @return array<int>
     */
    private function candidateCompanyIds(Request $request): array
    {
        $user = $request->user();
        $ids = [];

        $rememberedId = (int) $request->cookie(
            'ego_last_company_id',
            0
        );

        if ($rememberedId > 0) {
            $ids[] = $rememberedId;
        }

        if ($user) {
            foreach ([
                'default_company_id',
                'company_id',
                'current_company_id',
                'active_company_id',
            ] as $field) {
                $value = (int) ($user->{$field} ?? 0);

                if ($value > 0) {
                    $ids[] = $value;
                }
            }

            /*
             * Lấy các công ty đã gán cho user nếu model có relationship.
             */
            foreach (['companies', 'company'] as $relation) {
                try {
                    if (! method_exists($user, $relation)) {
                        continue;
                    }

                    $relationship = $user->{$relation}();

                    $relationIds = $relationship
                        ->get()
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->filter(fn (int $id): bool => $id > 0)
                        ->all();

                    array_push($ids, ...$relationIds);
                } catch (Throwable) {
                    // Tiếp tục bằng bảng companies.
                }
            }
        }

        array_push($ids, ...$this->databaseCompanyIds());

        return collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ưu tiên EGO Quốc Tế rồi mới tới các công ty còn lại.
     *
     * @return array<int>
     */
    private function databaseCompanyIds(): array
    {
        if (! Schema::hasTable('companies')) {
            return [];
        }

        $columns = Schema::getColumnListing('companies');
        $select = ['id'];

        foreach (['name', 'code', 'short_name'] as $column) {
            if (in_array($column, $columns, true)) {
                $select[] = $column;
            }
        }

        $query = DB::table('companies')->select($select);

        if (in_array('is_active', $columns, true)) {
            $query->where('is_active', true);
        } elseif (in_array('active', $columns, true)) {
            $query->where('active', true);
        }

        return $query
            ->get()
            ->sortBy(function ($company): int {
                $text = Str::ascii(Str::upper(
                    implode(' ', [
                        $company->code ?? '',
                        $company->name ?? '',
                        $company->short_name ?? '',
                    ])
                ));

                if (
                    str_contains($text, 'EGO_QT')
                    || str_contains($text, 'EGO QT')
                    || str_contains($text, 'QUOC TE')
                    || str_contains($text, 'INTERNATIONAL')
                ) {
                    return 0;
                }

                if (
                    str_contains($text, 'EGO_VN')
                    || str_contains($text, 'EGO VN')
                    || str_contains($text, 'VIET NAM')
                ) {
                    return 10;
                }

                return 20;
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function rememberCompany(
        Request $request,
        int $companyId
    ): void {
        Cookie::queue(
            Cookie::make(
                name: 'ego_last_company_id',
                value: (string) $companyId,
                minutes: 60 * 24 * 365,
                path: '/',
                domain: null,
                secure: $request->isSecure(),
                httpOnly: true,
                raw: false,
                sameSite: 'lax',
            )
        );
    }
}
