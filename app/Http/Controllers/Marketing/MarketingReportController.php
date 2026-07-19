<?php

namespace App\Http\Controllers\Marketing;

use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarketingReportController extends Controller
{
    public function ads(Request $request)
    {
        $table = 'mkt_actual_kpi_daily';

        if (!Schema::hasTable($table)) {
            $filters = [
                'from' => Carbon::today()->startOfMonth()->toDateString(),
                'to' => Carbon::today()->toDateString(),
                'channel' => '',
                'campaign' => '',
                'compare' => '0',
            ];

            return view('marketing.reports.ads', [
                'filters' => $filters,
                'from' => $filters['from'],
                'to' => $filters['to'],
                'channel' => $filters['channel'],
                'campaign' => $filters['campaign'],
                'compare' => $filters['compare'],
                'rangeText' => Carbon::parse($filters['from'])->format('d/m/Y') . ' đến ' . Carbon::parse($filters['to'])->format('d/m/Y'),

                'kpi' => (object)[
                    'spend' => 0,
                    'impressions' => 0,
                    'clicks' => 0,
                    'leads' => 0,
                    'revenue' => 0,
                    'roas' => 0,
                    'cpl' => 0,
                ],
                'summary' => (object)[
                    'spend' => 0,
                    'impressions' => 0,
                    'clicks' => 0,
                    'leads' => 0,
                    'revenue' => 0,
                ],

                'daily' => collect(),
                'rows' => collect(),
                'campaignRows' => collect(),

                'channels' => collect(),
                'channelOptions' => collect(),
                'campaigns' => collect(),
                'campaignOptions' => collect(),

                'compareKpi' => null,
                'cmp' => null,
                'deltas' => [],
            ]);
        }

        $today = Carbon::today();

        $fromStr = $request->get('from') ?: $today->copy()->startOfMonth()->toDateString();
        $toStr   = $request->get('to') ?: $today->toDateString();

        try {
            $fromC = Carbon::parse($fromStr)->startOfDay();
        } catch (\Throwable $e) {
            $fromC = $today->copy()->startOfMonth();
        }

        try {
            $toC = Carbon::parse($toStr)->endOfDay();
        } catch (\Throwable $e) {
            $toC = $today->copy()->endOfDay();
        }

        if ($fromC->gt($toC)) {
            [$fromC, $toC] = [$toC, $fromC];
        }

        $from = $fromC->toDateString();
        $to   = $toC->toDateString();

        $channel  = trim((string)$request->get('channel', ''));
        $campaign = trim((string)$request->get('campaign', ''));

        $compare = (string)$request->get('compare', '0');
        $compareOn = ($compare === '1' || $compare === 'prev' || $compare === 'prev_period');

        $filters = [
            'from' => $from,
            'to' => $to,
            'channel' => $channel,
            'campaign' => $campaign,
            'compare' => $compareOn ? '1' : '0',
        ];

        $rangeText = Carbon::parse($from)->format('d/m/Y') . ' đến ' . Carbon::parse($to)->format('d/m/Y');

        $channelOptions = DB::table($table)
            ->select('channel')
            ->whereNotNull('channel')
            ->where('channel', '!=', '')
            ->groupBy('channel')
            ->orderBy('channel')
            ->pluck('channel');

        $campaignOptions = DB::table($table)
            ->select('campaign_name')
            ->whereNotNull('campaign_name')
            ->where('campaign_name', '!=', '')
            ->when($channel !== '', fn ($q) => $q->where('channel', $channel))
            ->groupBy('campaign_name')
            ->orderBy('campaign_name')
            ->pluck('campaign_name');

        $base = DB::table($table)
            ->whereBetween('date', [$from, $to])
            ->when($channel !== '', fn ($q) => $q->where('channel', $channel))
            ->when($campaign !== '', fn ($q) => $q->where('campaign_name', $campaign));

        $kpiRow = (clone $base)->selectRaw('
            COALESCE(SUM(spend),0) as spend,
            COALESCE(SUM(impressions),0) as impressions,
            COALESCE(SUM(clicks),0) as clicks,
            COALESCE(SUM(leads),0) as leads,
            COALESCE(SUM(revenue),0) as revenue
        ')->first();

        $spend = (float)($kpiRow->spend ?? 0);
        $leads = (float)($kpiRow->leads ?? 0);
        $rev   = (float)($kpiRow->revenue ?? 0);

        $kpi = (object)[
            'spend' => $spend,
            'impressions' => (float)($kpiRow->impressions ?? 0),
            'clicks' => (float)($kpiRow->clicks ?? 0),
            'leads' => $leads,
            'revenue' => $rev,
            'cpl' => $leads > 0 ? round($spend / $leads, 0) : 0,
            'roas' => $spend > 0 ? round($rev / $spend, 2) : 0,
        ];

        $summary = (object)[
            'spend' => $kpi->spend,
            'impressions' => $kpi->impressions,
            'clicks' => $kpi->clicks,
            'leads' => $kpi->leads,
            'revenue' => $kpi->revenue,
        ];

        $daily = (clone $base)
            ->selectRaw('
                date as date,
                COALESCE(SUM(spend),0) as spend,
                COALESCE(SUM(leads),0) as leads,
                COALESCE(SUM(revenue),0) as revenue
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($r) {
                $sp = (float)($r->spend ?? 0);
                $ld = (float)($r->leads ?? 0);
                $rv = (float)($r->revenue ?? 0);

                $r->cpl = $ld > 0 ? round($sp / $ld, 2) : 0;
                $r->roas = $sp > 0 ? round($rv / $sp, 2) : 0;

                return $r;
            });

        $hasExternal = Schema::hasColumn($table, 'campaign_external_id');

        $selectCampaign = '
            COALESCE(campaign_name,"(no campaign)") as campaign_name,
            COALESCE(channel,"(no channel)") as channel,
            COALESCE(SUM(spend),0) as spend,
            COALESCE(SUM(impressions),0) as impressions,
            COALESCE(SUM(clicks),0) as clicks,
            COALESCE(SUM(leads),0) as leads,
            COALESCE(SUM(revenue),0) as revenue
        ';

        if ($hasExternal) {
            $selectCampaign .= ', MAX(campaign_external_id) as campaign_external_id';
        }

        $campaignRows = (clone $base)
            ->selectRaw($selectCampaign)
            ->groupBy('campaign_name', 'channel')
            ->orderByDesc('spend')
            ->get()
            ->map(function ($r) {
                $sp = (float)($r->spend ?? 0);
                $ld = (float)($r->leads ?? 0);
                $rv = (float)($r->revenue ?? 0);

                $r->cpl = $ld > 0 ? round($sp / $ld, 0) : 0;
                $r->roas = $sp > 0 ? round($rv / $sp, 2) : 0;

                return $r;
            });

        $rows = $campaignRows;

        $compareKpi = null;
        $cmp = null;
        $deltas = [];

        if ($compareOn) {
            $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;

            $prevTo = Carbon::parse($from)->subDay()->toDateString();
            $prevFrom = Carbon::parse($prevTo)->subDays($days - 1)->toDateString();

            $prevBase = DB::table($table)
                ->whereBetween('date', [$prevFrom, $prevTo])
                ->when($channel !== '', fn ($q) => $q->where('channel', $channel))
                ->when($campaign !== '', fn ($q) => $q->where('campaign_name', $campaign));

            $prev = $prevBase->selectRaw('
                COALESCE(SUM(spend),0) as spend,
                COALESCE(SUM(impressions),0) as impressions,
                COALESCE(SUM(clicks),0) as clicks,
                COALESCE(SUM(leads),0) as leads,
                COALESCE(SUM(revenue),0) as revenue
            ')->first();

            $pSpend = (float)($prev->spend ?? 0);
            $pLeads = (float)($prev->leads ?? 0);
            $pRev   = (float)($prev->revenue ?? 0);

            $compareKpi = (object)[
                'spend' => $pSpend,
                'impressions' => (float)($prev->impressions ?? 0),
                'clicks' => (float)($prev->clicks ?? 0),
                'leads' => $pLeads,
                'revenue' => $pRev,
                'cpl' => $pLeads > 0 ? round($pSpend / $pLeads, 0) : 0,
                'roas' => $pSpend > 0 ? round($pRev / $pSpend, 2) : 0,
                'from' => $prevFrom,
                'to' => $prevTo,
            ];

            $cmp = $compareKpi;

            $pct = function ($cur, $prev) {
                $cur = (float)($cur ?? 0);
                $prev = (float)($prev ?? 0);

                if ($prev == 0) {
                    return null;
                }

                return round((($cur - $prev) / $prev) * 100, 1);
            };

            $deltas = [
                'prev_range' => ['from' => $prevFrom, 'to' => $prevTo],
                'spend' => $pct($kpi->spend, $compareKpi->spend),
                'impressions' => $pct($kpi->impressions, $compareKpi->impressions),
                'clicks' => $pct($kpi->clicks, $compareKpi->clicks),
                'leads' => $pct($kpi->leads, $compareKpi->leads),
                'revenue' => $pct($kpi->revenue, $compareKpi->revenue),
                'roas' => $pct($kpi->roas, $compareKpi->roas),
                'cpl' => $pct($kpi->cpl, $compareKpi->cpl),
            ];
        }

        return view('marketing.reports.ads', [
            'filters' => $filters,
            'from' => $from,
            'to' => $to,
            'channel' => $channel,
            'campaign' => $campaign,
            'compare' => $filters['compare'],
            'rangeText' => $rangeText,

            'kpi' => $kpi,
            'summary' => $summary,

            'daily' => $daily,
            'rows' => $rows,
            'campaignRows' => $campaignRows,

            'channels' => $channelOptions,
            'channelOptions' => $channelOptions,
            'campaigns' => $campaignOptions,
            'campaignOptions' => $campaignOptions,

            'compareKpi' => $compareKpi,
            'cmp' => $cmp,
            'deltas' => $deltas,
        ]);
    }

    public function adsInput(Request $request)
    {
        $planTable = 'mkt_ads_plans';
        $planPlatformCol = 'platform';
        $planCampaignCol = 'campaign_name';

        $actualTable = 'mkt_actual_kpi_daily';
        $actualChannelCol = 'channel';

        $date = $request->get('date') ?: Carbon::today()->toDateString();
$channelParam = trim((string) old('channel', $request->get('channel', '')));
$campaignName = trim((string) old('campaign_name', $request->get('campaign_name', '')));

        $channelOptions = collect();

        if (Schema::hasTable($planTable)) {
            $channelOptions = $channelOptions->merge(
                DB::table($planTable)
                    ->select($planPlatformCol)
                    ->whereNotNull($planPlatformCol)
                    ->where($planPlatformCol, '!=', '')
                    ->groupBy($planPlatformCol)
                    ->orderBy($planPlatformCol)
                    ->pluck($planPlatformCol)
            );
        }

        if (Schema::hasTable($actualTable)) {
            $channelOptions = $channelOptions->merge(
                DB::table($actualTable)
                    ->select($actualChannelCol)
                    ->whereNotNull($actualChannelCol)
                    ->where($actualChannelCol, '!=', '')
                    ->groupBy($actualChannelCol)
                    ->orderBy($actualChannelCol)
                    ->pluck($actualChannelCol)
            );
        }

        $channelOptions = $channelOptions->unique()->values();

        $campaignOptions = collect();
        if (Schema::hasTable($planTable)) {
            $q = DB::table($planTable)
                ->select($planCampaignCol)
                ->whereNotNull($planCampaignCol)
                ->where($planCampaignCol, '!=', '');

            if ($channelParam !== '') {
                $q->whereRaw('LOWER(' . $planPlatformCol . ') = LOWER(?)', [$channelParam]);
            }

            $campaignOptions = $q->groupBy($planCampaignCol)
                ->orderBy($planCampaignCol)
                ->pluck($planCampaignCol);
        }

        $recent = collect();
        if (Schema::hasTable($actualTable)) {
            $recent = DB::table($actualTable)
    ->select('date','channel','campaign_name','spend','impressions','clicks','leads','revenue','updated_at')
    ->orderByDesc('date')
    ->orderByDesc('updated_at')
    ->limit(100)
    ->get();
        }

        return view('marketing.reports.ads_input', [
            'date' => $date,
            'channelParam' => $channelParam,
            'channelOptions' => $channelOptions,
            'campaignOptions' => $campaignOptions,
            'recent' => $recent,
            'campaignName' => $campaignName,
        ]);
    }

    public function adsStore(Request $request)
    {
        $actualTable = 'mkt_actual_kpi_daily';

        if (!Schema::hasTable($actualTable)) {
            return back()->with('error', 'Chưa có bảng mkt_actual_kpi_daily.');
        }

        $data = $request->validate([
            'date' => 'required|date',
            'channel' => 'required|string|max:255',
            'campaign_name' => 'required|string|max:255',
            'spend' => 'required|numeric|min:0',
            'impressions' => 'nullable|integer|min:0',
            'clicks' => 'nullable|integer|min:0',
            'leads' => 'nullable|integer|min:0',
            'revenue' => 'nullable|numeric|min:0',
        ]);

        $payload = [
            'spend' => (float)$data['spend'],
            'impressions' => (int)($data['impressions'] ?? 0),
            'clicks' => (int)($data['clicks'] ?? 0),
            'leads' => (int)($data['leads'] ?? 0),
            'revenue' => (float)($data['revenue'] ?? 0),
        ];

        if (Schema::hasColumn($actualTable, 'updated_at')) {
            $payload['updated_at'] = now();
        }

        if (Schema::hasColumn($actualTable, 'created_at')) {
            $payload['created_at'] = now();
        }

        DB::table($actualTable)->updateOrInsert(
            [
                'date' => Carbon::parse($data['date'])->toDateString(),
                'channel' => trim($data['channel']),
                'campaign_name' => trim($data['campaign_name']),
            ],
            $payload
        );

        $nextDate = Carbon::parse($data['date'])->addDay()->toDateString();

$qs = http_build_query([
    'date' => $nextDate,
    'channel' => trim($data['channel']),
    'campaign_name' => trim($data['campaign_name']),
]);

return redirect(url('/marketing/report/ads/input') . '?' . $qs)
    ->with('success', 'Đã lưu dữ liệu ADS.');
    }

    public function adsDelete(Request $request)
    {
        $actualTable = 'mkt_actual_kpi_daily';

        if (!Schema::hasTable($actualTable)) {
            return back()->with('error', 'Chưa có bảng mkt_actual_kpi_daily.');
        }

        $data = $request->validate([
            'date' => 'required|date',
            'channel' => 'required|string|max:255',
            'campaign_name' => 'required|string|max:255',
        ]);

        $deleted = DB::table($actualTable)
            ->where('date', Carbon::parse($data['date'])->toDateString())
            ->where('channel', trim($data['channel']))
            ->where('campaign_name', trim($data['campaign_name']))
            ->delete();

        if ($deleted) {
            return back()->with('success', 'Đã xóa dữ liệu chiến dịch.');
        }

        return back()->with('error', 'Không tìm thấy dữ liệu để xóa.');
    }


    private function ensureAdsActualTableForImport(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS mkt_actual_kpi_daily (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                date DATE NOT NULL,
                channel VARCHAR(120) NULL,
                channel_id BIGINT UNSIGNED NULL,
                platform_id BIGINT UNSIGNED NULL,
                website_id BIGINT UNSIGNED NULL,
                campaign_external_id VARCHAR(255) NULL,
                campaign_name VARCHAR(255) NOT NULL,
                spend DECIMAL(15,2) NOT NULL DEFAULT 0,
                impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
                clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
                leads BIGINT UNSIGNED NOT NULL DEFAULT 0,
                revenue DECIMAL(15,2) NOT NULL DEFAULT 0,
                sessions BIGINT UNSIGNED NOT NULL DEFAULT 0,
                conversions BIGINT UNSIGNED NOT NULL DEFAULT 0,
                source_type VARCHAR(100) NULL,
                raw_payload LONGTEXT NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                INDEX mkt_actual_date_index (date),
                INDEX mkt_actual_channel_index (channel),
                INDEX mkt_actual_campaign_index (campaign_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if (!Schema::hasColumn('mkt_actual_kpi_daily', 'source_type')) {
            DB::statement("ALTER TABLE mkt_actual_kpi_daily ADD COLUMN source_type VARCHAR(100) NULL AFTER conversions");
        }

        if (!Schema::hasColumn('mkt_actual_kpi_daily', 'raw_payload')) {
            DB::statement("ALTER TABLE mkt_actual_kpi_daily ADD COLUMN raw_payload LONGTEXT NULL AFTER source_type");
        }
    }

    public function adsImport(Request $request)
    {
        $this->ensureAdsActualTableForImport();

        $actualTable = 'mkt_actual_kpi_daily';

        $request->validate([
            'ads_file' => 'required|file|max:20480',
            'channel' => 'nullable|string|max:255',
        ]);

        $file = $request->file('ads_file');
        $ext = strtolower($file->getClientOriginalExtension());
        $channelDefault = trim((string) $request->input('channel', 'Facebook')) ?: 'Facebook';

        try {
            $rows = $ext === 'csv'
                ? $this->readAdsCsvRows($file->getRealPath())
                : $this->readAdsXlsxRows($file->getRealPath());
        } catch (\Throwable $e) {
            return back()->with('error', 'Không đọc được file: ' . $e->getMessage());
        }

        if (empty($rows)) {
            return back()->with('error', 'File không có dữ liệu để nhập.');
        }

        $columns = array_flip(Schema::getColumnListing($actualTable));

        $imported = 0;
        $skipped = 0;
        $totalSpend = 0;
        $totalLeads = 0;
        $fromMin = null;
        $toMax = null;

        foreach ($rows as $row) {
            $n = $this->normalizeAdsRowKeys($row);

            $campaignName = trim((string) $this->adsPick($n, [
                'ten chien dich',
                'campaign name',
                'campaign',
                'chien dich',
            ]));

            if ($campaignName === '') {
                $skipped++;
                continue;
            }

            $startRaw = $this->adsPick($n, [
                'luot bat dau bao cao',
                'bat dau bao cao',
                'reporting starts',
                'ngay bat dau',
            ]);

            $endRaw = $this->adsPick($n, [
                'luot ket thuc bao cao',
                'ket thuc bao cao',
                'reporting ends',
                'ngay ket thuc',
            ]);

            $date = $this->parseAdsDate($endRaw) ?: $this->parseAdsDate($startRaw) ?: \Carbon\Carbon::today()->toDateString();
            $startDate = $this->parseAdsDate($startRaw) ?: $date;
            $endDate = $this->parseAdsDate($endRaw) ?: $date;

            $spend = $this->adsNumber($this->adsPick($n, [
                'so tien da chi tieu vnd',
                'amount spent vnd',
                'amount spent',
                'spend',
                'chi tieu',
            ]));

            $impressions = (int) round($this->adsNumber($this->adsPick($n, [
                'luot hien thi',
                'impressions',
            ])));

            $reach = (int) round($this->adsNumber($this->adsPick($n, [
                'nguoi tiep can',
                'reach',
            ])));

            $leads = (int) round($this->adsNumber($this->adsPick($n, [
                'ket qua',
                'results',
                'leads',
                'lead',
            ])));

            $clicks = (int) round($this->adsNumber($this->adsPick($n, [
                'clicks',
                'luot click',
                'lien ket click',
            ])));

            $cpl = $leads > 0 ? round($spend / max($leads, 1), 0) : 0;

            $payload = [
                'spend' => $spend,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'leads' => $leads,
                'revenue' => 0,
                'source_type' => 'excel_meta_ads',
                'raw_payload' => json_encode([
                    'file' => $file->getClientOriginalName(),
                    'report_start' => $startDate,
                    'report_end' => $endDate,
                    'reach' => $reach,
                    'cpl' => $cpl,
                    'raw' => $row,
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
                'created_at' => now(),
            ];

            if (isset($columns['reach'])) {
                $payload['reach'] = $reach;
            }

            $payload = array_intersect_key($payload, $columns);

            DB::table($actualTable)->updateOrInsert(
                [
                    'date' => $date,
                    'channel' => $channelDefault,
                    'campaign_name' => $campaignName,
                ],
                $payload
            );

            $imported++;
            $totalSpend += $spend;
            $totalLeads += $leads;
            $fromMin = $fromMin ? min($fromMin, $startDate) : $startDate;
            $toMax = $toMax ? max($toMax, $endDate) : $endDate;
        }

        $message = 'Đã import ' . $imported . ' chiến dịch. Bỏ qua ' . $skipped . ' dòng. Tổng chi tiêu: ' . number_format($totalSpend, 0, ',', '.') . ' đ, tổng leads: ' . number_format($totalLeads, 0, ',', '.');

        return redirect(url('/marketing/report/ads') . '?' . http_build_query([
            'from' => $fromMin ?: \Carbon\Carbon::today()->startOfMonth()->toDateString(),
            'to' => $toMax ?: \Carbon\Carbon::today()->toDateString(),
            'channel' => $channelDefault,
        ]))->with('success', $message);
    }

    private function readAdsCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');

        if (!$handle) {
            return [];
        }

        $headers = null;
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = $data;
                continue;
            }

            $row = [];

            foreach ($headers as $i => $header) {
                $row[(string) $header] = $data[$i] ?? null;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function readAdsXlsxRows(string $path): array
    {
        $sharedXml = null;
        $sheetXml = null;

        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();

            if ($zip->open($path) !== true) {
                throw new \RuntimeException('Không mở được file Excel.');
            }

            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

            $sheetName = null;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);

                if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                    $sheetName = $name;
                    break;
                }
            }

            if (!$sheetName) {
                $zip->close();
                throw new \RuntimeException('Không tìm thấy worksheet trong file Excel.');
            }

            $sheetXml = $zip->getFromName($sheetName);
            $zip->close();
        } else {
            if (!function_exists('shell_exec')) {
                throw new \RuntimeException('Server chưa bật ZipArchive và cũng khóa shell_exec. Vui lòng bật PHP extension zip hoặc lưu file Excel thành CSV rồi upload.');
            }

            $unzipPath = trim((string) shell_exec('command -v unzip 2>/dev/null'));

            if ($unzipPath === '') {
                throw new \RuntimeException('Server chưa bật ZipArchive và không có lệnh unzip. Vui lòng bật PHP extension zip hoặc lưu file Excel thành CSV rồi upload.');
            }

            $listCommand = escapeshellcmd($unzipPath) . ' -Z1 ' . escapeshellarg($path) . ' 2>/dev/null';
            $listOutput = (string) shell_exec($listCommand);

            if (trim($listOutput) === '') {
                $listCommand = escapeshellcmd($unzipPath) . ' -l ' . escapeshellarg($path) . ' 2>/dev/null';
                $listOutput = (string) shell_exec($listCommand);
            }

            $sheetName = null;

            foreach (preg_split('/\R/', $listOutput) as $line) {
                if (preg_match('#(xl/worksheets/sheet\d+\.xml)#', $line, $m)) {
                    $sheetName = $m[1];
                    break;
                }
            }

            if (!$sheetName) {
                throw new \RuntimeException('Không tìm thấy worksheet trong file Excel.');
            }

            $sharedXml = (string) shell_exec(escapeshellcmd($unzipPath) . ' -p ' . escapeshellarg($path) . ' xl/sharedStrings.xml 2>/dev/null');
            $sheetXml = (string) shell_exec(escapeshellcmd($unzipPath) . ' -p ' . escapeshellarg($path) . ' ' . escapeshellarg($sheetName) . ' 2>/dev/null');
        }

        if (!$sheetXml) {
            throw new \RuntimeException('Không đọc được nội dung sheet trong file Excel.');
        }

        $shared = [];

        if ($sharedXml) {
            $sxShared = simplexml_load_string($sharedXml);

            if ($sxShared) {
                foreach ($sxShared->si ?? [] as $si) {
                    $parts = [];

                    if (isset($si->t)) {
                        $parts[] = (string) $si->t;
                    }

                    foreach ($si->r ?? [] as $r) {
                        $parts[] = (string) ($r->t ?? '');
                    }

                    $shared[] = implode('', $parts);
                }
            }
        }

        $sx = simplexml_load_string($sheetXml);

        if (!$sx) {
            throw new \RuntimeException('File Excel không đúng định dạng XML worksheet.');
        }

        $matrix = [];

        foreach ($sx->sheetData->row ?? [] as $row) {
            $cells = [];

            foreach ($row->c ?? [] as $cell) {
                $ref = (string) $cell['r'];
                preg_match('/^([A-Z]+)/', $ref, $m);

                $idx = $this->adsColumnIndex($m[1] ?? 'A');
                $type = (string) $cell['t'];

                if ($type === 's') {
                    $value = $shared[(int) ($cell->v ?? 0)] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                } else {
                    $value = isset($cell->v) ? (string) $cell->v : '';
                }

                $cells[$idx] = $value;
            }

            if (!empty($cells)) {
                ksort($cells);
                $matrix[] = $cells;
            }
        }

        if (empty($matrix)) {
            return [];
        }

        $headerRow = array_shift($matrix);
        $maxCol = max(array_keys($headerRow));
        $headers = [];

        for ($i = 0; $i <= $maxCol; $i++) {
            $headers[$i] = trim((string) ($headerRow[$i] ?? ''));
        }

        $rows = [];

        foreach ($matrix as $line) {
            $row = [];
            $hasValue = false;

            foreach ($headers as $i => $header) {
                if ($header === '') {
                    continue;
                }

                $value = $line[$i] ?? null;

                if ($value !== null && $value !== '') {
                    $hasValue = true;
                }

                $row[$header] = $value;
            }

            if ($hasValue) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function adsColumnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $num = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $num = $num * 26 + (ord($letters[$i]) - 64);
        }

        return $num - 1;
    }

    private function normalizeAdsRowKeys(array $row): array
    {
        $out = [];

        foreach ($row as $key => $value) {
            $out[$this->adsNorm((string) $key)] = $value;
        }

        return $out;
    }

    private function adsNorm(string $value): string
    {
        $value = \Illuminate\Support\Str::ascii(trim($value));
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function adsPick(array $row, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            $norm = $this->adsNorm($key);

            if (array_key_exists($norm, $row)) {
                return $row[$norm];
            }
        }

        return $default;
    }

    private function adsNumber($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = preg_replace('/[^0-9,\.\-]/', '', (string) $value);

        if (substr_count($value, ',') > 0 && substr_count($value, '.') === 0) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : 0;
    }

    private function parseAdsDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return \Carbon\Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
            } catch (\Throwable $e) {
                return null;
            }
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
    public function seo()
    {
        return view('marketing.reports.seo');
    }

    public function overview()
    {
        return view('marketing.reports.overview');
    }
}