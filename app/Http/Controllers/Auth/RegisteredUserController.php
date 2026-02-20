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
use Illuminate\Support\Facades\DB;

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
            $rules['description'] = 'nullable|string';
            $rules['experience'] = 'nullable|string|max:255';
            $rules['profession'] = 'nullable|string|max:255';
            $rules['education_level'] = 'nullable|string|max:255';
            $rules['strikes'] = 'nullable|integer|min:0';
        }

        // Empresa
        if ($request->role_id == 3) {
            $rules['nit'] = 'required|string|max:50|unique:companies,nit';
            $rules['address'] = 'required|string|max:255';
            $rules['website'] = 'nullable|url|max:255';
        }


        $validated = $request->validate($rules);

        return DB::transaction(function () use ($validated) {
            
            $user = User::create([
                'names' => $validated['names'],
                'last_names' => $validated['last_names'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'role_id' => $validated['role_id'],
            ]);

            if ($user->role_id == 2) {
                \App\Models\FreelancerProfile::create([
                    'user_id' => $user->id,
                    'description' => $validated['description'] ?? null,
                    'experience' => $validated['experience'] ?? null,
                    'profession' => $validated['profession'] ?? null,
                    'education_level' => $validated['education_level'] ?? null,
                    'strikes' => $validated['strikes'] ?? 0,
                ]);
            }

            if ($user->role_id == 3) {
                \App\Models\Company::create([
                    'user_id' => $user->id,
                    'nit' => $validated['nit'],
                    'address' => $validated['address'],
                    'website' => $validated['website'] ?? null,
                ]);

            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Usuario registrado correctamente',
                'user' => $user->load('role'),
                'token' => $token
            ], 201);
        });
    }

}
