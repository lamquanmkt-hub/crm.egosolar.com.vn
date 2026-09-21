<?php

declare(strict_types=1);

namespace App\Services\Technical;

use App\Models\Technical\TechnicalPlanDayMark;
use App\Models\Technical\TechnicalPlanHistory;
use App\Models\Technical\TechnicalPlanItem;
use App\Models\Technical\TechnicalWeekPlan;
use App\Models\User;
use App\Support\Technical\TechnicalWorkItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nghiệp vụ kế hoạch tuần Kỹ thuật.
 *
 * Trách nhiệm:
 *  - Chuẩn hoá tuần (luôn thứ Hai → Chủ nhật).
 *  - Tạo / lấy kế hoạch tuần của một nhân viên.
 *  - Thêm / sửa / chuyển ngày / sao chép / xoá dòng kế hoạch — mỗi thao tác ghi
 *    nhật ký trong CÙNG transaction.
 *  - Kiểm tra trước khi "Hoàn tất kế hoạch tuần": tách rõ LỖI CHẶN và CẢNH BÁO.
 *
 * Nguyên tắc bảo mật: khi dòng kế hoạch trỏ tới công việc nguồn
 * (project_workflow / task / maintenance) thì nguồn đó LUÔN được xác thực lại
 * với `TechnicalWorkFeedService` theo đúng user sở hữu kế hoạch — không bao giờ
 * tin dữ liệu form.
 */
class TechnicalWeekPlanService
{
    /**
     * Bỏ dấu nhận diện dữ liệu nghiệm thu ở tầng hiển thị, không thay đổi dữ liệu DB.
     * Dữ liệu production không có tiền tố này nên được trả nguyên vẹn.
     */
    public static function displayLabel(?string $value): string
    {
        $value = trim((string) $value);

        return trim((string) (preg_replace('/^\[LOCAL TEST\]\s*/iu', '', $value) ?? $value));
    }

