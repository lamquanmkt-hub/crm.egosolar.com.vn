<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingLead;
use Illuminate\Http\Request;

/**
 * Controller quản lý và import lead marketing.
 */
class MarketingLeadController extends Controller
{
    /**
     * Danh sách lead marketing có phân trang.
     */
    public function index(Request $request)
    {
        $leads = MarketingLead::query()
            ->with(['assignedUser', 'importedBy'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('marketing.leads.index', compact('leads'));
    }

    /**
     * Hiển thị form upload file CSV lead.
     */
    public function upload()
    {
        return view('marketing.leads.upload');
    }

    /**
     * Import lead từ file CSV (tự nhận diện header VN/EN, bỏ qua bản ghi trùng SĐT/email).
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'source' => ['nullable', 'string', 'max:100'],
            'import_date' => ['nullable', 'date'],
        ]);

        $source = $request->input('source') ?: 'Facebook Ads';
        $importDate = $request->input('import_date') ?: now()->toDateString();

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if (! $handle) {
            return back()->withErrors(['file' => 'Không đọc được file CSV.'])->withInput();
        }

        $firstRow = fgetcsv($handle, 0, ',');
        if ($firstRow === false) {
            fclose($handle);

            return back()->withErrors(['file' => 'File CSV trống.'])->withInput();
        }

        $normalize = function ($s) {
            $s = (string) $s;
            $s = preg_replace('/^\xEF\xBB\xBF/', '', $s); // bỏ BOM nếu có
            $s = trim($s);
            $s = mb_strtolower($s);
            $s = preg_replace('/\s+/', '_', $s);

            return $s;
        };

        $headers = array_map($normalize, $firstRow);

        $possibleHeaders = [
            // chuẩn hệ thống
            'name', 'phone', 'email', 'source', 'campaign', 'adset', 'ad', 'note',

            // CSV tiếng Việt bạn đang dùng
            'thời_gian_tạo',
            'tên',
            'email',
            'nguồn',
            'mẫu',
            'kênh',
            'giai_đoạn',
            'người_chịu_trách_nhiệm',
            'nhãn',
            'điện_thoại',
            'số_điện_thoại_phụ',
            'số_whatsapp',
        ];

        $hasHeader = count(array_intersect($headers, $possibleHeaders)) >= 2;

        $keyMap = [
            // name
            'name' => ['name', 'full_name', 'tên'],

            // phone (ưu tiên điện_thoại, fallback số phụ / whatsapp)
            'phone' => ['phone', 'phone_number', 'điện_thoại', 'số_điện_thoại_phụ', 'số_whatsapp'],

            // email
            'email' => ['email'],

            // source: mình lấy từ "kênh" (Messenger/Email/...)
            'source' => ['source', 'kênh'],

            // campaign: mình lấy từ "mẫu"
            'campaign' => ['campaign', 'campaign_name', 'mẫu'],

            // note: gom thông tin "nguồn / nhãn / giai đoạn / người chịu trách nhiệm / thời gian tạo"
            'note' => ['note', 'nguồn', 'nhãn', 'giai_đoạn', 'người_chịu_trách_nhiệm', 'thời_gian_tạo'],

            // created time (để nhét vào note cho dễ truy vết)
            'created_time' => ['created_time', 'time', 'thời_gian_tạo'],
        ];

        $colIndex = [];
        if ($hasHeader) {
            foreach ($keyMap as $key => $aliases) {
                foreach ($aliases as $alias) {
                    $idx = array_search($alias, $headers, true);
                    if ($idx !== false) {
                        $colIndex[$key] = $idx;
                        break;
                    }
                }
            }
        }

        $cleanPhone = function ($phone) {
            $phone = trim((string) $phone);
            if ($phone === '') {
                return null;
            }

            $phone = preg_replace('/[^\d\+]/', '', $phone);

            if (str_starts_with($phone, '+84')) {
                $phone = '0'.substr($phone, 3);
            }

            return $phone ?: null;
        };

        $getVal = function (array $row, string $key) use ($colIndex, $hasHeader) {
            if ($hasHeader && isset($colIndex[$key])) {
                return $row[$colIndex[$key]] ?? null;
            }

            return null;
        };

        $inserted = 0;
        $skipped = 0;

        $processRow = function (array $row) use (
            $hasHeader, $getVal, $cleanPhone, $source, $importDate, &$inserted, &$skipped
        ) {
            if ($hasHeader) {
                $name =
    trim((string) $getVal($row, 'full_name'))
    ?: trim((string) $getVal($row, 'name'));
                $phone = $cleanPhone($getVal($row, 'điện_thoại'));
                if (! $phone) {
                    $phone = $cleanPhone($getVal($row, 'số_điện_thoại_phụ'));
                }
                if (! $phone) {
                    $phone = $cleanPhone($getVal($row, 'số_whatsapp'));
                }
                if (! $phone) {
                    $phone = $cleanPhone($getVal($row, 'phone'));
                }
                $email = trim((string) $getVal($row, 'email'));
                $campaign = trim((string) $getVal($row, 'campaign'));
                $adset = trim((string) $getVal($row, 'adset'));
                $ad = trim((string) $getVal($row, 'ad'));
                $note = trim((string) $getVal($row, 'note'));
                $createdTime = trim((string) $getVal($row, 'created_time'));
                if ($createdTime) {
                    $note = $note
                        ? $note.' | Created: '.$createdTime
                        : 'Created: '.$createdTime;
                }
                $rowSource = trim((string) $getVal($row, 'source')) ?: $source;

                if (! $note) {
                    $ct = trim((string) $getVal($row, 'created_time'));
                    if ($ct) {
                        $note = 'Created: '.$ct;
                    }
                }
            } else {
                $v0 = trim((string) ($row[0] ?? ''));
                $v1 = trim((string) ($row[1] ?? ''));
                $v3 = trim((string) ($row[3] ?? ''));

                $looksLikeDateTime = preg_match('/\d{1,2}\/\d{1,2}\/\d{4}/', $v0) || str_contains($v0, ':');
                $name = $looksLikeDateTime ? $v1 : $v0;

                $email = null;
                foreach ($row as $cell) {
                    $cell = trim((string) $cell);
                    if (filter_var($cell, FILTER_VALIDATE_EMAIL)) {
                        $email = $cell;
                        break;
                    }
                }

                $phone = $cleanPhone($v3);
                if (! $phone) {
                    foreach ($row as $cell) {
                        $p = $cleanPhone($cell);
                        if ($p && strlen(preg_replace('/\D/', '', $p)) >= 9) {
                            $phone = $p;
                            break;
                        }
                    }
                }

                $campaign = trim((string) ($row[4] ?? ''));
                $adset = trim((string) ($row[5] ?? ''));
                $ad = trim((string) ($row[6] ?? ''));
                $note = trim((string) ($row[7] ?? ''));

                $rowSource = $source;
            }

            if (! $phone && ! $email) {
                $skipped++;

                return;
            }

            $existsQuery = MarketingLead::query();
            if ($phone) {
                $existsQuery->where('phone', $phone);
            } else {
                $existsQuery->where('email', $email);
            }

            if ($existsQuery->exists()) {
                $skipped++;

                return;
            }

            MarketingLead::create([
                'name' => $name ?: null,
                'phone' => $phone,
                'email' => $email ?: null,
                'source' => $rowSource ?: null,
                'campaign' => $campaign ?: null,
                'adset' => $adset ?: null,
                'ad' => $ad ?: null,
                'status' => MarketingLead::STATUS_NEW,
                'assigned_user_id' => null,
                'imported_by' => auth()->id(),
                'import_date' => $importDate,
                'note' => $note ?: null,
            ]);

            $inserted++;
        };

        if (! $hasHeader) {
            $processRow($firstRow);
        }

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }
            $processRow($row);
        }

        fclose($handle);

        return redirect()
            ->route('marketing.leads.index')
            ->with('success', "Import xong ✅ Thêm mới: {$inserted} | Bỏ qua (trùng/thiếu): {$skipped}");
    }
}
