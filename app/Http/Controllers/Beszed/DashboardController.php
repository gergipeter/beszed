<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\Dashboard\DashboardService;
use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use AuthorizesChild;

    public function __construct(private DashboardService $dashboardService) {}

    /**
     * Get parent dashboard for a child
     */
    public function parentDashboard(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $dashboard = $this->dashboardService->getParentDashboard($child);

        return response()->json($dashboard);
    }

    /**
     * Get therapist dashboard (multiple children). There is no separate therapist
     * role yet, so this stays scoped to the caller's own children, same as every
     * other endpoint here — never trust the id list in the request body.
     */
    public function therapistDashboard(Request $request): JsonResponse
    {
        $requested = $request->input('children', []);

        if (empty($requested)) {
            return response()->json(['error' => 'No children specified'], 400);
        }

        $childIds = Child::where('user_id', $request->user()->id)
            ->whereIn('id', $requested)
            ->pluck('id')
            ->all();

        $dashboard = $this->dashboardService->getTherapistDashboard($childIds);

        return response()->json($dashboard);
    }

    /**
     * Generate weekly progress report PDF
     */
    public function weeklyReport(Request $request, Child $child): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorizeChild($request, $child);

        $reportPath = (new \App\Beszed\Reports\ReportGenerator())
            ->generateWeeklyReport($child);

        return response()->download($reportPath, "weekly-report-{$child->id}.pdf");
    }

    /**
     * Generate monthly progress report PDF
     */
    public function monthlyReport(Request $request, Child $child): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorizeChild($request, $child);

        $reportPath = (new \App\Beszed\Reports\ReportGenerator())
            ->generateMonthlyReport($child);

        return response()->download($reportPath, "monthly-report-{$child->id}.pdf");
    }

    /**
     * Generate therapist progress note PDF
     */
    public function therapistNote(Request $request, Child $child): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorizeChild($request, $child);

        $reportPath = (new \App\Beszed\Reports\ReportGenerator())
            ->generateTherapistNote($child);

        return response()->download($reportPath, "therapist-note-{$child->id}.pdf");
    }
}
