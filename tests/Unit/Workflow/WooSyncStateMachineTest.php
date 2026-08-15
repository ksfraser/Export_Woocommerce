<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Woocommerce\Tests\Unit\Workflow;

use ksfraser\FrontAccounting\Woocommerce\Workflow\WooSyncStateMachine;
use ksfraser\FrontAccounting\Woocommerce\Workflow\Status\StagingStatusInterface;
use ksfraser\FrontAccounting\Woocommerce\Workflow\StateMachine\StateMachineInterface;
use PHPUnit\Framework\TestCase;

class WooSyncStateMachineTest extends TestCase
{
    private WooSyncStateMachine $stateMachine;

    protected function setUp(): void
    {
        $this->stateMachine = new WooSyncStateMachine();
    }

    public function testImplementsStagingStatusInterface(): void
    {
        $this->assertInstanceOf(StagingStatusInterface::class, $this->stateMachine);
    }

    public function testImplementsStateMachineInterface(): void
    {
        $this->assertInstanceOf(StateMachineInterface::class, $this->stateMachine);
    }

    public function testCustomerEntityCanTransitionFromStagedToMatched(): void
    {
        $this->assertTrue(
            $this->stateMachine->canTransition(
                StagingStatusInterface::STATUS_STAGED,
                StagingStatusInterface::STATUS_MATCHED,
                WooSyncStateMachine::ENTITY_CUSTOMER
            )
        );
    }

    public function testCustomerEntityCannotTransitionFromStagedToImported(): void
    {
        $this->assertFalse(
            $this->stateMachine->canTransition(
                StagingStatusInterface::STATUS_STAGED,
                StagingStatusInterface::STATUS_IMPORTED,
                WooSyncStateMachine::ENTITY_CUSTOMER
            )
        );
    }

    public function testOrderEntityCanTransitionFromStagedToPendingReview(): void
    {
        $this->assertTrue(
            $this->stateMachine->canTransition(
                StagingStatusInterface::STATUS_STAGED,
                StagingStatusInterface::STATUS_PENDING_REVIEW,
                WooSyncStateMachine::ENTITY_ORDER
            )
        );
    }

    public function testOrderEntityRequiresCustomerMatch(): void
    {
        $this->assertTrue($this->stateMachine->requiresCustomerMatch(WooSyncStateMachine::ENTITY_ORDER));
    }

    public function testCustomerEntityDoesNotRequireCustomerMatch(): void
    {
        $this->assertFalse($this->stateMachine->requiresCustomerMatch(WooSyncStateMachine::ENTITY_CUSTOMER));
    }

    public function testGetValidTransitionsFromStaged(): void
    {
        $transitions = $this->stateMachine->getValidTransitions(
            StagingStatusInterface::STATUS_STAGED,
            WooSyncStateMachine::ENTITY_CUSTOMER
        );

        $this->assertContains(StagingStatusInterface::STATUS_PENDING_REVIEW, $transitions);
        $this->assertContains(StagingStatusInterface::STATUS_MATCHED, $transitions);
        $this->assertContains(StagingStatusInterface::STATUS_ERROR, $transitions);
    }

    public function testImportedIsFinalState(): void
    {
        $this->assertTrue($this->stateMachine->isFinalState(StagingStatusInterface::STATUS_IMPORTED));
    }

    public function testStagedIsNotFinalState(): void
    {
        $this->assertFalse($this->stateMachine->isFinalState(StagingStatusInterface::STATUS_STAGED));
    }

    public function testTransitionRecordsHistory(): void
    {
        $entity = ['woo_customer_id' => 123];
        
        $result = $this->stateMachine->transition(
            StagingStatusInterface::STATUS_STAGED,
            StagingStatusInterface::STATUS_MATCHED,
            $entity,
            WooSyncStateMachine::ENTITY_CUSTOMER
        );

        $this->assertTrue($result);
        
        $history = $this->stateMachine->getTransitionHistory();
        $this->assertCount(1, $history);
        $this->assertEquals(WooSyncStateMachine::ENTITY_CUSTOMER, $history[0]['entity_type']);
        $this->assertEquals(StagingStatusInterface::STATUS_STAGED, $history[0]['from_status']);
        $this->assertEquals(StagingStatusInterface::STATUS_MATCHED, $history[0]['to_status']);
    }

