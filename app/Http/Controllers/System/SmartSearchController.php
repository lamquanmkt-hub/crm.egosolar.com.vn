<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SmartSearch\SmartSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class SmartSearchController extends Controller
{
    public function __construct(
        private readonly SmartSearchService $smartSearch
    ) {
        $this->middleware('auth');
    }

    public function bootstrap(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(
            $this->smartSearch->bootstrap(
                $user,
                (string) $request->query('path', '/')
            )
        );
    }

    public function query(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:180'],
            'path' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            return response()->json(
                $this->smartSearch->search(
                    $user,
                    trim((string) $validated['q']),
                    (string) ($validated['path'] ?? '/')
                )
            );
        } catch (\Throwable $e) {
            Log::error('EGO Smart Search failed', [
                'user_id' => $user->id,
                'query' => $validated['q'],
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Không thể tra cứu dữ liệu lúc này. Vui lòng thử lại.',
            ], 500);
        }
    }
}
