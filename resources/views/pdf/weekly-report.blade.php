@php
    use App\Beszed\Reports\Twemoji;
    /** @var array $r  App\Beszed\Reports\WeeklyReport::for() */
    $bandColor = ['strong' => '#2f9e5b', 'growing' => '#f5b02e', 'practice' => '#ff6f61', 'noData' => '#c8bedb'];
    $trend = ['up' => ['▲', '#2f9e5b', 'jobb, mint múlt héten'], 'down' => ['▼', '#ff6f61', 'kicsit gyengébb'], 'flat' => ['■', '#6b5a77', 'mint múlt héten']];
    $soundKind = ['start' => 'kezdőhang', 'contrast' => 'hangpár', 'rhyme' => 'rím'];
    $name = $r['child']['name'];
@endphp
<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 22px 28px; }
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { color: #3b1f4a; font-size: 11pt; }
    h1 { margin: 0; font-size: 22pt; color: #ffffff; }
    h2 { margin: 11px 0 6px; font-size: 12.5pt; }
    .head { background: #7cc8ff; border-radius: 18px; padding: 14px 20px; }
    .kicker { margin: 0 0 4px; font-size: 9pt; font-weight: bold; color: #ffffff; letter-spacing: 1px; text-transform: uppercase; }
    .sub { margin: 4px 0 0; font-size: 10.5pt; color: #ffffff; }
    .stats { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 10px -8px 0; }
    .stat { background: #eaf7ff; border-radius: 14px; padding: 7px 4px; text-align: center; }
    .stat b { display: block; font-size: 20pt; line-height: 1.1; }
    .stat small { color: #6b5a77; font-size: 9pt; }
    .words { font-size: 10.5pt; line-height: 1.45; margin: 0; }
    .area { width: 100%; margin: 0 0 5px; }
    .area td { padding: 0; }
    .area-name { font-weight: bold; font-size: 10.5pt; }
    .kind { font-weight: normal; font-size: 8.5pt; color: #6b5a77; }
    .band { text-align: right; font-weight: bold; font-size: 9.5pt; }
    .track { width: 100%; height: 9px; background: #f1eef6; border-radius: 6px; margin-top: 4px; }
    .fill { height: 9px; border-radius: 6px; }
    .note { color: #6b5a77; font-size: 8.5pt; margin-top: 3px; }
    .games { width: 100%; border-collapse: collapse; }
    .games td { padding: 3px 0; border-bottom: 1px solid #efe9f5; font-size: 10.5pt; }
    .games .right { text-align: right; color: #6b5a77; font-size: 9pt; }
    .sticker { display: inline-block; margin: 0 6px 6px 0; padding: 3px 10px; background: #fff3c4; border-radius: 12px; font-size: 10pt; }
    .tip { background: #eefaf2; border-radius: 14px; padding: 10px 14px; }
    .tip p { margin: 3px 0; font-size: 10.5pt; line-height: 1.4; }
    .foot { margin-top: 8px; color: #6b5a77; font-size: 8pt; line-height: 1.45; }
    .two { width: 100%; }
    .two td { vertical-align: top; }
</style>
</head>
<body>
    <div class="head">
        <p class="kicker">{!! Twemoji::img('🦄', 14) !!} Csillám heti beszámolója · {{ $r['period'] }}</p>
        <h1>{{ $name }} @if ($r['child']['sign']){!! Twemoji::img($r['child']['sign'], 26) !!}@endif</h1>
        <p class="sub">{{ $r['level']['number'] }}. szint · {{ $r['streak'] }} napos sorozat @if ($r['child']['age'])· {{ $r['child']['age'] }}@endif</p>
    </div>

    <table class="stats"><tr>
        @foreach ([['🎮', $r['totals']['games'], 'játék'], ['⏱️', $r['totals']['minutes'], 'perc'], ['⭐', $r['totals']['stars'], 'csillag'], ['📅', $r['totals']['days'], 'játéknap']] as [$icon, $value, $label])
            <td width="25%"><div class="stat">{!! Twemoji::img($icon, 18) !!}<b>{{ $value }}</b><small>{{ $label }}</small></div></td>
        @endforeach
    </tr></table>

    <h2>Így ment a héten</h2>
    <p class="words">{{ $r['narrative'] }}</p>

    @if ($r['areas'])
        <h2>Területek</h2>
        @foreach ($r['areas'] as $a)
            @php $pct = $a['firstTryRate'] === null ? 0 : (int) round($a['firstTryRate'] * 100); @endphp
            <table class="area"><tr>
                <td class="area-name">{!! Twemoji::img($a['emoji'], 14) !!} {{ $a['label'] }}</td>
                <td class="band" style="color: {{ $bandColor[$a['band']] }};">{{ $r['bandLabels'][$a['band']] }}</td>
            </tr><tr><td colspan="2">
                <div class="track"><div class="fill" style="width: {{ max($pct, 2) }}%; background: {{ $bandColor[$a['band']] }};"></div></div>
                <div class="note">
                    @if ($a['firstTryRate'] !== null) elsőre jó: {{ $pct }}% @else még kevés válasz @endif
                    @if ($a['trend']) · <span style="color: {{ $trend[$a['trend']][1] }};">{{ $trend[$a['trend']][0] }} {{ $trend[$a['trend']][2] }}</span>@endif
                </div>
            </td></tr></table>
        @endforeach
    @endif

    @if ($r['sounds']['items'])
        <h2>Hangok</h2>
        @foreach ($r['sounds']['items'] as $s)
            @php $pct = (int) round($s['firstTryRate'] * 100); @endphp
            <table class="area"><tr>
                <td class="area-name">„{{ $s['label'] }}” <span class="kind">{{ $soundKind[$s['kind']] }}@if ($s['examples']) · {{ implode(', ', array_slice($s['examples'], 0, 2)) }}@endif</span></td>
                <td class="band" style="color: {{ $bandColor[$s['band']] }};">{{ $r['bandLabels'][$s['band']] }}</td>
            </tr><tr><td colspan="2">
                <div class="track"><div class="fill" style="width: {{ max($pct, 2) }}%; background: {{ $bandColor[$s['band']] }};"></div></div>
                <div class="note">
                    elsőre jó: {{ $pct }}%
                    @if ($s['trend']) · <span style="color: {{ $trend[$s['trend']][1] }};">{{ $trend[$s['trend']][0] }} {{ $trend[$s['trend']][2] }}</span>@endif
                </div>
            </td></tr></table>
        @endforeach
    @endif

    <table class="two"><tr>
        <td width="56%" style="padding-right: 14px;">
            @if ($r['games'])
                <h2>A hét kedvenc játékai</h2>
                <table class="games">
                    @foreach ($r['games'] as $g)
                        <tr>
                            <td>{!! Twemoji::img($g['emoji'], 14) !!} <b>{{ $g['name'] }}</b></td>
                            <td class="right">{{ $g['sessions'] }}× @if ($g['firstTryRate'] !== null)· {{ (int) round($g['firstTryRate'] * 100) }}% @endif</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </td>
        <td width="44%">
            @if ($r['badges'])
                <h2>Új matricák</h2>
                @foreach ($r['badges'] as $b)
                    <span class="sticker">{!! Twemoji::img($b['emoji'], 13) !!} {{ $b['name'] }}</span>
                @endforeach
            @endif
            @if ($r['tips'])
                <h2>{!! Twemoji::img('💡', 14) !!} Tipp a jövő hétre</h2>
                <div class="tip">@foreach ($r['tips'] as $tip)<p>{{ $tip }}</p>@endforeach</div>
            @endif
        </td>
    </tr></table>

    <p class="foot">
        A sávok azt mutatják, hogyan mentek a játékok a héten (elsőre jó válaszok aránya), nem összehasonlítás más gyerekekkel: az appban nincsenek korosztályos normák, és a DIFER-területekhez rendelés csak közelítő.
        Készült a Csillám játékai (Beszéd & DIFER) alkalmazásban.
    </p>
</body>
</html>
