<?php
namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit\Workflow;

use ksfraser\FrontAccounting\Woocommerce\Workflow\WooSyncStateMachine;
use ksfraser\FrontAccounting\Woocommerce\Workflow\Status\StagingStatusInterface;
use PHPUnit\Framework\TestCase;

class StatusTraitsCoverageTest extends TestCase
{
    public function testGetStagingStatusDescriptionStaged(): void
    {
        $desc = WooSyncStateMachine::getStagingStatusDescription(StagingStatusInterface::STATUS_STAGED);
        $this->assertStringContainsString('Staged', $desc);
    }

    public function testGetStagingStatusDescriptionPendingReview(): void
    {
        $desc = WooSyncStateMachine::getStagingStatusDescription(StagingStatusInterface::STATUS_PENDING_REVIEW);
        $this->assertStringContainsString('Pending Review', $desc);
    }

    public function testGetStagingStatusDescriptionMatched(): void
    {
        $desc = WooSyncStateMachine::getStagingStatusDescription(StagingStatusInterface::STATUS_MATCHED);
        $this->assertStringContainsString('Matched', $desc);
    }

    public function testGetStagingStatusDescriptionProcessing(): void
    {
        $desc = WooSyncStateMachine::getStagingStatusDescription(StagingStatusInterface::STATUS_PROCESSING);
        $this->assertStringContainsString('Processing', $desc);
    }

    public function testGetStagingStatusDescriptionProcessed(): void
    {
        $desc = WooSyncStateMachine::getStagingStatusDescription(StagingStatusInterface::STATUS_PROCESSED);
        $this->assertStringContainsString('Processed', $desc);
    }

    public function testGetStagingStatusDescriptionImported(): void
    {
        $desc = WooSyncStateMachine::getStagingStatusDescription(StagingStatusInterface::STATUS_IMPORTED);
        $this->assertStringContainsString('Imported', $desc);
    }

    public function testGetStagingStatusDescriptionUnknown(): void
    {
        $desc = WooSyncStateMachine::getStagingStatusDescription('UNKNOWN_STATUS');
        $this->assertStringContainsString('Unknown staging status', $desc);
    }

    public function testGetStagingStatusDescriptionBaseStatuses(): void
    {
        $desc = WooSyncStateMachine::getStagingStatusDescription(StagingStatusInterface::STATUS_PENDING);
        $this->assertStringContainsString('Pending', $desc);

        $desc = WooSyncStateMachine::getStagingStatusDescription(StagingStatusInterface::STATUS_IN_PROGRESS);
        $this->assertStringContainsString('In Progress', $desc);

        $desc = WooSyncStateMachine::getStagingStatusDescription(StagingStatusInterface::STATUS_COMPLETED);
        $this->assertStringContainsString('Completed', $desc);

        $desc = WooSyncStateMachine::getStagingStatusDescription(StagingStatusInterface::STATUS_ERROR);
        $this->assertStringContainsString('Error', $desc);

        $desc = WooSyncStateMachine::getStagingStatusDescription(StagingStatusInterface::STATUS_FAILED);
        $this->assertStringContainsString('Failed', $desc);

        $desc = WooSyncStateMachine::getStagingStatusDescription(StagingStatusInterface::STATUS_CANCELLED);
        $this->assertStringContainsString('Cancelled', $desc);
    }

    public function testRequiresActionStaged(): void
    {
        $this->assertTrue(WooSyncStateMachine::requiresAction(StagingStatusInterface::STATUS_STAGED));
    }

    public function testRequiresActionPendingReview(): void
    {
        $this->assertTrue(WooSyncStateMachine::requiresAction(StagingStatusInterface::STATUS_PENDING_REVIEW));
    }

    public function testRequiresActionPending(): void
    {
        $this->assertTrue(WooSyncStateMachine::requiresAction(StagingStatusInterface::STATUS_PENDING));
    }

    public function testRequiresActionError(): void
    {
        $this->assertTrue(WooSyncStateMachine::requiresAction(StagingStatusInterface::STATUS_ERROR));
    }

    public function testRequiresActionFailed(): void
    {
        $this->assertTrue(WooSyncStateMachine::requiresAction(StagingStatusInterface::STATUS_FAILED));
    }

    public function testRequiresActionImported(): void
    {
        $this->assertFalse(WooSyncStateMachine::requiresAction(StagingStatusInterface::STATUS_IMPORTED));
    }

    public function testRequiresActionCompleted(): void
    {
        $this->assertFalse(WooSyncStateMachine::requiresAction(StagingStatusInterface::STATUS_COMPLETED));
    }

    public function testRequiresActionInProgress(): void
    {
        $this->assertFalse(WooSyncStateMachine::requiresAction(StagingStatusInterface::STATUS_IN_PROGRESS));
    }

    public function testIsImportableMatched(): void
    {
        $this->assertTrue(WooSyncStateMachine::isImportable(StagingStatusInterface::STATUS_MATCHED));
    }

    public function testIsImportableProcessing(): void
    {
        $this->assertTrue(WooSyncStateMachine::isImportable(StagingStatusInterface::STATUS_PROCESSING));
    }

