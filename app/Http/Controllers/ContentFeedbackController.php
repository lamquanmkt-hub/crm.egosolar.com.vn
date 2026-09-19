<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ContentFeedback;

class ContentFeedbackController extends Controller
{
    public function store(Request $request, $contentCalendarId)
    {
        $request->validate([
            'message' => 'required|string',
            'image'   => 'nullable|image|max:4096',
        ]);

        $path = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('feedback', 'public');
        }

        ContentFeedback::create([
            'content_calendar_id' => $contentCalendarId,
            'user_id'   => auth()->id(),
            'message'   => $request->message,
            'image_path'=> $path,
        ]);

        return back()->with('success', 'Đã gửi feedback');
    }
}