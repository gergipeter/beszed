<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;

/** The one-click "Leiratkozás" in the weekly e-mail (a signed link, no sign-in needed). */
class WeeklyReportUnsubscribeController extends Controller
{
    public function __invoke(User $user): Response
    {
        $user->forceFill(['weekly_report_enabled' => false])->save();

        $page = <<<'HTML'
            <!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Leiratkozás</title></head>
            <body style="margin:0;display:grid;place-items:center;min-height:100vh;background:#e2f4ff;font-family:'Trebuchet MS',Arial,sans-serif;color:#3b1f4a;">
            <div style="max-width:420px;margin:24px;padding:28px;border-radius:28px;background:#fff;text-align:center;box-shadow:0 8px 0 rgba(59,31,74,.12);">
            <div style="font-size:56px;">🦄</div>
            <h1 style="margin:8px 0;font-size:24px;">Leiratkoztál a heti beszámolóról</h1>
            <p style="margin:0;font-size:16px;line-height:1.5;">Nem küldünk több heti levelet. Bármikor visszakapcsolhatod a „Ki játszik ma?” oldalon.</p>
            </div></body></html>
            HTML;

        return response($page)->header('Referrer-Policy', 'no-referrer');
    }
}
