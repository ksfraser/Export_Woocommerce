<?php
namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit\Workflow;

use Ksfraser\frontaccounting\Woocommerce\Workflow\WooSyncStateMachine;
use Ksfraser\frontaccounting\Woocommerce\Workflow\Status\StagingStatusInterface;
use PHPUnit\Framework\TestCase;

class WooSyncStateMachineCoverageTest extends TestCase
{
    private WooSyncStateMachine $stateMachine;

    protected function setUp(): void
    {
        $this->stateMachine = new WooSyncStateMachine();
    }

    public function testTransitionInvalidPathSetsError(): void
    {
        $result = $this->stateMachine->transition(
            StagingStatusInterface::STATUS_IMPORTED,
            StagingStatusInterface::STATUS_STAGED,
            [],
            WooSyncStateMachine::ENTITY_CUSTOMER
        );

        $this->assertFalse($result);
        $this->assertNotNull($this->stateMachine->getLastError());
        $this->assertStringContainsString('Invalid transition', $this->stateMachine->getLastError());
        $this->assertStringContainsString('customer_staging', $this->stateMachine->getLastError());
    }

    public function testTransitionValidPathRecordsHistoryWithContext(): void
    {
        $context = ['woo_customer_id' => 123, 'fa_debtor_no' => 10];

        $result = $this->stateMachine->transition(
            StagingStatusInterface::STATUS_STAGED,
            StagingStatusInterface::STATUS_MATCHED,
            $context,
            WooSyncStateMachine::ENTITY_CUSTOMER
        );

        $this->assertTrue($result);

        $history = $this->stateMachine->getTransitionHistory();
        $this->assertCount(1, $history);
        $this->assertEquals(WooSyncStateMachine::ENTITY_CUSTOMER, $history[0]['entity_type']);
        $this->assertEquals(StagingStatusInterface::STATUS_STAGED, $history[0]['from_status']);
        $this->assertEquals(StagingStatusInterface::STATUS_MATCHED, $history[0]['to_status']);
        $this->assertEquals($context, $history[0]['context']);
        $this->assertArrayHasKey('timestamp', $history[0]);
    }

    public function testTransitionValidPathEmptyContext(): void
    {
        $result = $this->stateMachine->transition(
            StagingStatusInterface::STATUS_STAGED,
            StagingStatusInterface::STATUS_MATCHED,
            [],
            WooSyncStateMachine::ENTITY_CUSTOMER
        );

        $this->assertTrue($result);

        $history = $this->stateMachine->getTransitionHistory();
        $this->assertCount(1, $history);
        $this->assertEquals([], $history[0]['context']);
    }

    public function testTransitionWithoutEntityTypeDefaultsToCustomer(): void
    {
        $result = $this->stateMachine->transition(
            StagingStatusInterface::STATUS_STAGED,
            StagingStatusInterface::STATUS_MATCHED,
            ['test' => true]
        );

        $this->assertTrue($result);

        $history = $this->stateMachine->getTransitionHistory();
        $this->assertEquals(WooSyncStateMachine::ENTITY_CUSTOMER, $history[0]['entity_type']);
    }

    public function testIsFinalStateMatchesImported(): void
    {
        $this->assertTrue($this->stateMachine->isFinalState(StagingStatusInterface::STATUS_IMPORTED));
    }

    public function testIsFinalStateNonFinal(): void
    {
        $this->assertFalse($this->stateMachine->isFinalState(StagingStatusInterface::STATUS_STAGED));
        $this->assertFalse($this->stateMachine->isFinalState(StagingStatusInterface::STATUS_PENDING));
        $this->assertFalse($this->stateMachine->isFinalState(StagingStatusInterface::STATUS_IN_PROGRESS));
    }

    public function testRecordTransitionDirectly(): void
    {
        $method = new \ReflectionMethod(WooSyncStateMachine::class, 'recordTransition');
        $method->setAccessible(true);
        $method->invoke($this->stateMachine, 'STAGED', 'MATCHED', ['test' => true]);

        $history = $this->stateMachine->getTransitionHistory();
        $this->assertCount(1, $history);
        $this->assertEquals('STAGED', $history[0]['from_status']);
        $this->assertEquals('MATCHED', $history[0]['to_status']);
    }