    public function testInvalidTransitionSetsError(): void
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
    }

    public function testGetRequiredFieldsForMatchedStatus(): void
    {
        $fields = $this->stateMachine->getRequiredFieldsForStatus(StagingStatusInterface::STATUS_MATCHED);

        $this->assertContains('fa_debtor_no', $fields);
        $this->assertContains('fa_branch_ref', $fields);
    }

    public function testGetRequiredFieldsForImportedStatus(): void
    {
        $fields = $this->stateMachine->getRequiredFieldsForStatus(StagingStatusInterface::STATUS_IMPORTED);

        $this->assertContains('fa_order_no', $fields);
        $this->assertContains('fa_debtor_no', $fields);
    }

    public function testValidateEntityForTransitionSuccess(): void
    {
        $entity = [
            'fa_debtor_no' => 10,
            'fa_branch_ref' => 'BR001',
        ];

        $errors = $this->stateMachine->validateEntityForStatus(
            StagingStatusInterface::STATUS_MATCHED,
            $entity
        );

        $this->assertEmpty($errors);
    }

    public function testValidateEntityForTransitionFailure(): void
    {
        $entity = [
            'fa_debtor_no' => 10,
            'fa_branch_ref' => null,
        ];

        $errors = $this->stateMachine->validateEntityForStatus(
            StagingStatusInterface::STATUS_MATCHED,
            $entity
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('fa_branch_ref', $errors[0]);
    }

    public function testGetStatusDescription(): void
    {
        $desc = $this->stateMachine->getStagingStatusDescription(StagingStatusInterface::STATUS_STAGED);
        $this->assertStringContainsString('Staged', $desc);

        $desc = $this->stateMachine->getStagingStatusDescription(StagingStatusInterface::STATUS_IMPORTED);
        $this->assertStringContainsString('Imported', $desc);
    }

    public function testGetEntityTypes(): void
    {
        $types = $this->stateMachine->getEntityTypes();
        $this->assertContains(WooSyncStateMachine::ENTITY_CUSTOMER, $types);
        $this->assertContains(WooSyncStateMachine::ENTITY_ORDER, $types);
    }

    public function testFullCustomerWorkflow(): void
    {
        $this->assertTrue($this->stateMachine->transition(
            StagingStatusInterface::STATUS_STAGED,
            StagingStatusInterface::STATUS_MATCHED,
            [],
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));
        $this->assertTrue($this->stateMachine->transition(
            StagingStatusInterface::STATUS_MATCHED,
            StagingStatusInterface::STATUS_IN_PROGRESS,
            [],
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));
        $this->assertTrue($this->stateMachine->transition(
            StagingStatusInterface::STATUS_IN_PROGRESS,
            StagingStatusInterface::STATUS_COMPLETED,
            [],
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));
        $this->assertTrue($this->stateMachine->transition(
            StagingStatusInterface::STATUS_COMPLETED,
            StagingStatusInterface::STATUS_IMPORTED,
            [],
            WooSyncStateMachine::ENTITY_CUSTOMER
        ));

        $history = $this->stateMachine->getTransitionHistory();
        $this->assertCount(4, $history);
    }

    public function testErrorCanTransitionBackToStaged(): void
    {
        $this->assertTrue(
            $this->stateMachine->canTransition(
                StagingStatusInterface::STATUS_ERROR,
                StagingStatusInterface::STATUS_STAGED,
                WooSyncStateMachine::ENTITY_CUSTOMER
            )
        );
    }

    public function testCannotTransitionFromImported(): void
    {
        $transitions = $this->stateMachine->getValidTransitions(
            StagingStatusInterface::STATUS_IMPORTED,
            WooSyncStateMachine::ENTITY_CUSTOMER
        );

        $this->assertEmpty($transitions);
    }

    public function testInterfaceStaticMethods(): void
    {
        $this->assertEquals(StagingStatusInterface::STATUS_STAGED, WooSyncStateMachine::getInitialStagingStatus());
        $this->assertEquals(StagingStatusInterface::STATUS_IMPORTED, WooSyncStateMachine::getFinalStagingStatus());
        $this->assertTrue(WooSyncStateMachine::isImportable(StagingStatusInterface::STATUS_MATCHED));
        $this->assertFalse(WooSyncStateMachine::isImportable(StagingStatusInterface::STATUS_STAGED));
    }

    public function testIsFinalStatus(): void
    {
        $this->assertTrue(WooSyncStateMachine::isFinalStatus(StagingStatusInterface::STATUS_IMPORTED));
        $this->assertTrue(WooSyncStateMachine::isFinalStatus(StagingStatusInterface::STATUS_COMPLETED));
        $this->assertFalse(WooSyncStateMachine::isFinalStatus(StagingStatusInterface::STATUS_STAGED));
    }

    public function testIsErrorStatus(): void
    {
        $this->assertTrue(WooSyncStateMachine::isErrorStatus(StagingStatusInterface::STATUS_ERROR));
        $this->assertTrue(WooSyncStateMachine::isErrorStatus(StagingStatusInterface::STATUS_FAILED));
        $this->assertFalse(WooSyncStateMachine::isErrorStatus(StagingStatusInterface::STATUS_PENDING));
    }

    public function testCanRetry(): void
    {
        $this->assertTrue(WooSyncStateMachine::canRetry(StagingStatusInterface::STATUS_ERROR));
        $this->assertTrue(WooSyncStateMachine::canRetry(StagingStatusInterface::STATUS_FAILED));
        $this->assertFalse(WooSyncStateMachine::canRetry(StagingStatusInterface::STATUS_PENDING));
    }

    public function testRequiresAction(): void
    {
        $this->assertTrue(WooSyncStateMachine::requiresAction(StagingStatusInterface::STATUS_STAGED));
        $this->assertTrue(WooSyncStateMachine::requiresAction(StagingStatusInterface::STATUS_PENDING_REVIEW));
        $this->assertFalse(WooSyncStateMachine::requiresAction(StagingStatusInterface::STATUS_IMPORTED));
    }
}