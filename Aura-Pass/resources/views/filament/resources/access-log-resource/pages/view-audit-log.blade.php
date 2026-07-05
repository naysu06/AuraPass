<x-filament-panels::page>
    
    <div class="flex flex-col sm:flex-row gap-4 mb-2 items-center justify-between">
        
        <div class="w-full sm:w-1/2 relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none" style="padding-left: 7px">
                <x-heroicon-m-magnifying-glass class="w-5 h-5 text-gray-400" />
            </div>
            
            <input type="text" style="padding-left: 2rem;" 
                wire:model.live.debounce.300ms="search" 
                placeholder="Search by name, action, or admin..." 
                class="block w-full !pl-12 pr-4 py-2.5 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg focus:ring-primary-500 focus:border-primary-500 sm:text-sm shadow-sm transition-colors">
        </div>

        <div class="w-full sm:w-1/4">
            <select wire:model.live="filter" class="block w-full p-2.5 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg focus:ring-primary-500 focus:border-primary-500 sm:text-sm shadow-sm transition-colors cursor-pointer">
                <option value="all">All Events</option>
                <option value="admin">Admin Actions Only</option>
                <option value="member">Member Actions Only</option>
                <option value="system">System Events Only</option>
            </select>
        </div>
    </div>

    <div class="relative min-h-[400px]">
        
        <div wire:loading class="absolute inset-0 z-10 flex items-center justify-center bg-white/50 dark:bg-gray-900/50 rounded-lg backdrop-blur-sm">
            <x-filament::loading-indicator class="w-10 h-10 text-primary-500" />
        </div>

        <div wire:loading.class="opacity-50 pointer-events-none" class="space-y-4 transition-opacity duration-200">
            
            @forelse($groupedLogs as $date => $logsOnDate)
                <div x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 transition-all">
                    
                    <div @click="open = !open" class="cursor-pointer flex items-center justify-between group">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white group-hover:text-primary-500 transition-colors">
                            {{ \Carbon\Carbon::parse($date)->format('F j, Y') }}
                        </h2>
                        <x-heroicon-m-chevron-down class="w-5 h-5 text-gray-400 transition-transform duration-300" x-bind:class="open ? 'rotate-180' : ''" />
                    </div>
                    
                    <div x-show="open" x-collapse x-cloak class="mt-4 space-y-4">
                        @foreach($logsOnDate as $log)
                            <div class="flex items-center space-x-4">
                                <div class="text-gray-500 dark:text-gray-400 w-28 shrink-0 text-sm font-medium pr-4">
                                    {{ $log->created_at->format('h:i:s A') }}
                                </div>
                                
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-2 text-sm pr-10">
                                        
                                        @switch($log->activity)
                                            @case('member.checked_in')
                                            @case('member.checked_in_manually')
                                                <x-heroicon-m-arrow-right-circle class="w-5 h-5 text-emerald-500" />
                                                <span class="text-gray-500 dark:text-gray-400">
                                                    {{ str_contains($log->activity, 'manually') ? 'Manual Check-In:' : 'Member Checked In:' }}
                                                </span>
                                                <a href="{{ \App\Filament\Resources\MemberResource::getUrl('view', ['record' => $log->loggable_id ?? 1]) }}" class="text-primary-600 dark:text-primary-500 hover:underline font-semibold">
                                                    {{ $log->details['member_name'] ?? $log->loggable?->name ?? 'Unknown' }}
                                                </a>
                                                @break

                                            @case('member.checked_out')
                                            @case('member.checked_out_manually')
                                                <x-heroicon-m-arrow-left-circle class="w-5 h-5 text-gray-500" />
                                                <span class="text-gray-500 dark:text-gray-400">
                                                    {{ str_contains($log->activity, 'manually') ? 'Manual Check-Out:' : 'Member Checked Out:' }}
                                                </span>
                                                <a href="{{ \App\Filament\Resources\MemberResource::getUrl('view', ['record' => $log->loggable_id ?? 1]) }}" class="text-primary-600 dark:text-primary-500 hover:underline font-semibold">
                                                    {{ $log->details['member_name'] ?? $log->loggable?->name ?? 'Unknown' }}
                                                </a>
                                                @break  

                                            @case('member.scan_failed')
                                            @case('member.scan.failed')
                                                <x-heroicon-m-x-circle class="w-5 h-5 text-red-500" />
                                                <span class="text-gray-500 dark:text-gray-400">Access Denied (Scan Failed):</span>
                                                
                                                @if($log->loggable_id || isset($log->details['member_name']))
                                                    <a href="{{ \App\Filament\Resources\MemberResource::getUrl('view', ['record' => $log->loggable_id ?? 1]) }}" class="text-primary-600 dark:text-primary-500 hover:underline font-semibold">
                                                        {{ $log->details['member_name'] ?? $log->loggable?->name ?? 'Unknown' }}
                                                    </a>
                                                @else
                                                    <span class="font-semibold text-red-600 dark:text-red-400">
                                                        {{ $log->details['scanned_code'] ?? 'Unrecognized QR Code' }}
                                                    </span>
                                                @endif

                                                @if(isset($log->details['reason']))
                                                    <span class="text-xs bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 px-2 py-0.5 rounded font-medium border border-red-200 dark:border-red-800 ml-1">
                                                        {{ $log->details['reason'] }}
                                                    </span>
                                                @endif
                                                @break

                                            @case('member.created')
                                                <x-heroicon-m-user-plus class="w-5 h-5 text-blue-500" />
                                                <span class="text-gray-500 dark:text-gray-400">New Member Registered:</span>
                                                <a href="{{ \App\Filament\Resources\MemberResource::getUrl('view', ['record' => $log->loggable_id ?? 1]) }}" class="text-primary-600 dark:text-primary-500 hover:underline font-semibold">
                                                    {{ $log->details['member_name'] ?? $log->loggable?->name ?? 'Unknown' }}
                                                </a>
                                                @break

                                            @case('member.renewed')
                                                <x-heroicon-m-arrow-path class="w-5 h-5 text-green-500" />
                                                <span class="text-gray-500 dark:text-gray-400">Membership Renewed:</span>
                                                <a href="{{ \App\Filament\Resources\MemberResource::getUrl('view', ['record' => $log->loggable_id ?? 1]) }}" class="text-primary-600 dark:text-primary-500 hover:underline font-semibold">
                                                    {{ $log->details['member_name'] ?? $log->loggable?->name ?? 'Unknown' }}
                                                </a>
                                                @if(isset($log->details['new_expiry']))
                                                    <span class="text-xs bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 px-2 py-0.5 rounded font-medium border border-green-200 dark:border-green-800">
                                                        Until {{ \Carbon\Carbon::parse($log->details['new_expiry'])->format('M j, Y') }}
                                                    </span>
                                                @endif
                                                @break

                                            @case('member.updated')
                                                @php
                                                    $isCorrection = false;
                                                    // Check if this update was specifically our expiry date modification
                                                    if (isset($log->details['note']) && $log->details['note'] === 'Expiry date modified') {
                                                        $old = \Carbon\Carbon::parse($log->details['old_expiry']);
                                                        $new = \Carbon\Carbon::parse($log->details['new_expiry']);
                                                        
                                                        // If the new date is earlier than the old date, it's a correction
                                                        if ($new->lessThan($old)) {
                                                            $isCorrection = true;
                                                        }
                                                    }
                                                @endphp

                                                @if($isCorrection)
                                                    <x-heroicon-m-clock class="w-5 h-5 text-amber-500" />
                                                    <span class="text-gray-500 dark:text-gray-400">Membership Changed:</span>
                                                    <a href="{{ \App\Filament\Resources\MemberResource::getUrl('view', ['record' => $log->loggable_id ?? 1]) }}" class="text-primary-600 dark:text-primary-500 hover:underline font-semibold">
                                                        {{ $log->details['member_name'] ?? $log->loggable?->name ?? 'Unknown' }}
                                                    </a>
                                                    <span class="text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 px-2 py-0.5 rounded font-medium border border-amber-200 dark:border-amber-800 ml-2">
                                                        Until {{ \Carbon\Carbon::parse($log->details['new_expiry'])->format('M j, Y') }}
                                                    </span>
                                                @else
                                                    <x-heroicon-m-pencil-square class="w-5 h-5 text-blue-400" />
                                                    <span class="text-gray-500 dark:text-gray-400">Member Profile Updated:</span>
                                                    <a href="{{ \App\Filament\Resources\MemberResource::getUrl('view', ['record' => $log->loggable_id ?? 1]) }}" class="text-primary-600 dark:text-primary-500 hover:underline font-semibold">
                                                        {{ $log->details['member_name'] ?? $log->loggable?->name ?? 'Unknown' }}
                                                    </a>
                                                    @if(isset($log->details['changes']))
                                                        <div class="flex gap-2 ml-2">
                                                            @foreach($log->details['changes'] as $key => $changeData)
                                                                <span class="text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 px-2 py-0.5 rounded font-medium border border-amber-200 dark:border-amber-800 ml-2">
                                                                    {{ ucfirst(str_replace('_', ' ', $key)) }} updated
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                @endif
                                                @break

                                            @case('member.deleted')
                                                <x-heroicon-m-trash class="w-5 h-5 text-red-400" />
                                                <span class="text-gray-500 dark:text-gray-400">Member Account Deleted:</span>
                                                <span class="font-semibold text-red-600 dark:text-red-400">
                                                    {{ $log->details['member_name'] ?? 'Unknown Member' }}
                                                </span>
                                                @break
                                            
                                            @case('admin.created')
                                                <x-heroicon-m-user-plus class="w-5 h-5 text-indigo-500" />
                                                <span class="text-gray-500 dark:text-gray-400">Admin Account Created:</span>
                                                <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $log->details['username'] ?? 'Unknown' }}</span>
                                                @if(isset($log->details['role']))
                                                    <span class="text-xs bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 px-2 py-0.5 rounded font-medium border border-red-200 dark:border-red-800 ml-2 uppercase">{{ $log->details['role'] }}</span>
                                                @endif
                                                @break

                                            @case('admin.updated')
                                                <x-heroicon-m-pencil-square class="w-5 h-5 text-blue-500" />
                                                <span class="text-gray-500 dark:text-gray-400">Admin Account Modified:</span>
                                                <span class="font-semibold text-blue-600 dark:text-blue-400">{{ $log->details['username'] ?? 'Unknown' }}</span>
                                                
                                                @if(isset($log->details['changes']))
                                                    <div class="flex gap-2 ml-2">
                                                        @foreach($log->details['changes'] as $key => $changeData)
                                                            <span class="text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 px-2 py-0.5 rounded font-medium border border-amber-200 dark:border-amber-800">
                                                                {{ ucfirst($key) }} updated
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                                @break

                                            @case('admin.deleted')
                                                <x-heroicon-m-trash class="w-5 h-5 text-red-500" />
                                                <span class="text-gray-500 dark:text-gray-400">Admin Account Deleted:</span>
                                                <span class="font-semibold text-red-600 dark:text-red-500">{{ $log->details['username'] ?? 'Unknown' }}</span>
                                                @break

                                            @case('admin.logged_in')
                                            @case('admin.logged_out')
                                                <x-heroicon-m-shield-check class="w-5 h-5 text-amber-500" />
                                                <span class="text-gray-500 dark:text-gray-400">{{ str_replace('_', ' ', ucfirst(substr($log->activity, 6))) }}</span>
                                                <span class="font-semibold text-amber-600 dark:text-amber-500">
                                                    {{ $log->details['username'] ?? 'Unknown' }}
                                                </span>
                                                @break

                                            @default
                                                <x-heroicon-m-cpu-chip class="w-5 h-5 text-purple-500" />
                                                <span class="text-gray-500 dark:text-gray-400">{{ $log->activity }}</span>
                                        @endswitch
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="text-center py-6 text-gray-500 bg-white dark:bg-gray-800 shadow-sm rounded-lg px-4 border border-dashed border-gray-300 dark:border-gray-700">
                    <x-heroicon-o-document-magnifying-glass class="w-8 h-8 mx-auto text-gray-400 mb-2" />
                    <p class="text-sm">No activity logs matched your search or filters.</p>
                </div>
            @endforelse
        </div>
    </div>

    @if($paginator->hasPages())
        <div class="mt-4">
            {{ $paginator->links() }}
        </div>
    @endif

</x-filament-panels::page>