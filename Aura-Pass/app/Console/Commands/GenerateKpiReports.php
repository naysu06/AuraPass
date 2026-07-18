<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\KpiService;
use Illuminate\Support\Facades\Log;

class GenerateKpiReports extends Command
{
    // The {type} allows the scheduler to specify which report to generate
    protected $signature = 'aurapass:generate-reports {type}';
    protected $description = 'Automatically generate and archive KPI reports to the server';

    public function handle(KpiService $kpiService)
    {
        $type = $this->argument('type');
        $this->info("Generating {$type} report...");

        try {
            // Asks the service to make the PDF and save it to the neat folder
            $pdfPath = $kpiService->generateReport($type);

            $this->info("Successfully archived {$type} report to: {$pdfPath}");
            Log::info("Automated {$type} KPI report successfully generated and saved to server.");
            
        } catch (\Exception $e) {
            Log::error("Automated {$type} report generation failed: " . $e->getMessage());
            $this->error("Failed to generate report.");
        }
    }
}