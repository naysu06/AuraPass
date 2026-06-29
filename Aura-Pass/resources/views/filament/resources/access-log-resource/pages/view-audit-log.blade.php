<x-filament-panels::page>
    <div class="space-y-4">
        @foreach($logs as $date => $logsOnDate)
            <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4">
                <div @click="open = !open" class="cursor-pointer">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($date)->format('F j, Y') }}</h2>
                </div>
                <div x-show="open" x-cloak class="mt-4 space-y-4">
                    @foreach($logsOnDate as $log)
                        <div class="flex items-center space-x-4">
                            <div class="text-gray-500 dark:text-gray-400 w-24 text-sm">
                                {{ $log->created_at->format('h:i:s A') }}
                            </div>
                            <div class="flex-1">
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $log->user?->name ?? 'System' }}</span>
                                    <span>{{ $log->activity }}</span>
                                    @if($log->loggable instanceof \Illuminate\Database\Eloquent\Model)
                                        <span>{{ class_basename($log->loggable_type) }}</span>
                                        @if($log->loggable instanceof \App\Models\Member)
                                            <a href="{{ \App\Filament\Resources\MemberResource::getUrl('edit', ['record' => $log->loggable]) }}" class="text-primary-500 hover:underline">
                                                {{ $log->loggable->name ?? $log->loggable->id }}
                                            </a>
                                        @else
                                            <span>
                                                {{ $log->loggable->name ?? $log->loggable->id }}
                                            </span>
                                        @endif
                                    @endif
                                </p>
                                @if($log->details)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ json_encode($log->details) }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
