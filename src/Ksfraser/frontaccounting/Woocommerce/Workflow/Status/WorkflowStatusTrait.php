<?php

declare(strict_types=1);

namespace Ksfraser\frontaccounting\Woocommerce\Workflow\Status;

/**
 * Workflow Status Trait
 * 
 * Provides default implementations for WorkflowStatusInterface.
 * Use in classes that implement WorkflowStatusInterface.
 * 
 * @since 1.0.0
 */
trait WorkflowStatusTrait
{
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_ERROR,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ];
    }

    public static function getStatusDescription(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'Pending - Awaiting processing',
            self::STATUS_IN_PROGRESS => 'In Progress - Currently being processed',
            self::STATUS_COMPLETED => 'Completed - Successfully finished',
            self::STATUS_ERROR => 'Error - An error occurred',
            self::STATUS_FAILED => 'Failed - Operation failed',
            self::STATUS_CANCELLED => 'Cancelled - Operation cancelled',
            default => 'Unknown status: ' . $status,
        };
    }

    public static function isFinalStatus(string $status): bool
    {
        $finalStatuses = [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ];

        if (defined('self::STATUS_IMPORTED')) {
            $finalStatuses[] = self::STATUS_IMPORTED;
        }

        return in_array($status, $finalStatuses, true);
    }

    public static function isErrorStatus(string $status): bool
    {
        return in_array($status, [
            self::STATUS_ERROR,
            self::STATUS_FAILED,
        ], true);
    }

    public static function canRetry(string $status): bool
    {
        return in_array($status, [
            self::STATUS_ERROR,
            self::STATUS_FAILED,
        ], true);
    }

    public static function isActiveStatus(string $status): bool
    {
        return in_array($status, [
            self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
        ], true);
    }
}