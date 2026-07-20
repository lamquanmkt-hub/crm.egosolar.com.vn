<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EgoSidebarStatus
{
    public static function data(bool $debug = false): array
    {
        $data = [
            'online' => self::onlineCount(),
            'working' => self::workingTodayCount(),
            'employees' => self::employeesCount(),
            'updated_at' => now()->format('H:i'),
        ];

        if ($debug) {
            $data['debug'] = [
                'users_has_last_seen_at' => Schema::hasTable('users') && Schema::hasColumn('users', 'last_seen_at'),
                'attendance_detected' => self::detectAttendanceConfig(),
                'employee_detected' => self::detectEmployeeConfig(),
                'note' => 'working = số user đã check-in hôm nay: work_date hôm nay + check_in_at không rỗng',
            ];
        }

        return $data;
    }

    public static function onlineCount(): int
    {
        try {
            if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'last_seen_at')) {
                return auth()->check() ? 1 : 0;
            }

            // Cứ user đang đăng nhập thì cập nhật online ngay tại đây.
            // Cách này giúp sidebar luôn chạy kể cả middleware chưa ăn.
            if (auth()->check()) {
                DB::table('users')
                    ->where('id', auth()->id())
                    ->update([
                        'last_seen_at' => now(),
                    ]);
            }

            $query = DB::table('users')
                ->whereNotNull('last_seen_at')
                ->where('last_seen_at', '>=', now()->subMinutes(5));

            if (Schema::hasColumn('users', 'is_active')) {
                $query->where('is_active', 1);
            }

            if (Schema::hasColumn('users', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            $count = (int) $query->count();

            // Nếu đang đăng nhập mà vì lý do nào đó query vẫn ra 0,
            // ép tối thiểu = 1 để đúng thực tế có bạn đang online.
            return auth()->check() ? max($count, 1) : $count;
        } catch (\Throwable $e) {
            return auth()->check() ? 1 : 0;
        }
    }

    public static function workingTodayCount(): int
    {
        try {
            $config = self::detectAttendanceConfig();

            if (! $config['table']) {
                return 0;
            }

            $query = DB::table($config['table']);
            EgoCompanyScope::applyToQuery($query, $config['table'], $config['table']);

            if ($config['date_column']) {
                $query->whereDate($config['date_column'], now()->toDateString());
            } else {
                return 0;
            }

            if ($config['check_in_column']) {
                $query->whereNotNull($config['check_in_column']);
            }

            if ($config['status_column']) {
                $query->where(function ($q) use ($config) {
                    $q->whereNull($config['status_column'])
                        ->orWhereNotIn($config['status_column'], [
                            'absent',
                            'leave',
                            'off',
                            'rejected',
                            'cancelled',
                            'canceled',
                        ]);
                });
            }

            if ($config['person_column']) {
                return (int) $query
                    ->whereNotNull($config['person_column'])
                    ->distinct()
                    ->count($config['person_column']);
            }

            return (int) $query->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function employeesCount(): int
    {
        try {
            $config = self::detectEmployeeConfig();

            if (! $config['table']) {
                return 0;
            }

            $query = DB::table($config['table']);
            EgoCompanyScope::applyToQuery($query, $config['table'], $config['table']);

            if ($config['status_column']) {
                if (in_array($config['status_column'], ['is_active', 'active'], true)) {
                    $query->where($config['status_column'], 1);
                } else {
                    $query->where(function ($q) use ($config) {
                        $q->whereNull($config['status_column'])
                            ->orWhereIn($config['status_column'], [
                                'active',
                                'working',
                                'on',
                                'enabled',
                                1,
                            ]);
                    });
                }
            }

            if (Schema::hasColumn($config['table'], 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            return (int) $query->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function detectAttendanceConfig(): array
    {
        $tables = [
            'attendance_records',
            'attendances',
            'hr_attendances',
            'employee_attendances',
            'time_attendances',
            'checkins',
            'check_ins',
        ];

        $dateColumns = [
            'work_date',
            'attendance_date',
            'date',
            'checked_date',
            'day',
        ];

        $checkInColumns = [
            'check_in_at',
            'clock_in_at',
            'checked_in_at',
            'time_in',
            'start_at',
            'created_at',
        ];

        $personColumns = [
            'user_id',
            'employee_id',
            'staff_id',
            'personnel_id',
        ];

        $statusColumns = [
            'status',
            'attendance_status',
            'type',
        ];

        $table = collect($tables)->first(fn ($table) => Schema::hasTable($table));

        if (! $table) {
            return [
                'table' => null,
                'date_column' => null,
                'check_in_column' => null,
                'person_column' => null,
                'status_column' => null,
            ];
        }

        return [
            'table' => $table,
            'date_column' => collect($dateColumns)->first(fn ($col) => Schema::hasColumn($table, $col)),
            'check_in_column' => collect($checkInColumns)->first(fn ($col) => Schema::hasColumn($table, $col)),
            'person_column' => collect($personColumns)->first(fn ($col) => Schema::hasColumn($table, $col)),
            'status_column' => collect($statusColumns)->first(fn ($col) => Schema::hasColumn($table, $col)),
        ];
    }

    public static function detectEmployeeConfig(): array
    {
        $tables = [
            'users',
            'employees',
            'hr_employees',
            'staff',
        ];

        $statusColumns = [
            'is_active',
            'active',
            'status',
            'employee_status',
        ];

        $table = collect($tables)->first(fn ($table) => Schema::hasTable($table));

        if (! $table) {
            return [
                'table' => null,
                'status_column' => null,
            ];
        }

        return [
            'table' => $table,
            'status_column' => collect($statusColumns)->first(fn ($col) => Schema::hasColumn($table, $col)),
        ];
    }
}