    public function testSetErrorDirectly(): void
    {
        $method = new \ReflectionMethod(WooSyncStateMachine::class, 'setError');
        $method->setAccessible(true);
        $method->invoke($this->stateMachine, 'Custom error message');

        $this->assertEquals('Custom error message', $this->stateMachine->getLastError());
    }

    public function testClearHistory(): void
    {
        $this->stateMachine->transition(
            StagingStatusInterface::STATUS_STAGED,
            StagingStatusInterface::STATUS_MATCHED,
            [],
            WooSyncStateMachine::ENTITY_CUSTOMER
        );

        $this->assertCount(1, $this->stateMachine->getTransitionHistory());

        $this->stateMachine->clearHistory();
        $this->assertEmpty($this->stateMachine->getTransitionHistory());
    }

    public function testGetValidTransitionsForEntity(): void
    {
        $transitions = $this->stateMachine->getValidTransitionsForEntity(
            WooSyncStateMachine::ENTITY_ORDER,
            StagingStatusInterface::STATUS_STAGED
        );

        $this->assertContains(StagingStatusInterface::STATUS_PENDING_REVIEW, $transitions);
        $this->assertContains(StagingStatusInterface::STATUS_MATCHED, $transitions);
    }

    public function testGetValidTransitionsUnknownStatus(): void
    {
        $transitions = $this->stateMachine->getValidTransitions('UNKNOWN_STATUS', WooSyncStateMachine::ENTITY_CUSTOMER);
        $this->assertEmpty($transitions);
    }

    public function testGetValidTransitionsNullEntityType(): void
    {
        $transitions = $this->stateMachine->getValidTransitions(StagingStatusInterface::STATUS_STAGED);
        $this->assertNotEmpty($transitions);
    }

    public function testGetRequiredFieldsForProcessing(): void
    {
        $fields = $this->stateMachine->getRequiredFieldsForStatus(StagingStatusInterface::STATUS_PROCESSING);
        $this->assertContains('fa_debtor_no', $fields);
    }

    public function testGetRequiredFieldsForProcessed(): void
    {
        $fields = $this->stateMachine->getRequiredFieldsForStatus(StagingStatusInterface::STATUS_PROCESSED);
        $this->assertContains('fa_order_no', $fields);
    }

    public function testGetRequiredFieldsForUnknownStatus(): void
    {
        $fields = $this->stateMachine->getRequiredFieldsForStatus('UNKNOWN');
        $this->assertEmpty($fields);
    }

    public function testValidateEntityForTransitionSuccess(): void
    {
        $entity = [
            'fa_order_no' => 100,
            'fa_debtor_no' => 10,
        ];

        $errors = $this->stateMachine->validateEntityForStatus(StagingStatusInterface::STATUS_IMPORTED, $entity);
        $this->assertEmpty($errors);
    }

    public function testValidateEntityForTransitionMissingFields(): void
    {
        $entity = [
            'fa_order_no' => null,
        ];

        $errors = $this->stateMachine->validateEntityForStatus(StagingStatusInterface::STATUS_IMPORTED, $entity);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('fa_order_no', $errors[0]);
        $this->assertStringContainsString('fa_debtor_no', $errors[1]);
    }

    public function testGetEntityTypes(): void
    {
        $types = $this->stateMachine->getEntityTypes();
        $this->assertContains(WooSyncStateMachine::ENTITY_CUSTOMER, $types);
        $this->assertContains(WooSyncStateMachine::ENTITY_ORDER, $types);
        $this->assertCount(2, $types);
    }

    public function testGetFinalStatus(): void
    {
        $this->assertEquals(StagingStatusInterface::STATUS_IMPORTED, $this->stateMachine->getFinalStatus());
    }

    public function testRequiresCustomerMatchForOrder(): void
    {
        $this->assertTrue($this->stateMachine->requiresCustomerMatch(WooSyncStateMachine::ENTITY_ORDER));
    }

