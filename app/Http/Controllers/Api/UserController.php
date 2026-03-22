<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name'         => $data['name'],
            'email'        => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'role'         => $data['role'] ?? 'customer',
            'password'     => bcrypt($data['password']),
        ]);

        return $this->created($user, 'User created successfully');
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return $this->success($user, 'User updated successfully');
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return $this->message('User deleted successfully');
    }
}
