<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\ContentFeedback;
use Illuminate\Http\Request;

/**
 * Nhận feedback cho nội dung trong lịch content.
 */
class ContentFeedbackController extends Controller
{
    /**
     * Lưu feedback (nội dung + ảnh kèm nếu có) cho một mục content calendar.
     */
    public function store(Request $request, $contentCalendarId)
    {
        $request->validate([
            'message' => 'required|string',
            'image' => 'nullable|image|max:4096',
        ]);

        $path = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('feedback', 'public');
        }

        ContentFeedback::create([
            'content_calendar_id' => $contentCalendarId,
            'user_id' => auth()->id(),
            'message' => $request->message,
            'image_path' => $path,
        ]);

        return back()->with('success', 'Đã gửi feedback');
    }
}
