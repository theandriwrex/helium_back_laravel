<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FreelancerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Skill;

class ProfileController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user()->load([
            'role',
            'freelancerProfile.skills.category',
            'company',
        ]);

        return response()->json($user);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $rules = [
            'names' => 'sometimes|string|max:255',
            'last_names' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'photo' => 'nullable|image|mimes:jpeg,png,webp|max:2048',
        ];

        if ($user->role_id == 2) {
            $rules['description'] = 'nullable|string';
            $rules['experience'] = 'nullable|string';
            $rules['profession'] = 'nullable|string|max:255';
            $rules['education_level'] = 'nullable|string|max:255';
        }

        if ($user->role_id == 3) {
            $rules['nit'] = 'sometimes|string|max:50|unique:companies,nit,' . optional($user->company)->id;
            $rules['address'] = 'sometimes|string|max:255';
            $rules['website'] = 'nullable|url|max:255';
        }

        $validated = $request->validate($rules);

        return DB::transaction(function () use ($validated, $request, $user) {
            if ($request->hasFile('photo')) {
                $validated['photo'] = $request->file('photo')
                    ->store('photos_profile', 'public');
            }

            $user->update([
                'names' => $validated['names'] ?? $user->names,
                'last_names' => $validated['last_names'] ?? $user->last_names,
                'phone' => $validated['phone'] ?? $user->phone,
                'photo' => $validated['photo'] ?? $user->photo,
            ]);

            if ($user->role_id == 2) {
                $profile = $user->freelancerProfile()->firstOrCreate(
                    ['user_id' => $user->id],
                    ['profession' => 'Sin definir']
                );

                $profile->update([
                    'description' => $validated['description'] ?? $profile->description,
                    'experience' => $validated['experience'] ?? $profile->experience,
                    'profession' => $validated['profession'] ?? $profile->profession,
                    'education_level' => $validated['education_level'] ?? $profile->education_level,
                ]);
            }

            if ($user->role_id == 3) {
                $company = $user->company;

                if (!$company && (!isset($validated['nit']) || !isset($validated['address']))) {
                    return response()->json([
                        'error' => 'Para crear perfil de empresa se requieren nit y address',
                    ], 422);
                }

                if (!$company) {
                    $company = $user->company()->create([
                        'nit' => $validated['nit'],
                        'address' => $validated['address'],
                        'website' => $validated['website'] ?? null,
                    ]);
                }

                $company->update([
                    'nit' => $validated['nit'] ?? $company->nit,
                    'address' => $validated['address'] ?? $company->address,
                    'website' => $validated['website'] ?? $company->website,
                ]);
            }

            return response()->json([
                'message' => 'Perfil actualizado correctamente',
                'user' => $user->load([
                    'role',
                    'freelancerProfile.skills.category',
                    'company',
                ]),
            ]);
        });
    }

    public function updateSkills(Request $request)
    {
        $user = $request->user();

        if ($user->role_id != 2) {
            return response()->json([
                'error' => 'Solo freelancers pueden actualizar skills',
            ], 403);
        }

        $validated = $request->validate([
            'skill_ids' => 'required|array|min:1',
            'skill_ids.*' => 'integer|exists:skills,id',
        ]);

        $profile = $user->freelancerProfile()->firstOrCreate(
            ['user_id' => $user->id],
            ['profession' => 'Sin definir']
        );

        $profile->skills()->sync($validated['skill_ids']);

        return response()->json([
            'message' => 'Skills actualizadas correctamente',
            'freelancer_profile' => $profile->load('skills.category'),
        ]);
    }

    public function showFreelancer(FreelancerProfile $freelancerProfile)
    {
        $freelancerProfile->load([
            'user:id,names,last_names,photo',
            'skills:id,name,category_id',
            'skills.category:id,name',
        ]);

        $services = $freelancerProfile->services()
            ->where('is_active', true)
            ->with('category:id,name')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->select('id', 'freelancer_id', 'category_id', 'title', 'description', 'price', 'photo', 'is_active', 'created_at')
            ->get();

        $ratedServices = $services->where('reviews_count', '>', 0);
        $avgServiceRating = $ratedServices->isNotEmpty()
            ? round((float) $ratedServices->avg(function ($service) {
                return (float) $service->reviews_avg_rating;
            }), 2)
            : 0.0;

        return response()->json([
            'freelancer_profile' => $freelancerProfile,
            'services' => $services,
            'avg_service_rating' => $avgServiceRating,
        ]);
    }

    public function topFreelancers(Request $request)
    {
        $limit = (int) $request->get('limit', 5);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 20) {
            $limit = 20;
        }

        $profiles = FreelancerProfile::query()
            ->with('user:id,names,last_names,photo')
            ->with(['services' => function ($query) {
                $query->where('is_active', true)
                    ->withAvg('reviews', 'rating')
                    ->withCount('reviews')
                    ->select('id', 'freelancer_id', 'title');
            }])
            ->get();

        $ranked = $profiles->map(function ($profile) {
            $ratedServices = $profile->services->where('reviews_count', '>', 0);
            $avgServiceRating = $ratedServices->isNotEmpty()
                ? round((float) $ratedServices->avg(function ($service) {
                    return (float) $service->reviews_avg_rating;
                }), 2)
                : 0.0;

            return [
                'freelancer_profile_id' => $profile->id,
                'user' => $profile->user,
                'avg_service_rating' => $avgServiceRating,
                'services_count' => $profile->services->count(),
                'reviews_count' => (int) $profile->services->sum('reviews_count'),
            ];
        })->sortByDesc(function ($item) {
            return [$item['avg_service_rating'], $item['reviews_count']];
        })->values()->take($limit);

        return response()->json($ranked);
    }

    public function listFreelancers(Request $request)
    {
        $perPage = 10;

        $query = FreelancerProfile::query()
            ->with('user:id,names,last_names,photo')
            ->withCount(['services' => function ($q) {
                $q->where('is_active', true);
            }])
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('names', 'ILIKE', "%{$search}%")
                    ->orWhere('last_names', 'ILIKE', "%{$search}%");
            })->orWhere('profession', 'ILIKE', "%{$search}%");
        }

        if ($request->filled('category_id')) {
            $categoryId = (int) $request->category_id;
            $query->whereHas('services', function ($q) use ($categoryId) {
                $q->where('is_active', true)
                    ->where('category_id', $categoryId);
            });
        }

        return response()->json($query->paginate($perPage));
    }

    public function showSkills(){
        $skills = Skill::select('id', 'name')
        ->orderBy('name', 'asc')
        ->get();
        return response()->json($skills);
    }
    
}
