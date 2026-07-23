<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AppearanceSettingsController extends Controller
{
    private const DEFAULTS = [
        'brand_name' => 'EGO Solar CRM',
        'brand_short_name' => 'EGO Solar',
        'logo_light' => 'images/ego-logo.png',
        'logo_sidebar' => 'logo/ego-solar-white.png',
        'favicon' => '',
        'primary_color' => '#12ABC6',
        'secondary_color' => '#0D988C',
        'sidebar_color' => '#06182A',
        'topbar_color' => '#FFFFFF',
        'page_background' => '#F4F8FB',
        'card_radius' => '16',
        'ui_density' => 'comfortable',
    ];

    public function index(): View
    {
        return view('admin.settings.appearance', [
            'settings' => $this->readSettings(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'brand_name' => ['required', 'string', 'max:100'],
            'brand_short_name' => ['required', 'string', 'max:50'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'topbar_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'page_background' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'card_radius' => ['required', 'integer', 'min:8', 'max:30'],
            'ui_density' => ['required', 'in:comfortable,compact'],
            'logo_light_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:3072'],
            'logo_sidebar_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:3072'],
            'favicon_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,ico', 'max:1024'],
        ], [
            'brand_name.required' => 'Vui lòng nhập tên hệ thống.',
            'primary_color.regex' => 'Màu chủ đạo phải là mã HEX 6 ký tự.',
            'secondary_color.regex' => 'Màu phụ phải là mã HEX 6 ký tự.',
            'sidebar_color.regex' => 'Màu sidebar phải là mã HEX 6 ký tự.',
            'topbar_color.regex' => 'Màu topbar phải là mã HEX 6 ký tự.',
            'page_background.regex' => 'Màu nền phải là mã HEX 6 ký tự.',
        ]);

        $settings = [
            'brand_name' => trim($validated['brand_name']),
            'brand_short_name' => trim($validated['brand_short_name']),
            'primary_color' => strtoupper($validated['primary_color']),
            'secondary_color' => strtoupper($validated['secondary_color']),
            'sidebar_color' => strtoupper($validated['sidebar_color']),
            'topbar_color' => strtoupper($validated['topbar_color']),
            'page_background' => strtoupper($validated['page_background']),
            'card_radius' => (string) $validated['card_radius'],
            'ui_density' => $validated['ui_density'],
        ];

        foreach ([
            'logo_light_file' => ['logo_light', 'logo-light'],
            'logo_sidebar_file' => ['logo_sidebar', 'logo-sidebar'],
            'favicon_file' => ['favicon', 'favicon'],
        ] as $field => [$settingKey, $prefix]) {
            if ($request->hasFile($field)) {
                $settings[$settingKey] = $this->storeUpload(
                    $request->file($field),
                    $prefix
                );
            }
        }

        DB::transaction(function () use ($settings): void {
            foreach ($settings as $key => $value) {
                $exists = DB::table('ego_system_settings')
                    ->where('key', $key)
                    ->exists();

                if ($exists) {
                    DB::table('ego_system_settings')
                        ->where('key', $key)
                        ->update([
                            'value' => $value,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('ego_system_settings')->insert([
                        'key' => $key,
                        'value' => $value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        Cache::forget('ego.system.branding.v2');

        return redirect()
            ->route('admin.settings.appearance')
            ->with('success', 'Đã áp dụng giao diện mới cho toàn bộ CRM.');
    }

    public function reset(): RedirectResponse
    {
        if (Schema::hasTable('ego_system_settings')) {
            DB::table('ego_system_settings')
                ->whereIn('key', array_keys(self::DEFAULTS))
                ->delete();
        }

        Cache::forget('ego.system.branding.v2');

        return redirect()
            ->route('admin.settings.appearance')
            ->with('success', 'Đã khôi phục giao diện mặc định.');
    }

    private function readSettings(): array
    {
        $stored = [];

        if (Schema::hasTable('ego_system_settings')) {
            $stored = DB::table('ego_system_settings')
                ->whereIn('key', array_keys(self::DEFAULTS))
                ->pluck('value', 'key')
                ->map(fn ($value): string => (string) $value)
                ->all();
        }

        return array_merge(self::DEFAULTS, $stored);
    }

    private function storeUpload(UploadedFile $file, string $prefix): string
    {
        $directory = public_path('uploads/branding');
        File::ensureDirectoryExists($directory, 0755, true);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $filename = sprintf(
            '%s-%s-%s.%s',
            $prefix,
            now()->format('YmdHis'),
            bin2hex(random_bytes(3)),
            $extension
        );

        $file->move($directory, $filename);

        return 'uploads/branding/'.$filename;
    }
}
