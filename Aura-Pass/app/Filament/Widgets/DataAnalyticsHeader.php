<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class DataAnalyticsHeader extends Widget
{
    protected static string $view = 'filament.widgets.data-analytics-header';

    // Force it to span the entire width of the dashboard
    protected int | string | array $columnSpan = 'full';
    
    // Optional: Adjust sort if needed, but we usually control this in the Provider
}