    public function __construct(
        private readonly TechnicalWorkFeedService $feed,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Tuần
    |--------------------------------------------------------------------------
    */

    /** Thứ Hai của tuần đang xem, suy từ `week` (Y-m-d) hoặc `offset` (-1/0/1). */
    public function resolveWeekStart(?string $week, ?int $offset = null): Carbon
    {
        $anchor = Carbon::today();

        if ($week !== null && $week !== '') {
            try {
                $anchor = Carbon::parse($week);
            } catch (\Throwable) {
                $anchor = Carbon::today();
            }
        }

        $start = TechnicalWeekPlan::weekStartFor($anchor);

        if ($offset !== null && $offset !== 0) {
            $max = (int) config('technical.week_plan.max_week_offset', 8);
            $offset = max(-$max, min($max, $offset));
            $start = $start->addWeeks($offset);
        }

        return $start;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function weekRange(Carbon $weekStart): array
    {
        return [$weekStart->copy()->startOfDay(), $weekStart->copy()->addDays(6)->endOfDay()];
    }

    /*
    |--------------------------------------------------------------------------
    | Kế hoạch tuần
    |--------------------------------------------------------------------------
    */

    /** Kế hoạch tuần đang có (không tạo mới) — dùng cho màn hình chỉ đọc. */
    public function findPlan(int $userId, Carbon $weekStart): ?TechnicalWeekPlan
    {
        return TechnicalWeekPlan::query()
            ->where('user_id', $userId)
            ->whereDate('week_start', $weekStart->toDateString())
            ->first();
    }

    /**
     * Lấy hoặc tạo kế hoạch tuần. Luôn gọi bên trong transaction của thao tác
     * ghi để không để lại bản ghi rỗng khi thao tác thất bại.
     */
    public function firstOrCreatePlan(User $owner, Carbon $weekStart): TechnicalWeekPlan
    {
        $existing = $this->findPlan((int) $owner->id, $weekStart);

        if ($existing !== null) {
            return $existing;
        }

        return TechnicalWeekPlan::create([
            'company_id' => $this->feed->companyId(),
            'user_id' => $owner->id,
            'user_name' => $owner->name,
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekStart->copy()->addDays(6)->toDateString(),
            'status' => TechnicalWeekPlan::STATUS_DRAFT,
        ]);
    }

    /**
     * Dòng kế hoạch của một tuần, đã eager-load, nhóm theo ngày (Y-m-d).
     *
     * @return Collection<string, Collection<int, TechnicalPlanItem>>
     */
    public function itemsByDay(int $userId, Carbon $weekStart): Collection
    {
        [$from, $to] = $this->weekRange($weekStart);

        return TechnicalPlanItem::query()
            ->where('user_id', $userId)
            ->where('company_id', $this->feed->companyId())
            ->whereBetween('plan_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('plan_date')
            ->orderByRaw("FIELD(day_part, 'morning', 'afternoon', 'full_day', 'custom')")
            ->orderBy('start_time')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (TechnicalPlanItem $item): string => $item->plan_date->toDateString());
    }

    /** @return Collection<string, TechnicalPlanDayMark> */
    public function dayMarks(int $userId, Carbon $weekStart): Collection
    {
        [$from, $to] = $this->weekRange($weekStart);

        return TechnicalPlanDayMark::query()
            ->where('user_id', $userId)
            ->whereBetween('plan_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (TechnicalPlanDayMark $mark): string => $mark->plan_date->toDateString());
    }

    /*
    |--------------------------------------------------------------------------
    | Nguồn công việc cho ô chọn
    |--------------------------------------------------------------------------
    */

    /**
     * Các đầu việc ĐÃ ĐƯỢC GIAO cho chính `$userId` (3 nguồn thật).
     *
     * @return Collection<int, TechnicalWorkItem>
     */
    public function assignableItems(int $userId, int $limit = 80): Collection
    {
        return $this->feed->paginate([
            'user_id' => $userId,
            'filter' => TechnicalWorkFeedService::FILTER_ALL,
        ], $limit)->getCollection()
            ->filter(fn (TechnicalWorkItem $item): bool => $item->isActive())
            ->values();
    }

    /**
     * Xác thực nguồn ở PHÍA SERVER và trả về dữ liệu liên kết đã chuẩn hoá.
     *
     * @param  int  $ownerId  chủ sở hữu kế hoạch (không phải người đang thao tác)
     * @return array<string, mixed>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function resolveSource(string $sourceType, ?int $sourceId, int $ownerId, string $fallbackTitle): array
    {
        if (! array_key_exists($sourceType, TechnicalPlanItem::SOURCE_LABELS)) {
            $this->fail('source_type', 'Nguồn công việc không hợp lệ.');
        }

        // Việc nội bộ cá nhân / trưởng phòng giao thêm: không có bản ghi nguồn.
        if (! in_array($sourceType, TechnicalPlanItem::FEED_SOURCES, true)) {
            return [
                'source_type' => $sourceType,
                'source_id' => null,
                'site_id' => null,
                'site_name' => null,
                'title' => $fallbackTitle,
                'source_due_at' => null,
            ];
        }

        if ($sourceId === null || $sourceId <= 0) {
            $this->fail('source_id', 'Vui lòng chọn đúng công việc nguồn.');
        }

        // Điểm chặn giả mạo: feed chỉ trả về việc được giao cho CHÍNH chủ kế hoạch.
        $item = $this->feed->findItem($sourceType, (int) $sourceId, $ownerId);

        if ($item === null) {
            $this->fail('source_id', 'Công việc này không được giao cho nhân viên đó.');
        }

        return [
            'source_type' => $item->sourceType,
            'source_id' => $item->sourceId,
            'site_id' => $item->siteId,
            'site_name' => $item->siteName,
            'title' => $fallbackTitle !== '' ? $fallbackTitle : $item->title,
            'source_due_at' => $item->dueDate?->toDateTimeString(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Ghi dữ liệu
    |--------------------------------------------------------------------------
    */

    /**
     * Thêm một dòng kế hoạch. Trả về dòng vừa tạo.
     *
     * @param  array<string, mixed>  $data
     * @param  bool|null  $managerAssigned  ép trạng thái "quản lý giao việc".
     *                                      Mặc định (null) suy từ actor ≠ owner —
     *                                      giữ nguyên hành vi cũ. Truyền `false`
     *                                      khi quản lý LƯU NHÁP kế hoạch hộ nhân
     *                                      viên: dòng vẫn được ghi và vẫn ghi
     *                                      nhật ký, nhưng KHÔNG đánh dấu đã giao
     *                                      và KHÔNG đẩy tuần sang trạng thái
     *                                      "Đã được Trưởng phòng điều chỉnh".
     */
    public function addItem(
        User $owner,
        Carbon $weekStart,
        array $data,
        ?User $actor = null,
        ?string $reason = null,
        ?bool $managerAssigned = null,
    ): TechnicalPlanItem {
        $actor ??= $owner;
        $isManagerAssigned = $managerAssigned ?? ((int) $actor->id !== (int) $owner->id);

        return DB::transaction(function () use ($owner, $weekStart, $data, $actor, $reason, $isManagerAssigned): TechnicalPlanItem {
            $plan = $this->firstOrCreatePlan($owner, $weekStart);
            $planDate = Carbon::parse($data['plan_date'])->startOfDay();

            $this->guardDateInsideWeek($planDate, $weekStart);
            $this->guardDayCapacity((int) $owner->id, $planDate);

            $source = $this->resolveSource(
                (string) $data['source_type'],
                isset($data['source_id']) ? (int) $data['source_id'] : null,
                (int) $owner->id,
                trim((string) ($data['title'] ?? '')),
            );

            $this->guardDuplicateSource((int) $owner->id, $planDate, $source);

            $item = TechnicalPlanItem::create([
                'company_id' => $plan->company_id,
                'week_plan_id' => $plan->id,
                'user_id' => $owner->id,
                'user_name' => $owner->name,
                'plan_date' => $planDate->toDateString(),
                'day_part' => $data['day_part'] ?? 'full_day',
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'title' => $source['title'] !== '' ? $source['title'] : 'Công việc kỹ thuật',
                'objective' => $data['objective'] ?? null,
                'note' => $data['note'] ?? null,
                'source_type' => $source['source_type'],
                'source_id' => $source['source_id'],
                'site_id' => $source['site_id'],
                'site_name' => $source['site_name'],
                'estimated_minutes' => $data['estimated_minutes'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'status' => TechnicalPlanItem::STATUS_PLANNED,
                'source_due_at' => $source['source_due_at'],
                'created_by' => $actor->id,
                'created_by_name' => $actor->name,
                'is_manager_assigned' => $isManagerAssigned,
                'assigned_by' => $isManagerAssigned ? $actor->id : null,
                'assigned_at' => $isManagerAssigned ? now() : null,
            ]);

            TechnicalPlanLogger::log(
                $isManagerAssigned ? TechnicalPlanHistory::ACTION_ASSIGN : TechnicalPlanHistory::ACTION_CREATE,
                $plan,
                $item,
                null,
                TechnicalPlanLogger::snapshot($item),
                $reason,
            );

            if ($isManagerAssigned) {
                $this->markAdjusted($plan, $actor, $reason);
            }

            // Ngày đã có việc thì bỏ đánh dấu "không có kế hoạch".
            $this->clearDayMark((int) $owner->id, $planDate);

            return $item;
        });
    }

    /**
     * Sửa một dòng kế hoạch.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateItem(
        TechnicalPlanItem $item,
        array $data,
        User $actor,
        ?string $reason = null,
    ): TechnicalPlanItem {
        return DB::transaction(function () use ($item, $data, $actor, $reason): TechnicalPlanItem {
            $before = TechnicalPlanLogger::snapshot($item);
            $plan = $item->weekPlan;
            $isManagerEdit = (int) $actor->id !== (int) $item->user_id;

            if (array_key_exists('plan_date', $data) && $data['plan_date'] !== null) {
                $newDate = Carbon::parse($data['plan_date'])->startOfDay();
                $this->guardDateInsideWeek($newDate, Carbon::parse($plan->week_start));

                if ($newDate->toDateString() !== $item->plan_date->toDateString()) {
                    $item->moved_from_date = $item->plan_date->toDateString();
                    $item->moved_to_date = $newDate->toDateString();
                }

                $item->plan_date = $newDate->toDateString();
            }

            foreach (['day_part', 'start_time', 'end_time', 'title', 'objective', 'note', 'estimated_minutes', 'priority'] as $field) {
                if (array_key_exists($field, $data)) {
                    $item->{$field} = $data[$field];
                }
            }

            if (! empty($data['status']) && array_key_exists($data['status'], TechnicalPlanItem::STATUS_LABELS)) {
                $item->status = $data['status'];
            }

            if (array_key_exists('progress_percent', $data) && $data['progress_percent'] !== null) {
                $item->progress_percent = max(0, min(100, (int) $data['progress_percent']));
            }

            $item->save();

            [$changedBefore, $changedAfter] = TechnicalPlanLogger::diff(
                $before,
                TechnicalPlanLogger::snapshot($item),
            );

            TechnicalPlanLogger::log(
                $item->moved_to_date !== null && isset($changedAfter['plan_date'])
                    ? TechnicalPlanHistory::ACTION_MOVE
                    : TechnicalPlanHistory::ACTION_UPDATE,
                $plan,
                $item,
                $changedBefore,
                $changedAfter,
                $reason,
            );

            if ($isManagerEdit && $plan !== null) {
                $this->markAdjusted($plan, $actor, $reason);
            }

            return $item;
        });
    }

    /** Đổi trạng thái / tiến độ của DÒNG KẾ HOẠCH (không đụng dữ liệu nguồn). */
    public function changeStatus(TechnicalPlanItem $item, string $status, ?int $progress, User $actor, ?string $reason = null): TechnicalPlanItem
    {
        if (! array_key_exists($status, TechnicalPlanItem::STATUS_LABELS)) {
            $this->fail('status', 'Trạng thái công việc không hợp lệ.');
        }

        return DB::transaction(function () use ($item, $status, $progress, $reason): TechnicalPlanItem {
            $before = TechnicalPlanLogger::snapshot($item);

            $item->status = $status;

            if ($progress !== null) {
                $item->progress_percent = max(0, min(100, $progress));
            } elseif ($status === TechnicalPlanItem::STATUS_DONE) {
                $item->progress_percent = 100;
            }

            $item->save();

            [$changedBefore, $changedAfter] = TechnicalPlanLogger::diff($before, TechnicalPlanLogger::snapshot($item));

            TechnicalPlanLogger::log(
                TechnicalPlanHistory::ACTION_STATUS,
                $item->weekPlan,
                $item,
                $changedBefore,
                $changedAfter,
                $reason,
            );

            return $item;
        });
    }

    /** Sao chép một dòng sang ngày khác (trong cùng tuần hoặc tuần khác). */
    public function copyItemToDate(TechnicalPlanItem $item, Carbon $targetDate, User $actor, ?string $reason = null): TechnicalPlanItem
    {
        $owner = $item->user ?? User::find($item->user_id);

        if ($owner === null) {
            $this->fail('plan_date', 'Không xác định được chủ sở hữu kế hoạch.');
        }

        return $this->addItem(
            $owner,
            TechnicalWeekPlan::weekStartFor($targetDate),
            [
                'plan_date' => $targetDate->toDateString(),
                'day_part' => $item->day_part,
                'start_time' => $item->start_time,
                'end_time' => $item->end_time,
                'title' => $item->title,
                'objective' => $item->objective,
                'note' => $item->note,
                'source_type' => $item->source_type,
                'source_id' => $item->source_id,
                'estimated_minutes' => $item->estimated_minutes,
                'priority' => $item->priority,
            ],
            $actor,
            $reason,
        );
    }

    /**
     * Xoá một dòng kế hoạch (soft delete).
     *
     * KHÔNG cho xoá khi đã phát sinh báo cáo — báo cáo là bằng chứng công việc.
     */
    public function deleteItem(TechnicalPlanItem $item, User $actor, ?string $reason = null): void
    {
        if ($this->reportCountFor($item) > 0) {
            $this->fail('id', 'Công việc này đã có báo cáo — không thể xoá khỏi kế hoạch.');
        }

        DB::transaction(function () use ($item, $reason): void {
            $before = TechnicalPlanLogger::snapshot($item);

            TechnicalPlanLogger::log(
                TechnicalPlanHistory::ACTION_DELETE,
                $item->weekPlan,
                $item,
                $before,
                null,
                $reason,
            );

            $item->delete();
        });
    }

    /** Số báo cáo đang trỏ tới dòng kế hoạch — đếm ở DB. */
    public function reportCountFor(TechnicalPlanItem $item): int
    {
        if (! Schema::hasColumn('technical_daily_reports', 'plan_item_id')) {
            return 0;
        }

        return (int) DB::table('technical_daily_reports')
            ->where('plan_item_id', $item->id)
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Sao chép toàn bộ kế hoạch của tuần trước sang tuần đang xem.
     *
     * Chỉ chép các dòng CÒN Ý NGHĨA (dự kiến / đang thực hiện / chưa hoàn thành)
     * và bỏ qua dòng nào sẽ bị trùng — sao chép không bao giờ làm hỏng tuần mới.
     *
     * @return array{copied:int, skipped:int}
     */
    public function copyPreviousWeek(User $owner, Carbon $weekStart, User $actor): array
    {
        $previousStart = $weekStart->copy()->subWeek();
        $sourceItems = $this->itemsByDay((int) $owner->id, $previousStart)->flatten();

        $copied = 0;
        $skipped = 0;

        foreach ($sourceItems as $source) {
            if (in_array($source->status, [TechnicalPlanItem::STATUS_CANCELLED, TechnicalPlanItem::STATUS_DONE], true)) {
                $skipped++;

                continue;
            }

            $targetDate = Carbon::parse($source->plan_date)->addWeek();

            try {
                $this->addItem($owner, $weekStart, [
                    'plan_date' => $targetDate->toDateString(),
                    'day_part' => $source->day_part,
                    'start_time' => $source->start_time,
                    'end_time' => $source->end_time,
                    'title' => $source->title,
                    'objective' => $source->objective,
                    'note' => $source->note,
                    'source_type' => $source->source_type,
                    'source_id' => $source->source_id,
                    'estimated_minutes' => $source->estimated_minutes,
                    'priority' => $source->priority,
                ], $actor);

                $copied++;
            } catch (\Throwable) {
                // Việc nguồn đã đóng / đã bị thu hồi / trùng: bỏ qua, không
                // làm hỏng cả thao tác sao chép.
                $skipped++;
            }
        }

        if ($copied > 0) {
            DB::transaction(function () use ($owner, $weekStart, $copied, $skipped): void {
                $plan = $this->firstOrCreatePlan($owner, $weekStart);

                TechnicalPlanLogger::log(
                    TechnicalPlanHistory::ACTION_COPY_WEEK,
                    $plan,
                    null,
                    null,
                    ['copied' => $copied, 'skipped' => $skipped],
                );
            });
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }

    /** Đánh dấu một ngày là nghỉ / chờ phân công / không có kế hoạch. */
    public function setDayMark(User $owner, Carbon $weekStart, Carbon $date, string $mark, ?string $reason): TechnicalPlanDayMark
    {
        if (! array_key_exists($mark, TechnicalPlanDayMark::MARK_LABELS)) {
            $this->fail('mark', 'Loại đánh dấu ngày không hợp lệ.');
        }

        $this->guardDateInsideWeek($date, $weekStart);

        return DB::transaction(function () use ($owner, $weekStart, $date, $mark, $reason): TechnicalPlanDayMark {
            $plan = $this->firstOrCreatePlan($owner, $weekStart);

            $record = TechnicalPlanDayMark::updateOrCreate(
                ['user_id' => $owner->id, 'plan_date' => $date->toDateString()],
                [
                    'company_id' => $plan->company_id,
                    'week_plan_id' => $plan->id,
                    'mark' => $mark,
                    'reason' => $reason,
                ],
            );

            TechnicalPlanLogger::log(
                TechnicalPlanHistory::ACTION_DAY_MARK,
                $plan,
                null,
                null,
                ['plan_date' => $date->toDateString(), 'mark' => $mark],
                $reason,
            );

            return $record;
        });
    }

    private function clearDayMark(int $userId, Carbon $date): void
    {
        TechnicalPlanDayMark::query()
            ->where('user_id', $userId)
            ->whereDate('plan_date', $date->toDateString())
            ->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Hoàn tất kế hoạch tuần
    |--------------------------------------------------------------------------
    */

    /**
     * Kiểm tra kế hoạch tuần.
     *
     * Phân loại (đã chọn theo hướng "chặn cái sai chắc chắn, cảnh báo cái cần
     * người quyết định"):
     *
     *  LỖI CHẶN — không cho hoàn tất:
     *   - Tuần chưa có dòng kế hoạch nào.
     *   - Có ngày làm việc (T2–T7) vừa không có việc vừa không được đánh dấu.
     *   - Tổng thời lượng một ngày vượt trần cứng (16 giờ) — chắc chắn nhập sai.
     *
     *  CẢNH BÁO — vẫn cho hoàn tất nhưng hiển thị rõ:
     *   - Trùng việc nguồn trong tuần (cùng việc ở nhiều ngày).
     *   - Trùng khung giờ trong cùng một ngày.
     *   - Tổng thời lượng ngày vượt giờ làm việc cấu hình (quá tải).
     *   - Việc có hạn (due) TRƯỚC ngày dự kiến làm.
     *   - Việc nguồn hiện đang do người khác phụ trách.
     *
     * @return array{errors: array<int, string>, warnings: array<int, string>, daily: array<string, array<string, mixed>>}
     */
    public function validateWeek(int $userId, Carbon $weekStart): array
    {
        $itemsByDay = $this->itemsByDay($userId, $weekStart);
        $marks = $this->dayMarks($userId, $weekStart);
        $days = TechnicalWeekPlan::weekDays($weekStart);

        $errors = [];
        $warnings = [];
        $daily = [];

        $dailyMinutes = $this->dailyWorkingMinutes();
        $overloadLimit = (int) round($dailyMinutes * (float) config('technical.week_plan.overload_ratio', 1.0));
        $hardLimit = (int) config('technical.week_plan.hard_limit_minutes', 960);
        $optionalWeekdays = (array) config('technical.week_plan.optional_weekdays', [7]);

        $total = $itemsByDay->flatten()->count();

        if ($total === 0) {
            $errors[] = 'Tuần này chưa có công việc nào — hãy thêm ít nhất một việc trước khi hoàn tất.';
        }

        foreach ($days as $day) {
            $key = $day->toDateString();
            /** @var Collection<int, TechnicalPlanItem> $items */
            $items = $itemsByDay->get($key, collect());
            $mark = $marks->get($key);

            $minutes = (int) $items->sum(fn (TechnicalPlanItem $i): int => (int) ($i->estimated_minutes ?? 0));
            $isOptional = in_array((int) $day->dayOfWeekIso, $optionalWeekdays, true);

            $daily[$key] = [
                'date' => $day,
                'count' => $items->count(),
                'minutes' => $minutes,
                'mark' => $mark,
                'overloaded' => $minutes > $overloadLimit,
            ];

            if ($items->isEmpty() && $mark === null && ! $isOptional) {
                $errors[] = 'Ngày '.$day->format('d/m/Y').' ('.$this->weekdayLabel($day)
                    .') chưa có kế hoạch và chưa được đánh dấu nghỉ / chờ phân công.';
            }

            if ($minutes > $hardLimit) {
                $errors[] = 'Ngày '.$day->format('d/m/Y').' có tổng thời lượng '
                    .$this->minutesLabel($minutes).' — vượt trần '.$this->minutesLabel($hardLimit)
                    .', vui lòng kiểm tra lại.';
            } elseif ($minutes > $overloadLimit && $overloadLimit > 0) {
                $warnings[] = 'Ngày '.$day->format('d/m/Y').' dự kiến '.$this->minutesLabel($minutes)
                    .', vượt giờ làm việc chuẩn '.$this->minutesLabel($overloadLimit).' (quá tải).';
            }

            foreach ($this->timeConflicts($items) as $conflict) {
                $warnings[] = 'Ngày '.$day->format('d/m/Y').': trùng khung giờ giữa "'
                    .$conflict[0].'" và "'.$conflict[1].'".';
            }

            foreach ($items as $item) {
                if ($item->source_due_at !== null && $item->source_due_at->lt($day->copy()->startOfDay())) {
                    $warnings[] = '"'.$item->title.'" có hạn '.$item->source_due_at->format('d/m/Y')
                        .' nhưng được xếp vào ngày '.$day->format('d/m/Y').' (sau hạn).';
                }
            }
        }

        foreach ($this->duplicateSources($itemsByDay->flatten()) as $duplicate) {
            $warnings[] = 'Công việc "'.$duplicate['title'].'" xuất hiện ở '
                .$duplicate['count'].' ngày trong tuần.';
        }

        foreach ($this->itemsOwnedByOthers($itemsByDay->flatten(), $userId) as $title) {
            $warnings[] = 'Công việc "'.$title.'" hiện không còn được giao cho nhân viên này.';
        }

        return ['errors' => $errors, 'warnings' => $warnings, 'daily' => $daily];
    }

    /** Hoàn tất kế hoạch tuần — chỉ chạy khi không còn lỗi chặn. */
    public function finalize(User $owner, Carbon $weekStart, User $actor): TechnicalWeekPlan
    {
        $check = $this->validateWeek((int) $owner->id, $weekStart);

        if ($check['errors'] !== []) {
            $this->fail('week', implode(' ', $check['errors']));
        }

        return DB::transaction(function () use ($owner, $weekStart, $actor): TechnicalWeekPlan {
            $plan = $this->firstOrCreatePlan($owner, $weekStart);
            $before = ['status' => $plan->status];

            $plan->status = TechnicalWeekPlan::STATUS_FINALIZED;
            $plan->finalized_at = now();
            $plan->finalized_by = $actor->id;
            $plan->update_requested = false;
            $plan->save();

            TechnicalPlanLogger::log(
                TechnicalPlanHistory::ACTION_FINALIZE,
                $plan,
                null,
                $before,
                ['status' => $plan->status],
            );

            return $plan;
        });
    }

    /** Trưởng phòng yêu cầu nhân viên cập nhật lại kế hoạch — BẮT BUỘC lý do. */
    public function requestUpdate(TechnicalWeekPlan $plan, User $actor, string $reason): TechnicalWeekPlan
    {
        return DB::transaction(function () use ($plan, $actor, $reason): TechnicalWeekPlan {
            $before = ['status' => $plan->status, 'update_requested' => $plan->update_requested];

            $plan->update_requested = true;
            $plan->update_request_note = $reason;
            $plan->update_requested_at = now();
            $plan->save();

            TechnicalPlanLogger::log(
                TechnicalPlanHistory::ACTION_REQUEST_UPDATE,
                $plan,
                null,
                $before,
                ['update_requested' => true],
                $reason,
            );

            unset($actor);

            return $plan;
        });
    }

    /** Đánh dấu kế hoạch tuần "đã được Trưởng phòng điều chỉnh". */
    private function markAdjusted(TechnicalWeekPlan $plan, User $actor, ?string $reason): void
    {
        $plan->status = TechnicalWeekPlan::STATUS_ADJUSTED;
        $plan->adjusted_at = now();
        $plan->adjusted_by = $actor->id;
        $plan->adjust_note = $reason;
        $plan->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Giờ làm việc
    |--------------------------------------------------------------------------
    */

    /**
     * Số phút làm việc chuẩn một ngày.
     *
     * Nguồn sự thật là bảng `attendance_settings` đang chạy (min_work_minutes,
     * hoặc khoảng cách giờ vào — giờ ra). Chỉ khi bảng chưa có dữ liệu mới rơi
     * về `config/technical.php`.
     */
    public function dailyWorkingMinutes(): int
    {
        $fallback = (int) config('technical.week_plan.default_daily_minutes', 480);

        if (! Schema::hasTable('attendance_settings')) {
            return $fallback;
        }

        try {
            $row = DB::table('attendance_settings')->orderBy('id')->first();
        } catch (\Throwable) {
            return $fallback;
        }

        if ($row === null) {
            return $fallback;
        }

        $minutes = (int) ($row->min_work_minutes ?? 0);

        if ($minutes > 0) {
            return $minutes;
        }

        if (! empty($row->work_start_time) && ! empty($row->work_end_time)) {
            try {
                $start = Carbon::createFromFormat('H:i:s', (string) $row->work_start_time);
                $end = Carbon::createFromFormat('H:i:s', (string) $row->work_end_time);
                $diff = $start->diffInMinutes($end, false);

                if ($diff > 0) {
                    return (int) $diff;
                }
            } catch (\Throwable) {
                // Rơi về fallback bên dưới.
            }
        }

        return $fallback;
    }

    /*
    |--------------------------------------------------------------------------
    | Trợ giúp
    |--------------------------------------------------------------------------
    */

    public function weekdayLabel(Carbon $date): string
    {
        return [
            1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm',
            5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ nhật',
        ][$date->dayOfWeekIso] ?? $date->format('D');
    }

    public function minutesLabel(?int $minutes): string
    {
        if ($minutes === null || $minutes <= 0) {
            return '—';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours === 0) {
            return $rest.' phút';
        }

        return $rest === 0 ? $hours.' giờ' : $hours.'g'.str_pad((string) $rest, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Các cặp việc trùng khung giờ trong cùng một ngày.
     *
     * @param  Collection<int, TechnicalPlanItem>  $items
     * @return array<int, array{0:string, 1:string}>
     */
    private function timeConflicts(Collection $items): array
    {
        $ranges = [];

        foreach ($items as $item) {
            if (in_array($item->status, [TechnicalPlanItem::STATUS_CANCELLED, TechnicalPlanItem::STATUS_MOVED], true)) {
                continue;
            }

            $ranges[] = [$item->title, ...$this->dayPartRange($item)];
        }

        $conflicts = [];
        $count = count($ranges);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                [$titleA, $startA, $endA] = $ranges[$i];
                [$titleB, $startB, $endB] = $ranges[$j];

                if ($startA < $endB && $startB < $endA) {
                    $conflicts[] = [$titleA, $titleB];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Khung giờ (phút kể từ 00:00) của một dòng kế hoạch.
     *
     * @return array{0:int, 1:int}
     */
    private function dayPartRange(TechnicalPlanItem $item): array
    {
        if ($item->day_part === 'custom' && $item->start_time) {
            $start = $this->timeToMinutes((string) $item->start_time);
            $end = $item->end_time
                ? $this->timeToMinutes((string) $item->end_time)
                : $start + (int) ($item->estimated_minutes ?? 60);

            return [$start, max($end, $start + 1)];
        }

        return match ($item->day_part) {
            'morning' => [8 * 60, 12 * 60],
            'afternoon' => [13 * 60, 18 * 60],
            default => [8 * 60, 18 * 60],
        };
    }

    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
    }

    /**
     * Việc nguồn xuất hiện ở nhiều ngày trong cùng tuần.
     *
     * @param  Collection<int, TechnicalPlanItem>  $items
     * @return array<int, array{title:string, count:int}>
     */
    private function duplicateSources(Collection $items): array
    {
        return $items
            ->filter(fn (TechnicalPlanItem $i): bool => $i->hasFeedSource())
            ->groupBy(fn (TechnicalPlanItem $i): string => $i->source_type.':'.$i->source_id)
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->map(fn (Collection $group): array => [
                'title' => (string) $group->first()->title,
                'count' => $group->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * Việc nguồn hiện KHÔNG còn được giao cho chủ kế hoạch.
     *
     * @param  Collection<int, TechnicalPlanItem>  $items
     * @return array<int, string>
     */
    private function itemsOwnedByOthers(Collection $items, int $userId): array
    {
        $titles = [];

        foreach ($items as $item) {
            if (! $item->hasFeedSource()) {
                continue;
            }

            if ($this->feed->findItem($item->source_type, (int) $item->source_id, $userId) === null) {
                $titles[] = (string) $item->title;
            }
        }

        return array_values(array_unique($titles));
    }

    private function guardDateInsideWeek(Carbon $date, Carbon $weekStart): void
    {
        [$from, $to] = $this->weekRange($weekStart);

        if ($date->lt($from) || $date->gt($to)) {
            $this->fail('plan_date', 'Ngày thực hiện phải nằm trong tuần đang lập kế hoạch.');
        }
    }

    private function guardDayCapacity(int $userId, Carbon $date): void
    {
        $max = (int) config('technical.week_plan.max_items_per_day', 12);

        $count = TechnicalPlanItem::query()
            ->where('user_id', $userId)
            ->whereDate('plan_date', $date->toDateString())
            ->count();

        if ($count >= $max) {
            $this->fail('plan_date', 'Một ngày chỉ được lập tối đa '.$max.' công việc.');
        }
    }

    /** @param array<string, mixed> $source */
    private function guardDuplicateSource(int $userId, Carbon $date, array $source): void
    {
        if ($source['source_id'] === null) {
            return; // việc nội bộ cá nhân: không ràng buộc
        }

        $exists = TechnicalPlanItem::query()
            ->where('user_id', $userId)
            ->whereDate('plan_date', $date->toDateString())
            ->where('source_type', $source['source_type'])
            ->where('source_id', $source['source_id'])
            ->exists();

        if ($exists) {
            $this->fail('source_id', 'Công việc này đã có trong kế hoạch của ngày '.$date->format('d/m/Y').'.');
        }
    }

    /** @throws \Illuminate\Validation\ValidationException */
    private function fail(string $field, string $message): never
    {
        throw \Illuminate\Validation\ValidationException::withMessages([$field => $message]);
    }
}
