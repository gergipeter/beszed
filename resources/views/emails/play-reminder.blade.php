<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Csillám várja a mai játékot</title>
</head>
<body style="margin:0;padding:0;background:#e2f4ff;font-family:'Trebuchet MS',Arial,sans-serif;color:#3b1f4a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#e2f4ff;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="520" cellspacing="0" cellpadding="0" style="max-width:520px;width:100%;background:#ffffff;border-radius:28px;overflow:hidden;box-shadow:0 8px 0 rgba(59,31,74,0.12);">
    <tr><td style="background:#7cc8ff;padding:26px 26px 20px;">
        <p style="margin:0 0 6px;font-size:14px;font-weight:bold;letter-spacing:.04em;color:#ffffff;text-transform:uppercase;">🦄 Csillám üzenete</p>
        <h1 style="margin:0;font-size:26px;line-height:1.15;color:#ffffff;">Ma még nem játszottatok</h1>
    </td></tr>
    <tr><td style="padding:22px 26px 8px;font-size:17px;line-height:1.5;">
        <p style="margin:0 0 14px;">Néhány perc is elég: egy-két játék, és a gyakorlás megy tovább.</p>
        @foreach ($children as $c)
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 12px;background:#eaf7ff;border-radius:20px;">
                <tr>
                    <td style="padding:14px 16px;">
                        <div style="font-size:19px;font-weight:bold;">{{ $c['sign'] }} {{ $c['name'] }}</div>
                        @if ($c['streak'] > 1)
                            <div style="font-size:15px;color:#6b5a77;">🔥 {{ $c['streak'] }} napos sorozat: egy játékkal tovább él.</div>
                        @endif
                    </td>
                    <td align="right" style="padding:14px 16px;">
                        <a href="{{ $c['url'] }}" style="display:inline-block;padding:10px 18px;border-radius:999px;background:#ff6f61;color:#ffffff;text-decoration:none;font-weight:bold;">Játék</a>
                    </td>
                </tr>
            </table>
        @endforeach
    </td></tr>
    <tr><td style="padding:10px 26px 24px;font-size:13px;color:#6b5a77;">
        Ezt a levelet azért kaptad, mert bekapcsoltad az emlékeztetőt. <a href="{{ $unsubscribeUrl }}" style="color:#6b5a77;">Leiratkozás</a>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
