<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Woocommerce\Workflow\Status;

/**
 * Staging Status Trait
 * 
 * Provides default implementations for StagingStatusInterface.
 * Use in classes that implement StagingStatusInterface.
 * 
 * @since 1.0.0
 */
trait StagingStatusTrait
{
    use WorkflowStatusTrait;

    public static function getStagingStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_ERROR,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_STAGED,
            self::STATUS_PENDING_REVIEW,
            self::STATUS_MATCHED,
            self::STATUS_PROCESSING,
            self::STATUS_PROCESSED,
            self::STATUS_IMPORTED,
        ];
    }

    public static function getStagingStatusDescription(string $status): string
    {
        $baseDesc = self::getStatusDescription($status);
        if ($baseDesc !== 'Unknown status: ' . $status) {
            return $baseDesc;
        }

        return match ($status) {
            self::STATUS_STAGED => 'Staged - Awaiting processing',
            self::STATUS_PENDING_REVIEW => 'Pending Review - Requires manual review',
            self::STATUS_MATCHED => 'Matched - Entity matched in target system',
            self::STATUS_PROCESSING => 'Processing - Import in progress',
            self::STATUS_PROCESSED => 'Processed - Import completed successfully',
            self::STATUS_IMPORTED => 'Imported - Successfully imported to target system',
            default => 'Unknown staging status: ' . $status,
        };
    }

    public static function isImportable(string $status): bool
    {
        return in_array($status, [
            self::STATUS_MATCHED,
            self::STATUS_PROCESSING,
            self::STATUS_PROCESSED,
            self::STATUS_IMPORTED,
        ], true);
    }

    public static function requiresAction(string $status): bool
    {
        return in_array($status, [
            self::STATUS_STAGED,
            self::STATUS_PENDING_REVIEW,
            self::STATUS_PENDING,
            self::STATUS_ERROR,
            self::STATUS_FAILED,
        ], true);
    }

    public static function getInitialStagingStatus(): string
    {
        return self::STATUS_STAGED;
    }

    public static function getFinalStagingStatus(): string
    {
        return self::STATUS_IMPORTED;
    }
}