<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Listar servicios activos (público)
     */
    public function index(Request $request)
    {
        $query = Service::with(['category', 'freelancerProfile.user'])
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
                    'category' => $service->category->name,

                    'freelancer' => [
                        'id' => $service->freelancerProfile->user->id,
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

        $service->load(['category', 'freelancer.user']);

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
        ]);

        $freelancer = $user->freelancerProfile;

        $service = Service::create([
            'freelancer_id' => $freelancer->id,
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'is_active' => true,
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
            'is_active' => 'sometimes|boolean'
        ]);

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
}