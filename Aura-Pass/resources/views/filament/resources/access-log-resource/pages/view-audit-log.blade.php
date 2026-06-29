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
                            
                            <div class="text-gray-500 dark:text-gray-400 w-28 shrink-0 text-sm font-medium">
                                {{ $log->created_at->format('h:i:s A') }}
                            </div>
                            
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-1.5 text-sm">
                                    
                                    <span class="font-bold text-gray-900 dark:text-white">
                                        {{ $log->user?->name ?? 'System' }}
                                    </span>
                                    
                                    @switch($log->activity)
                                        @case('system.started')
                                        @case('system.shutdown')
                                            <span class="text-gray-500 dark:text-gray-400">{{ $log->activity }}</span>
                                            @break

                                        @case('admin.logged_in')
                                        @case('admin.logged_out')
                                            <span class="text-gray-500 dark:text-gray-400">{{ $log->activity }} Administrator</span>
                                            <span class="font-semibold text-amber-600 dark:text-amber-500">
                                                {{ $log->details['username'] ?? 'Unknown' }}
                                            </span>
                                            @break

                                        @default
                                            <span class="text-gray-500 dark:text-gray-400">{{ $log->activity }}</span>
                                            @if($log->loggable instanceof \Illuminate\Database\Eloquent\Model)
                                                <span class="text-gray-500 dark:text-gray-400">{{ class_basename($log->loggable_type) }}</span>
                                                @if($log->loggable instanceof \App\Models\Member)
                                                    <a href="{{ \App\Filament\Resources\MemberResource::getUrl('edit', ['record' => $log->loggable]) }}" class="text-primary-600 dark:text-primary-500 hover:underline font-semibold">
                                                        {{ $log->loggable->name ?? $log->loggable->id }}
                                                    </a>
                                                @else
                                                    <span class="font-semibold text-gray-900 dark:text-white">
                                                        {{ $log->loggable->name ?? $log->loggable->id }}
                                                    </span>
                                                @endif
                                            @endif
                                    @endswitch
                                </div>
                                
                                @if($log->details && !in_array($log->activity, ['admin.logged_in', 'admin.logged_out']))
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ json_encode($log->details) }}</p>
                                @endif
                            </div>
                            
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>