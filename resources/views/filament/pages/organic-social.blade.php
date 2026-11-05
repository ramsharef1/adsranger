<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Scheduled & published posts') }}</div>
                <div class="text-2xl font-bold text-teal-600 dark:text-teal-400 mt-1">{{ $posts->count() }}</div>
            </div>
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Unified social inbox') }}</div>
                <div class="text-2xl font-bold text-teal-600 dark:text-teal-400 mt-1">{{ $inbox->where('status', 'open')->count() }} {{ __('open') }}</div>
            </div>
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Best time to post') }}</div>
                <div class="text-lg font-bold text-teal-600 dark:text-teal-400 mt-1">{{ __($bestTime['best_day']) }} · {{ $bestTime['recommended_hour'] }}</div>
            </div>
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('Auto-moderation rules') }}</div>
                <div class="text-2xl font-bold text-teal-600 dark:text-teal-400 mt-1">{{ $rulesCount }}</div>
            </div>
        </div>

        <div class="p-5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
            <h3 class="text-base font-semibold mb-3">{{ __('Scheduled & published posts') }}</h3>
            @if($posts->isEmpty())
                <p class="text-sm text-gray-500">{{ __('No organic posts scheduled yet.') }}</p>
            @else
                <div class="space-y-3">
                    @foreach($posts as $post)
                        <div class="p-3 rounded-lg border border-gray-100 dark:border-gray-800 flex items-center justify-between">
                            <div>
                                <div class="font-medium text-sm">{{ $post->title ?: \Illuminate\Support\Str::limit($post->content, 60) }}</div>
                                <div class="text-xs text-gray-500 mt-1">{{ implode(', ', $post->platforms ?? []) }} · {{ $post->post_type }}</div>
                            </div>
                            <span class="px-2.5 py-1 text-xs rounded-full bg-teal-50 dark:bg-teal-950 text-teal-700 dark:text-teal-300">
                                {{ $post->status }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

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
