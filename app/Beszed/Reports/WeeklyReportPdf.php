<?php

namespace App\Beszed\Reports;

use Dompdf\Dompdf;
use Dompdf\Options;

/** The weekly report as an A4 PDF (resources/views/pdf/weekly-report.blade.php through dompdf). */
final class WeeklyReportPdf
{
    /** @param  array  $report  WeeklyReport::for() */
    public static function render(array $report): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans'); // has ő and ű
        $options->set('isRemoteEnabled', false); // pictures are inline data, nothing is fetched
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pdf.weekly-report', ['r' => $report])->render(), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }
}
