<?php

namespace Tests\Feature;

use App\Alerts\AlertText;
use App\Filament\Admin\Pages\CustomersBoard;
use App\Filament\Admin\Pages\OperationsBoard;
use App\Models\User;
use App\Support\ResultResolver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * EH-23 (D-290): numbers and money in Arabic screens use Western digits (CR-16), the ISO code after the amount
 * (D-173 / D-181), no "$" sign, and the Latin part sits in an isolate so it does not flip in right-to-left text.
 */
class ArabicFormatsTest extends TestCase
{
    use RefreshDatabase;

    private const EASTERN = '/[٠-٩۰-۹]/u';

    public function test_money_is_amount_then_iso_code_with_western_digits(): void
    {
        $this->assertSame('1,234.50 SAR', ResultResolver::money(1234.5, 'SAR'));
        $this->assertSame('0.125 AED', ResultResolver::money(0.125, 'AED'));
        $this->assertSame('–', ResultResolver::money(null, 'SAR'));
        $this->assertDoesNotMatchRegularExpression(self::EASTERN, ResultResolver::money(98765.43, 'KWD'));
    }

    public function test_arabic_strings_have_no_eastern_digits_or_dollar_amounts(): void
    {
        $ar = json_decode((string) file_get_contents(lang_path('ar.json')), true);
        $bad = array_keys(array_filter($ar, fn ($v) => is_string($v) && (preg_match(self::EASTERN, $v) || preg_match('/\$\s?\d/', $v))));
        $this->assertSame([], $bad, 'Arabic strings with Eastern digits or a $ amount');
    }

    public function test_arabic_alert_text_keeps_numbers_western_and_isolated(): void
    {
        $out = AlertText::detail('1,250.00 SAR spent in the last 3 days with 0 results.', 'ar');
        $this->assertDoesNotMatchRegularExpression(self::EASTERN, $out);
        $this->assertStringContainsString('1,250.00 SAR', $out);

        $out = AlertText::detail('https://shop.test/a — HTTP 500. Down since 2026-11-13 03:30 UTC. Ads sent 12 clicks here in 30 days.', 'ar');
        $this->assertDoesNotMatchRegularExpression(self::EASTERN, $out);
        $this->assertStringContainsString("\u{2066}2026-11-13 03:30\u{2069}", $out, 'the date sits in a left-to-right isolate');
        $this->assertStringNotContainsString('Down since', $out);
    }

    public function test_arabic_admin_screens_render_western_digits(): void
    {
        app()->setLocale('ar');
        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        foreach ([CustomersBoard::class, OperationsBoard::class] as $page) {
            $html = Livewire::test($page)->html();
            $this->assertDoesNotMatchRegularExpression(self::EASTERN, $html, $page);
        }
    }
}
