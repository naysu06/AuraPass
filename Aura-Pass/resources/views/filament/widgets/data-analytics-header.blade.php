<x-filament-widgets::widget class="col-span-full">
    {{-- 
        We use a simple DIV instead of x-filament::section to avoid the 
        white/dark background card look. This makes it transparent.
    --}}
    <div class="flex items-center gap-3 py-1 mt-4 mb-2 border-b border-gray-200 dark:border-gray-800 pb-4">
        {{-- Icon Container --}}
        <div class="p-2 bg-primary-50 dark:bg-primary-900/20 rounded-lg">
            <x-heroicon-o-presentation-chart-line class="w-6 h-6 text-primary-600 dark:text-primary-400" />
        </div>

        {{-- Text --}}
        <div>
            <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                Data Analytics
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Overview of gym traffic trends and peak hours.
            </p>
        </div>
    </div>
</x-filament-widgets::widget>