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
    /**
     * @OA\Get(
     *     path="/api/v1/users",
     *     summary="List users (admin only)",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="role", in="query", required=false, @OA\Schema(type="string", enum={"admin","customer","driver","partner_admin"})),
     *     @OA\Response(response=200, description="Paginated users",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/User")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta"),
     *             @OA\Property(property="links", ref="#/components/schemas/PaginationLinks")
     *         )
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            return $this->error('Forbidden', 403);
        }

        $query = User::query();

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        return $this->success($query->orderBy('id', 'desc')->paginate(20));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/users/{id}",
     *     summary="Get user by ID",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User details",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/User"))
     *     ),
     *     @OA\Response(response=404, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function show(Request $request, User $user): JsonResponse
    {
        if (!$request->user()?->isAdmin() && (int) $request->user()->id !== (int) $user->id) {
            return $this->error('Forbidden', 403);
        }

        return $this->success($user);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users",
     *     summary="Create a user (admin only)",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"name","email","password"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="phone_number", type="string", nullable=true),
     *             @OA\Property(property="role", type="string", enum={"admin","customer","driver","partner_admin"}, example="customer")
     *         )
     *     ),
     *     @OA\Response(response=201, description="User created",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/User"))
     *     ),
     *     @OA\Response(response=422, ref="#/components/schemas/ErrorResponse")
     * )
     */
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

    /**
     * @OA\Patch(
     *     path="/api/v1/users/{id}",
     *     summary="Update a user",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="phone_number", type="string")
     *     )),
     *     @OA\Response(response=200, description="User updated",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/User"))
     *     ),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        if (!$request->user()?->isAdmin() && (int) $request->user()->id !== (int) $user->id) {
            return $this->error('Forbidden', 403);
        }

        $user->update($request->validated());

        return $this->success($user, 'User updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/users/{id}",
     *     summary="Delete a user (admin only)",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User deleted"),
     *     @OA\Response(response=403, ref="#/components/schemas/ErrorResponse")
     * )
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            return $this->error('Forbidden', 403);
        }

        $user->delete();

        return $this->message('User deleted successfully');
    }
}
