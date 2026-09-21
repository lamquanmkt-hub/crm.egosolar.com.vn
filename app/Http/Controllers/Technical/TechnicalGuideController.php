<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Services\Technical\TechnicalGuideRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trang "Cách sử dụng" của module Kỹ thuật — CHỈ ĐỌC.
 *
 * Controller cố tình MỎNG: metadata nằm ở `config/technical_guides.php`
 * (qua `TechnicalGuideRegistry`), nội dung từng bài nằm ở một Blade riêng
 * `resources/views/technical/guides/{slug}.blade.php`. Không có HTML trong
 * controller, không có bảng DB, không có migration.
 *
 * Phân quyền được kiểm ở ĐÂY (backend), không chỉ ẩn nút ở Blade: người không
 * đúng vai trò gõ thẳng slug quản lý vẫn nhận 403.
 */
final class TechnicalGuideController extends Controller
{
    public function __construct(private readonly TechnicalGuideRegistry $registry) {}

    /** Danh mục hướng dẫn, đã lọc theo vai trò người đang đăng nhập. */
    public function index(Request $request): View
    {
        $role = $this->authorizeModule($request);

        return view('technical.guides.index', [
            'role' => $role,
            'groups' => $this->registry->groupedFor($role),
            'guideCount' => count($this->registry->visibleFor($role)),
        ]);
    }

    /** Một bài hướng dẫn. 404 nếu slug không tồn tại, 403 nếu sai vai trò. */
    public function show(Request $request, string $slug): View
    {
        $role = $this->authorizeModule($request);

        $guide = $this->registry->find($slug);
        abort_if($guide === null, 404, 'Không tìm thấy bài hướng dẫn này.');

        abort_unless(
            $this->registry->isVisibleTo($guide, $role),
            403,
            'Bài hướng dẫn này dành cho vai trò khác trong module Kỹ thuật.'
        );

        return view('technical.guides.'.$guide['slug'], [
            'guide' => $guide,
            'role' => $role,
            'returnUrl' => $this->registry->safeReturnUrl($request->query('return'), $guide),
            'nextGuide' => $this->registry->nextInGroup($guide, $role),
        ]);
    }

    /**
     * Chỉ người thuộc module Kỹ thuật mới vào được khu vực hướng dẫn.
     *
     * Dùng lại đúng `TechnicalAccess` như mọi trang Kỹ thuật khác, và trả 403
     * (không redirect) cho nhất quán với `TechnicalPlanBoardController`.
     */
    private function authorizeModule(Request $request): string
    {
        $role = $this->registry->roleFor($request->user());

        abort_if($role === null, 403, 'Bạn không thuộc phạm vi module Kỹ thuật.');

        return $role;
    }
}
