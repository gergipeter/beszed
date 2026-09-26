<?php

namespace App\Http\Controllers;

use App\Models\BeszedContentImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ContentImageController extends Controller
{
    public function __invoke(int $id): Response
    {
        $image = BeszedContentImage::find($id);
        abort_unless($image, 404);

        return Storage::disk($image->disk)->response($image->path, null, [
            'Content-Type' => $image->mime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
