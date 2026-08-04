<?php

namespace App\Http\Controllers;

use App\Models\Uploads;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|max:2048',
        ]);
        $upload = Uploads::upload($validated['file'], auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully',
            'data' => [
                'id' => $upload->id,
                'url' => $upload->file_name,
            ],
        ], 201);
    }
}
