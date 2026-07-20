<?php

namespace App\Http\Controllers\Solar;

use App\Http\Controllers\Controller;
use App\Models\SolarProvince;
use App\Models\SolarSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controller tính toán cấu hình và báo giá nhanh hệ thống điện mặt trời.
 */
class SolarCalculatorController extends Controller
{
    private const DEFAULT_ELECTRICITY_PRICE = 3000;

    private const DEFAULT_PANEL_POWER_WP = 630;

    private const DEFAULT_PANEL_AREA_M2 = 2.7;

    private const DEFAULT_PANEL_PRICE = 2709000;

    private const DEFAULT_GENERATION_PER_KWP_YEAR = 1800;

    private const DEFAULT_DC_AC_RATIO = 1.15;

    /**
     * Hiển thị trang công cụ tính toán điện mặt trời.
     */
    public function index()
    {
        $provinces = SolarProvince::with('regionProfile')
            ->orderBy('region')
            ->orderBy('name')
            ->get();

        $defaults = [
            'electricity_price' => (float) SolarSetting::getValue('ego_average_electricity_price', self::DEFAULT_ELECTRICITY_PRICE),
            'panel_power_wp' => (float) SolarSetting::getValue('panel_power_wp', self::DEFAULT_PANEL_POWER_WP),
            'panel_area_m2' => (float) SolarSetting::getValue('panel_area_m2', self::DEFAULT_PANEL_AREA_M2),
            'generation_per_kwp_year' => max(self::DEFAULT_GENERATION_PER_KWP_YEAR, (float) SolarSetting::getValue('ego_generation_per_kwp_year', self::DEFAULT_GENERATION_PER_KWP_YEAR)),
            'material_cost' => (float) SolarSetting::getValue('ego_solar_material_cost', 10000000),
            'electric_cabinet_cost' => (float) SolarSetting::getValue('ego_solar_electric_cabinet_cost', 5000000),
            'labor_per_kwp' => (float) SolarSetting::getValue('ego_solar_labor_per_kwp', 1000000),
            'shipping_cost' => (float) SolarSetting::getValue('ego_solar_shipping_cost', 0),
            'analysis_years' => (int) SolarSetting::getValue('analysis_years', 25),
        ];

        $productOptions = $this->getSolarProductOptions();
        $panels = $productOptions['panels'];
        $inverters = $productOptions['inverters'];
        $batteries = $productOptions['batteries'];

        return view('solar.calculator', compact('provinces', 'defaults', 'panels', 'inverters', 'batteries'));
    }

