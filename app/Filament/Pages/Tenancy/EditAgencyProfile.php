<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\AuditLog;
use App\Support\AgencyLogo;
use App\Support\CustomDomainManager;
use App\Support\Modules;
use App\Support\RamadanHours;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EditAgencyProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return __('Agency settings');
    }

    public static function canView(Model $tenant): bool
    {
        return in_array(auth()->user()?->roleIn($tenant), ['owner', 'admin'], true);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            ...AgencyFields::make(),
            // #176 (D-105): logo only, all tiers; shown on client links, the monthly PDF and report emails.
            FileUpload::make('logo_path')->label(__('Agency logo'))
                ->disk('local')->directory(AgencyLogo::DIRECTORY)->visibility('private')
                ->acceptedFileTypes(['image/png', 'image/svg+xml'])->maxSize(AgencyLogo::MAX_KB)
                ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => Str::random(40).'.'.(str_contains((string) $file->getMimeType(), 'svg') ? 'svg' : 'png'))
                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if ($message = static::logoProblem($value)) {
                        $fail($message);
                    }
                }])
                ->helperText(__('SVG or PNG, up to 200 KB, at least 400 px wide; a transparent background works best. Shown on client links, the monthly PDF and report emails. Without a logo, your agency name is shown.')),
            // #74: members without two-step verification are sent to set it up before they can open this workspace.
            Section::make(__('White-label custom domain'))->description(__('Point a CNAME record (for example reports.agency.com → adsranger.com) via your DNS or Cloudflare proxy for shared client report links.'))
                ->visible(fn (): bool => Modules::on('custom_domains'))
                ->columns(2)->schema([
                    TextInput::make('custom_domain')->label(__('Custom report domain'))
                        ->placeholder('reports.agency.com')
                        ->maxLength(190)
                        ->dehydrateStateUsing(fn (?string $state): ?string => $state ? CustomDomainManager::normalizeHost($state) : null)
                        ->helperText(__('CNAME target: adsranger.com (Cloudflare for SaaS / DNS proxy supported).')),
                    TextEntry::make('custom_domain_verification')->label(__('Domain verification status'))
                        ->state(fn (): string => $this->tenant->custom_domain
                            ? __(ucfirst((string) ($this->tenant->custom_domain_status ?: 'pending')))
                            : __('Not configured')),
                ]),
            // BX-05 (D-278 / D-280): on by default; null counts as on.
            Toggle::make('report_powered_by')->label(__('Show "Powered by AdsRanger" on client reports'))
                ->helperText(__('A small line at the bottom of client report links and PDFs. Switch it off to show only your agency.'))
                ->default(true)->formatStateUsing(fn ($state): bool => $state === null || (bool) $state),
            // PS-19 (R5, D-307): on by default; null counts as on.
            Section::make(__('Ramadan hours'))->description(__('During Ramadan the daily summary comes after iftar, and push quiet hours move to 03:00–12:00 for members who kept the default. It switches on and off by itself from the Ramadan dates.'))
                ->columns(2)->schema([
                    Toggle::make('ramadan_hours')->label(__('Use Ramadan hours'))->default(true)->formatStateUsing(fn ($state): bool => $state === null || (bool) $state),
                    Select::make('ramadan_digest_at')->label(__('Daily summary time in Ramadan'))->options(RamadanHours::digestTimes())->default(RamadanHours::DEFAULT_DIGEST)
                        ->formatStateUsing(fn ($state): string => $state ?: RamadanHours::DEFAULT_DIGEST),
                ]),
            Toggle::make('require_two_factor')->label(__('Require two-step verification for all members'))
                ->helperText(__('Members who haven\'t set it up are asked to add an authenticator app before they can open this workspace. This includes you.')),
        ]);
    }

    /** Experience spec B validation for #176; returns the message to show, or null when the file is fine. */
    public static function logoProblem(mixed $value): ?string
    {
        if (! $value instanceof UploadedFile) {
            return null;
        }
        $realPath = $value->getRealPath();
        $contents = (string) @file_get_contents($realPath);
        if (strlen($contents) > AgencyLogo::MAX_KB * 1024) {
            return __('The file is too large: the limit is 200 KB.');
        }
        if (str_contains((string) $value->getMimeType(), 'svg')) {
            // Logos are drawn by the PDF renderer and served from our domain: no scripts, external files or entities.
            if (preg_match('/<script|<foreignObject|<!ENTITY|\bon[a-z]+\s*=|(?:xlink:)?href\s*=\s*["\']\s*(?!#)/i', $contents)) {
                return __('This SVG has scripts or links to other files. Export a plain SVG or use a PNG.');
            }

            return null;
        }
        $size = @getimagesize($realPath);
        if (! $size || ($size[2] ?? null) !== IMAGETYPE_PNG) {
            return __('Use an SVG or PNG file.');
        }
        if ($size[0] < AgencyLogo::MIN_WIDTH) {
            return __('Use an image at least 400 px wide.');
        }

        return null;
    }

    protected function afterSave(): void
    {
        if ($this->tenant->wasChanged('logo_path')) {
            AuditLog::record('agency.logo_changed', $this->tenant, ['has_logo' => AgencyLogo::has($this->tenant)]);
        }

        if ($this->tenant->wasChanged('require_two_factor')) {
            AuditLog::record('agency.require_2fa_changed', $this->tenant, ['on' => (bool) $this->tenant->require_two_factor]);
        }

        if ($this->tenant->wasChanged('custom_domain')) {
            $this->tenant->forceFill([
                'custom_domain_status' => 'pending',
                'custom_domain_verified_at' => null,
            ])->saveQuietly();
            if ($this->tenant->custom_domain) {
                AuditLog::record('agency.custom_domain_configured', $this->tenant, ['domain' => $this->tenant->custom_domain]);
            }
        }
    }
}
