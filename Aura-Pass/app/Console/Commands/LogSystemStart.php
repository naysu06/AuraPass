<?php

namespace App\Console\Commands;

use App\Services\AuditLogService;
use Illuminate\Console\Command;
use App\Events\SystemStatusLogged;

class LogSystemStart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:log-system-start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Logs the system start time to the audit log.';

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
        $this->auditLogService->logActivity('system.started');
    
        // Broadcast the event to the UI
        event(new SystemStatusLogged('system.started'));

        $this->info('System start logged successfully.');
    }
}
