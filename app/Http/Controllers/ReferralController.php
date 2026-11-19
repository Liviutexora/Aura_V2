<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $counts = $user->sponsoredUsers()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $referrals = $user->sponsoredUsers()->latest('created_at')->latest('id')
            ->paginate(12, ['name', 'created_at', 'status'], total: (int) $counts->sum());

        return view('referrals.index', [
            'demoRoute' => 'referrals',
            'referrals' => $referrals,
            'activeCount' => (int) $counts->get('active', 0),
            'referralUrl' => preg_match(User::REFERRAL_CODE_PATTERN, $user->referral_code)
                ? route('aura.register', ['ref' => $user->referral_code])
                : null,
        ]);
    }
}
