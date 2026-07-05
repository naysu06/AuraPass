<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\App;

class AuditLogService
{
    public function logActivity(string $activity, ?Model $model = null, array $details = [], $forceUserId = null)
    {
        $isConsole = App::runningInConsole();

        // SAFETY NET: If $model is somehow an array or collection due to a calling mixup,
        // merge it into details and set the model back to null to prevent database corruption.
        if ($model !== null && !($model instanceof Model)) {
            $details = array_merge($details, is_array($model) ? $model : ['raw_data' => (string)$model]);
            $model = null;
        }

        // --- IMMUTABLE OPERATOR STAMP ---
        // Grab the currently authenticated admin
        $activeAdmin = Auth::user();

        // Force the operator's name into the JSON payload as a permanent failsafe.
        // We only apply this if an observer hasn't already manually set it.
        if (!isset($details['operator_name'])) {
            $details['operator_name'] = $activeAdmin 
                ? ($activeAdmin->username ?? $activeAdmin->name ?? 'System') 
                : 'System';
        }
        // --------------------------------

        $logData = [
            'user_id'    => $forceUserId ?? Auth::id(),
            'activity'   => $activity,
            'details'    => $details, // The safely stamped array is now passed here
            'ip_address' => $isConsole ? '127.0.0.1' : Request::ip(),
            'user_agent' => $isConsole ? 'Windows CLI (System Event)' : Request::header('user-agent'),
        ];

        if ($model instanceof Model) {
            $logData['loggable_id'] = $model->id;
            $logData['loggable_type'] = get_class($model);
        }

        AuditLog::create($logData);
    }
}