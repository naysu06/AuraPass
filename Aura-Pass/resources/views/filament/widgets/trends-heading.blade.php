{{-- resources/views/filament/widgets/trends-heading.blade.php --}}
<div class="flex items-center gap-2">
    <span>Predictive Trends & Retention</span>

    <div x-data="{ open: false }" class="relative flex items-center mt-0.5" @mouseleave="open = false">
        <x-heroicon-m-information-circle
            class="w-5 h-5 text-gray-400 hover:text-primary-500 cursor-help transition-colors"
            @mouseenter="open = true"
            @click="open = !open"
        />

        <div
            x-show="open"
            x-transition
            style="display: none; width: 380px;"
            class="absolute top-full left-0 mt-2 p-4 text-sm text-left text-gray-600 bg-white border border-gray-200 rounded-lg shadow-2xl dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 z-[999] pointer-events-none font-normal"
        >
            {{-- Header --}}
            <p class="font-bold mb-2 text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-700 pb-2">
                Calculation Breakdown ({{ $months }} Months)
            </p>

            {{-- Projected total --}}
            <p class="mb-3 text-gray-800 dark:text-gray-200 text-base">
                <strong>Projected Total:
                    <span class="text-blue-600 dark:text-blue-400">{{ $projected }} Members</span>
                </strong>
            </p>

            {{-- Core numbers --}}
            <ul class="space-y-2">
                <li>🏋️ <span class="font-medium">Current Members:</span> {{ $current }}</li>

                <li class="text-red-500 dark:text-red-400">
                    ➖ <span class="font-medium">Total Expiring:</span> {{ $expiring }}
                </li>

                <li class="text-blue-500 dark:text-blue-400">
                    ➕ <span class="font-medium">Renewals ({{ $blendedChurn }} Blended Churn):</span> {{ $renewals }}
                </li>

                {{-- SUG #1 — Per-type churn breakdown --}}
                <li class="ml-5 space-y-1">
                    <p class="text-xs text-gray-400 dark:text-gray-500 italic mb-1">
                        ↳ Churn calculated individually per membership type:
                    </p>
                    <div class="grid grid-cols-3 gap-1 text-xs">
                        <div class="bg-orange-50 dark:bg-orange-900/20 rounded px-2 py-1 text-center">
                            <div class="font-semibold text-orange-600 dark:text-orange-400">{{ $promoChurn }}</div>
                            <div class="text-gray-500 dark:text-gray-400">Promo</div>
                        </div>
                        <div class="bg-purple-50 dark:bg-purple-900/20 rounded px-2 py-1 text-center">
                            <div class="font-semibold text-purple-600 dark:text-purple-400">{{ $discountChurn }}</div>
                            <div class="text-gray-500 dark:text-gray-400">Discount</div>
                        </div>
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded px-2 py-1 text-center">
                            <div class="font-semibold text-blue-600 dark:text-blue-400">{{ $regularChurn }}</div>
                            <div class="text-gray-500 dark:text-gray-400">Regular</div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500 italic mt-1">
                        ↳ Adjusted via weighted 4-week attendance trend
                    </p>
                </li>

                <li class="text-emerald-500 dark:text-emerald-400">
                    ➕ <span class="font-medium">New Signups (Seasonal):</span> {{ $signups }}
                </li>

                <li class="text-xs text-gray-400 dark:text-gray-500 italic ml-5">
                    ↳ Multiplied by Baguio's seasonal calendar (Jan peaks, Jul dips)
                </li>
            </ul>
        </div>
    </div>
</div>