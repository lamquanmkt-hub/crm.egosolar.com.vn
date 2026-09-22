<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Guides\GuideCatalog;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trang "Hướng dẫn sử dụng" — mỗi trang nghiệp vụ mở đúng bài của mình (không có 1 bài chung).
 * Ảnh minh họa là ảnh chụp thật từ trình duyệt local (public/images/guides/*.png).
 */
class GuideController extends Controller
{
    public function show(Request $request, string $slug): View
    {
        $guide = GuideCatalog::find($slug);
        abort_if($guide === null, 404);

        $user = $request->user();
        $allowed = SolarMaintenanceAccess::isAdmin($user) || $user->hasAnyRole($guide['roles']);
        abort_unless($allowed, 403, 'Bạn không có quyền xem hướng dẫn này.');

        return view('guides.show', [
            'slug' => $slug,
            'guide' => $guide,
            'back' => url()->previous() ?: url('/'),
        ]);
    }
}
