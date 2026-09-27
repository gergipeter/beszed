<?php

namespace App\Http\Controllers\Beszed;

use App\Beszed\Healthcare\ComplianceLogger;
use App\Beszed\Healthcare\FHIRExporter;
use App\Beszed\Security\EncryptionService;
use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EnterpriseController extends Controller
{
    use AuthorizesChild;

    /**
     * Export child data in FHIR format (JSON)
     */
    public function exportFHIRJson(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        ComplianceLogger::logExport($child->id, auth()->id(), 'fhir_json', 'Patient export request');

        $exporter = new FHIRExporter();
        $data = $exporter->exportAsJson($child);

        return response()->json(json_decode($data, true));
    }

    /**
     * Export child data in FHIR format (XML)
     */
    public function exportFHIRXml(Request $request, Child $child): BinaryFileResponse
    {
        $this->authorizeChild($request, $child);

        ComplianceLogger::logExport($child->id, auth()->id(), 'fhir_xml', 'Patient export request');

        $exporter = new FHIRExporter();
        $xml = $exporter->exportAsXml($child);

        $filename = "fhir-export-{$child->id}.xml";
        $path = storage_path("exports/$filename");

        file_put_contents($path, $xml);

        return response()->download($path, $filename);
    }

    /**
     * Get child's encryption keys
     */
    public function getEncryptionKeys(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $publicKey = $child->public_encryption_key;

        if (!$publicKey) {
            // Generate new key pair
            $keys = EncryptionService::generateKeyPair();
            $child->update([
                'public_encryption_key' => $keys['public_key'],
                'secret_encryption_key' => encrypt($keys['secret_key']),
            ]);
            $publicKey = $keys['public_key'];
        }

        ComplianceLogger::logAccess($child->id, auth()->id(), 'read', 'encryption_keys');

        return response()->json([
            'child_id' => $child->id,
            'public_key' => $publicKey,
        ]);
    }

    /**
     * Get compliance audit trail
     */
    public function getAuditTrail(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $days = $request->input('days', 90);
        $trail = ComplianceLogger::getAuditTrail($child->id, $days);

        ComplianceLogger::logAccess($child->id, auth()->id(), 'read', 'audit_trail');

        return response()->json([
            'child_id' => $child->id,
            'days' => $days,
            'entries' => $trail,
            'total' => count($trail),
        ]);
    }

    /**
     * Generate GDPR Data Subject Access Request report
     */
    public function generateDSARReport(Request $request, Child $child): BinaryFileResponse
    {
        $this->authorizeChild($request, $child);

        ComplianceLogger::logExport($child->id, auth()->id(), 'dsar_report', 'GDPR Data Subject Access Request');

        $report = ComplianceLogger::generateDSARReport($child->id);

        $filename = "dsar-{$child->id}-" . now()->format('Y-m-d') . ".json";
        $path = storage_path("reports/$filename");

        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT));

        return response()->download($path, $filename);
    }

    /**
     * Validate HIPAA compliance
     */
    public function validateCompliance(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $result = ComplianceLogger::validateHIPAACompliance($child->id);

        ComplianceLogger::logAccess($child->id, auth()->id(), 'read', 'compliance_check');

        return response()->json($result);
    }

    /**
     * Request deletion (GDPR right to be forgotten)
     */
    public function requestDeletion(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $reason = $request->input('reason', 'No reason provided');

        ComplianceLogger::deleteAllData($child->id, auth()->id());

        return response()->json([
            'status' => 'deletion_requested',
            'child_id' => $child->id,
            'scheduled_for' => now()->addDays(30),
            'message' => 'Data will be permanently deleted in 30 days. You can cancel before then.',
        ]);
    }

    /**
     * Check parental controls
     */
    public function getParentalControls(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        return response()->json([
            'child_id' => $child->id,
            'screen_time_limit' => $child->screen_time_limit_minutes ?? 60,
            'content_filter' => $child->content_filter_level ?? 'medium', // kid, medium, teen
            'allowed_games' => $child->allowed_games ?? [],
            'bedtime_start' => $child->bedtime_start ?? '21:00',
            'bedtime_end' => $child->bedtime_end ?? '07:00',
        ]);
    }

    /**
     * Update parental controls
     */
    public function updateParentalControls(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $updates = $request->validate([
            'screen_time_limit_minutes' => 'nullable|integer|min:15|max:480',
            'content_filter_level' => 'nullable|in:kid,medium,teen',
            'bedtime_start' => 'nullable|date_format:H:i',
            'bedtime_end' => 'nullable|date_format:H:i',
        ]);

        $child->update($updates);

        ComplianceLogger::logModification($child->id, auth()->id(), 'children', 'parental_controls', null, $updates);

        return response()->json([
            'child_id' => $child->id,
            'controls' => $updates,
            'updated_at' => now(),
        ]);
    }

    /**
     * Send parent notification
     */
    public function sendNotification(Request $request, Child $child): JsonResponse
    {
        $this->authorizeChild($request, $child);

        $message = $request->input('message');
        $type = $request->input('type', 'info'); // info, warning, achievement

        $child->parent->notify(
            new \App\Notifications\BeszedNotification($child, $message, $type)
        );

        return response()->json([
            'status' => 'sent',
            'message' => $message,
            'type' => $type,
        ]);
    }
}
