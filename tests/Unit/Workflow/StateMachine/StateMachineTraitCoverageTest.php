<?php
namespace Ksfraser\frontaccounting\Woocommerce\Tests\Unit\Workflow\StateMachine;

use Ksfraser\frontaccounting\Woocommerce\Workflow\StateMachine\StateMachineInterface;
use Ksfraser\frontaccounting\Woocommerce\Workflow\StateMachine\StateMachineTrait;
use PHPUnit\Framework\TestCase;

class TestStateMachine implements StateMachineInterface
{
    use StateMachineTrait;

    public function getValidTransitions(string $currentStatus, ?string $entityType = null): array
    {
        return [
            'pending' => ['in_progress', 'error'],
            'in_progress' => ['completed', 'error'],
            'completed' => [],
            'error' => ['pending'],
        ][$currentStatus] ?? [];
    }

    public function getFinalStatus(): string
    {
        return 'completed';
    }
}

class StateMachineTraitCoverageTest extends TestCase
{
    private TestStateMachine $stateMachine;

    protected function setUp(): void
    {
        $this->stateMachine = new TestStateMachine();
    }

    public function testCanTransitionValid(): void
    {
        $this->assertTrue($this->stateMachine->canTransition('pending', 'in_progress'));
    }

    public function testCanTransitionInvalid(): void
    {
        $this->assertFalse($this->stateMachine->canTransition('pending', 'completed'));
    }

    public function testTransitionValidRecordsHistory(): void
    {
        $result = $this->stateMachine->transition('pending', 'in_progress', ['test' => true]);

        $this->assertTrue($result);

        $history = $this->stateMachine->getTransitionHistory();
        $this->assertCount(1, $history);
        $this->assertEquals('pending', $history[0]['from_status']);
        $this->assertEquals('in_progress', $history[0]['to_status']);
        $this->assertEquals(['test' => true], $history[0]['context']);
        $this->assertArrayHasKey('timestamp', $history[0]);
    }

    public function testTransitionInvalidSetsError(): void
    {
        $result = $this->stateMachine->transition('completed', 'pending');

        $this->assertFalse($result);
        $this->assertNotNull($this->stateMachine->getLastError());
        $this->assertStringContainsString('Invalid transition from completed to pending', $this->stateMachine->getLastError());
    }

    public function testTransitionClearsPreviousError(): void
    {
        $this->stateMachine->transition('completed', 'pending');
        $this->assertNotNull($this->stateMachine->getLastError());

        $this->stateMachine->transition('pending', 'in_progress');
        $this->assertNull($this->stateMachine->getLastError());
    }

    public function testIsFinalStateTrue(): void
    {
        $this->assertTrue($this->stateMachine->isFinalState('completed'));
    }

    public function testIsFinalStateFalse(): void
    {
        $this->assertFalse($this->stateMachine->isFinalState('pending'));
        $this->assertFalse($this->stateMachine->isFinalState('in_progress'));
    }

    public function testRecordTransitionDirectly(): void
    {
        $method = new \ReflectionMethod(TestStateMachine::class, 'recordTransition');
        $method->setAccessible(true);
        $method->invoke($this->stateMachine, 'pending', 'in_progress', ['direct' => true]);

        $history = $this->stateMachine->getTransitionHistory();
        $this->assertCount(1, $history);
        $this->assertEquals('pending', $history[0]['from_status']);
        $this->assertEquals('in_progress', $history[0]['to_status']);
        $this->assertEquals(['direct' => true], $history[0]['context']);
    }

    public function testSetErrorDirectly(): void
    {
        $method = new \ReflectionMethod(TestStateMachine::class, 'setError');
        $method->setAccessible(true);
        $method->invoke($this->stateMachine, 'Test error');

        $this->assertEquals('Test error', $this->stateMachine->getLastError());
    }

    public function testGetTransitionHistoryEmpty(): void
    {
        $this->assertEmpty($this->stateMachine->getTransitionHistory());
    }

    public function testClearHistory(): void
    {
        $this->stateMachine->transition('pending', 'in_progress');
        $this->assertCount(1, $this->stateMachine->getTransitionHistory());

        $this->stateMachine->clearHistory();
        $this->assertEmpty($this->stateMachine->getTransitionHistory());
    }

    public function testMultipleTransitions(): void
    {
        $this->stateMachine->transition('pending', 'in_progress');
        $this->stateMachine->transition('in_progress', 'completed');

        $history = $this->stateMachine->getTransitionHistory();
        $this->assertCount(2, $history);
    }

    public function testTransitionWithEmptyContext(): void
    {
        $result = $this->stateMachine->transition('pending', 'in_progress', []);
        $this->assertTrue($result);

        $history = $this->stateMachine->getTransitionHistory();
        $this->assertEquals([], $history[0]['context']);
    }

    public function testGetLastErrorInitiallyNull(): void
    {
        $this->assertNull($this->stateMachine->getLastError());
    }
}
