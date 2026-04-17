<?php

namespace Tests\Unit;

use App\Support\OrderStatusTransition;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    public function test_happy_path_transitions_are_allowed(): void
    {
        $transition = new OrderStatusTransition();

        $this->assertTrue($transition->canTransition('pending_payment', 'paid'));
        $this->assertTrue($transition->canTransition('paid', 'packed'));
        $this->assertTrue($transition->canTransition('packed', 'shipped'));
        $this->assertTrue($transition->canTransition('shipped', 'completed'));
    }

    public function test_skip_transition_is_rejected(): void
    {
        $transition = new OrderStatusTransition();

        $this->assertFalse($transition->canTransition('paid', 'shipped'));
        $this->assertFalse($transition->canTransition('pending_payment', 'completed'));
    }

    public function test_assert_transition_throws_validation_error_for_invalid_move(): void
    {
        $transition = new OrderStatusTransition();

        try {
            $transition->assertTransition('paid', 'shipped');
            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'order_status' => ['Order harus packed sebelum shipped.'],
            ], $exception->errors());
        }
    }
}
