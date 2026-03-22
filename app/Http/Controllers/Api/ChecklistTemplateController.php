<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChecklistTemplateRequest;
use App\Models\ChecklistTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChecklistTemplateController extends Controller
{
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

    public function store(StoreChecklistTemplateRequest $request): JsonResponse
    {
        $template = ChecklistTemplate::create($request->validated());

        return $this->created($template, 'Checklist template created successfully');
    }
}
