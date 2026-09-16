<?php

namespace App\Http\Controllers;

use App\Models\McpActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'token_name' => 'nullable|string|max:255',
            'page'       => 'nullable|integer|min:1',
        ]);

        $query = McpActivityLog::orderByDesc('created_at');

        if ($request->filled('token_name')) {
            $query->where('token_name', $request->input('token_name'));
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Allow the panel to log panel-local tool calls into the engine activity log.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token_name'    => 'required|string|max:255',
            'tool_name'     => 'required|string|max:255',
            'input'         => 'nullable|array',
            'status'        => 'required|in:success,error',
            'error_message' => 'nullable|string',
        ]);

        $log = McpActivityLog::create($data);

        return response()->json(['data' => $log], 201);
    }
}
