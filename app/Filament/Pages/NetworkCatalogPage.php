<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AuditLog;
use App\Models\NetworkRequest;
use App\Support\NetworkCatalog;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\RateLimiter;
use UnitEnum;

/**
 * #127 Network catalog + request-a-network votes (Starter, every role). Lists every network AdsRanger has or plans and
 * what it means for this workspace; members ask for networks that aren't open yet, or suggest one we don't list.
 */
class NetworkCatalogPage extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?string $navigationLabel = 'Network catalog';

    protected static ?string $title = 'Network catalog';

    protected static ?string $slug = 'network-catalog';

    protected static ?int $navigationSort = 21;

    protected string $view = 'filament.pages.network-catalog';

    public string $suggestName = '';

    public string $suggestNote = '';

    public function toggleRequest(string $key): void
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        abort_unless(in_array($key, NetworkCatalog::knownKeys(), true), 404);
        $row = collect(NetworkCatalog::forMember($user, $agency))->flatten(1)->firstWhere('key', $key);
        abort_unless($row && NetworkCatalog::requestable($row['status']), 422);

        $existing = NetworkRequest::where(['agency_id' => $agency->id, 'user_id' => $user->id, 'network_key' => $key])->first();
        if ($existing) {
            $existing->delete();
            Notification::make()->title(__('Request removed'))->send();

            return;
        }
        NetworkRequest::create(['agency_id' => $agency->id, 'user_id' => $user->id, 'network_key' => $key, 'network_name' => NetworkCatalog::nameFor($key) ?? $key]);
        AuditLog::record('network.requested', $agency, ['network' => $key]);
        Notification::make()->title(__('Thanks: we count your request'))->success()->send();
    }

    public function suggest(): void
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $this->validate(['suggestName' => 'required|string|min:2|max:120', 'suggestNote' => 'nullable|string|max:500'], [],
            ['suggestName' => __('Network name'), 'suggestNote' => __('Why you need it')]);
        $rl = 'net-suggest:'.$user->id;
        if (RateLimiter::tooManyAttempts($rl, 10)) {
            $this->addError('suggestName', __('Too many suggestions today. Try again tomorrow.'));

            return;
        }
        RateLimiter::hit($rl, 86400);
        $name = trim($this->suggestName);
        $key = NetworkCatalog::suggestionKey($name);
        NetworkRequest::updateOrCreate(['agency_id' => $agency->id, 'user_id' => $user->id, 'network_key' => $key],
            ['network_name' => $name, 'note' => trim($this->suggestNote) ?: null]);
        AuditLog::record('network.suggested', $agency, ['network' => $key]);
        $this->reset('suggestName', 'suggestNote');
        Notification::make()->title(__('Thanks: suggestion sent'))->success()->send();
    }

    protected function getViewData(): array
    {
        $agency = Filament::getTenant();

        return [
            'groups' => NetworkCatalog::forMember(auth()->user(), $agency),
            'mySuggestions' => NetworkRequest::where('agency_id', $agency->id)->where('user_id', auth()->id())
                ->where('network_key', 'like', 'other:%')->orderByDesc('created_at')->get(),
        ];
    }
}
