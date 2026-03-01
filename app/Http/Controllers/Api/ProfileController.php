<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $rules = [
            'names' => 'sometimes|string|max:255',
            'last_names' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'photo' => 'nullable|image|mimes:jpeg,png,webp|max:2048',
        ];

        // freelancer
        if ($user->role_id == 2) {
            $rules['description'] = 'nullable|string';
            $rules['experience'] = 'nullable|string';
            $rules['profession'] = 'nullable|string|max:255';
            $rules['education_level'] = 'nullable|string|max:255';
        }

        // empresa
        if ($user->role_id == 3) {
            $rules['nit'] = 'sometimes|string|max:50';
            $rules['address'] = 'sometimes|string|max:255';
            $rules['website'] = 'nullable|url|max:255';
        }

        $validated = $request->validate($rules);

        return DB::transaction(function () use ($validated, $request, $user) {

            // subir foto
            if ($request->hasFile('photo')) {
                $validated['photo'] = $request->file('photo')
                    ->store('photos_profile', 'public');
            }

            // actualizar tabla users
            $user->update([
                'names' => $validated['names'] ?? $user->names,
                'last_names' => $validated['last_names'] ?? $user->last_names,
                'phone' => $validated['phone'] ?? $user->phone,
                'photo' => $validated['photo'] ?? $user->photo,
            ]);

            // freelancer
            if ($user->role_id == 2) {

                $profile = $user->freelancerProfile;

                $profile->update([
                    'description' => $validated['description'] ?? $profile->description,
                    'experience' => $validated['experience'] ?? $profile->experience,
                    'profession' => $validated['profession'] ?? $profile->profession,
                    'education_level' => $validated['education_level'] ?? $profile->education_level,
                ]);
            }

            // empresa
            if ($user->role_id == 3) {

                $company = $user->company;

                $company->update([
                    'nit' => $validated['nit'] ?? $company->nit,
                    'address' => $validated['address'] ?? $company->address,
                    'website' => $validated['website'] ?? $company->website,
                ]);
            }

            return response()->json([
                'message' => 'Perfil actualizado correctamente',
                'user' => $user->load([
                    'freelancerProfile',
                    'company'
                ])
            ]);
        });
    }
}