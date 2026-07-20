<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller báo giá công trình: soạn hạng mục vật tư và đợt thanh toán.
 */
class SiteQuoteController extends Controller
{
    /**
     * Form soạn báo giá công trình với hạng mục vật tư và đợt thanh toán (có mẫu mặc định).
     */
    public function edit(Site $site)
    {
        $quoteItems = DB::table('site_planned_materials')
            ->where('site_id', $site->id)
            ->where('source', 'quote')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $paymentTerms = DB::table('site_payment_terms')
            ->where('site_id', $site->id)
            ->orderBy('id')
            ->get();

        if ($paymentTerms->isEmpty()) {
            $paymentTerms = collect([
                (object) [
                    'name' => 'Đợt 1 - Tạm ứng khi hợp đồng có hiệu lực',
                    'percent' => 40,
                    'note' => 'Tạm ứng sau khi hai bên ký xác nhận báo giá / hợp đồng.',
                ],
                (object) [
                    'name' => 'Đợt 2 - Thanh toán khi vật tư tập kết và thi công',
                    'percent' => 50,
                    'note' => 'Thanh toán khi thiết bị, vật tư tập kết tại công trình.',
                ],
                (object) [
                    'name' => 'Đợt 3 - Thanh toán khi hoàn thành bàn giao',
                    'percent' => 10,
                    'note' => 'Thanh toán sau khi hoàn thành lắp đặt, nghiệm thu và bàn giao.',
                ],
            ]);
        }

        return view('sites.quote', compact('site', 'quoteItems', 'paymentTerms'));
    }

    /**
     * Lưu báo giá: ghi lại hạng mục, tính tổng/giảm giá/VAT, cập nhật site và đợt thanh toán.
     */
    public function update(Request $request, Site $site)
    {
        $data = $request->validate([
            'quote_no' => ['nullable', 'string', 'max:50'],
            'quote_date' => ['nullable', 'date'],
            'quote_valid_until' => ['nullable', 'date'],
            'quote_status' => ['nullable', 'string', 'max:50'],
            'quote_customer_company' => ['nullable', 'string', 'max:255'],
            'quote_customer_email' => ['nullable', 'email', 'max:255'],
            'quote_customer_tax_code' => ['nullable', 'string', 'max:50'],
            'quote_config_summary' => ['nullable', 'string'],
            'quote_application_note' => ['nullable', 'string'],
            'quote_scope' => ['nullable', 'string'],
            'quote_commercial_terms' => ['nullable', 'string'],
            'quote_warranty_terms' => ['nullable', 'string'],
            'quote_om_terms' => ['nullable', 'string'],
            'quote_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'quote_vat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items' => ['nullable', 'array'],
            'payment_terms' => ['nullable', 'array'],
        ]);

        $items = $request->input('items', []);
        $subtotal = 0;

        DB::transaction(function () use ($request, $site, $data, $items, &$subtotal) {
            DB::table('site_planned_materials')
                ->where('site_id', $site->id)
                ->where('source', 'quote')
                ->delete();

            foreach ($items as $index => $item) {
                $name = trim($item['name'] ?? '');

                if ($name === '') {
                    continue;
                }

                $qty = $this->money($item['qty_decimal'] ?? $item['qty'] ?? 0);
                $unitPrice = $this->money($item['unit_price'] ?? 0);
                $lineTotal = $qty * $unitPrice;
                $subtotal += $lineTotal;

                DB::table('site_planned_materials')->insert([
                    'site_id' => $site->id,
                    'source' => 'quote',
                    'sort_order' => $index + 1,
                    'section_code' => $item['section_code'] ?? null,
                    'section_title' => $item['section_title'] ?? null,
                    'product_id' => null,
                    'name' => $name,
                    'brand' => $item['brand'] ?? null,
                    'model' => $item['model'] ?? null,
                    'specs_text' => $item['specs_text'] ?? null,
                    'unit' => $item['unit'] ?? null,
                    'qty' => (int) $qty,
                    'qty_decimal' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'image_path' => $item['image_path'] ?? null,
                    'quote_note' => $item['quote_note'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $discount = $this->money($data['quote_discount_amount'] ?? 0);
            $vatPercent = $this->money($data['quote_vat_percent'] ?? 0);

            $beforeVat = max($subtotal - $discount, 0);
            $vatAmount = $beforeVat * $vatPercent / 100;
            $grandTotal = $beforeVat + $vatAmount;

            $quoteNo = $data['quote_no'] ?? null;
            if (!$quoteNo) {
                $quoteNo = 'BGCT-' . now()->format('Ymd') . '-' . str_pad((string) $site->id, 4, '0', STR_PAD_LEFT);
            }

            DB::table('sites')->where('id', $site->id)->update([
                'quote_no' => $quoteNo,
                'quote_date' => $data['quote_date'] ?? now()->toDateString(),
                'quote_valid_until' => $data['quote_valid_until'] ?? now()->addDays(15)->toDateString(),
                'quote_status' => $data['quote_status'] ?? 'draft',
                'quote_customer_company' => $data['quote_customer_company'] ?? null,
                'quote_customer_email' => $data['quote_customer_email'] ?? null,
                'quote_customer_tax_code' => $data['quote_customer_tax_code'] ?? null,
                'quote_config_summary' => $data['quote_config_summary'] ?? null,
                'quote_application_note' => $data['quote_application_note'] ?? null,
                'quote_scope' => $data['quote_scope'] ?? null,
                'quote_commercial_terms' => $data['quote_commercial_terms'] ?? null,
                'quote_warranty_terms' => $data['quote_warranty_terms'] ?? null,
                'quote_om_terms' => $data['quote_om_terms'] ?? null,
                'quote_subtotal' => $subtotal,
                'quote_discount_amount' => $discount,
                'quote_vat_percent' => $vatPercent,
                'quote_vat_amount' => $vatAmount,
                'quote_grand_total' => $grandTotal,
                'contract_amount' => $grandTotal,
                'updated_at' => now(),
            ]);

            DB::table('site_payment_terms')
                ->where('site_id', $site->id)
                ->delete();

            $paymentTerms = $request->input('payment_terms', []);

            foreach ($paymentTerms as $term) {
                $name = trim($term['name'] ?? '');

                if ($name === '') {
                    continue;
                }

                $percent = $this->money($term['percent'] ?? 0);
                $amount = $grandTotal * $percent / 100;

                DB::table('site_payment_terms')->insert([
                    'site_id' => $site->id,
                    'name' => $name,
                    'percent' => $percent,
                    'amount' => $amount,
                    'due_date' => null,
                    'status' => 'pending',
                    'note' => $term['note'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('sites.quote.edit', $site->id)
            ->with('success', 'Đã lưu báo giá công trình thành công.');
    }

    /**
     * Chuyển chuỗi số định dạng Việt Nam (chấm ngăn nghìn, phẩy thập phân) về float.
     */
    private function money($value): float
    {
        if ($value === null) {
            return 0;
        }

        $value = str_replace(['.', ','], ['', '.'], (string) $value);

        return is_numeric($value) ? (float) $value : 0;
    }
}
