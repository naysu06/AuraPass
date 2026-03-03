{{-- resources/views/filament/widgets/trends-heading.blade.php --}}
<div class="flex items-center gap-2">
    <span>Predictive Trends & Retention</span>

    {{-- At-risk badge --}}
    @if(($highRiskCount ?? 0) > 0)
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                     bg-orange-500/20 text-orange-500 border border-orange-500/30">
            {{ $highRiskCount }} at risk
        </span>
    @endif

    {{-- Info tooltip --}}
    <div x-data="{ open: false }" class="relative flex items-center mt-0.5" @mouseleave="open = false">
        <x-heroicon-m-information-circle
            class="w-5 h-5 text-gray-400 hover:text-white cursor-help transition-colors"
            @mouseenter="open = true"
            @click="open = !open"
        />

        {{-- Tooltip Container --}}
        <div
            x-show="open"
            x-transition
            style="display:none; min-width: 22rem; width: max-content; background-color:#111827; border:1px solid rgba(75,85,99,0.6); border-radius:0.75rem; overflow:hidden;"
            class="absolute top-full left-0 mt-2 shadow-2xl z-[999] pointer-events-none"
        >
            {{-- Header --}}
            <div style="background-color:#1f2937; border-bottom:1px solid rgba(75,85,99,0.6);"
                 class="px-4 py-3 text-center">
                <span class="text-s font-semibold text-white-400 uppercase tracking-widest">
                    {{ $months }}-Month Forecast
                </span>
            </div>

            <div class="px-4 py-3 space-y-3">

                {{-- Membership flow --}}
                <div class="space-y-1.5">
                    <p class="text-[10px] font-semibold text-white-500 uppercase tracking-widest">
                        Membership Flow
                    </p>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-white-400">Currently active</span>
                        <span class="font-semibold text-white">{{ $current }} members</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-white-400">Expiring this period</span>
                        <span class="font-semibold text-red-400">−{{ $expiring }} members</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-white-400">Expected renewals</span>
                        <span class="font-semibold text-blue-400">+{{ $renewals }} members</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-white-400">New signups (seasonal)</span>
                        <span class="font-semibold text-emerald-400">+{{ $signups }} members</span>
                    </div>

                    {{-- Projected total --}}
                    <div style="border-top: 1px dashed rgba(107, 114, 128, 0.6);" class="flex items-center justify-between text-xs pt-2 mt-2">
                        <span class="text-gray-300 font-semibold">Projected active members</span>
                        <span class="font-bold text-white">{{ $projected }} members</span>
                    </div>
                </div>

                {{-- Divider --}}
                <div style="border-top:1px solid rgba(75,85,99,0.5);"></div>

                {{-- Churn by type --}}
                <div class="space-y-2">
                    <p class="text-[10px] font-semibold text-white-500 uppercase tracking-widest">
                        Churn Rate by Type
                        <span class="normal-case font-semibold text-white-400 ml-1">— Overall: {{ $blendedChurn }}</span>
                    </p>

                    {{-- Promo --}}
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="text-white-400">Promo</span>
                            <span class="font-bold text-orange-400">{{ $promoChurn }}</span>
                        </div>
                        <div style="height:4px; background-color:#374151; border-radius:9999px; overflow:hidden;">
                            <div style="height:100%; background-color:rgba(249,115,22,0.7); border-radius:9999px; width:{{ $promoChurn }};"></div>
                        </div>
                    </div>

                    {{-- Discount --}}
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="text-white-400">Discount</span>
                            <span class="font-bold text-purple-400">{{ $discountChurn }}</span>
                        </div>
                        <div style="height:4px; background-color:#374151; border-radius:9999px; overflow:hidden;">
                            <div style="height:100%; background-color:rgba(168,85,247,0.7); border-radius:9999px; width:{{ $discountChurn }};"></div>
                        </div>
                    </div>

                    {{-- Regular --}}
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="text-white-400">Regular</span>
                            <span class="font-bold text-blue-400">{{ $regularChurn }}</span>
                        </div>
                        <div style="height:4px; background-color:#374151; border-radius:9999px; overflow:hidden;">
                            <div style="height:100%; background-color:rgba(59,130,246,0.7); border-radius:9999px; width:{{ $regularChurn }};"></div>
                        </div>
                    </div>
                </div>
                {{-- At-risk footer --}}
                @if(($highRiskCount ?? 0) > 0)
                    <div style="border-top:1px solid rgba(75,85,99,0.5);" class="pt-2.5 flex items-center gap-2">
                        <div style="background-color:rgba(249,115,22,0.15);"
                             class="flex items-center justify-center w-6 h-6 rounded-full flex-shrink-0">
                            <svg class="w-3.5 h-3.5 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <p class="text-xs text-orange-400">
                            <span class="font-semibold">{{ $highRiskCount }} members</span>
                            haven't visited in 20+ days and are at high risk of not renewing.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>