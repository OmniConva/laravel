<?php

namespace OmniConva\Laravel\Http\Controllers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use OmniConva\Laravel\Data\OrderData;
use OmniConva\Laravel\Idempotency\IdempotencyStore;
use OmniConva\Laravel\OmniConva;
use Throwable;

class OrdersController
{
    public function __construct(
        private readonly OmniConva $omniconva,
        private readonly IdempotencyStore $idempotency,
        private readonly ConnectionInterface $db,
        private readonly ExceptionHandler $exceptions,
    ) {}

    /**
     * POST {prefix}/orders. Creates the order once per Idempotency-Key:
     * 201 with {order_id, order_number}; a retry of a finished order gets
     * the same body with 200; one still being created gets 503 so OmniConva
     * tries again shortly. 422 when the order can't be created (the message
     * shows in OmniConva's connection logs).
     */
    public function store(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return new JsonResponse(['message' => 'The body must be a JSON object'], 422);
        }
        try {
            $order = OrderData::fromPayload($payload);
        } catch (InvalidArgumentException $error) {
            return new JsonResponse(['message' => $error->getMessage()], 422);
        }

        if ($order->test && ! $this->omniconva->config('pass_test_orders')) {
            return new JsonResponse([
                'order_id' => 'test-'.substr(hash('sha256', $order->id), 0, 12),
                'order_number' => null,
                'test' => true,
            ], 201);
        }

        $key = (string) ($request->header('Idempotency-Key') ?: $order->id);
        $claim = $this->idempotency->claim($key);
        if ($claim['state'] === 'done') {
            return new JsonResponse($claim['response'], 200);
        }
        if ($claim['state'] === 'busy') {
            return new JsonResponse(
                ['message' => 'This order is being created; retry shortly.'],
                503,
                ['Retry-After' => '30'],
            );
        }

        try {
            $response = $this->db->transaction(function () use ($order, $key) {
                $response = $this->omniconva->orders()->createOrder($order)->toArray();
                $this->idempotency->complete($key, $response);

                return $response;
            });
        } catch (Throwable $error) {
            $this->idempotency->release($key);
            $this->exceptions->report($error);

            return new JsonResponse(['message' => $error->getMessage() ?: 'The order could not be created'], 422);
        }

        return new JsonResponse($response, 201);
    }
}
