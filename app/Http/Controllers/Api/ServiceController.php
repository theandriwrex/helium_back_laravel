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
        $query = Service::with(['category', 'freelancer.user'])
            ->where('is_active', true);

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

        return response()->json($query->paginate(10));
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