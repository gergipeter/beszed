<?php

namespace App\Http\Controllers\Beszed;

use App\Models\Child;
use Illuminate\Http\Request;

trait AuthorizesChild
{
    /** Swap for a ChildPolicy / family check if Betűvarázs already has one. */
    protected function authorizeChild(Request $request, Child $child): void
    {
        abort_unless($child->user_id === $request->user()->id, 403);
    }
}
