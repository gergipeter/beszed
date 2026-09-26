<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BeszedContentImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentImageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $diskName = 'local';
        $path = $data['image']->store('beszed/content-images', $diskName);

        $image = BeszedContentImage::create([
            'disk' => $diskName,
            'path' => $path,
            'mime' => $data['image']->getMimeType(),
            'size' => $data['image']->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json([
            'reference' => "upload:{$image->id}",
            'url' => route('content-images.show', $image->id),
        ], 201);
    }
}
