<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistTemplate;
use Illuminate\Http\Request;

class ChecklistTemplateController extends Controller
{
    /**
     * GET /api/v1/checklist-templates
     */
    public function index(Request $request)
    {
        $query = ChecklistTemplate::where('is_active', true);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return response()->json($query->get());
    }

    /**
     * POST /api/v1/checklist-templates
     * (Admin only ideally)
     */
    public function store(Request $request)
    {
        // Simple auth check or role check
        // if (!$request->user()->isAdmin()) ...

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'category'      => ['nullable', 'string', 'max:100'],
            'default_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $template = ChecklistTemplate::create($data);

        return response()->json($template, 201);
    }
}
