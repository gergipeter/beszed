<?php

namespace Database\Seeders;

use App\Models\BeszedContentItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Loads database/seeders/data/beszed/{game}.json. Safe to run on every start:
 * new items are added, changed ones updated, removed ones deactivated (answers
 * keep pointing at them), and anything edited in the content editor is left alone.
 */
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

            $added = DB::transaction(function () use ($game, $rows) {
                $existing = BeszedContentItem::where('game', $game)->where('source', 'seed')->get()->keyBy('seed_key');
                $keep = [];
                $added = 0;

                foreach ($rows as $row) {
                    $key = BeszedContentItem::seedKey($row['payload']);
                    $keep[] = $key;
                    $item = $existing[$key] ?? new BeszedContentItem(['game' => $game, 'source' => 'seed', 'seed_key' => $key]);
                    if ($item->edited_at) {
                        continue; // the editor wins
                    }
                    $added += (int) ! $item->exists;
                    $item->fill(['level' => $row['level'] ?? 1, 'payload' => $row['payload'], 'active' => true])->save();
                }

                BeszedContentItem::where('game', $game)->where('source', 'seed')->whereNull('edited_at')
                    ->whereNotIn('seed_key', $keep)->update(['active' => false]);

                return $added;
            });

            $this->command?->info(sprintf('%-10s %3d items (%d new)', $game, count($rows), $added));
        }
    }
}
