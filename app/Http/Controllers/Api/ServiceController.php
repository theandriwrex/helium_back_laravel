<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Skill;
use Illuminate\Http\Request;


class ServiceController extends Controller
{
    public function top(Request $request)
    {
        $limit = (int) $request->get('limit', 5);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 20) {
            $limit = 20;
        }

        $services = Service::with(['category', 'freelancerProfile.user'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->where('is_active', true)
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('reviews_count')
            ->limit($limit)
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'title' => $service->title,
                    'description' => $service->description,
                    'price' => $service->price,
                    'delivery_time' => $service->delivery_time,
                    'photo' => $service->photo,
                    'revisions' => $service->revisions,
                    'requirements' => $service->requirements,
                    'category' => $service->category->name,
                    'avg_rating' => round((float) ($service->reviews_avg_rating ?? 0), 2),
                    'reviews_count' => (int) ($service->reviews_count ?? 0),
                    'freelancer' => [
                        'id' => $service->freelancerProfile->user->id,
                        'user_id' => $service->freelancerProfile->user->id,
                        'profile_id' => $service->freelancerProfile->id,
                        'name' => $service->freelancerProfile->user->names . ' ' .
                                $service->freelancerProfile->user->last_names,
                        'photo' => $service->freelancerProfile->user->photo,
                        'profession' => $service->freelancerProfile->profession
                    ]
                ];
            });

        return response()->json([
            'services' => $services,
        ]);
    }
    /**
     * Listar servicios activos (público)
     */
    public function index(Request $request)
    {
        $query = Service::with(['category', 'freelancerProfile.user'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->where('is_active', true);

        if ($request->has('search')) {

            $search = $request->search;

            $query->where(function ($q) use ($search) {

                $q->where('title', 'ILIKE', "%{$search}%")
                ->orWhere('description', 'ILIKE', "%{$search}%")

                ->orWhereHas('category', function ($q2) use ($search) {
                    $q2->where('name', 'ILIKE', "%{$search}%");
                })

                ->orWhereHas('freelancerProfile', function ($q3) use ($search) {
                    $q3->where('profession', 'ILIKE', "%{$search}%")
                        ->orWhereHas('user', function ($q4) use ($search) {
                            $q4->where('names', 'ILIKE', "%{$search}%")
                                ->orWhere('last_names', 'ILIKE', "%{$search}%");
                        });
                });

        });
}
        // Filtro por categoría
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filtro por skill del freelancer
        if ($request->has('skill_id')) {
            $skill = Skill::query()->select('id', 'category_id')->find($request->skill_id);

            if (!$skill) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('category_id', $skill->category_id)
                    ->whereHas('freelancerProfile.skills', function ($q) use ($skill) {
                        $q->where('skills.id', $skill->id);
                    });
            }
        }

        // Filtro por precio mínimo
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        // Filtro por precio máximo
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        

        $services = $query->paginate(10);

        return response()->json([
            'current_page' => $services->currentPage(),
            'services' => $services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'title' => $service->title,
                    'description' => $service->description,
                    'price' => $service->price,
                    'delivery_time' => $service->delivery_time,
                    'photo' => $service->photo,
                    'revisions' => $service->revisions,
                    'requirements' => $service->requirements,
                    'category' => $service->category->name,
                    'avg_rating' => round((float) ($service->reviews_avg_rating ?? 0), 2),
                    'reviews_count' => (int) ($service->reviews_count ?? 0),

                    'freelancer' => [
                        'id' => $service->freelancerProfile->user->id,
                        'user_id' => $service->freelancerProfile->user->id,
                        'profile_id' => $service->freelancerProfile->id,
                        'name' => $service->freelancerProfile->user->names . ' ' .
                                $service->freelancerProfile->user->last_names,
                        'photo' => $service->freelancerProfile->user->photo,
                        'profession' => $service->freelancerProfile->profession
                    ]
                ];
            }),

            'pagination' => [
                'total' => $services->total(),
                'per_page' => $services->perPage(),
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
            ]
]);
    }

    /**
     * Mostrar servicio específico
     */
    public function show(Service $service)
    {
        if (!$service->is_active) {
            return response()->json(['error' => 'Servicio no disponible'], 404);
        }

        $service->load(['category', 'freelancerProfile.user']);

        return response()->json($service);
    }

    /**
     * Crear servicio (solo freelancer)
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        if ($user->role_id != 2) {
            return response()->json(['error' => 'Solo freelancers pueden crear servicios'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'delivery_time' => 'required|integer|min:1|max:365',
            'revisions' => 'required|integer|min:1|max:3',
            'requirements' => 'nullable|string|max:3000',
            'photo' => 'required|image|mimes:jpeg,png,webp|max:2048',
        ]);

        $freelancer = $user->freelancerProfile;
        $service_photo = $request->file('photo')->store('services_photos', 'public');
        $service = Service::create([
            'freelancer_id' => $freelancer->id,
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'delivery_time' => $validated['delivery_time'],
            'revisions' => $validated['revisions'],
            'requirements' => $validated['requirements'] ?? null,
            'is_active' => true,
            'photo' => $service_photo,
        ]);

        return response()->json($service, 201);
    }

    /**
     * Actualizar servicio (solo dueño)
     */
    public function update(Request $request, Service $service)
    {
        $user = auth()->user();

        if ($service->freelancerProfile->user_id != $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category_id' => 'sometimes|exists:categories,id',
            'price' => 'sometimes|numeric|min:0',
            'delivery_time' => 'sometimes|integer|min:1|max:365',
            'revisions' => 'sometimes|integer|min:1|max:3',
            'requirements' => 'nullable|string|max:3000',
            'is_active' => 'sometimes|boolean',
            'deactivation_reason' => 'nullable|string|max:2000',
            'photo' => 'nullable|image|mimes:jpeg,png,webp|max:2048',
        ]);
        if($request->hasfile('photo')){
        $validated['photo']= $request->file('photo')->store('change_photo', 'public');
        }

        if (array_key_exists('is_active', $validated) && $validated['is_active'] === true) {
            $validated['deactivation_reason'] = null;
        }

        $service->update($validated);

        return response()->json($service);
    }

    /**
     * Desactivar servicio (no borrar)
     */
    public function destroy(Service $service)
    {
        $user = auth()->user();

        if ($service->freelancerProfile->user_id != $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $service->update(['is_active' => false]);

        return response()->json(['message' => 'Servicio desactivado correctamente']);
    }
    public function myServices()
    {
        $user = auth()->user();


        if ($user->role_id != 2) {
            return response()->json(['error' => 'No autorizado'], 403);
        }
        $services = Service::with('category')
            ->where('freelancer_id', $user->freelancerProfile->id)
            ->get(); 

        return response()->json([
            'services' => $services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'title' => $service->title,
                    'price' => $service->price,
                    'is_active' => $service->is_active,
                    'deactivation_reason' => $service->deactivation_reason,
                    'photo' => $service->photo,
                    'category' => $service->category->name,
                ];
            })
        ]);
    }
}
