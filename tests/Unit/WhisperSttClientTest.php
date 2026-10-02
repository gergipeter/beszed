<?php

namespace Tests\Unit;

use App\Beszed\Stt\WhisperSttClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhisperSttClientTest extends TestCase
{
    public function test_exact_sentence_scores_full_marks(): void
    {
        $r = WhisperSttClient::score('Ez egy alma.', 'ez egy alma');

        $this->assertSame(100.0, $r->accuracy);
        $this->assertSame(100.0, $r->completeness);
        $this->assertSame(1, $r->tries());
        $this->assertTrue($r->correct());
    }

    public function test_hungarian_letters_count_as_one_letter(): void
    {
        $this->assertSame(100.0, WhisperSttClient::score('őszi tűz', 'Őszi tűz!')->accuracy);
    }

    public function test_missing_words_lower_completeness(): void
    {
        $r = WhisperSttClient::score('a kutya ugat', 'kutya');

        $this->assertEqualsWithDelta(33.3, $r->completeness, 0.1);
        $this->assertSame(3, $r->tries());
    }

    public function test_silence_or_other_words_fail(): void
    {
        $this->assertFalse(WhisperSttClient::score('alma', '')->correct());
        $this->assertFalse(WhisperSttClient::score('alma', 'sajt')->correct());
    }

    public function test_it_posts_the_audio_to_the_whisper_server(): void
    {
        Http::fake(['whisper.test/*' => Http::response(['text' => 'Ez egy alma.'])]);
        $client = new WhisperSttClient([
            'url' => 'http://whisper.test', 'model' => 'm', 'language' => 'hu', 'key' => null,
            'timeout' => 5, 'audio_format' => 'webm',
        ]);

        $r = $client->assess('audio-bytes', 'ez egy alma');

        $this->assertSame(1, $r->tries());
        Http::assertSent(fn ($req) => $req->url() === 'http://whisper.test/v1/audio/transcriptions'
            && $req->isMultipart());
    }

    public function test_server_error_means_no_assessment(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);
        $client = new WhisperSttClient([
            'url' => 'http://whisper.test', 'model' => 'm', 'language' => 'hu', 'key' => null,
            'timeout' => 5, 'audio_format' => 'webm',
        ]);

        $this->assertNull($client->assess('x', 'alma'));
    }
}
