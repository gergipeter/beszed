<?php

namespace App\Console\Commands;

use App\Beszed\Lines;
use App\Jobs\SynthesizeSpeech;
use App\Models\BeszedContentItem;
use Illuminate\Console\Command;

/**
 * Pre-generates the fixed sentences (intros, praise, words, sentences) so the
 * first session doesn't wait on the TTS API. Dynamic prompts are cached on first use.
 */
class BeszedTtsWarm extends Command
{
    protected $signature = 'beszed:tts-warm {--sync : Run now instead of queueing}';

    protected $description = 'Pre-generate TTS audio for the Beszéd module';

    public function handle(): int
    {
        $texts = collect(Lines::all())->pluck('text')
            ->merge(config('beszed.retry'))
            ->push('Segítek! Figyelj.', 'Figyelj még egyszer!', 'Figyelj, és mondd utánam!',
                'Mondd vissza hangosan, aztán mutasd meg sorban a képeken!');

        BeszedContentItem::where('active', true)->get()->each(function ($it) use ($texts) {
            $p = $it->payload;
            foreach (['word', 'text', 'good', 'bad'] as $k) {
                if (! empty($p[$k])) {
                    $texts->push($p[$k]);
                }
            }
            foreach ($p['chunks'] ?? [] as $c) {
                $texts->push($c);
            }
        });

        $texts = $texts->filter()->unique()->values();
        $bar = $this->output->createProgressBar($texts->count());

        foreach ($texts as $t) {
            $this->option('sync') ? SynthesizeSpeech::dispatchSync($t) : SynthesizeSpeech::dispatch($t);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$texts->count()} sentences ".($this->option('sync') ? 'generated.' : 'queued.'));

        return self::SUCCESS;
    }
}
