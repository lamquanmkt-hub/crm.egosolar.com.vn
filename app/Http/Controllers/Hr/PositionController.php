<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Position;

class PositionController extends Controller
{
    public function index()
    {
        $positions = Position::latest()->paginate(10);

        return view('hr.positions.index', compact('positions'));
    }

    public function create()
    {
        return view('hr.positions.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255',
        ]);

        Position::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
        ]);

        return redirect()->route('hr.positions.index')
            ->with('success', 'Tạo chức vụ thành công');
    }

    public function edit($id)
    {
        $position = Position::findOrFail($id);

        return view('hr.positions.edit', compact('position'));
    }

    public function update(Request $request, $id)
    {
        $position = Position::findOrFail($id);

        $request->validate([
            'name' => 'required|max:255',
        ]);

        $position->update([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
        ]);

        return redirect()->route('hr.positions.index')
            ->with('success', 'Cập nhật thành công');
    }

    public function destroy($id)
    {
        Position::findOrFail($id)->delete();

        return back()->with('success', 'Đã xoá');
    }
}