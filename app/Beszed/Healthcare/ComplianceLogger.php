<?php

namespace App\Beszed\Healthcare;

use Illuminate\Support\Facades\DB;

/**
 * Compliance Logger
 * GDPR/HIPAA audit trail
 */
class ComplianceLogger
{
    /**
     * Log data access
     */
    public static function logAccess(
        int $childId,
        int $userId,
        string $action,
        string $dataType,
        ?string $reason = null
    ): void {
        DB::table('compliance_logs')->insert([
            'child_id' => $childId,
            'user_id' => $userId,
            'action' => $action, // read, write, delete, export
            'data_type' => $dataType, // speech, medical, personal
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log data modification
     */
    public static function logModification(
        int $childId,
        int $userId,
        string $table,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        DB::table('compliance_logs')->insert([
            'child_id' => $childId,
            'user_id' => $userId,
            'action' => "modify_{$action}",
            'data_type' => $table,
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($newValues),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log data deletion
     */
    public static function logDeletion(
        int $childId,
        int $userId,
        string $dataType,
        ?string $reason = null
    ): void {
        self::logAccess($childId, $userId, 'delete', $dataType, $reason);
    }

    /**
     * Log data export
     */
    public static function logExport(
        int $childId,
        int $userId,
        string $format,
        ?string $reason = null
    ): void {
        DB::table('compliance_logs')->insert([
            'child_id' => $childId,
            'user_id' => $userId,
            'action' => 'export',
            'data_type' => $format, // json, xml, pdf
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log consent
     */
    public static function logConsent(
        int $childId,
        string $consentType,
        bool $granted,
        ?string $reason = null
    ): void {
        DB::table('compliance_logs')->insert([
            'child_id' => $childId,
            'user_id' => auth()->id(),
            'action' => $granted ? 'consent_granted' : 'consent_revoked',
            'data_type' => $consentType,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get audit trail for child
     */
    public static function getAuditTrail(int $childId, int $days = 90): array
    {
        return DB::table('compliance_logs')
            ->where('child_id', $childId)
            ->where('timestamp', '>=', now()->subDays($days))
            ->orderByDesc('timestamp')
            ->get()
            ->toArray();
    }

    /**
     * Generate GDPR data subject access report
     */
    public static function generateDSARReport(int $childId): array
    {
        $child = DB::table('children')->where('id', $childId)->first();

        return [
            'subject' => $child,
            'personal_data' => DB::table('children')
                ->where('id', $childId)
                ->first(),
            'health_data' => DB::table('beszed_speech_recordings')
                ->where('child_id', $childId)
                ->get(),
            'activity_data' => DB::table('beszed_attempts')
                ->where('child_id', $childId)
                ->get(),
            'audit_trail' => self::getAuditTrail($childId),
            'generated_at' => now(),
        ];
    }

    /**
     * Validate HIPAA compliance
     */
    public static function validateHIPAACompliance(int $childId): array
    {
        $issues = [];

        // Check PHI is encrypted
        $unencrypted = DB::table('beszed_speech_recordings')
            ->where('child_id', $childId)
            ->where('encrypted', false)
            ->count();

        if ($unencrypted > 0) {
            $issues[] = "Found $unencrypted unencrypted speech recordings";
        }

        // Check access is logged
        $unlogged = DB::table('beszed_speech_recordings')
            ->where('child_id', $childId)
            ->where('created_at', '>=', now()->subDay())
            ->whereNotExists(function ($q) use ($childId) {
                $q->selectRaw(1)
                    ->from('compliance_logs')
                    ->where('child_id', $childId)
                    ->where('action', 'read');
            })
            ->count();

        if ($unlogged > 0) {
            $issues[] = "Found $unlogged accesses not in audit trail";
        }

        return [
            'compliant' => empty($issues),
            'issues' => $issues,
            'checked_at' => now(),
        ];
    }

    /**
     * Delete all data for child (GDPR right to be forgotten)
     */
    public static function deleteAllData(int $childId, int $requestedById): void
    {
        // Log the request
        self::logDeletion($childId, $requestedById, 'all_personal_data', 'GDPR right to be forgotten');

        // Schedule deletion (30-day grace period)
        DB::table('deletion_requests')->insert([
            'child_id' => $childId,
            'requested_by' => $requestedById,
            'requested_at' => now(),
            'scheduled_for' => now()->addDays(30),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
