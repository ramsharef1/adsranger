<x-filament-panels::page>
    @include('filament.pages.partials.hub-styles')
    <div class="hub space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Posts in the plan') }}</div>
                <div class="text-2xl font-bold text-teal-600 dark:text-teal-400 mt-1">{{ $posts->count() }}</div>
            </div>
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Unified social inbox') }}</div>
                <div class="text-2xl font-bold text-teal-600 dark:text-teal-400 mt-1">{{ $inbox->where('status', 'open')->count() }} {{ __('open') }}</div>
            </div>
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                {{-- Shown only when it comes from this client's own posts; the hour is not measured, so it is not shown. --}}
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Best day to post') }}</div>
                @if (($bestTime['avg_engagements'] ?? 0) > 0)
                    <div class="text-lg font-bold text-teal-600 dark:text-teal-400 mt-1">{{ __($bestTime['best_day']) }}</div>
                    <div class="hub-note">{{ __('From this client\'s past posts') }}</div>
                @else
                    <div class="text-sm mt-1" data-no-best-day>{{ __('Not enough posts yet to say') }}</div>
                @endif
            </div>
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Auto-moderation rules') }}</div>
                <div class="text-2xl font-bold text-teal-600 dark:text-teal-400 mt-1">{{ $rulesCount }}</div>
            </div>
        </div>

        <p class="p-3 rounded-lg bg-gray-50 border text-sm" data-social-honesty>{{ __('This is a content plan. Publishing to the platforms and reading their comments are not connected yet, so nothing here is sent or fetched automatically.') }}</p>

        @if ($canManageSocial)
            <section class="p-5 rounded-xl bg-white border space-y-4" aria-labelledby="os-plan">
                <h3 id="os-plan" class="text-base font-bold text-gray-900">{{ __('Plan a post') }}</h3>
                <form wire:submit="planPost" class="space-y-3">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label for="os-client" class="block text-xs font-medium">{{ __('Client') }}</label>
                            <select id="os-client" wire:model="planClientId" class="mt-1 w-full">
                                @foreach ($clients as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="os-type" class="block text-xs font-medium">{{ __('Type') }}</label>
                            <select id="os-type" wire:model="planType" class="mt-1 w-full">
                                <option value="post">{{ __('Post') }}</option>
                                <option value="reel">{{ __('Reel') }}</option>
                                <option value="carousel">{{ __('Carousel') }}</option>
                                <option value="story">{{ __('Story') }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="os-at" class="block text-xs font-medium">{{ __('Planned for') }} <span class="hub-note">(<bdi dir="ltr">{{ $tz }}</bdi>)</span></label>
                            <input id="os-at" type="datetime-local" wire:model="planAt" dir="ltr" class="mt-1 w-full" />
                            @error('planAt')<p class="hub-note" style="color:#B42318">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label for="os-content" class="block text-xs font-medium">{{ __('Post text') }}</label>
                        <textarea id="os-content" rows="3" maxlength="2200" wire:model="planContent" class="mt-1 w-full"></textarea>
                        @error('planContent')<p class="hub-note" style="color:#B42318">{{ $message }}</p>@enderror
                    </div>
                    <fieldset style="border:0;padding:0;margin:0">
                        <legend class="text-xs font-medium" style="padding:0">{{ __('Platforms') }}</legend>
                        <div class="flex gap-4 mt-1" style="flex-wrap:wrap">
                            @foreach ($platformNames as $key => $name)
                                <label class="flex items-center gap-3" style="min-height:44px;gap:8px"><input type="checkbox" value="{{ $key }}" wire:model="planPlatforms"> <bdi dir="ltr">{{ $name }}</bdi></label>
                            @endforeach
                        </div>
                        @error('planPlatforms')<p class="hub-note" style="color:#B42318">{{ $message }}</p>@enderror
                    </fieldset>
                    <button type="submit">{{ __('Add to the plan') }}</button>
                </form>
            </section>
        @endif

        <section class="p-5 rounded-xl bg-white border" aria-labelledby="os-posts">
            <h3 id="os-posts" class="text-base font-bold mb-3">{{ __('Content plan') }}</h3>
            @if ($posts->isEmpty())
                <p class="hub-note">{{ __('No posts in the plan yet.') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($posts as $post)
                        @php [$label, $tone] = $statusLabels[$post->status] ?? [$post->status, '']; $open = in_array($post->status, ['draft', 'simulated', 'scheduled'], true); @endphp
                        <div class="p-3 rounded-lg bg-gray-50 flex items-center justify-between gap-3" style="flex-wrap:wrap">
                            <div style="min-width:0;flex:1 1 260px">
                                <div class="font-medium text-sm"><bdi>{{ $post->title ?: \Illuminate\Support\Str::limit($post->content, 90) }}</bdi></div>
                                <div class="hub-note mt-1"><bdi>{{ $clients->firstWhere('id', $post->client_id)?->name }}</bdi> · <bdi dir="ltr">{{ implode(', ', array_map(fn ($p) => $platformNames[$p] ?? $p, $post->platforms ?? [])) }}</bdi>@if ($post->scheduled_at) · <bdi dir="ltr">{{ $post->scheduled_at->timezone($tz)->format('Y-m-d H:i') }}</bdi>@endif</div>
                            </div>
                            <span class="flex items-center gap-3" style="flex-wrap:wrap">
                                <span class="hub-chip {{ $tone ? 'hub-'.$tone : '' }}">{{ $label }}</span>
                                @if ($canManageSocial && $open)
                                    <button type="button" class="hub-btn" wire:click="markPosted({{ $post->id }})">{{ __('I posted it') }}</button>
                                    <button type="button" class="hub-btn" wire:click="cancelPost({{ $post->id }})" style="background:transparent;color:var(--h-2);border:1px solid var(--h-control)">{{ __('Cancel') }}</button>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="p-5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
            <h3 class="text-base font-semibold mb-3">{{ __('Unified social inbox') }}</h3>
            @if($inbox->isEmpty())
                <p class="text-sm text-gray-500">{{ __('No inbox messages yet.') }}</p>
            @else
                <div class="space-y-3">
                    @foreach($inbox as $msg)
                        <div class="p-3 rounded-lg border border-gray-100 dark:border-gray-800 flex items-center justify-between">
                            <div>
                                <div class="font-medium text-sm">{{ $msg->sender_name }} <span class="text-xs text-gray-400">({{ $msg->platform }} · {{ $msg->message_type }})</span></div>
                                <div class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $msg->body }}</div>
                            </div>
                            <span class="px-2.5 py-1 text-xs rounded-full bg-gray-100 dark:bg-gray-800">
                                {{ $msg->sentiment }} · {{ $msg->status }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
