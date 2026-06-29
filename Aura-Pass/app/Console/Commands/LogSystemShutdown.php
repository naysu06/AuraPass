<?php

namespace App\Console\Commands;

use App\Services\AuditLogService;
use Illuminate\Console\Command;
use App\Events\SystemStatusLogged;

class LogSystemShutdown extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:log-system-shutdown';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Logs the system shutdown time to the audit log.';

    /**
     * Our AuditLogService instance.
     *
     * @var AuditLogService
     */
    protected $auditLogService;

    /**
     * Create a new command instance.
     *
     * @param AuditLogService $auditLogService
     * @return void
     */
    public function __construct(AuditLogService $auditLogService)
    {
        parent::__construct();
        $this->auditLogService = $auditLogService;
    }


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->auditLogService->logActivity('system.shutdown');
        
        // Broadcast the event to the UI
        event(new SystemStatusLogged('system.shutdown'));
        
        $this->info('System shutdown logged successfully.');
    }
}
