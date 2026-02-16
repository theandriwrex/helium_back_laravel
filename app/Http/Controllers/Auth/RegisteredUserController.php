<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $rules = [
            'names' => 'required|string|max:255',
            'last_names' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
        ];

        // Freelancer
        if ($request->role_id == 2) {
            $rules['category_id'] = 'required|exists:categories,id';
            $rules['description'] = 'nullable|string';
            $rules['hourly_rate'] = 'nullable|numeric|min:0';
            $rules['skills'] = 'required|array';
            $rules['skills.*'] = 'exists:skills,id';
        }

        // Empresa
        if ($request->role_id == 3) {
            $rules['company_name'] = 'required|string|max:255';
            $rules['company_description'] = 'nullable|string';
        }

        $validated = $request->validate($rules);

        // Crear usuario
        $user = User::create([
            'names' => $validated['names'],
            'last_names' => $validated['last_names'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
        ]);

        // Si es freelancer
        if ($user->role_id == 2) {

            $freelancer = \App\Models\FreelancerProfile::create([
                'user_id' => $user->id,
                'category_id' => $validated['category_id'],
                'description' => $validated['description'] ?? null,
                'hourly_rate' => $validated['hourly_rate'] ?? null,
            ]);

            // Guardar skills en freelancer_skills
            foreach ($validated['skills'] as $skillId) {
                \App\Models\FreelancerSkill::create([
                    'freelancer_profile_id' => $freelancer->id,
                    'skill_id' => $skillId,
                ]);
            }
        }

        // Si es empresa
        if ($user->role_id == 3) {

            \App\Models\Company::create([
                'user_id' => $user->id,
                'name' => $validated['company_name'],
                'description' => $validated['company_description'] ?? null,
            ]);
        }

        // Crear token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Usuario registrado correctamente',
            'user' => $user->load('role'),
            'token' => $token
        ], 201);
    }

}
