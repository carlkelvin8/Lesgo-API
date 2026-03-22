<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChecklistTemplateRequest;
use App\Models\ChecklistTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChecklistTemplateController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/checklist-templates",
     *     summary="List active checklist templates",
     *     tags={"Checklist"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="List of checklist templates")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = ChecklistTemplate::where('is_active', true);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return $this->success($query->get());
    }

    /**
     * @OA\Post(
     *     path="/api/v1/checklist-templates",
     *     summary="Create a checklist template (admin only)",
     *     tags={"Checklist"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name"},
     *         @OA\Property(property="name", type="string", example="Grocery List"),
     *         @OA\Property(property="category", type="string", example="lesbuy"),
     *         @OA\Property(property="items", type="array", @OA\Items(type="string"))
     *     )),
     *     @OA\Response(response=201, description="Template created")
     * )
     */
    public function store(StoreChecklistTemplateRequest $request): JsonResponse
    {
        $template = ChecklistTemplate::create($request->validated());

        return $this->created($template, 'Checklist template created successfully');
    }
}
