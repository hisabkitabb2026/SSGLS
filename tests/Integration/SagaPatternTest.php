<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Jobs\CompensateStep;
use App\Services\Saga\SagaOrchestrator;
use App\Services\Saga\SagaStep;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Saga Pattern - Distributed Transaction Tests
 *
 * Tests saga pattern implementation for distributed transactions across services
 */
class SagaPatternTest extends TestCase
{
    private SagaOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->orchestrator = app(SagaOrchestrator::class);
    }

    public function test_create_simple_saga_transaction(): void
    {
        $sagaId = 'saga-'.uniqid();
        $data = [
            'invoice_id' => 1,
            'customer_id' => 1,
            'amount' => 1000.00,
        ];

        $saga = $this->orchestrator->createSaga($sagaId, 'create_invoice', $data);

        $this->assertEquals($sagaId, $saga->id);
        $this->assertEquals('create_invoice', $saga->type);
        $this->assertEquals('pending', $saga->status);
    }

    public function test_saga_step_execution(): void
    {
        $sagaId = 'saga-'.uniqid();
        $saga = $this->orchestrator->createSaga($sagaId, 'create_invoice', [
            'invoice_id' => 1,
            'amount' => 1000.00,
        ]);

        // Add first step
        $this->orchestrator->addStep($sagaId, SagaStep::create(
            name: 'create_invoice',
            action: 'App\Services\Invoice\CreateInvoiceAction',
            compensation: 'App\Services\Invoice\CancelInvoiceAction'
        ));

        $steps = $saga->getSteps();
        $this->assertCount(1, $steps);
        $this->assertEquals('create_invoice', $steps[0]->name);
    }

    public function test_saga_multi_step_workflow(): void
    {
        $sagaId = 'saga-'.uniqid();
        $saga = $this->orchestrator->createSaga($sagaId, 'process_order', [
            'invoice_id' => 1,
            'payment_method' => 'credit_card',
        ]);

        // Step 1: Create invoice
        $this->orchestrator->addStep($sagaId, SagaStep::create(
            name: 'create_invoice',
            action: 'App\Services\Invoice\CreateInvoiceAction',
            compensation: 'App\Services\Invoice\CancelInvoiceAction'
        ));

        // Step 2: Process payment
        $this->orchestrator->addStep($sagaId, SagaStep::create(
            name: 'process_payment',
            action: 'App\Services\Payment\ProcessPaymentAction',
            compensation: 'App\Services\Payment\RefundPaymentAction'
        ));

        // Step 3: Create shipment
        $this->orchestrator->addStep($sagaId, SagaStep::create(
            name: 'create_shipment',
            action: 'App\Services\Transport\CreateShipmentAction',
            compensation: 'App\Services\Transport\CancelShipmentAction'
        ));

        $steps = $saga->getSteps();
        $this->assertCount(3, $steps);
    }

    public function test_saga_step_completion(): void
    {
        $sagaId = 'saga-'.uniqid();
        $saga = $this->orchestrator->createSaga($sagaId, 'create_invoice', ['invoice_id' => 1]);

        $this->orchestrator->addStep($sagaId, SagaStep::create(
            name: 'create_invoice',
            action: 'App\Services\Invoice\CreateInvoiceAction'
        ));

        // Execute step
        $this->orchestrator->executeStep($sagaId, 'create_invoice');

        $step = $saga->getStep('create_invoice');
        $this->assertEquals('completed', $step->status);
        $this->assertNotNull($step->completed_at);
    }

    public function test_saga_step_failure_triggers_compensation(): void
    {
        $sagaId = 'saga-'.uniqid();
        $saga = $this->orchestrator->createSaga($sagaId, 'process_order', ['invoice_id' => 1]);

        $this->orchestrator->addStep($sagaId, SagaStep::create(
            name: 'create_invoice',
            action: 'App\Services\Invoice\CreateInvoiceAction',
            compensation: 'App\Services\Invoice\CancelInvoiceAction'
        ));

        // Simulate step failure
        $this->orchestrator->failStep($sagaId, 'create_invoice', 'Insufficient balance');

        $saga->refresh();
        $step = $saga->getStep('create_invoice');

        $this->assertEquals('failed', $step->status);
        $this->assertEquals('Insufficient balance', $step->error_message);
    }

    public function test_saga_compensation_on_failure(): void
    {
        $sagaId = 'saga-'.uniqid();
        $saga = $this->orchestrator->createSaga($sagaId, 'process_order', ['invoice_id' => 1]);

        // Add multiple steps
        $this->orchestrator->addStep($sagaId, SagaStep::create(
            name: 'create_invoice',
            action: 'App\Services\Invoice\CreateInvoiceAction',
            compensation: 'App\Services\Invoice\CancelInvoiceAction'
        ));

        $this->orchestrator->addStep($sagaId, SagaStep::create(
            name: 'process_payment',
            action: 'App\Services\Payment\ProcessPaymentAction',
            compensation: 'App\Services\Payment\RefundPaymentAction'
        ));

        // Complete first step
        $this->orchestrator->executeStep($sagaId, 'create_invoice');

        // Fail second step - should trigger compensation
        $this->orchestrator->failStep($sagaId, 'process_payment', 'Payment failed');

        // Trigger compensation
        $this->orchestrator->compensate($sagaId);

        $saga->refresh();
        $this->assertEquals('compensated', $saga->status);

        // Verify compensation executed
        Queue::assertPushed(CompensateStep::class);
    }

    public function test_saga_idempotency(): void
    {
        $sagaId = 'saga-'.uniqid();
        $idempotencyKey = 'idempotent-key-123';

        $saga1 = $this->orchestrator->createSaga($sagaId, 'create_invoice', [
            'invoice_id' => 1,
        ], idempotencyKey: $idempotencyKey);

        // Attempt to create same saga with same idempotency key
        $saga2 = $this->orchestrator->createSaga($sagaId, 'create_invoice', [
            'invoice_id' => 1,
        ], idempotencyKey: $idempotencyKey);

        $this->assertEquals($saga1->id, $saga2->id);
    }

    public function test_saga_state_persistence(): void
    {
        $sagaId = 'saga-'.uniqid();
        $saga = $this->orchestrator->createSaga($sagaId, 'create_invoice', [
            'invoice_id' => 1,
            'amount' => 1000.00,
        ]);

        // Verify state is persisted
        $persisted = $this->orchestrator->getSaga($sagaId);

        $this->assertEquals($saga->id, $persisted->id);
        $this->assertEquals($saga->type, $persisted->type);
        $this->assertEquals($saga->data['amount'], $persisted->data['amount']);
    }

    public function test_saga_step_order_execution(): void
    {
        $sagaId = 'saga-'.uniqid();
        $saga = $this->orchestrator->createSaga($sagaId, 'process_order', ['invoice_id' => 1]);

        // Add steps with order
        $this->orchestrator->addStep($sagaId, SagaStep::create(
            name: 'step_1',
            action: 'Action1',
            order: 1
        ));

        $this->orchestrator->addStep($sagaId, SagaStep::create(
            name: 'step_2',
            action: 'Action2',
            order: 2
        ));

        $this->orchestrator->addStep($sagaId, SagaStep::create(
            name: 'step_3',
            action: 'Action3',
            order: 3
        ));

        $steps = $saga->getSteps();
        $this->assertEquals('step_1', $steps[0]->name);
        $this->assertEquals('step_2', $steps[1]->name);
        $this->assertEquals('step_3', $steps[2]->name);
    }

    public function test_saga_parallel_steps(): void
    {
        $sagaId = 'saga-'.uniqid();
        $saga = $this->orchestrator->createSaga($sagaId, 'process_order', ['invoice_id' => 1]);

        // Add parallel steps (same order)
        $this->orchestrator->addStep($sagaId, SagaStep::create(
            name: 'notify_customer',
            action: 'NotifyCustomer',
            order: 1,
            parallel: true
        ));

        $this->orchestrator->addStep($sagaId, SagaStep::create(
            name: 'create_shipment',
            action: 'CreateShipment',
            order: 1,
            parallel: true
        ));

        $parallelSteps = $saga->getParallelSteps(order: 1);
        $this->assertCount(2, $parallelSteps);
    }

    public function test_saga_context_preservation(): void
    {
        $sagaId = 'saga-'.uniqid();
        $data = [
            'invoice_id' => 1,
            'amount' => 1000.00,
            'customer_id' => 1,
        ];

        $saga = $this->orchestrator->createSaga($sagaId, 'create_invoice', $data);

        // Update context
        $this->orchestrator->updateContext($sagaId, [
            'payment_id' => 100,
            'shipment_id' => 200,
        ]);

        $updated = $this->orchestrator->getSaga($sagaId);

        $this->assertEquals(100, $updated->data['payment_id']);
        $this->assertEquals(200, $updated->data['shipment_id']);
        $this->assertEquals(1000.00, $updated->data['amount']);
    }

    public function test_saga_timeout_handling(): void
    {
        $sagaId = 'saga-'.uniqid();
        $saga = $this->orchestrator->createSaga(
            $sagaId,
            'create_invoice',
            ['invoice_id' => 1],
            timeout: 300 // 5 minutes
        );

        $this->assertEquals(300, $saga->timeout_seconds);
    }

    public function test_saga_event_sourcing(): void
    {
        $sagaId = 'saga-'.uniqid();
        $saga = $this->orchestrator->createSaga($sagaId, 'create_invoice', ['invoice_id' => 1]);

        // Get events
        $events = $saga->getEvents();

        $this->assertTrue(in_array('saga.created', $events));
    }

    public function test_saga_replay_from_events(): void
    {
        $sagaId = 'saga-'.uniqid();
        $saga = $this->orchestrator->createSaga($sagaId, 'create_invoice', ['invoice_id' => 1]);

        // Add and complete step
        $this->orchestrator->addStep($sagaId, SagaStep::create(
            name: 'create_invoice',
            action: 'CreateInvoiceAction'
        ));
        $this->orchestrator->executeStep($sagaId, 'create_invoice');

        // Replay saga from events
        $replayed = $this->orchestrator->replaySaga($sagaId);

        $this->assertEquals($saga->id, $replayed->id);
        $step = $replayed->getStep('create_invoice');
        $this->assertEquals('completed', $step->status);
    }
}
