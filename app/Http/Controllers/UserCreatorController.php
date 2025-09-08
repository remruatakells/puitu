<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class UserCreatorController extends Controller
{
    // POST /v1/users  (create user and optional creator profile)
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'id'            => 'required|string|max:122|unique:user,id',
                'name'          => 'required|string|max:15',
                'phone'         => 'required|integer',
                'dob'           => 'nullable|string|max:122',
                'country_code'  => 'nullable|string',
                'country'       => 'nullable|string|max:122',
                'state'         => 'nullable|string|max:122',
                'district'      => 'nullable|string|max:122',
                'town'          => 'nullable|string|max:122',
                'profile_image' => 'nullable|string',

                'creator'                        => 'nullable|array',
                'creator.marital_status'         => 'nullable|in:single,married,divorced,widowed',
                'creator.occupation'             => 'nullable|string|max:120',
                'creator.religion'               => 'nullable|string|max:80',
                'creator.total_years_experience' => 'nullable|numeric|min:0|max:999.9',
            ]);

            $user = User::create($validated);

            if (!empty($validated['creator'])) {
                $user->creatorProfile()->create($validated['creator']);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'User created successfully',
                'data'    => $user->load('creatorProfile'),
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create user',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // GET /v1/users/{id}
    public function show($id)
    {
        try {
            $user = User::with('creatorProfile')->findOrFail($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'User retrieved successfully',
                'data'    => $user,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found',
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve user',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // PUT /v1/users/{id}
    public function update(Request $request, $id)
    {
        try {
            $user = User::with('creatorProfile')->findOrFail($id);

            $validated = $request->validate([
                'name'          => 'sometimes|string|max:15',
                'phone'         => 'sometimes|integer',
                'dob'           => 'sometimes|string|max:122',
                'country_code'  => 'sometimes|string',
                'country'       => 'sometimes|string|max:122',
                'state'         => 'sometimes|string|max:122',
                'district'      => 'sometimes|string|max:122',
                'town'          => 'sometimes|string|max:122',
                'profile_image' => 'sometimes|string',

                'creator'                        => 'nullable|array',
                'creator.marital_status'         => 'nullable|in:single,married,divorced,widowed',
                'creator.occupation'             => 'nullable|string|max:120',
                'creator.religion'               => 'nullable|string|max:80',
                'creator.total_years_experience' => 'nullable|numeric|min:0|max:999.9',
            ]);

            unset($validated['id']); // prevent PK overwrite
            $user->update($validated);

            if (array_key_exists('creator', $validated)) {
                if ($user->creatorProfile) {
                    $user->creatorProfile->update($validated['creator'] ?? []);
                } elseif (!empty($validated['creator'])) {
                    $user->creatorProfile()->create($validated['creator']);
                }
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'User updated successfully',
                'data'    => $user->fresh('creatorProfile'),
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found',
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update user',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // DELETE /v1/users/{id}/creator
    public function destroyCreator($id)
    {
        try {
            $user = User::with('creatorProfile')->findOrFail($id);

            if ($user->creatorProfile) {
                $user->creatorProfile->delete();

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Creator profile deleted successfully',
                    'data'    => $user,
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'Creator profile not found for this user',
            ], 404);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found',
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete creator profile',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
