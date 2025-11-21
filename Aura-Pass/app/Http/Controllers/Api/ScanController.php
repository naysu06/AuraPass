<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessQrScan; // <--- We only need to import the Job
use Illuminate\Http\Request;

class ScanController extends Controller
{
    /**
     * Handle the incoming QR scan request.
     */
    public function store(Request $request)
    {
        // 1. Validate input
        $request->validate([
            'qrData' => 'required|string'
        ]);

        $qrData = $request->input('qrData');

        // 2. Dispatch the Job to the Queue
        // This sends the data to your worker instantly.
        // The worker will decide if it's a Check-In, Check-Out, or Error
        // and fire the appropriate event (MemberCheckedIn / MemberCheckedOut / MemberScanFailed).
        ProcessQrScan::dispatch($qrData);

        // 3. Return immediately
        // We return 'processing' because the actual result (Success/Fail) 
        // will arrive via Pusher a few milliseconds later.
        return response()->json([
            'status' => 'processing', 
            'message' => 'Scan received, processing in background...'
        ]);
    }
}