<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Juan Dela Cruz"),
 *     @OA\Property(property="email", type="string", example="juan@example.com"),
 *     @OA\Property(property="phone_number", type="string", example="09123456789"),
 *     @OA\Property(property="role", type="string", example="customer"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        return $this->success($query->orderBy('id', 'desc')->paginate(20));
    }

    public function show(User $user): JsonResponse
    {
        return $this->success($user);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'role'         => ['nullable', 'string'],
            'password'     => ['required', 'string', 'min:6'],
        ]);

        $user = User::create([
            'name'         => $data['name'],
            'email'        => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'role'         => $data['role'] ?? 'customer',
            'password'     => bcrypt($data['password']),
        ]);

        return $this->created($user, 'User created successfully');
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name'         => ['sometimes', 'required', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'role'         => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $user->update($data);

        return $this->success($user, 'User updated successfully');
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return $this->message('User deleted successfully');
    }
}