    public function testIsImportableProcessed(): void
    {
        $this->assertTrue(WooSyncStateMachine::isImportable(StagingStatusInterface::STATUS_PROCESSED));
    }

    public function testIsImportableImported(): void
    {
        $this->assertTrue(WooSyncStateMachine::isImportable(StagingStatusInterface::STATUS_IMPORTED));
    }

    public function testIsImportableStaged(): void
    {
        $this->assertFalse(WooSyncStateMachine::isImportable(StagingStatusInterface::STATUS_STAGED));
    }

    public function testIsFinalStatusImported(): void
    {
        $this->assertTrue(WooSyncStateMachine::isFinalStatus(StagingStatusInterface::STATUS_IMPORTED));
    }

    public function testIsFinalStatusCompleted(): void
    {
        $this->assertTrue(WooSyncStateMachine::isFinalStatus(StagingStatusInterface::STATUS_COMPLETED));
    }

    public function testIsFinalStatusFailed(): void
    {
        $this->assertTrue(WooSyncStateMachine::isFinalStatus(StagingStatusInterface::STATUS_FAILED));
    }

    public function testIsFinalStatusCancelled(): void
    {
        $this->assertTrue(WooSyncStateMachine::isFinalStatus(StagingStatusInterface::STATUS_CANCELLED));
    }

    public function testIsFinalStatusStaged(): void
    {
        $this->assertFalse(WooSyncStateMachine::isFinalStatus(StagingStatusInterface::STATUS_STAGED));
    }

    public function testIsFinalStatusPending(): void
    {
        $this->assertFalse(WooSyncStateMachine::isFinalStatus(StagingStatusInterface::STATUS_PENDING));
    }

    public function testCanRetryError(): void
    {
        $this->assertTrue(WooSyncStateMachine::canRetry(StagingStatusInterface::STATUS_ERROR));
    }

    public function testCanRetryFailed(): void
    {
        $this->assertTrue(WooSyncStateMachine::canRetry(StagingStatusInterface::STATUS_FAILED));
    }

    public function testCanRetryCompleted(): void
    {
        $this->assertFalse(WooSyncStateMachine::canRetry(StagingStatusInterface::STATUS_COMPLETED));
    }

    public function testCanRetryPending(): void
    {
        $this->assertFalse(WooSyncStateMachine::canRetry(StagingStatusInterface::STATUS_PENDING));
    }

    public function testIsErrorStatusError(): void
    {
        $this->assertTrue(WooSyncStateMachine::isErrorStatus(StagingStatusInterface::STATUS_ERROR));
    }

    public function testIsErrorStatusFailed(): void
    {
        $this->assertTrue(WooSyncStateMachine::isErrorStatus(StagingStatusInterface::STATUS_FAILED));
    }

    public function testIsErrorStatusPending(): void
    {
        $this->assertFalse(WooSyncStateMachine::isErrorStatus(StagingStatusInterface::STATUS_PENDING));
    }

    public function testIsActiveStatusPending(): void
    {
        $this->assertTrue(WooSyncStateMachine::isActiveStatus(StagingStatusInterface::STATUS_PENDING));
    }

    public function testIsActiveStatusInProgress(): void
    {
        $this->assertTrue(WooSyncStateMachine::isActiveStatus(StagingStatusInterface::STATUS_IN_PROGRESS));
    }

    public function testIsActiveStatusCompleted(): void
    {
        $this->assertFalse(WooSyncStateMachine::isActiveStatus(StagingStatusInterface::STATUS_COMPLETED));
    }

    public function testGetStagingStatuses(): void
    {
        $statuses = WooSyncStateMachine::getStagingStatuses();
        $this->assertContains(StagingStatusInterface::STATUS_STAGED, $statuses);
        $this->assertContains(StagingStatusInterface::STATUS_PENDING_REVIEW, $statuses);
        $this->assertContains(StagingStatusInterface::STATUS_MATCHED, $statuses);
        $this->assertContains(StagingStatusInterface::STATUS_PROCESSING, $statuses);
        $this->assertContains(StagingStatusInterface::STATUS_PROCESSED, $statuses);
        $this->assertContains(StagingStatusInterface::STATUS_IMPORTED, $statuses);
    }

    public function testGetStatuses(): void
    {
        $statuses = WooSyncStateMachine::getStatuses();
        $this->assertContains(StagingStatusInterface::STATUS_PENDING, $statuses);
        $this->assertContains(StagingStatusInterface::STATUS_IN_PROGRESS, $statuses);
        $this->assertContains(StagingStatusInterface::STATUS_COMPLETED, $statuses);
        $this->assertContains(StagingStatusInterface::STATUS_ERROR, $statuses);
        $this->assertContains(StagingStatusInterface::STATUS_FAILED, $statuses);
        $this->assertContains(StagingStatusInterface::STATUS_CANCELLED, $statuses);
    }

    public function testGetInitialStagingStatus(): void
    {
        $this->assertEquals(StagingStatusInterface::STATUS_STAGED, WooSyncStateMachine::getInitialStagingStatus());
    }

    public function testGetFinalStagingStatus(): void
    {
        $this->assertEquals(StagingStatusInterface::STATUS_IMPORTED, WooSyncStateMachine::getFinalStagingStatus());
    }
}
