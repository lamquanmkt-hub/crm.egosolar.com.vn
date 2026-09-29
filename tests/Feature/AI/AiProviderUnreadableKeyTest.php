<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Models\AI\AiProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * API key AI lưu mã hoá theo APP_KEY. Khi APP_KEY đổi (hoặc DB chép từ server khác),
 * trang Cài đặt → AI API vẫn phải mở được và Admin phải nhập lại key được.
 */
final class AiProviderUnreadableKeyTest extends TestCase
{
    use DatabaseTransactions;

    /** Chuỗi mã hoá hợp lệ về cấu trúc nhưng không giải mã được bằng APP_KEY hiện tại. */
    private const FOREIGN_CIPHER = 'eyJpdiI6ImludmFsaWQiLCJ2YWx1ZSI6IngiLCJtYWMiOiJ4In0=';

    private function providerWithUnreadableKey(): AiProvider
    {
        DB::table('ai_providers')->update(['is_default' => false]);
        $provider = AiProvider::query()->create([
            'name' => 'Gemini chép từ server khác',
            'provider_type' => 'gemini',
            'model' => 'gemini-test',
            'api_key' => 'tam-thoi',
            'timeout_seconds' => 30,
            'max_output_tokens' => 800,
            'temperature' => 0.2,
            'is_active' => true,
            'is_default' => true,
        ]);
        DB::table('ai_providers')->where('id', $provider->id)->update(['api_key' => self::FOREIGN_CIPHER]);

        return $provider->fresh();
    }

    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Gemini chép từ server khác',
            'provider_type' => 'gemini',
            'model' => 'gemini-test',
            'timeout_seconds' => 30,
            'max_output_tokens' => 800,
            'temperature' => 0.2,
            'is_active' => 1,
            'is_default' => 1,
        ], $overrides);
    }

    public function test_settings_page_opens_and_warns_when_key_is_unreadable(): void
    {
        $provider = $this->providerWithUnreadableKey();

        $this->assertFalse($provider->hasReadableKey());
        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.settings.ai.index'))
            ->assertOk()
            ->assertSee('Không đọc được key cũ');
    }

    public function test_connection_test_explains_unreadable_key(): void
    {
        $provider = $this->providerWithUnreadableKey();

        $this->actingAs($this->userWithRole('admin'))
            ->postJson(route('admin.settings.ai.providers.test', $provider))
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Không đọc được API key đã lưu (khóa mã hoá của hệ thống đã thay đổi). Hãy nhập lại API key rồi kiểm tra lại.');
    }

    public function test_admin_can_replace_unreadable_key(): void
    {
        $provider = $this->providerWithUnreadableKey();

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('admin.settings.ai.providers.update', $provider), $this->updatePayload(['api_key' => 'khoa-moi-1234']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $provider->refresh();
        $this->assertTrue($provider->hasReadableKey());
        $this->assertSame('khoa-moi-1234', $provider->api_key);
        $this->assertSame('••••••••••••1234', $provider->maskedKey());
    }

    public function test_updating_other_fields_keeps_readable_key(): void
    {
        $provider = $this->providerWithUnreadableKey();
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->put(route('admin.settings.ai.providers.update', $provider), $this->updatePayload(['api_key' => 'khoa-moi-1234']));

        $this->actingAs($admin)
            ->put(route('admin.settings.ai.providers.update', $provider), $this->updatePayload(['model' => 'gemini-khac', 'api_key' => '']))
            ->assertSessionHasNoErrors();

        $provider->refresh();
        $this->assertSame('gemini-khac', $provider->model);
        $this->assertSame('khoa-moi-1234', $provider->api_key);
    }

    public function test_updating_other_fields_does_not_crash_while_key_is_unreadable(): void
    {
        $provider = $this->providerWithUnreadableKey();

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('admin.settings.ai.providers.update', $provider), $this->updatePayload(['model' => 'gemini-khac']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $provider->refresh();
        $this->assertSame('gemini-khac', $provider->model);
        $this->assertFalse($provider->hasReadableKey());
    }
}
