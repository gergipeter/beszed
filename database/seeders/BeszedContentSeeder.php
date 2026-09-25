<?php

namespace Database\Seeders;

use App\Models\BeszedContentItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Loads database/seeders/data/beszed/{game}.json. Re-running replaces a game's content. */
class BeszedContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_keys(config('beszed.games')) as $game) {
            $file = database_path("seeders/data/beszed/{$game}.json");
            if (! is_file($file)) {
                $this->command?->warn("Missing $file");

                continue;
            }

            $rows = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

            DB::transaction(function () use ($game, $rows) {
                BeszedContentItem::where('game', $game)->delete();
                foreach ($rows as $row) {
                    BeszedContentItem::create(['game' => $game, 'level' => $row['level'] ?? 1, 'payload' => $row['payload']]);
                }
            });

            $this->command?->info(sprintf('%-8s %3d items', $game, count($rows)));
        }
    }
}