    /**
     * Tính cấu hình, dự toán và phân tích hoàn vốn theo tiền điện, trả về JSON.
     */
    public function calculate(Request $request)
    {
        $request->validate([
            'province_id' => ['nullable', 'exists:solar_provinces,id'],
            'electricity_mode' => ['nullable', 'in:residential,business,production,public'],
            'usage_type' => ['nullable', 'in:day,balanced,night'],
            'system_type' => ['nullable', 'in:on_grid,hybrid,battery'],
            'phase' => ['nullable', 'in:auto,1,3'],
            'panel_product_id' => ['nullable', 'integer', 'min:0'],
            'inverter_product_id' => ['nullable', 'integer', 'min:0'],
            'battery_product_id' => ['nullable', 'integer', 'min:0'],
            'monthly_bill' => ['nullable', 'numeric', 'min:0'],
            'monthly_kwh' => ['nullable', 'numeric', 'min:0'],
            'roof_area' => ['nullable', 'numeric', 'min:0'],
        ]);

        $monthlyBill = (float) $request->input('monthly_bill', 0);
        $monthlyKwhInput = (float) $request->input('monthly_kwh', 0);
        $roofArea = (float) $request->input('roof_area', 0);
        $usageType = $request->input('usage_type', 'day') ?: 'day';
        $systemType = $request->input('system_type', 'hybrid') ?: 'hybrid';
        $phase = $request->input('phase', 'auto') ?: 'auto';

        $selectedPanelId = (int) $request->input('panel_product_id', 0);
        $selectedInverterId = (int) $request->input('inverter_product_id', 0);
        $selectedBatteryId = (int) $request->input('battery_product_id', 0);

        if ($monthlyBill <= 0 && $monthlyKwhInput <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Nhập tiền điện hàng tháng là hệ thống sẽ tự tính đủ cấu hình.',
            ], 422);
        }

        [$provinceText, $regionText, $irradiation] = $this->resolveProvinceInfo($request);

        $electricityPrice = (float) SolarSetting::getValue('ego_average_electricity_price', self::DEFAULT_ELECTRICITY_PRICE);
        if ($electricityPrice <= 0) {
            $electricityPrice = self::DEFAULT_ELECTRICITY_PRICE;
        }

        $generationPerKwpYear = (float) SolarSetting::getValue('ego_generation_per_kwp_year', self::DEFAULT_GENERATION_PER_KWP_YEAR);
        // Dùng cho báo giá nhanh theo thực tế sale: khoảng 150 kWh/kWp/tháng.
        // Nếu setting cũ còn thấp như 1.200 kWh/kWp/năm thì 3 triệu tiền điện sẽ bị đẩy lên 10kWp,
        // trong khi mẫu thực tế 3 triệu chỉ cần khoảng 7.56kWp DC đi với inverter 6.6kW AC.
        if ($generationPerKwpYear < self::DEFAULT_GENERATION_PER_KWP_YEAR) {
            $generationPerKwpYear = self::DEFAULT_GENERATION_PER_KWP_YEAR;
        }

        $productOptions = $this->getSolarProductOptions();
        $panelProduct = $this->resolvePanelProduct($selectedPanelId, $productOptions['panels']);

        $panelPowerWp = (float) ($panelProduct['power_wp'] ?? 0);
        if ($panelPowerWp <= 0) {
            $panelPowerWp = (float) SolarSetting::getValue('panel_power_wp', self::DEFAULT_PANEL_POWER_WP);
        }
        if ($panelPowerWp <= 0) {
            $panelPowerWp = self::DEFAULT_PANEL_POWER_WP;
        }

        $panelAreaM2 = (float) SolarSetting::getValue('panel_area_m2', self::DEFAULT_PANEL_AREA_M2);
        if ($panelAreaM2 <= 0) {
            $panelAreaM2 = self::DEFAULT_PANEL_AREA_M2;
        }

        $monthlyKwh = $monthlyKwhInput > 0
            ? $monthlyKwhInput
            : round($monthlyBill / $electricityPrice, 2);

        $yearlyKwh = $monthlyKwh * 12;
        $targetKwp = $yearlyKwh / $generationPerKwpYear;
        $rawPanelCount = (int) ceil(($targetKwp * 1000) / max($panelPowerWp, 1));
        $panelCount = $this->normalizePanelCount($rawPanelCount);
        $recommendedKwp = round(($panelCount * $panelPowerWp) / 1000, 2);

        $package = $this->buildQuotePackage(
            $recommendedKwp,
            $panelCount,
            $panelPowerWp,
            $panelAreaM2,
            $monthlyKwh,
            $yearlyKwh,
            $electricityPrice,
            $generationPerKwpYear,
            $usageType,
            $systemType,
            $phase,
            $panelProduct,
            $productOptions['inverters'],
            $selectedInverterId,
            $productOptions['batteries'],
            $selectedBatteryId
        );

        $estimatedArea = round($panelCount * $panelAreaM2, 1);
        $roofFit = $roofArea > 0 ? $roofArea >= $estimatedArea : null;

        $analysisYears = (int) SolarSetting::getValue('analysis_years', 25);
        $analysisYears = $analysisYears > 0 ? min($analysisYears, 30) : 25;
        $analysis = [];
        $cumulativeSaving = 0;
        $paybackYear = null;
        $priceGrowth = (float) SolarSetting::getValue('electricity_price_growth', 0.03);
        $degradation = (float) SolarSetting::getValue('yearly_degradation', 0.005);

        for ($year = 1; $year <= $analysisYears; $year++) {
            $yearGeneration = round($package['year1_generation'] * pow((1 - $degradation), $year - 1), 0);
            $gridPrice = round($electricityPrice * pow((1 + $priceGrowth), $year - 1), 0);
            $offsetKwh = min($yearlyKwh, $yearGeneration * $package['self_consumption_ratio']);
            $yearSaving = round($offsetKwh * $gridPrice, 0);
            $cumulativeSaving += $yearSaving;

            if ($paybackYear === null && $cumulativeSaving >= $package['total_investment']) {
                $paybackYear = $year;
            }

            $analysis[] = [
                'year' => $year,
                'generation' => $yearGeneration,
                'grid_price' => $gridPrice,
                'saving' => $yearSaving,
                'cumulative_saving' => round($cumulativeSaving, 0),
            ];
        }

        $comparisonPlans = $this->buildComparisonPlans(
            $panelCount,
            $panelPowerWp,
            $panelAreaM2,
            $monthlyKwh,
            $yearlyKwh,
            $electricityPrice,
            $generationPerKwpYear,
            $usageType,
            $systemType,
            $phase,
            $panelProduct,
            $productOptions['inverters'],
            $selectedInverterId,
            $productOptions['batteries'],
            $selectedBatteryId
        );

        $inverterQtyText = ((int) ($package['inverter_qty'] ?? 1) > 1 ? ((int) $package['inverter_qty']).' x ' : '');
        $batteryQtyText = ((int) ($package['battery_qty'] ?? 0) > 1 ? ((int) $package['battery_qty']).' x ' : '');
        $configurationText = $panelCount.' tấm pin '.number_format($panelPowerWp, 0, ',', '.').'Wp'
            .' + '.$inverterQtyText.($package['inverter_product']['short_name'] ?? $package['inverter_product']['display_name'] ?? 'Inverter phù hợp')
            .($package['battery_capacity'] > 0 ? ' + '.$batteryQtyText.'Pin lưu trữ '.number_format($package['battery_capacity'], 1, ',', '.').'kWh' : '');

        $advice = [
            'Giá điện trung bình đang tính: '.number_format($electricityPrice, 0, ',', '.').' VNĐ/kWh.',
            'Chỉ cần nhập tiền điện, hệ thống quy đổi ra khoảng '.number_format($monthlyKwh, 0, ',', '.').' kWh/tháng và đề xuất gói '.number_format($recommendedKwp, 2, ',', '.').' kWp.',
            'Dự toán đang tách rõ: tấm pin, inverter, pin lưu trữ, vật tư phụ, tủ điện, nhân công và giao hàng.',
            'Công suất tấm pin DC được tính cao hơn inverter AC khoảng '.number_format(self::DEFAULT_DC_AC_RATIO, 2, ',', '.').' lần để đúng cấu hình thực tế.',
            ((int) ($package['inverter_qty'] ?? 1) > 1 ? 'Inverter đang được ghép '.((int) $package['inverter_qty']).' bộ cùng pha do công suất/tồn kho.' : 'Inverter được chọn đúng pha: 1 pha chỉ dùng inverter 1 pha, 3 pha chỉ dùng inverter 3 pha.'),
            ((int) ($package['battery_qty'] ?? 0) > 1 ? 'Pin lưu trữ đang được ghép '.((int) $package['battery_qty']).' cục theo mức tiền điện/thói quen dùng điện.' : 'Dung lượng pin lưu trữ được tính theo tiền điện, không cố định một mức.'),
            $roofFit === false ? 'Diện tích mái đang nhập chưa đủ, nên giảm công suất hoặc khảo sát thêm mái.' : 'Thông số hiển thị ở mức cơ bản để sales tư vấn nhanh cho khách.',
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'province' => $provinceText,
                'region' => $regionText,
                'irradiation' => round($irradiation, 2),
                'electricity_price' => round($electricityPrice, 0),
                'effective_price' => round($electricityPrice, 0),
                'monthly_bill' => round($monthlyBill, 0),
                'monthly_kwh' => round($monthlyKwh, 2),
                'yearly_kwh' => round($yearlyKwh, 2),
                'recommended_kwp' => round($recommendedKwp, 2),
                'target_kwp' => round($targetKwp, 2),
                'estimated_panel_count' => $panelCount,
                'panel_power_wp' => round($panelPowerWp, 0),
                'panel_unit_price' => round($package['panel_unit_price'], 0),
                'panel_cost' => round($package['panel_cost'], 0),
                'panel_product_name' => $package['panel_product']['display_name'] ?? 'Tấm pin NLMT '.number_format($panelPowerWp, 0, ',', '.').'Wp',
                'estimated_area' => $estimatedArea,
                'roof_area' => $roofArea,
                'roof_fit' => $roofFit,
                'phase' => $phase,
                'phase_label' => $this->getPhaseLabel($phase, $package['inverter_product']['phase'] ?? ''),
                'system_type' => $systemType,
                'system_label' => $this->getSystemTypeLabel($systemType),
                'usage_type' => $usageType,
                'usage_label' => $this->getUsageTypeLabel($usageType),
                'configuration_text' => $configurationText,
                'inverter_product_name' => $package['inverter_product']['display_name'] ?? 'Inverter phù hợp',
                'inverter_capacity_kw' => round((float) ($package['inverter_total_capacity'] ?? ($package['inverter_product']['capacity_kw'] ?? 0)), 2),
                'inverter_unit_capacity_kw' => round((float) ($package['inverter_product']['capacity_kw'] ?? 0), 2),
                'dc_ac_ratio' => round(((float) ($package['inverter_total_capacity'] ?? 0)) > 0 ? $recommendedKwp / (float) ($package['inverter_total_capacity'] ?? 1) : 0, 2),
                'inverter_unit_price' => round($package['inverter_unit_price'], 0),
                'inverter_qty' => (int) $package['inverter_qty'],
                'inverter_cost' => round($package['inverter_cost'], 0),
                'inverter_stock_qty' => round((float) ($package['inverter_product']['stock_qty'] ?? 0), 2),
                'battery_product_name' => $package['battery_product']['display_name'] ?? ($systemType === 'on_grid' ? 'Không dùng pin' : 'Pin lưu trữ theo tiền điện'),
                'battery_capacity' => round($package['battery_capacity'], 2),
                'target_battery_kwh' => round((float) ($package['target_battery_kwh'] ?? 0), 2),
                'battery_unit_capacity_kwh' => round((float) ($package['battery_product']['capacity_kwh'] ?? 0), 2),
                'battery_unit_price' => round($package['battery_unit_price'], 0),
                'battery_qty' => (int) $package['battery_qty'],
                'battery_cost' => round($package['battery_cost'], 0),
                'battery_stock_qty' => round((float) ($package['battery_product']['stock_qty'] ?? 0), 2),
                'material_cost' => round($package['material_cost'], 0),
                'electric_cabinet_cost' => round($package['electric_cabinet_cost'], 0),
                'labor_cost' => round($package['labor_cost'], 0),
                'labor_unit_price' => round($package['labor_unit_price'], 0),
                'shipping_cost' => round($package['shipping_cost'], 0),
                'total_investment' => round($package['total_investment'], 0),
                'year1_generation' => round($package['year1_generation'], 0),
                'year1_self_used' => round(min($yearlyKwh, $package['year1_generation'] * $package['self_consumption_ratio']), 0),
                'year1_saving' => round($analysis[0]['saving'] ?? 0, 0),
                'payback_year' => $paybackYear,
                'quote_items' => $package['quote_items'],
                'comparison_plans' => $comparisonPlans,
                'analysis' => $analysis,
                'advice' => $advice,
            ],
        ]);
    }

    /**
     * Lấy thông tin tỉnh/vùng và bức xạ từ request.
     *
     * @return array [provinceText, regionText, irradiation]
     */
    private function resolveProvinceInfo(Request $request): array
    {
        $irradiation = 4.6;
        $provinceText = 'Mặc định';
        $regionText = 'Mặc định';

        if ($request->filled('province_id')) {
            $province = SolarProvince::with('regionProfile')->find($request->province_id);
            if ($province) {
                $irradiation = (float) ($province->irradiation_override
                    ?? optional($province->regionProfile)->irradiation_default
                    ?? 4.6);
                $provinceText = $province->name;
                $regionText = $province->region ?: (optional($province->regionProfile)->name ?: 'Mặc định');
            }
        }

        return [$provinceText, $regionText, $irradiation];
    }

    /**
     * Dựng gói báo giá đầy đủ: tấm pin, inverter, pin lưu trữ, vật tư, nhân công.
     */
    private function buildQuotePackage(
        float $recommendedKwp,
        int $panelCount,
        float $panelPowerWp,
        float $panelAreaM2,
        float $monthlyKwh,
        float $yearlyKwh,
        float $electricityPrice,
        float $generationPerKwpYear,
        string $usageType,
        string $systemType,
        string $phase,
        ?array $panelProduct,
        array $inverters,
        int $selectedInverterId,
        array $batteries,
        int $selectedBatteryId
    ): array {
        $panelUnitPrice = max(
            (float) ($panelProduct['price'] ?? 0),
            (float) SolarSetting::getValue('ego_solar_panel_price_floor', self::DEFAULT_PANEL_PRICE)
        );
        $panelCost = round($panelUnitPrice * $panelCount, 0);

        // Công suất tấm pin DC luôn cho phép cao hơn inverter AC.
        // Quy đổi theo DC/AC khoảng 1.15: 7.56kWp -> inverter 6.6kW, 8.82kWp -> 8kW, 10.08kWp -> 10kW.
        // Nếu kho không còn model lớn, hệ thống được phép ghép nhiều inverter cùng pha.
        // Lưu ý bắt buộc: 1 pha chỉ chọn inverter 1 pha, 3 pha chỉ chọn inverter 3 pha.
        $targetInverterKw = max(3, $recommendedKwp / self::DEFAULT_DC_AC_RATIO);
        $inverterBundle = $this->resolveInverterBundle($targetInverterKw, $phase, $systemType, $selectedInverterId, $inverters);
        $inverterProduct = $inverterBundle['product'] ?? null;

        if (! $inverterProduct) {
            $fallbackCapacity = $this->fallbackInverterCapacity($recommendedKwp);
            $inverterProduct = [
                'id' => 0,
                'display_name' => 'Inverter Hybrid '.number_format($fallbackCapacity, 1, ',', '.').'kW',
                'short_name' => number_format($fallbackCapacity, 1, ',', '.').'kW',
                'capacity_kw' => $fallbackCapacity,
                'phase' => $phase === '3' ? '3' : '1',
                'price' => $this->fallbackInverterPrice($fallbackCapacity),
                'stock_qty' => 0,
            ];
            $inverterBundle = [
                'product' => $inverterProduct,
                'qty' => 1,
                'total_capacity_kw' => $fallbackCapacity,
                'mode' => 'fallback',
            ];
        }

        $inverterCapacity = (float) ($inverterProduct['capacity_kw'] ?? 0);
        $inverterQty = max(1, (int) ($inverterBundle['qty'] ?? 1));
        $inverterTotalCapacity = round((float) ($inverterBundle['total_capacity_kw'] ?? ($inverterCapacity * $inverterQty)), 2);
        $inverterUnitPrice = max((float) ($inverterProduct['price'] ?? 0), $this->fallbackInverterPrice($inverterCapacity));
        $inverterCost = round($inverterUnitPrice * $inverterQty, 0);

        $batteryProduct = null;
        $batteryQty = 0;
        $batteryCapacity = 0;
        $batteryUnitPrice = 0;
        $batteryCost = 0;
        $targetBatteryKwh = 0;

        if ($systemType !== 'on_grid') {
            // Dung lượng pin phụ thuộc tiền điện/thói quen dùng điện, không cố định 16kWh.
            // Ví dụ 3 triệu/tháng ~ 1.000 kWh/tháng -> khoảng 15-16kWh lưu trữ.
            $targetBatteryKwh = $this->resolveTargetBatteryKwh($monthlyKwh, $usageType, $systemType);
            $batteryBundle = $this->resolveBatteryBundle($targetBatteryKwh, $selectedBatteryId, $batteries);
            $batteryProduct = $batteryBundle['product'] ?? null;

            if (! $batteryProduct) {
                $fallbackUnitCapacity = $targetBatteryKwh <= 6 ? 5.12 : 16;
                $batteryQty = max(1, (int) ceil(($targetBatteryKwh * 0.92) / max($fallbackUnitCapacity, 1)));
                $batteryProduct = [
                    'id' => 0,
                    'display_name' => 'Pin lưu trữ '.number_format($fallbackUnitCapacity, 2, ',', '.').'kWh',
                    'capacity_kwh' => $fallbackUnitCapacity,
                    'price' => $fallbackUnitCapacity <= 6 ? 12000000 : 47000000,
                    'stock_qty' => 0,
                ];
                $batteryBundle = [
                    'product' => $batteryProduct,
                    'qty' => $batteryQty,
                    'total_capacity_kwh' => round($fallbackUnitCapacity * $batteryQty, 2),
                    'mode' => 'fallback',
                ];
            }

            $unitCapacity = (float) ($batteryProduct['capacity_kwh'] ?? 0);
            $batteryQty = max(1, (int) ($batteryBundle['qty'] ?? 1));
            $batteryCapacity = round((float) ($batteryBundle['total_capacity_kwh'] ?? ($unitCapacity * $batteryQty)), 2);
            $batteryUnitPrice = max((float) ($batteryProduct['price'] ?? 0), $this->fallbackBatteryPrice($batteryProduct, $targetBatteryKwh));
            $batteryCost = round($batteryUnitPrice * $batteryQty, 0);
        }

        $materialCost = (float) SolarSetting::getValue('ego_solar_material_cost', 10000000);
        $electricCabinetCost = (float) SolarSetting::getValue('ego_solar_electric_cabinet_cost', 5000000);
        $laborUnitPrice = (float) SolarSetting::getValue('ego_solar_labor_per_kwp', 1000000);
        $laborCost = round($recommendedKwp * $laborUnitPrice, 0);
        $shippingCost = (float) SolarSetting::getValue('ego_solar_shipping_cost', 0);

        $year1Generation = round($recommendedKwp * $generationPerKwpYear, 0);
        $selfConsumptionRatio = $this->getSelfConsumptionRatio($usageType, $systemType);

        $inverterNote = trim(($inverterProduct['display_name'] ?? 'Inverter phù hợp')
            .' - Tổng AC '.number_format($inverterTotalCapacity, 2, ',', '.').'kW'
            .' - '.$this->getPhaseLabel($phase, $inverterProduct['phase'] ?? ''));
        if ($inverterQty > 1) {
            $inverterNote .= ' - ghép '.$inverterQty.' bộ do cấu hình/tồn kho';
        }

        $batteryNote = $systemType === 'on_grid'
            ? 'Không dùng pin lưu trữ'
            : trim(($batteryProduct['display_name'] ?? 'Pin lưu trữ')
                .' - mục tiêu khoảng '.number_format($targetBatteryKwh, 1, ',', '.').'kWh'
                .($batteryQty > 1 ? ' - ghép '.$batteryQty.' cục' : ''));

        $quoteItems = [
            $this->quoteItem('Tấm pin NLMT', 'Tấm', $panelCount, $panelUnitPrice, $panelCost, $panelProduct['display_name'] ?? ('Tấm pin '.number_format($panelPowerWp, 0, ',', '.').'Wp')),
            $this->quoteItem('Inverter', 'Bộ', $inverterQty, $inverterUnitPrice, $inverterCost, $inverterNote),
            $this->quoteItem('Pin lưu trữ', 'Bộ', $batteryQty, $batteryUnitPrice, $batteryCost, $batteryNote),
            $this->quoteItem('Vật tư phụ', 'Hệ', 1, $materialCost, $materialCost, 'Dây AC/DC, MC4, rail, kẹp, ống điện, phụ kiện lắp tấm'),
            $this->quoteItem('Tủ điện', 'Tủ', 1, $electricCabinetCost, $electricCabinetCost, 'Tủ AC, MCB, ATS/chống sét và phụ kiện'),
            $this->quoteItem('Nhân công', 'kWp', $recommendedKwp, $laborUnitPrice, $laborCost, 'Khảo sát, thiết kế kỹ thuật, lắp đặt, setup hệ thống'),
            $this->quoteItem('Giao hàng', 'Lần', $shippingCost > 0 ? 1 : 0, $shippingCost, $shippingCost, $shippingCost > 0 ? 'Vận chuyển thiết bị đến công trình' : 'Đã gồm trong hạng mục nhân công/vật tư'),
        ];

        $totalInvestment = array_sum(array_map(fn ($item) => (float) $item['total'], $quoteItems));

        return [
            'panel_product' => $panelProduct,
            'panel_unit_price' => $panelUnitPrice,
            'panel_cost' => $panelCost,
            'inverter_product' => $inverterProduct,
            'inverter_qty' => $inverterQty,
            'inverter_unit_price' => $inverterUnitPrice,
            'inverter_cost' => $inverterCost,
            'inverter_total_capacity' => $inverterTotalCapacity,
            'battery_product' => $batteryProduct,
            'battery_qty' => $batteryQty,
            'battery_capacity' => $batteryCapacity,
            'target_battery_kwh' => $targetBatteryKwh,
            'battery_unit_price' => $batteryUnitPrice,
            'battery_cost' => $batteryCost,
            'material_cost' => $materialCost,
            'electric_cabinet_cost' => $electricCabinetCost,
            'labor_unit_price' => $laborUnitPrice,
            'labor_cost' => $laborCost,
            'shipping_cost' => $shippingCost,
            'total_investment' => $totalInvestment,
            'year1_generation' => $year1Generation,
            'self_consumption_ratio' => $selfConsumptionRatio,
            'quote_items' => $quoteItems,
        ];
    }

    /**
     * Dựng các phương án so sánh với số tấm pin lân cận.
     */
    private function buildComparisonPlans(
        int $panelCount,
        float $panelPowerWp,
        float $panelAreaM2,
        float $monthlyKwh,
        float $yearlyKwh,
        float $electricityPrice,
        float $generationPerKwpYear,
        string $usageType,
        string $systemType,
        string $phase,
        ?array $panelProduct,
        array $inverters,
        int $selectedInverterId,
        array $batteries,
        int $selectedBatteryId
    ): array {
        $counts = collect([$panelCount - 2, $panelCount, $panelCount + 2])
            ->map(fn ($count) => $this->normalizePanelCount((int) $count))
            ->unique()
            ->sort()
            ->values();

        return $counts->map(function ($count) use ($panelPowerWp, $panelAreaM2, $monthlyKwh, $yearlyKwh, $electricityPrice, $generationPerKwpYear, $usageType, $systemType, $phase, $panelProduct, $inverters, $selectedInverterId, $batteries, $selectedBatteryId) {
            $kwp = round(($count * $panelPowerWp) / 1000, 2);
            $package = $this->buildQuotePackage(
                $kwp,
                $count,
                $panelPowerWp,
                $panelAreaM2,
                $monthlyKwh,
                $yearlyKwh,
                $electricityPrice,
                $generationPerKwpYear,
                $usageType,
                $systemType,
                $phase,
                $panelProduct,
                $inverters,
                $selectedInverterId,
                $batteries,
                $selectedBatteryId
            );

            $year1Saving = round(min($yearlyKwh, $package['year1_generation'] * $package['self_consumption_ratio']) * $electricityPrice, 0);

            return [
                'panel_count' => $count,
                'kwp' => $kwp,
                'inverter_name' => ((int) ($package['inverter_qty'] ?? 1) > 1 ? ((int) $package['inverter_qty']).' x ' : '').($package['inverter_product']['short_name'] ?? $package['inverter_product']['display_name'] ?? '-'),
                'dc_ac_ratio' => round(((float) ($package['inverter_total_capacity'] ?? 0)) > 0 ? $kwp / (float) ($package['inverter_total_capacity'] ?? 1) : 0, 2),
                'battery_capacity' => $package['battery_capacity'],
                'total_investment' => round($package['total_investment'], 0),
                'yearly_generation' => round($package['year1_generation'], 0),
                'year1_saving' => $year1Saving,
            ];
        })->values()->all();
    }

    /**
     * Tạo một dòng hạng mục báo giá.
     */
    private function quoteItem(string $name, string $unit, float $qty, float $unitPrice, float $total, string $note = ''): array
    {
        return [
            'name' => $name,
            'unit' => $unit,
            'qty' => round($qty, 2),
            'unit_price' => round($unitPrice, 0),
            'total' => round($total, 0),
            'note' => $note,
        ];
    }

    /**
     * Chuẩn hóa số tấm pin: tối thiểu 4 và làm tròn thành số chẵn.
     */
    private function normalizePanelCount(int $count): int
    {
        $count = max(4, $count);
        if ($count % 2 !== 0) {
            $count++;
        }

        return $count;
    }

    /**
     * Lấy danh sách tấm pin, inverter, pin lưu trữ từ danh mục sản phẩm.
     */
    private function getSolarProductOptions(): array
    {
        if (! Schema::hasTable('crm_product_catalog')) {
            return ['panels' => [], 'inverters' => [], 'batteries' => []];
        }

        $productColumns = Schema::getColumnListing('crm_product_catalog');
        $has = fn (string $col): bool => in_array($col, $productColumns, true);

        $query = DB::table('crm_product_catalog as p');

        $hasBrands = Schema::hasTable('crm_brands') && $has('brand_id');
        if ($hasBrands) {
            $query->leftJoin('crm_brands as b', 'b.id', '=', 'p.brand_id');
        }

        $hasCategories = Schema::hasTable('crm_product_categories') && $has('category_id');
        if ($hasCategories) {
            $query->leftJoin('crm_product_categories as c', 'c.id', '=', 'p.category_id');
        }

        $hasStockJoin = false;
        if (Schema::hasTable('crm_product_stock')) {
            $stockColumns = Schema::getColumnListing('crm_product_stock');
            if (in_array('product_id', $stockColumns, true) && in_array('qty', $stockColumns, true)) {
                $stockQuery = DB::table('crm_product_stock')
                    ->select('product_id', DB::raw('SUM(qty) as stock_qty'))
                    ->groupBy('product_id');

                $query->leftJoinSub($stockQuery, 'st', function ($join) {
                    $join->on('st.product_id', '=', 'p.id');
                });
                $hasStockJoin = true;
            }
        }

        $selects = ['p.id'];
        foreach ([
            'name', 'sku', 'description', 'category_id', 'brand_id', 'price', 'price_agent', 'price_agent_vat',
            'price_retail', 'price_retail_vat', 'vat_percent', 'cost_vat_percent', 'quantity', 'is_active',
        ] as $col) {
            $selects[] = $has($col) ? ('p.'.$col) : DB::raw('NULL as '.$col);
        }

        $selects[] = $hasBrands ? DB::raw('b.name as brand_name') : DB::raw('NULL as brand_name');
        $selects[] = $hasCategories ? DB::raw('c.name as category_name') : DB::raw('NULL as category_name');
        $selects[] = $hasStockJoin ? DB::raw('COALESCE(st.stock_qty, 0) as stock_qty') : DB::raw('0 as stock_qty');

        $query->select($selects)
            ->where(function ($q) use ($has) {
                if ($has('category_id')) {
                    $q->orWhereIn('p.category_id', [1, 3, 4]);
                }

                $q->orWhere('p.name', 'like', '%Tấm pin%')
                    ->orWhere('p.name', 'like', '%NLMT%')
                    ->orWhere('p.name', 'like', '%solar%')
                    ->orWhere('p.name', 'like', '%panel%')
                    ->orWhere('p.name', 'like', '%Inverter%')
                    ->orWhere('p.name', 'like', '%Biến tần%')
                    ->orWhere('p.name', 'like', '%Pin lưu trữ%')
                    ->orWhere('p.name', 'like', '%Battery%')
                    ->orWhere('p.sku', 'like', '%BAT%')
                    ->orWhere('p.sku', 'like', '%ESS%')
                    ->orWhere('p.sku', 'like', '%GW%')
                    ->orWhere('p.sku', 'like', '%Wp%')
                    ->orWhere('p.sku', 'like', '%W%');
            });

        if ($has('is_active')) {
            $query->where('p.is_active', 1);
        }

        $rows = $query->orderBy('p.category_id')->orderBy('p.name')->get();
        $tierPrices = $this->getRetailTierPrices($rows->pluck('id')->map(fn ($id) => (int) $id)->all());

        $panels = [];
        $inverters = [];
        $batteries = [];

        foreach ($rows as $row) {
            $text = trim((string) ($row->name ?? '').' '.(string) ($row->sku ?? '').' '.(string) ($row->description ?? '').' '.(string) ($row->category_name ?? ''));
            $type = $this->detectSolarProductType($row, $text);

            if ($type === null) {
                continue;
            }

            $price = $tierPrices[(int) $row->id] ?? $this->resolveProductSalePrice($row);
            $brandName = trim((string) ($row->brand_name ?? ''));
            $sku = trim((string) ($row->sku ?? ''));
            $name = trim((string) ($row->name ?? 'Sản phẩm #'.$row->id));
            $stockQty = (float) ($row->stock_qty ?? 0);
            if ($stockQty <= 0 && isset($row->quantity)) {
                $stockQty = (float) $row->quantity;
            }

            $base = [
                'id' => (int) $row->id,
                'name' => $name,
                'sku' => $sku,
                'brand_name' => $brandName,
                'display_name' => trim(($brandName ? $brandName.' - ' : '').$name.($sku ? ' ('.$sku.')' : '')),
                'price' => round($price, 0),
                'stock_qty' => $stockQty,
            ];

            if ($type === 'panel') {
                $powerWp = $this->extractPanelPowerWp($text);
                if ($powerWp <= 0) {
                    continue;
                }
                $panels[] = array_merge($base, ['power_wp' => round($powerWp, 0)]);
            }

            if ($type === 'inverter') {
                $capacityKw = $this->extractInverterCapacityKw($text);
                if ($capacityKw <= 0) {
                    continue;
                }
                $phase = $this->detectInverterPhase($text);
                $inverters[] = array_merge($base, [
                    'capacity_kw' => round($capacityKw, 2),
                    'phase' => $phase,
                    'phase_label' => $this->getPhaseLabel($phase),
                    'short_name' => number_format($capacityKw, 1, ',', '.').'kW',
                ]);
            }

            if ($type === 'battery') {
                $capacityKwh = $this->extractBatteryCapacityKwh($text);
                if ($capacityKwh <= 0) {
                    continue;
                }
                $batteries[] = array_merge($base, ['capacity_kwh' => round($capacityKwh, 2)]);
            }
        }

        usort($panels, fn ($a, $b) => [$a['power_wp'], $a['display_name']] <=> [$b['power_wp'], $b['display_name']]);
        usort($inverters, fn ($a, $b) => [$a['phase'], $a['capacity_kw'], $a['display_name']] <=> [$b['phase'], $b['capacity_kw'], $b['display_name']]);
        usort($batteries, fn ($a, $b) => [$a['capacity_kwh'], $a['display_name']] <=> [$b['capacity_kwh'], $b['display_name']]);

        return ['panels' => $panels, 'inverters' => $inverters, 'batteries' => $batteries];
    }

    /**
     * Lấy giá bán lẻ theo bậc giá cho các sản phẩm.
     */
    private function getRetailTierPrices(array $productIds): array
    {
        if (empty($productIds) || ! Schema::hasTable('crm_product_prices')) {
            return [];
        }

        $priceColumns = Schema::getColumnListing('crm_product_prices');
        foreach (['product_id', 'price_tier_id', 'price'] as $required) {
            if (! in_array($required, $priceColumns, true)) {
                return [];
            }
        }

        $tierIds = [5];
        if (Schema::hasTable('crm_price_tiers')) {
            $tierColumns = Schema::getColumnListing('crm_price_tiers');
            $tiers = DB::table('crm_price_tiers')->get();
            foreach ($tiers as $tier) {
                $text = '';
                foreach (['name', 'code', 'description'] as $col) {
                    if (in_array($col, $tierColumns, true)) {
                        $text .= ' '.mb_strtolower((string) ($tier->{$col} ?? ''), 'UTF-8');
                    }
                }
                if (str_contains($text, 'bán lẻ') || str_contains($text, 'ban le') || str_contains($text, 'retail')) {
                    $tierIds[] = (int) $tier->id;
                }
            }
        }

        $tierIds = array_values(array_unique(array_filter($tierIds)));
        $priceQuery = DB::table('crm_product_prices')
            ->whereIn('product_id', $productIds)
            ->whereIn('price_tier_id', $tierIds);

        if (in_array('price_after_vat', $priceColumns, true)) {
            $priceQuery->orderByDesc('price_after_vat');
        }

        $rows = $priceQuery->orderByDesc('price')->get();
        $map = [];
        foreach ($rows as $row) {
            $productId = (int) $row->product_id;
            if (isset($map[$productId])) {
                continue;
            }

            $price = 0;
            if (in_array('price_after_vat', $priceColumns, true)) {
                $price = (float) ($row->price_after_vat ?? 0);
            }
            if ($price <= 0) {
                $beforeVat = (float) ($row->price ?? 0);
                $vat = in_array('vat_percent', $priceColumns, true) ? (float) ($row->vat_percent ?? 0) : 0;
                $price = $beforeVat > 0 ? round($beforeVat * (1 + max(0, $vat) / 100), 0) : 0;
            }
            if ($price > 0) {
                $map[$productId] = $price;
            }
        }

        return $map;
    }

    /**
     * Phân loại sản phẩm là panel/inverter/battery từ tên, SKU và danh mục.
     */
    private function detectSolarProductType(object $row, string $text): ?string
    {
        $lower = mb_strtolower($text, 'UTF-8');
        $categoryId = (int) ($row->category_id ?? 0);
        $categoryName = mb_strtolower((string) ($row->category_name ?? ''), 'UTF-8');

        $isPanel = $categoryId === 1
            || str_contains($categoryName, 'tấm pin')
            || str_contains($categoryName, 'tam pin')
            || str_contains($lower, 'tấm pin')
            || str_contains($lower, 'tam pin')
            || str_contains($lower, 'nlmt')
            || str_contains($lower, 'solar panel')
            || preg_match('/\b\d{3,4}\s*wp\b/i', $text);

        if ($isPanel) {
            return 'panel';
        }

        if (
            $categoryId === 4
            || str_contains($categoryName, 'pin lưu trữ')
            || str_contains($lower, 'pin lưu trữ')
            || str_contains($lower, 'battery')
            || str_contains($lower, 'powerbrick')
            || str_contains($lower, 'stack100')
            || str_contains($lower, 'dyness')
            || preg_match('/\b(bat|s51\d{3}|lx\s*a\d)/i', $text)
        ) {
            return 'battery';
        }

        if (
            $categoryId === 3
            || str_contains($lower, 'biến tần')
            || str_contains($lower, 'bien tan')
            || str_contains($lower, 'inverter')
            || str_contains($lower, 'hòa lưới')
            || str_contains($lower, 'hoa luoi')
            || str_contains($lower, 'hybrid')
            || preg_match('/\b(GW\d+|ESS2-\d+K|SUN-\d+K|LX200-)/i', $text)
        ) {
            return 'inverter';
        }

        return null;
    }

    /**
     * Tính giá bán (đã VAT) của sản phẩm theo thứ tự ưu tiên các cột giá.
     */
    private function resolveProductSalePrice(object $row): float
    {
        $vatPercent = (float) ($row->vat_percent ?? 0);
        $costVatPercent = (float) ($row->cost_vat_percent ?? $vatPercent);

        $retailVat = (float) ($row->price_retail_vat ?? 0);
        if ($retailVat > 0) {
            return $retailVat;
        }

        $retail = (float) ($row->price_retail ?? 0);
        if ($retail > 0) {
            return round($retail * (1 + max(0, $vatPercent) / 100), 0);
        }

        $agentVat = (float) ($row->price_agent_vat ?? 0);
        if ($agentVat > 0) {
            return $agentVat;
        }

        $agent = (float) ($row->price_agent ?? 0);
        if ($agent > 0) {
            return round($agent * (1 + max(0, $costVatPercent) / 100), 0);
        }

        return (float) ($row->price ?? 0);
    }

    /**
     * Chọn tấm pin theo lựa chọn của người dùng hoặc gần công suất mặc định nhất.
     */
    private function resolvePanelProduct(int $selectedId, array $panels): ?array
    {
        if ($selectedId > 0) {
            foreach ($panels as $product) {
                if ((int) $product['id'] === $selectedId) {
                    return $product;
                }
            }
        }

        $candidates = array_values(array_filter($panels, fn ($product) => (float) ($product['power_wp'] ?? 0) > 0));
        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function ($a, $b) {
            $aScore = abs((float) ($a['power_wp'] ?? 0) - self::DEFAULT_PANEL_POWER_WP);
            $bScore = abs((float) ($b['power_wp'] ?? 0) - self::DEFAULT_PANEL_POWER_WP);
            $aStock = (float) ($a['stock_qty'] ?? 0) > 0 ? 0 : 1;
            $bStock = (float) ($b['stock_qty'] ?? 0) > 0 ? 0 : 1;

            return [$aScore, $aStock, $a['display_name']] <=> [$bScore, $bStock, $b['display_name']];
        });

        return $candidates[0] ?? null;
    }

    /**
     * Chọn tổ hợp inverter phù hợp công suất mục tiêu, số pha và tồn kho.
     */
    private function resolveInverterBundle(float $targetKw, string $phase, string $systemType, int $selectedId, array $inverters): array
    {
        $selectedProduct = null;
        if ($selectedId > 0) {
            foreach ($inverters as $product) {
                if ((int) $product['id'] === $selectedId && $this->isPhaseCompatible($product, $phase)) {
                    $selectedProduct = $product;
                    break;
                }
            }
        }

        $candidates = array_values(array_filter($inverters, function ($product) use ($phase) {
            if ((float) ($product['capacity_kw'] ?? 0) <= 0) {
                return false;
            }

            return $this->isPhaseCompatible($product, $phase);
        }));

        if (empty($candidates) && $phase === 'auto') {
            $candidates = array_values(array_filter($inverters, fn ($product) => (float) ($product['capacity_kw'] ?? 0) > 0));
        }

        $preferHybrid = in_array($systemType, ['hybrid', 'battery'], true);
        if ($preferHybrid && ! empty($candidates)) {
            $hybridCandidates = array_values(array_filter($candidates, function ($product) {
                $text = mb_strtolower(($product['name'] ?? '').' '.($product['sku'] ?? '').' '.($product['display_name'] ?? ''), 'UTF-8');

                return str_contains($text, 'hybrid') || str_contains($text, '-es') || str_contains($text, '-et') || str_contains($text, 'ess2');
            }));
            if (! empty($hybridCandidates)) {
                $candidates = $hybridCandidates;
            }
        }

        $hasAnyStock = ! empty(array_filter($candidates, fn ($product) => (float) ($product['stock_qty'] ?? 0) > 0));

        if ($selectedProduct && (! $hasAnyStock || (float) ($selectedProduct['stock_qty'] ?? 0) > 0)) {
            return $this->makeInverterBundle($selectedProduct, $targetKw, $hasAnyStock, true);
        }

        if (empty($candidates)) {
            return ['product' => null, 'qty' => 0, 'total_capacity_kw' => 0, 'mode' => 'none'];
        }

        $bundles = [];
        foreach ($candidates as $product) {
            $cap = (float) ($product['capacity_kw'] ?? 0);
            if ($cap <= 0) {
                continue;
            }

            $stockQty = (float) ($product['stock_qty'] ?? 0);
            $maxQty = $hasAnyStock ? (int) floor($stockQty) : 4;
            $maxQty = max(1, min($maxQty, 4));

            for ($qty = 1; $qty <= $maxQty; $qty++) {
                $totalCap = round($cap * $qty, 2);
                $enough = $totalCap >= ($targetKw * 0.92);
                $stockScore = $stockQty >= $qty ? 0 : 1;
                $hybridScore = $this->isHybridInverterProduct($product) ? 0 : 1;
                $bundles[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'total_capacity_kw' => $totalCap,
                    'mode' => $qty > 1 ? 'bundle' : 'single',
                    '_score' => [
                        $enough ? 0 : 1,
                        $stockScore,
                        abs($totalCap - $targetKw),
                        $qty,
                        $hybridScore,
                        $cap,
                        (string) ($product['display_name'] ?? ''),
                    ],
                ];
            }
        }

        usort($bundles, fn ($a, $b) => $a['_score'] <=> $b['_score']);
        $bundle = $bundles[0] ?? ['product' => null, 'qty' => 0, 'total_capacity_kw' => 0, 'mode' => 'none'];
        unset($bundle['_score']);

        return $bundle;
    }

    /**
     * Tạo tổ hợp inverter từ một sản phẩm cụ thể.
     */
    private function makeInverterBundle(array $product, float $targetKw, bool $respectStock, bool $manualSelected = false): array
    {
        $cap = max(0.1, (float) ($product['capacity_kw'] ?? 0));
        $stockQty = (float) ($product['stock_qty'] ?? 0);
        $qty = max(1, (int) ceil(($targetKw * 0.92) / $cap));
        if ($respectStock && $stockQty > 0) {
            $qty = min($qty, max(1, (int) floor($stockQty)));
        }
        $qty = min($qty, 4);

        return [
            'product' => $product,
            'qty' => $qty,
            'total_capacity_kw' => round($cap * $qty, 2),
            'mode' => $qty > 1 ? 'bundle' : ($manualSelected ? 'selected' : 'single'),
        ];
    }

    /**
     * Tính dung lượng pin lưu trữ mục tiêu theo mức dùng điện và thói quen.
     */
    private function resolveTargetBatteryKwh(float $monthlyKwh, string $usageType, string $systemType): float
    {
        if ($systemType === 'on_grid') {
            return 0;
        }

        $dailyKwh = max(1, $monthlyKwh / 30);
        $ratio = match ($usageType) {
            'night' => 0.65,
            'balanced' => 0.55,
            default => 0.45,
        };

        if ($systemType === 'battery') {
            $ratio = max($ratio, 0.75);
        }

        $target = $dailyKwh * $ratio;
        if ($monthlyKwh >= 850 && $monthlyKwh <= 1200 && $target < 15) {
            $target = 15;
        }

        return round(max(5.0, $target), 2);
    }

    /**
     * Chọn tổ hợp pin lưu trữ phù hợp dung lượng mục tiêu và tồn kho.
     */
    private function resolveBatteryBundle(float $targetKwh, int $selectedId, array $batteries): array
    {
        $selectedProduct = null;
        if ($selectedId > 0) {
            foreach ($batteries as $product) {
                if ((int) $product['id'] === $selectedId) {
                    $selectedProduct = $product;
                    break;
                }
            }
        }

        $candidates = array_values(array_filter($batteries, fn ($product) => (float) ($product['capacity_kwh'] ?? 0) > 0));
        $hasAnyStock = ! empty(array_filter($candidates, fn ($product) => (float) ($product['stock_qty'] ?? 0) > 0));

        if ($selectedProduct && (! $hasAnyStock || (float) ($selectedProduct['stock_qty'] ?? 0) > 0)) {
            return $this->makeBatteryBundle($selectedProduct, $targetKwh, $hasAnyStock, true);
        }

        if (empty($candidates)) {
            return ['product' => null, 'qty' => 0, 'total_capacity_kwh' => 0, 'mode' => 'none'];
        }

        $bundles = [];
        foreach ($candidates as $product) {
            $cap = (float) ($product['capacity_kwh'] ?? 0);
            if ($cap <= 0) {
                continue;
            }

            $stockQty = (float) ($product['stock_qty'] ?? 0);
            $maxQty = $hasAnyStock ? (int) floor($stockQty) : 10;
            $maxQty = max(1, min($maxQty, 10));

            for ($qty = 1; $qty <= $maxQty; $qty++) {
                $totalCap = round($cap * $qty, 2);
                $enough = $totalCap >= ($targetKwh * 0.92);
                $stockScore = $stockQty >= $qty ? 0 : 1;
                $text = mb_strtolower(($product['display_name'] ?? '').' '.($product['sku'] ?? ''), 'UTF-8');
                $brandScore = str_contains($text, 'dyness') ? 0 : (str_contains($text, 'goodwe') ? 1 : 2);

                $bundles[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'total_capacity_kwh' => $totalCap,
                    'mode' => $qty > 1 ? 'bundle' : 'single',
                    '_score' => [
                        $enough ? 0 : 1,
                        $stockScore,
                        abs($totalCap - $targetKwh),
                        $qty,
                        $brandScore,
                        $cap,
                        (string) ($product['display_name'] ?? ''),
                    ],
                ];
            }
        }

        usort($bundles, fn ($a, $b) => $a['_score'] <=> $b['_score']);
        $bundle = $bundles[0] ?? ['product' => null, 'qty' => 0, 'total_capacity_kwh' => 0, 'mode' => 'none'];
        unset($bundle['_score']);

        return $bundle;
    }

    /**
     * Tạo tổ hợp pin lưu trữ từ một sản phẩm cụ thể.
     */
    private function makeBatteryBundle(array $product, float $targetKwh, bool $respectStock, bool $manualSelected = false): array
    {
        $cap = max(0.1, (float) ($product['capacity_kwh'] ?? 0));
        $stockQty = (float) ($product['stock_qty'] ?? 0);
        $qty = max(1, (int) ceil(($targetKwh * 0.92) / $cap));
        if ($respectStock && $stockQty > 0) {
            $qty = min($qty, max(1, (int) floor($stockQty)));
        }
        $qty = min($qty, 10);

        return [
            'product' => $product,
            'qty' => $qty,
            'total_capacity_kwh' => round($cap * $qty, 2),
            'mode' => $qty > 1 ? 'bundle' : ($manualSelected ? 'selected' : 'single'),
        ];
    }

    /**
     * Kiểm tra inverter có tương thích số pha yêu cầu.
     */
    private function isPhaseCompatible(array $product, string $phase): bool
    {
        if ($phase === 'auto') {
            return true;
        }

        $productPhase = (string) ($product['phase'] ?? '');

        return $productPhase !== '' && $productPhase === (string) $phase;
    }

    /**
     * Nhận diện inverter hybrid từ tên/SKU.
     */
    private function isHybridInverterProduct(array $product): bool
    {
        $text = mb_strtolower(($product['name'] ?? '').' '.($product['sku'] ?? '').' '.($product['display_name'] ?? ''), 'UTF-8');

        return str_contains($text, 'hybrid') || str_contains($text, '-es') || str_contains($text, '-et') || str_contains($text, 'ess2');
    }

    /**
     * Trích công suất tấm pin (Wp) từ chuỗi mô tả.
     */
    private function extractPanelPowerWp(string $text): float
    {
        if (preg_match('/(\d{3,4}(?:[\.,]\d+)?)\s*wp\b/i', $text, $match)) {
            return (float) str_replace(',', '.', $match[1]);
        }
        if (preg_match('/(\d{3,4})\s*w\b/i', $text, $match)) {
            return (float) $match[1];
        }

        return 0;
    }

    /**
     * Trích công suất inverter (kW) từ chuỗi mô tả.
     */
    private function extractInverterCapacityKw(string $text): float
    {
        if (preg_match('/ESS2-(\d+(?:[\.,]\d+)?)K/i', $text, $match)) {
            $value = (float) str_replace(',', '.', $match[1]);

            return abs($value - 6) < 0.2 ? 6.6 : $value;
        }
        if (preg_match('/GW(\d{4,5})/i', $text, $match)) {
            return round(((float) $match[1]) / 1000, 2);
        }
        if (preg_match('/GW(\d+(?:[\.,]\d+)?)K/i', $text, $match)) {
            return (float) str_replace(',', '.', $match[1]);
        }
        if (preg_match('/SUN-(\d+(?:[\.,]\d+)?)K/i', $text, $match)) {
            return (float) str_replace(',', '.', $match[1]);
        }
        if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(?:kwp|kw)\b/i', $text, $match)) {
            return (float) str_replace(',', '.', $match[1]);
        }

        return 0;
    }

    /**
     * Trích dung lượng pin lưu trữ (kWh) từ chuỗi mô tả.
     */
    private function extractBatteryCapacityKwh(string $text): float
    {
        if (preg_match('/(\d+(?:[\.,]\d+)?)\s*kwh\b/i', $text, $match)) {
            return (float) str_replace(',', '.', $match[1]);
        }
        if (preg_match('/GW(\d+(?:[\.,]\d+)?)-?BAT/i', $text, $match)) {
            return (float) str_replace(',', '.', $match[1]);
        }
        if (preg_match('/POWERBRICK[^0-9]*(\d+(?:[\.,]\d+)?)/i', $text, $match)) {
            return (float) str_replace(',', '.', $match[1]);
        }
        if (preg_match('/S51(100|280)/i', $text, $match)) {
            return $match[1] === '280' ? 14.34 : 5.12;
        }
        if (preg_match('/LX\s*A(\d+(?:[\.,]\d+)?)/i', $text, $match)) {
            return (float) str_replace(',', '.', $match[1]);
        }

        return 0;
    }

    /**
     * Nhận diện số pha của inverter từ chuỗi mô tả.
     */
    private function detectInverterPhase(string $text): string
    {
        $lower = mb_strtolower($text, 'UTF-8');
        if (str_contains($lower, '1 pha') || str_contains($lower, '1p') || preg_match('/\b1\s*phase\b/i', $text) || preg_match('/6K1P/i', $text)) {
            return '1';
        }
        if (str_contains($lower, '3 pha') || str_contains($lower, '3p') || preg_match('/\b3\s*phase\b/i', $text)) {
            return '3';
        }
        if (preg_match('/GW\d+K-ET|GW\d+K-SDT|GW\d+K-SMT|GW\d+K-MT|GW\d+K-GT|SUN-\d+K.*P3/i', $text)) {
            return '3';
        }
        if (preg_match('/GW\d+K-ES|GW\d{4}-ES|ESS2-\d+K1P/i', $text)) {
            return '1';
        }

        return '';
    }

    /**
     * Công suất inverter mặc định theo kWp khi không có sản phẩm trong kho.
     */
    private function fallbackInverterCapacity(float $kwp): float
    {
        if ($kwp <= 7.6) {
            return 6.6;
        }
        if ($kwp <= 9) {
            return 8;
        }
        if ($kwp <= 11) {
            return 10;
        }
        if ($kwp <= 13) {
            return 12;
        }
        if ($kwp <= 16) {
            return 15;
        }

        return 20;
    }

    /**
     * Giá inverter tham chiếu theo công suất.
     */
    private function fallbackInverterPrice(float $capacityKw): float
    {
        if ($capacityKw <= 6.6) {
            return 18000000;
        }
        if ($capacityKw <= 8) {
            return 36000000;
        }
        if ($capacityKw <= 10) {
            return 38000000;
        }
        if ($capacityKw <= 12) {
            return 42000000;
        }
        if ($capacityKw <= 15) {
            return 45000000;
        }

        return 60000000;
    }

    /**
     * Giá pin lưu trữ tham chiếu theo sản phẩm/dung lượng.
     */
    private function fallbackBatteryPrice(?array $batteryProduct, float $targetKwh): float
    {
        $text = mb_strtolower(($batteryProduct['display_name'] ?? '').' '.($batteryProduct['sku'] ?? ''), 'UTF-8');
        if (str_contains($text, 'smart power') || str_contains($text, 'sp 314')) {
            return 37000000;
        }
        if (str_contains($text, 'goodwe') || str_contains($text, 'gw16') || str_contains($text, 'oh14')) {
            return 67000000;
        }
        if ($targetKwh <= 6) {
            return 12000000;
        }

        return 47000000;
    }

    /**
     * Tỷ lệ tự dùng điện theo thói quen sử dụng và loại hệ.
     */
    private function getSelfConsumptionRatio(string $usageType, string $systemType): float
    {
        if ($systemType === 'on_grid') {
            return $usageType === 'day' ? 0.80 : ($usageType === 'night' ? 0.55 : 0.70);
        }
        if ($systemType === 'battery') {
            return $usageType === 'night' ? 0.95 : 0.92;
        }

        return $usageType === 'night' ? 0.90 : 0.88;
    }

    /**
     * Nhãn tiếng Việt của số pha.
     */
    private function getPhaseLabel(?string $phase, ?string $fallbackPhase = null): string
    {
        $value = $phase && $phase !== 'auto' ? $phase : ($fallbackPhase ?: '');

        return match ((string) $value) {
            '1' => '1 pha',
            '3' => '3 pha',
            default => 'Tự động',
        };
    }

    /**
     * Nhãn tiếng Việt của loại hệ thống.
     */
    private function getSystemTypeLabel(string $systemType): string
    {
        return match ($systemType) {
            'on_grid' => 'Bám tải on-grid',
            'battery' => 'Lưu trữ mạnh',
            default => 'Hybrid có pin',
        };
    }

    /**
     * Nhãn tiếng Việt của thói quen dùng điện.
     */
    private function getUsageTypeLabel(string $usageType): string
    {
        return match ($usageType) {
            'night' => 'Dùng ban đêm nhiều',
            'balanced' => 'Dùng cân bằng',
            default => 'Dùng ban ngày nhiều',
        };
    }
}
