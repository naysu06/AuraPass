<?php

namespace App\Http\Controllers\Api;

use App\Events\MemberScanned;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\CheckIn;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function store(Request $request)
    {
        $qrData = $request->input('qrData');
        $member = Member::where('unique_id', $qrData)->first();

        // 1. Handle Member Not Found
        if (!$member) {
            MemberScanned::dispatch(null, 'not_found');
            return response()->json(['status' => 'not_found']);
        }

        // 2. Handle Expired Membership
        // We check isPast() but arguably we should check if it's BEFORE today 
        // to allow them to enter on the very last day of expiry.
        if ($member->membership_expiry_date < now()->startOfDay()) {
            MemberScanned::dispatch($member, 'expired');
            return response()->json(['status' => 'expired']);
        }

        // 3. THE TOGGLE LOGIC (Check In vs Check Out)
        
        // Look for an "Active Session" (Created in last 12 hours, NO check_out_time yet)
        $activeSession = $member->checkIns()
            ->whereNull('check_out_at')
            ->where('created_at', '>=', now()->subHours(12)) 
            ->latest()
            ->first();

        if ($activeSession) {
            // --- SCENARIO: THEY ARE LEAVING (Check Out) ---

            // A. Double Scan Protection (Debounce)
            // If they scan again within 2 minutes of entering, ignore it.
            if ($activeSession->created_at->diffInMinutes(now()) < 2) {
                 return response()->json(['status' => 'ignored', 'message' => 'Just scanned']);
            }

            // B. Close the session
            $activeSession->update([
                'check_out_at' => now(),
            ]);

            // C. Dispatch "checked_out" status so frontend shows "Goodbye!"
            MemberScanned::dispatch($member, 'checked_out');
            return response()->json(['status' => 'checked_out']);

        } else {
            // --- SCENARIO: THEY ARE ENTERING (Check In) ---

            // A. Create new session
            $member->checkIns()->create([
                // 'created_at' is set automatically (Check In Time)
                // 'check_out_at' is NULL automatically
            ]);

            // B. Dispatch "checked_in" status so frontend shows "Welcome!"
            MemberScanned::dispatch($member, 'checked_in');
            return response()->json(['status' => 'checked_in']);
        }
    }
}