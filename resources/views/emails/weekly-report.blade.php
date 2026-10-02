@php
    /** @var array $r  App\Beszed\Reports\WeeklyReport::for() */
    $ink = '#3b1f4a'; $muted = '#6b5a77'; $sky = '#7cc8ff'; $soft = '#eaf7ff';
    $bandColor = ['strong' => '#2f9e5b', 'growing' => '#f5b02e', 'practice' => '#ff6f61', 'noData' => '#c8bedb'];
    $trend = ['up' => ['▲', '#2f9e5b', 'jobb, mint múlt héten'], 'down' => ['▼', '#ff6f61', 'kicsit gyengébb, mint múlt héten'], 'flat' => ['▬', '#6b5a77', 'mint múlt héten']];
    $soundKind = ['start' => 'kezdőhang', 'contrast' => 'hangpár', 'rhyme' => 'rím'];
    $name = $r['child']['name'];
@endphp
<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $name }} heti beszámolója</title>
</head>
<body style="margin:0;padding:0;background:#e2f4ff;font-family:'Trebuchet MS',Arial,sans-serif;color:{{ $ink }};">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#e2f4ff;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:28px;overflow:hidden;box-shadow:0 8px 0 rgba(59,31,74,0.12);">

    {{-- the sky header --}}
    <tr><td style="background:{{ $sky }};padding:28px 28px 22px;">
        <p style="margin:0 0 6px;font-size:14px;font-weight:bold;letter-spacing:.04em;color:#ffffff;text-transform:uppercase;">🦄 Csillám heti levele · {{ $r['period'] }}</p>
        <h1 style="margin:0;font-size:30px;line-height:1.15;color:#ffffff;">{{ $name }} {{ $r['child']['sign'] }} heti beszámolója</h1>
        <p style="margin:8px 0 0;font-size:16px;color:#ffffff;">{{ $r['level']['number'] }}. szint · {{ $r['streak'] }} napos sorozat</p>
    </td></tr>

    {{-- the week in four numbers --}}
    <tr><td style="padding:22px 20px 6px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>
            @foreach ([['🎮', $r['totals']['games'], 'játék'], ['⏱️', $r['totals']['minutes'], 'perc'], ['⭐', $r['totals']['stars'], 'csillag'], ['📅', $r['totals']['days'], 'játéknap']] as [$icon, $value, $label])
                <td width="25%" align="center" style="padding:4px;">
                    <div style="background:{{ $soft }};border-radius:20px;padding:14px 6px;">
                        <div style="font-size:22px;">{{ $icon }}</div>
                        <div style="font-size:28px;font-weight:bold;line-height:1.1;">{{ $value }}</div>
                        <div style="font-size:13px;color:{{ $muted }};">{{ $label }}</div>
                    </div>
                </td>
            @endforeach
        </tr></table>
    </td></tr>

    {{-- Csillám's words --}}
    <tr><td style="padding:14px 28px 4px;">
        <h2 style="margin:0 0 8px;font-size:20px;">Így ment a héten</h2>
        <p style="margin:0;font-size:16px;line-height:1.5;">{{ $r['narrative'] }}</p>
    </td></tr>

    {{-- skill areas --}}
    @if ($r['areas'])
    <tr><td style="padding:18px 28px 4px;">
        <h2 style="margin:0 0 10px;font-size:20px;">Területek</h2>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
            @foreach ($r['areas'] as $a)
                @php $pct = $a['firstTryRate'] === null ? 0 : (int) round($a['firstTryRate'] * 100); @endphp
                <tr><td style="padding:6px 0;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>
                        <td style="font-size:15px;font-weight:bold;">{{ $a['emoji'] }} {{ $a['label'] }}</td>
                        <td align="right" style="font-size:14px;color:{{ $bandColor[$a['band']] }};font-weight:bold;white-space:nowrap;">{{ $r['bandLabels'][$a['band']] }}</td>
                    </tr></table>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:5px;background:#f1eef6;border-radius:8px;"><tr>
                        @if ($pct > 0)<td width="{{ $pct }}%" style="background:{{ $bandColor[$a['band']] }};height:12px;border-radius:8px;font-size:0;line-height:0;">&nbsp;</td>@endif
                        <td style="height:12px;font-size:0;line-height:0;">&nbsp;</td>
                    </tr></table>
                    <p style="margin:4px 0 0;font-size:13px;color:{{ $muted }};">
                        @if ($a['firstTryRate'] !== null) elsőre jó: {{ $pct }}% @else még kevés válasz @endif
                        @if ($a['trend']) · <span style="color:{{ $trend[$a['trend']][1] }};">{{ $trend[$a['trend']][0] }} {{ $trend[$a['trend']][2] }}</span>@endif
                    </p>
                </td></tr>
            @endforeach
        </table>
    </td></tr>
    @endif

    {{-- sounds --}}
    @if ($r['sounds']['items'])
    <tr><td style="padding:18px 28px 4px;">
        <h2 style="margin:0 0 10px;font-size:20px;">Hangok</h2>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
            @foreach ($r['sounds']['items'] as $s)
                @php $pct = (int) round($s['firstTryRate'] * 100); @endphp
                <tr><td style="padding:6px 0;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>
                        <td style="font-size:15px;font-weight:bold;">„{{ $s['label'] }}” <span style="font-size:13px;font-weight:normal;color:{{ $muted }};">{{ $soundKind[$s['kind']] }}@if ($s['examples']) · {{ implode(', ', array_slice($s['examples'], 0, 2)) }}@endif</span></td>
                        <td align="right" style="font-size:14px;color:{{ $bandColor[$s['band']] }};font-weight:bold;white-space:nowrap;">{{ $r['bandLabels'][$s['band']] }}</td>
                    </tr></table>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:5px;background:#f1eef6;border-radius:8px;"><tr>
                        @if ($pct > 0)<td width="{{ $pct }}%" style="background:{{ $bandColor[$s['band']] }};height:12px;border-radius:8px;font-size:0;line-height:0;">&nbsp;</td>@endif
                        <td style="height:12px;font-size:0;line-height:0;">&nbsp;</td>
                    </tr></table>
                    <p style="margin:4px 0 0;font-size:13px;color:{{ $muted }};">
                        elsőre jó: {{ $pct }}%
                        @if ($s['trend']) · <span style="color:{{ $trend[$s['trend']][1] }};">{{ $trend[$s['trend']][0] }} {{ $trend[$s['trend']][2] }}</span>@endif
                    </p>
                </td></tr>
            @endforeach
        </table>
    </td></tr>
    @endif

    {{-- the games played most --}}
    @if ($r['games'])
    <tr><td style="padding:18px 28px 4px;">
        <h2 style="margin:0 0 8px;font-size:20px;">A hét kedvenc játékai</h2>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
            @foreach ($r['games'] as $g)
                <tr>
                    <td style="padding:6px 0;font-size:16px;">{{ $g['emoji'] }} <b>{{ $g['name'] }}</b></td>
                    <td align="right" style="padding:6px 0;font-size:14px;color:{{ $muted }};white-space:nowrap;">
                        {{ $g['sessions'] }}× @if ($g['firstTryRate'] !== null)· {{ (int) round($g['firstTryRate'] * 100) }}% elsőre @endif
                        @if ($g['level'] && $g['maxLevel'])· {{ $g['level'] }}/{{ $g['maxLevel'] }}. szint @endif
                    </td>
                </tr>
            @endforeach
        </table>
    </td></tr>
    @endif

    {{-- new stickers --}}
    @if ($r['badges'])
    <tr><td style="padding:18px 28px 4px;">
        <h2 style="margin:0 0 8px;font-size:20px;">Új matricák</h2>
        <p style="margin:0;font-size:16px;line-height:1.8;">
            @foreach ($r['badges'] as $b)
                <span style="display:inline-block;margin:0 6px 6px 0;padding:4px 12px;background:#fff3c4;border-radius:999px;">{{ $b['emoji'] }} {{ $b['name'] }}</span>
            @endforeach
        </p>
    </td></tr>
    @endif

    {{-- a tip for next week --}}
    @if ($r['tips'])
    <tr><td style="padding:18px 28px 4px;">
        <div style="background:#eefaf2;border-radius:20px;padding:16px 18px;">
            <h2 style="margin:0 0 6px;font-size:18px;">💡 Tipp a jövő hétre</h2>
            @foreach ($r['tips'] as $tip)
                <p style="margin:4px 0 0;font-size:15px;line-height:1.45;">{{ $tip }}</p>
            @endforeach
        </div>
    </td></tr>
    @endif

    <tr><td align="center" style="padding:24px 28px 8px;">
        <a href="{{ $r['url'] }}" style="display:inline-block;padding:14px 28px;background:#2f9e5b;color:#ffffff;border-radius:999px;font-size:17px;font-weight:bold;text-decoration:none;">Megnézem a Haladás oldalt</a>
        <p style="margin:12px 0 0;font-size:13px;color:{{ $muted }};">📎 A beszámolót PDF-ben is csatoltuk, kinyomtathatod vagy elküldheted a logopédusnak.</p>
    </td></tr>

    <tr><td style="padding:18px 28px 26px;">
        <p style="margin:0;font-size:12px;line-height:1.5;color:{{ $muted }};">
            A sávok azt mutatják, hogyan mentek a játékok a héten, nem összehasonlítás más gyerekekkel: az appban nincsenek korosztályos normák.
            Ezt a levelet minden vasárnap küldjük, ha {{ $name }} játszott a héten.
            <a href="{{ $unsubscribeUrl }}" style="color:{{ $muted }};">Leiratkozás a heti beszámolóról</a>
        </p>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
