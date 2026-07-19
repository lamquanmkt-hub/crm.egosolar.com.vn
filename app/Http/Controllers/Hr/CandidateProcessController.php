<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CandidateProcessController extends Controller
{
    public function index()
    {
        $this->ensureDefaultRounds();

        $rounds = DB::table('hr_candidate_process_rounds')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $items = DB::table('hr_candidate_process_items as i')
            ->leftJoin('hr_candidate_process_rounds as r', 'r.id', '=', 'i.round_id')
            ->select('i.*', 'r.name as round_name')
            ->orderBy('r.sort_order')
            ->orderByDesc('i.id')
            ->get();

        $stats = [
            'total' => DB::table('hr_candidate_process_items')->count(),
            'rounds' => DB::table('hr_candidate_process_rounds')->count(),
            'done' => DB::table('hr_candidate_process_items')->whereNotNull('result')->where('result', '<>', '')->count(),
            'pending' => DB::table('hr_candidate_process_items')->where(function ($q) {
                $q->whereNull('result')->orWhere('result', '');
            })->count(),
        ];

        return view('hr.candidate-processes.index', compact('rounds', 'items', 'stats'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'candidate_name' => ['required', 'string', 'max:255'],
            'round_id' => ['required', 'integer'],
            'responsible_person' => ['nullable', 'string', 'max:255'],
            'evaluation' => ['nullable', 'string'],
            'result' => ['nullable', 'string', 'max:255'],
        ]);

        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('hr_candidate_process_items')->insert($data);

        return back()->with('success', 'Đã thêm ứng viên vào quy trình.');
    }

    public function update(Request $request, $item)
    {
        $data = $request->validate([
            'candidate_name' => ['required', 'string', 'max:255'],
            'round_id' => ['required', 'integer'],
            'responsible_person' => ['nullable', 'string', 'max:255'],
            'evaluation' => ['nullable', 'string'],
            'result' => ['nullable', 'string', 'max:255'],
        ]);

        $data['updated_at'] = now();

        DB::table('hr_candidate_process_items')
            ->where('id', (int) $item)
            ->update($data);

        return back()->with('success', 'Đã cập nhật quy trình ứng viên.');
    }

    public function destroy($item)
    {
        DB::table('hr_candidate_process_items')->where('id', (int) $item)->delete();

        return back()->with('success', 'Đã xoá ứng viên khỏi quy trình.');
    }

    public function storeRound(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        DB::table('hr_candidate_process_rounds')->insert([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã thêm lịch / đợt.');
    }

    public function updateRound(Request $request, $round)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        DB::table('hr_candidate_process_rounds')
            ->where('id', (int) $round)
            ->update([
                'name' => $data['name'],
                'sort_order' => $data['sort_order'] ?? 999,
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Đã cập nhật lịch / đợt.');
    }

    public function destroyRound($round)
    {
        $used = DB::table('hr_candidate_process_items')
            ->where('round_id', (int) $round)
            ->exists();

        if ($used) {
            return back()->withErrors('Không thể xoá đợt này vì đang có ứng viên sử dụng.');
        }

        DB::table('hr_candidate_process_rounds')
            ->where('id', (int) $round)
            ->delete();

        return back()->with('success', 'Đã xoá lịch / đợt.');
    }

    private function ensureDefaultRounds()
    {
        if (!Schema::hasTable('hr_candidate_process_rounds')) {
            return;
        }

        if (DB::table('hr_candidate_process_rounds')->count() === 0) {
            foreach (['Đợt 1', 'Đợt 2', 'Đợt 3'] as $i => $name) {
                DB::table('hr_candidate_process_rounds')->insert([
                    'name' => $name,
                    'sort_order' => $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
