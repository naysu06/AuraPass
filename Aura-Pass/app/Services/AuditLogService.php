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

        $logData = [
            'user_id'    => $forceUserId ?? Auth::id(),
            'activity'   => $activity,
            'details'    => $details,
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