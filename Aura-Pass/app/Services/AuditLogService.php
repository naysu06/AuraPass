<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\App;

class AuditLogService
{
    public function logActivity(string $activity, ?Model $model = null, array $details = [])
    {
        // Detect if running via Windows Command Line / Task Scheduler
        $isConsole = App::runningInConsole();

        $logData = [
            // Will naturally be null on system boot
            // Use forced ID if provided, otherwise fallback to Auth::id()
            'user_id'    => $forceUserId ?? Auth::id(),
            'activity'   => $activity,
            'details'    => $details,
            'ip_address' => $isConsole ? '127.0.0.1' : Request::ip(),
            'user_agent' => $isConsole ? 'Windows CLI (System Event)' : Request::header('user-agent'),
        ];

        if ($model) {
            $logData['loggable_id'] = $model->id;
            $logData['loggable_type'] = get_class($model);
        }

        AuditLog::create($logData);
    }
}