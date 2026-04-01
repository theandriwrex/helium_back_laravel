<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FreelancerProfile;
use App\Models\User;

class MetricsController extends Controller
{
    public function activeCounts()
    {
        $activeFreelancers = FreelancerProfile::query()
            ->whereHas('user', function ($q) {
                $q->where('is_active', true)
                    ->where('role_id', 2);
            })
            ->count();

        return response()->json([
            'active_counts' => [
                'freelancers' => $activeFreelancers,
                'companies' => User::where('role_id', 3)->where('is_active', true)->count(),
                'clients' => User::where('role_id', 1)->where('is_active', true)->count(),
            ],
        ]);
    }
}
