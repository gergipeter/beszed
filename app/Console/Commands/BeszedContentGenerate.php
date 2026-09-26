<?php

namespace App\Console\Commands;

use App\Beszed\Content\ContentGenerator;
use Illuminate\Console\Command;

/** Rebuilds the games' seed files from the word bank (database/lexicon/hu.json). */
class BeszedContentGenerate extends Command
{
    protected $signature = 'beszed:content-generate {--dry-run : Only show what would change}';

    protected $description = 'Generate game content from the Hungarian word bank into database/seeders/data/beszed';

    public function handle(): int
    {
        $generator = ContentGenerator::fromFiles();
        $games = $generator->build();

        $this->table(['Game', 'Hand-written', 'Generated', 'Total'], collect($games)->map(fn ($rows, $game) => [
            $game,
            collect($rows)->where('gen', '!=', true)->count(),
            collect($rows)->where('gen', true)->count(),
            count($rows),
        ])->values());

        foreach ($generator->rejected as $game => $rows) {
            $this->warn("$game: ".count($rows).' word(s) left out by the content rules');
            foreach ($rows as $row) {
                $this->line("  $row");
            }
        }

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        foreach ($games as $game => $rows) {
            $lines = array_map(fn ($row) => '  '.json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $rows);
            file_put_contents(database_path("seeders/data/beszed/$game.json"), "[\n".implode(",\n", $lines)."\n]\n");
        }
        $this->info('Written. Load it with: php artisan db:seed --class=BeszedContentSeeder');

        return self::SUCCESS;
    }
}
