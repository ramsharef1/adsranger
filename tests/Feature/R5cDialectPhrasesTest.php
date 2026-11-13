<?php

namespace Tests\Feature;

use App\Filament\Pages\ArabicPhrasesPage;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use App\Support\Arabic\Phrases;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R5 part 3 (D-307): XS-16 dialect per client, XS-18 phrase library (draft), XS-17 rule-based variants. */
class R5cDialectPhrasesTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r5c', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Riyadh', 'language' => 'en', 'country' => 'SA']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Cairo Clinic', 'currency' => 'EGP', 'status' => 'active', 'market' => 'EG', 'vertical' => 'clinic']);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);

        return [$agency, $client];
    }

    public function test_dialect_comes_from_the_client_then_its_market(): void
    {
        [$agency, $client] = $this->workspace();
        $this->assertSame('egyptian', Phrases::dialectOf($client));
        Livewire::test(EditClient::class, ['record' => $client->id])->fillForm(['dialect' => 'msa'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('msa', $client->fresh()->dialect);
        $this->assertSame('msa', Phrases::dialectOf($client->fresh()));
        $this->assertSame('levantine', Phrases::dialectOf(new Client(['market' => 'JO'])));
        $this->assertSame('msa', Phrases::dialectOf(null));
    }

    public function test_variants_swap_dialect_words_and_ctas(): void
    {
        $v = Phrases::variants('عرض خاص لفترة محدودة، احجز موعدك الآن', 'gulf');
        $texts = array_column($v, 'text');
        $this->assertContains('عرض خاص لفترة محدودة، احجز موعدك الحين', $texts);
        $this->assertContains('cta', array_column($v, 'type'));

        $back = array_column(Phrases::variants('اطلب دلوقتي', 'msa'), 'text');
        $this->assertContains('اطلب الآن', $back);
        $this->assertContains(['type' => 'spelling', 'text' => 'اطلب الآن'], Phrases::variants('اطلبْ الآن', 'msa'), 'diacritics removed');
        $this->assertSame([], Phrases::variants('', 'gulf'));
    }

    public function test_page_follows_the_client_dialect_and_marks_the_draft(): void
    {
        [, $client] = $this->workspace();
        Livewire::test(ArabicPhrasesPage::class)->set('clientId', $client->id)
            ->assertSet('dialect', 'egyptian')->assertSet('sector', 'clinic')
            ->assertSee('احجز معادك دلوقتي')->assertDontSee('احجز موعدك الحين')->assertSee('Draft set')
            ->set('line', 'احجز موعدك الآن')->assertSee('احجز معادك دلوقتي');
    }
}
