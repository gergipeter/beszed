<?php

namespace App\Console\Commands;

use App\Beszed\Content\PictogramStore;
use App\Beszed\Content\Pictures;
use App\Models\BeszedContentItem;
use Illuminate\Console\Command;

/** Downloads every ARASAAC pictogram the content uses, so the first player never waits for one. */
class BeszedPictogramsFetch extends Command
{
    protected $signature = 'beszed:pictograms-fetch';

    protected $description = 'Download the ARASAAC pictograms used by the game content';

    public function handle(): int
    {
        // The word bank's, plus any the content editor added ("arasaac:123").
        $fromEditor = BeszedContentItem::pluck('payload')
            ->flatMap(fn ($payload) => preg_match_all('/arasaac:(\d+)/', json_encode($payload), $m) ? $m[1] : [])
            ->map(fn ($id) => (int) $id);
        $ids = collect(Pictures::ids())->merge($fromEditor)->unique()->reject(fn ($id) => PictogramStore::has($id))->values();

        if ($ids->isEmpty()) {
            $this->info('All pictograms are here.');

            return self::SUCCESS;
        }

        $failed = [];
        $this->withProgressBar($ids, function (int $id) use (&$failed) {
            if (! PictogramStore::fetch($id)) {
                $failed[] = $id;
            }
        });
        $this->newLine();
        $this->info(($ids->count() - count($failed)).' downloaded.');
        if ($failed) {
            $this->warn('Not available: '.implode(', ', $failed));
        }

        return self::SUCCESS;
    }
}
