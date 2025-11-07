<?php

namespace App\Http\Controllers\Api;

use App\Events\MemberScanned;
use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    /**
     * Store a new scan and broadcast the result.
     */
    public function store(Request $request)
    {
        $qrData = $request->input('qrData');
        $member = Member::where('unique_id', $qrData)->first();

        if (!$member) {
            MemberScanned::dispatch(null, 'not_found');
            return response()->json(['status' => 'not_found']);
        }

        if ($member->membership_expiry_date->isPast()) {
            MemberScanned::dispatch($member, 'expired');
            return response()->json(['status' => 'expired']);
        }

        // Success: Create the check-in and broadcast
        $member->checkIns()->create();
        MemberScanned::dispatch($member, 'active');

        return response()->json(['status' => 'active']);
    }
}