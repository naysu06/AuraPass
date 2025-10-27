<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\CheckIn;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    public function validateMember(Request $request)
    {
        $request->validate(['unique_id' => 'required|string']);

        $member = Member::where('unique_id', $request->unique_id)->first();

        // Case 1: Member not found
        if (!$member) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Membership Not Found'
            ], 404);
        }

        // Case 2: Member found, but expired
        if ($member->membership_expiry_date < now()->toDateString()) {
            return response()->json([
                'status' => 'expired',
                'name' => $member->name,
                'message' => 'Membership Expired on ' . $member->membership_expiry_date,
            ]);
        }

        // Case 3: Success! Member is active.
        // Log the check-in
        CheckIn::create(['member_id' => $member->id]);

        return response()->json([
            'status' => 'active',
            'name' => $member->name,
            'message' => 'Check-in Successful',
        ]);
    }
}