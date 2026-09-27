<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Beszed\AuthorizesChild;
use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/** A parent's children: the players. */
class ChildController extends Controller
{
    use AuthorizesChild;

    public function index(Request $request): JsonResponse
    {
        return response()->json(['children' => self::present($request->user()->children()->orderBy('id')->get())]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->children()->count() >= 10, 422, 'Legfeljebb 10 gyereket adhatsz hozzá.');
        $child = $request->user()->children()->create($this->validated($request));

        return response()->json(['child' => self::one($child)], 201);
    }

    public function update(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);
        $child->update($this->validated($request));

        return response()->json(['child' => self::one($child)]);
    }

    /** Deletes the child and everything recorded about them (attempts, levels, stickers…). */
    public function destroy(Request $request, Child $child): Response
    {
        $this->authorizeChild($request, $child);
        $child->delete();

        return response()->noContent();
    }

    public static function present(Collection $children): array
    {
        return $children->map(fn (Child $c) => self::one($c))->all();
    }

    private static function one(Child $child): array
    {
        return [
            'id' => $child->id,
            'name' => $child->name,
            // the óvodai jel: a picture the child finds themselves by (config beszed.signs)
            'sign' => $child->sign,
            'sign_emoji' => config("beszed.signs.{$child->sign}.emoji"),
            'birth_date' => $child->birth_date?->toDateString(),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'sign' => ['nullable', Rule::in(array_keys(config('beszed.signs')))],
            'birth_date' => ['nullable', 'date', 'before:today', 'after:2010-01-01'],
        ]);
    }
}
