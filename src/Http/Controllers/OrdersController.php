<?php

namespace OmniConva\Laravel\Http\Controllers;

use DomainException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use OmniConva\Laravel\Data\OrderData;
use OmniConva\Laravel\Exceptions\OrderRefused;
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
     * tries again shortly. 422 when your OrderHandler refuses the order
     * (an OrderRefused exception — its message shows in OmniConva), 500
     * with a generic message when it fails for any other reason.
     */
    public function store(Request $request): JsonResponse
    {
        // Big numeric ids stay strings instead of turning into floats.
        $payload = json_decode($request->getContent(), true, 512, JSON_BIGINT_AS_STRING);
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
        $owner = $claim['owner'];

        try {
            $response = $this->db->transaction(function () use ($order, $key, $owner) {
                $response = $this->omniconva->orders()->createOrder($order)->toArray();
                $this->idempotency->complete($key, $response, $owner);

                return $response;
            });
        } catch (Throwable $error) {
            $this->idempotency->release($key, $owner);
            $this->exceptions->report($error);

            // A refusal is for the store owner's eyes. Anything else — a
            // QueryException carries the SQL, bound customer data and table
            // names in its message — is answered generically; OmniConva keeps
            // the response text in its connection logs, which every dashboard
            // user can read.
            if ($error instanceof OrderRefused || $error instanceof DomainException) {
                return new JsonResponse(['message' => $error->getMessage() ?: 'The order was refused'], 422);
            }

            return new JsonResponse(['message' => 'The order could not be created'], 500);
        }

        return new JsonResponse($response, 201);
    }
}
