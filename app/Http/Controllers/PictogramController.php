<?php

namespace App\Http\Controllers;

use App\Beszed\Content\PictogramStore;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** GET /pictograms/{id}.png: an ARASAAC pictogram, from this server's copy. */
class PictogramController extends Controller
{
    public function __invoke(int $id): BinaryFileResponse
    {
        abort_unless($id > 0 && PictogramStore::fetch($id), 404);

        return response()->file(PictogramStore::path($id), [
            'Content-Type' => 'image/png',
            // A pictogram never changes under its id.
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
