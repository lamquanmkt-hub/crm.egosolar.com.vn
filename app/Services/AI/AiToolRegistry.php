<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\User;
use Illuminate\Support\Str;

final class AiToolRegistry
{
    public function __construct(
        private readonly AiAccessService $access,
        private readonly AiDateResolver $dates,
        private readonly AiToolExecutor $executor,
    ) {}

    /**
     * @param array<int, string> $previousUserMessages
     * @return array<string, mixed>
     */
    public function run(
        User $user,
        string $message,
        array $previousUserMessages = [],
        ?string $previousTool = null
    ): array {
        $plan = $this->plan($user, $message, $previousTool);

        if (($plan['type'] ?? '') === 'none') {
            return [
                'used' => false,
                'denied' => false,
                'plan' => $plan,
                'contexts' => [],
                'combined' => null,
            ];
        }

        if (($plan['type'] ?? '') === 'denied') {
            return [
                'used' => false,
                'denied' => true,
                'denied_module' => $plan['module'] ?? null,
                'message' => $plan['message'] ?? 'Bạn không được cấp quyền truy cập dữ liệu này.',
                'plan' => $plan,
                'contexts' => [],
                'combined' => null,
            ];
        }

        $contexts = [];
        foreach ((array) ($plan['tools'] ?? []) as $tool) {
            $defaultPeriod = $tool === 'attendance' ? 'today' : 'month';
            $period = $this->dates->resolve($message, $previousUserMessages, $defaultPeriod);
            $contexts[] = $this->executor->execute(
                $tool,
                $user,
                $message,
                $period,
                $this->access->scopeFor($user, $tool)
            );
        }

        return [
            'used' => $contexts !== [],
            'denied' => false,
            'plan' => $plan,
            'contexts' => $contexts,
            'combined' => $this->combine($contexts),
        ];
    }

    /** @return array<string, mixed> */
    public function plan(User $user, string $message, ?string $previousTool = null): array
    {
        $normalized = $this->normalize($message);
        $modules = $this->detectModules($normalized);

        if ($modules === [] && $previousTool && $this->isTemporalCorrection($normalized)) {
            $modules = [$previousTool];
        }

        if ($modules === []) {
            return ['type' => 'none', 'tools' => []];
        }

        $allowed = [];
        foreach ($modules as $module) {
            if (! array_key_exists($module, (array) config('ego_ai.modules', []))) {
                continue;
            }

            if (! $this->access->canModule($user, $module)) {
                $label = (string) config("ego_ai.modules.{$module}.label", $module);
                return [
                    'type' => 'denied',
                    'module' => $module,
                    'message' => "Bạn chưa được cấp quyền hỏi AI về {$label}. AI chỉ được truy cập đúng phạm vi role và permission của tài khoản.",
                ];
            }

            $allowed[] = $module;
        }

        $allowed = array_values(array_unique(array_slice($allowed, 0, 3)));
        if ($allowed === []) {
            return ['type' => 'none', 'tools' => []];
        }

        return [
            'type' => 'tools',
            'tools' => $allowed,
            'primary' => $allowed[0],
        ];
    }

    /** @return array<int, string> */
    private function detectModules(string $text): array
    {
        $map = [
            'attendance' => [
                'cham cong', 'check in', 'checkin', 'check out', 'checkout', 'di muon',
                'vang mat', 'chua cham cong', 'gio lam', 'nhan vien hom nay', 'nhan vien hom qua',
            ],
            'orders' => [
                'don hang', 'ma don', 'ord', 'doanh thu', 'cong no', 'da thu', 'tien da thu',
                'giao tre', 'chua giao', 'chua xuat kho', 'xuat kho don',
            ],
            'customers' => [
                'khach hang', 'khach nao', 'so dien thoai khach', 'mst khach', 'ma so thue khach',
                'nguoi mua', 'dai ly',
            ],
            'inventory' => [
                'ton kho', 'san pham', 'sku', 'serial', 'het hang', 'sap het', 'kho hang',
                'nhap kho', 'xuat kho', 'bien tan', 'pin luu tru',
            ],
            'tasks' => [
                'cong viec', 'task', 'viec cua toi', 'viec qua han', 'giao viec', 'deadline',
            ],
            'sites' => [
                'cong trinh', 'khao sat', 'thi cong', 'nghiem thu', 'bao tri', 'bao hanh',
                'lich bao hanh', 'du an ky thuat',
            ],
            'payment_requests' => [
                'de nghi thanh toan', 'dntt', 'phieu thanh toan', 'cho duyet thanh toan',
                'chi phi can duyet',
            ],
            'marketing' => [
                'marketing', 'lead marketing', 'chien dich', 'quang cao', 'ads', 'content',
                'kpi marketing', 'lich noi dung',
            ],
            'hr' => [
                'nhan su', 'tuyen dung', 'ung vien', 'nghi phep', 'ho so nhan vien',
                'hop dong lao dong', 'nhan vien dang lam',
            ],
        ];

        $found = [];
        foreach ($map as $module => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $found[] = $module;
                    break;
                }
            }
        }

        if (in_array('orders', $found, true) && str_contains($text, 'khach hang')) {
            $found[] = 'customers';
        }

        return array_values(array_unique($found));
    }

    private function isTemporalCorrection(string $text): bool
    {
        foreach (['hom qua', 'hom kia', 'hom nay', 'tuan truoc', 'tuan nay', 'thang truoc', 'thang nay', 'ngay '] as $token) {
            if (str_contains($text, $token)) {
                return true;
            }
        }

        return str_contains($text, 'khong phai') || str_contains($text, 'toi hoi');
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(Str::ascii($value), 'UTF-8');
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    /** @param array<int, array<string, mixed>> $contexts */
    private function combine(array $contexts): ?array
    {
        if ($contexts === []) {
            return null;
        }

        if (count($contexts) === 1) {
            return $contexts[0];
        }

        return [
            'tool' => 'multi',
            'title' => 'Tổng hợp dữ liệu CRM',
            'summary' => 'Kết quả được tổng hợp từ '.count($contexts).' nhóm dữ liệu đúng quyền tài khoản.',
            'metrics' => collect($contexts)->flatMap(fn ($context) => (array) ($context['metrics'] ?? []))->take(12)->values()->all(),
            'items' => collect($contexts)->flatMap(fn ($context) => (array) ($context['items'] ?? []))->take(16)->values()->all(),
            'sections' => $contexts,
            'source' => 'CRM EGO Solar',
            'scope' => collect($contexts)->pluck('scope')->filter()->unique()->implode(' · '),
            'period' => collect($contexts)->pluck('period')->filter()->unique()->implode(' · '),
            'updated_at' => now()->format('d/m/Y H:i'),
        ];
    }
}