    public function testRequiresCustomerMatchForCustomer(): void
    {
        $this->assertFalse($this->stateMachine->requiresCustomerMatch(WooSyncStateMachine::ENTITY_CUSTOMER));
    }

    public function testFullOrderWorkflow(): void
    {
        $this->assertTrue($this->stateMachine->transition(
            StagingStatusInterface::STATUS_STAGED,
            StagingStatusInterface::STATUS_MATCHED,
            [],
            WooSyncStateMachine::ENTITY_ORDER
        ));
        $this->assertTrue($this->stateMachine->transition(
            StagingStatusInterface::STATUS_MATCHED,
            StagingStatusInterface::STATUS_IN_PROGRESS,
            [],
            WooSyncStateMachine::ENTITY_ORDER
        ));
        $this->assertTrue($this->stateMachine->transition(
            StagingStatusInterface::STATUS_IN_PROGRESS,
            StagingStatusInterface::STATUS_COMPLETED,
            [],
            WooSyncStateMachine::ENTITY_ORDER
        ));
        $this->assertTrue($this->stateMachine->transition(
            StagingStatusInterface::STATUS_COMPLETED,
            StagingStatusInterface::STATUS_IMPORTED,
            [],
            WooSyncStateMachine::ENTITY_ORDER
        ));

        $history = $this->stateMachine->getTransitionHistory();
        $this->assertCount(4, $history);
    }

    public function testErrorTransitionsForCustomer(): void
    {
        $this->assertTrue($this->stateMachine->canTransition(
            StagingStatusInterface::STATUS_ERROR,
            StagingStatusInterface::STATUS_STAGED,
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));
        $this->assertTrue($this->stateMachine->canTransition(
            StagingStatusInterface::STATUS_ERROR,
            StagingStatusInterface::STATUS_PENDING,
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));
        $this->assertTrue($this->stateMachine->canTransition(
            StagingStatusInterface::STATUS_ERROR,
            StagingStatusInterface::STATUS_IN_PROGRESS,
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));
    }

    public function testFailedTransitionsForCustomer(): void
    {
        $this->assertTrue($this->stateMachine->canTransition(
            StagingStatusInterface::STATUS_FAILED,
            StagingStatusInterface::STATUS_STAGED,
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));
        $this->assertTrue($this->stateMachine->canTransition(
            StagingStatusInterface::STATUS_FAILED,
            StagingStatusInterface::STATUS_PENDING,
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));
    }

    public function testPendingReviewTransitions(): void
    {
        $this->assertTrue($this->stateMachine->canTransition(
            StagingStatusInterface::STATUS_PENDING_REVIEW,
            StagingStatusInterface::STATUS_MATCHED,
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));
        $this->assertTrue($this->stateMachine->canTransition(
            StagingStatusInterface::STATUS_PENDING_REVIEW,
            StagingStatusInterface::STATUS_IN_PROGRESS,
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));
        $this->assertTrue($this->stateMachine->canTransition(
            StagingStatusInterface::STATUS_PENDING_REVIEW,
            StagingStatusInterface::STATUS_STAGED,
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));
    }

    public function testProcessingTransitions(): void
    {
        $this->assertTrue($this->stateMachine->canTransition(
            StagingStatusInterface::STATUS_PROCESSING,
            StagingStatusInterface::STATUS_PROCESSED,
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));
        $this->assertTrue($this->stateMachine->canTransition(
            StagingStatusInterface::STATUS_PROCESSING,
            StagingStatusInterface::STATUS_COMPLETED,
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));
        $this->assertTrue($this->stateMachine->canTransition(
            StagingStatusInterface::STATUS_PROCESSING,
            StagingStatusInterface::STATUS_IMPORTED,
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));
    }

    public function testProcessedTransitions(): void
    {
        $this->assertTrue($this->stateMachine->canTransition(
            StagingStatusInterface::STATUS_PROCESSED,
            StagingStatusInterface::STATUS_COMPLETED,
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));
        $this->assertTrue($this->stateMachine->canTransition(
            StagingStatusInterface::STATUS_PROCESSED,
            StagingStatusInterface::STATUS_IMPORTED,
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));
    }
}
