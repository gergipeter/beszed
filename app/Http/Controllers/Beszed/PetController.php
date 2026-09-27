<?php

namespace App\Http\Controllers\Beszed;

use App\Http\Controllers\Controller;
use App\Models\BeszedPet;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetController extends Controller
{
    use AuthorizesChild;

    /**
     * Get or create pet for a child
     */
    public function show(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $pet = $child->pets()->where('is_alive', true)->latest()->first();

        if (!$pet) {
            return response()->json(['pet' => null, 'message' => 'No active pet']);
        }

        // Apply time decay
        $pet->tick();

        return response()->json([
            'pet' => $this->format($pet),
            'stats' => $this->getStats($pet),
        ]);
    }

    /**
     * Create a new pet
     */
    public function store(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'species' => ['required', 'in:bunny,cat,dragon,unicorn,fox'],
        ]);

        // Check if child already has an active pet
        if ($child->pets()->where('is_alive', true)->exists()) {
            return response()->json(['error' => 'Child already has an active pet'], 422);
        }

        $pet = BeszedPet::create($data + [
            'child_id' => $child->id,
            'born_at' => now(),
            'last_fed_at' => now(),
            'last_played_at' => now(),
            'last_cleaned_at' => now(),
        ]);

        return response()->json([
            'pet' => $this->format($pet),
            'stats' => $this->getStats($pet),
        ], 201);
    }

    /**
     * Feed the pet
     */
    public function feed(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $pet = $child->pets()->where('is_alive', true)->latest()->firstOrFail();
        $data = $request->validate(['type' => ['required', 'in:standard,nutritious,treat']]);

        $result = $pet->feed($data['type']);

        if (isset($result['error'])) {
            return response()->json($result, 422);
        }

        return response()->json([
            'pet' => $this->format($pet),
            'stats' => $this->getStats($pet),
            'action' => 'fed',
        ]);
    }

    /**
     * Play with the pet
     */
    public function play(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $pet = $child->pets()->where('is_alive', true)->latest()->firstOrFail();
        $result = $pet->play();

        if (isset($result['error'])) {
            return response()->json($result, 422);
        }

        $leveledUp = $pet->checkLevelUp();

        return response()->json([
            'pet' => $this->format($pet),
            'stats' => $this->getStats($pet),
            'action' => 'played',
            'level_up' => $leveledUp,
        ]);
    }

    /**
     * Clean the pet
     */
    public function clean(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $pet = $child->pets()->where('is_alive', true)->latest()->firstOrFail();
        $result = $pet->clean();

        if (isset($result['error'])) {
            return response()->json($result, 422);
        }

        return response()->json([
            'pet' => $this->format($pet),
            'stats' => $this->getStats($pet),
            'action' => 'cleaned',
        ]);
    }

    /**
     * Pet sleep
     */
    public function sleep(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $pet = $child->pets()->where('is_alive', true)->latest()->firstOrFail();
        $result = $pet->sleep();

        if (isset($result['error'])) {
            return response()->json($result, 422);
        }

        return response()->json([
            'pet' => $this->format($pet),
            'stats' => $this->getStats($pet),
            'action' => 'sleeping',
        ]);
    }

    /**
     * Get pet history/stats
     */
    public function history(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $pets = $child->pets()->latest()->get();

        return response()->json([
            'pets' => $pets->map(fn ($p) => $this->format($p)),
            'total' => $pets->count(),
            'alive' => $pets->where('is_alive', true)->count(),
        ]);
    }

    private function format(BeszedPet $pet): array
    {
        return [
            'id' => $pet->id,
            'name' => $pet->name,
            'species' => $pet->species,
            'level' => $pet->level,
            'generation' => $pet->generation,
            'stage' => $pet->stage,
            'is_alive' => $pet->is_alive,
            'death_reason' => $pet->death_reason,
            'born_at' => $pet->born_at,
        ];
    }

    private function getStats(BeszedPet $pet): array
    {
        return [
            'hunger' => $pet->hunger,
            'happiness' => $pet->happiness,
            'energy' => $pet->energy,
            'hygiene' => $pet->hygiene,
            'health' => $pet->health,
            'experience' => $pet->experience,
        ];
    }
}
