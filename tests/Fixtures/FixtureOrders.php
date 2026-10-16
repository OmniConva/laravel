<?php

namespace OmniConva\Laravel\Tests\Fixtures;

use OmniConva\Laravel\Contracts\OrderHandler;
use OmniConva\Laravel\Data\OrderData;
use OmniConva\Laravel\Data\OrderResult;
use OmniConva\Laravel\Exceptions\OrderRefused;
use RuntimeException;

class FixtureOrders implements OrderHandler
{
    /** @var list<OrderData> */
    public static array $created = [];

    public static function reset(): void
    {
        self::$created = [];
    }

    public function createOrder(OrderData $order): OrderResult
    {
        if ($order->customer->name === 'Refuse Me') {
            throw new OrderRefused('Out of delivery area');
        }
        if ($order->customer->name === 'Break Me') {
            throw new RuntimeException('insert into orders (phone) values ("017") — connection refused 10.0.0.5:5432');
        }
        self::$created[] = $order;

        return OrderResult::make('W-'.(1000 + count(self::$created)), (string) (5000 + count(self::$created)));
    }
}
