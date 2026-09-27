<?php
/**
 * Duplicate all content items 10 times.
 * Usage: php scripts/duplicate-content.php
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BeszedContentItem;
use Illuminate\Support\Facades\DB;

$games = [
    'arnyek', 'ceruza', 'erzelmek', 'hallgasd', 'hol', 'ikerhangok',
    'kezdo', 'kirako', 'korus', 'kulonbseg', 'melyik', 'mitunt',
    'mondd', 'nagysag', 'okoska', 'papagaj', 'parkereso', 'rimelo',
    'rimparok', 'szamol', 'szotag', 'tortenet', 'utasitas', 'valogato', 'zs'
];

$totalAdded = 0;

foreach ($games as $game) {
    $items = BeszedContentItem::where('game', $game)->get();
    $countBefore = $items->count();

    foreach ($items as $item) {
        for ($i = 0; $i < 10; $i++) {
            BeszedContentItem::create([
                'game' => $item->game,
                'level' => $item->level,
                'payload' => $item->payload,
                'active' => true,
                'source' => 'api',
                'status' => $item->status,
            ]);
        }
    }

    $countAfter = BeszedContentItem::where('game', $game)->count();
    $added = $countAfter - $countBefore;
    echo sprintf("%-15s %4d → %4d items (+%d)\n", $game, $countBefore, $countAfter, $added);
    $totalAdded += $added;
}

echo "\n✅ Total added: $totalAdded items\n";
