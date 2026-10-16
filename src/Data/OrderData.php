<?php

namespace OmniConva\Laravel\Data;

use InvalidArgumentException;

/**
 * An order OmniConva sends to your OrderHandler: cash on delivery, unpaid,
 * exactly as the customer agreed it in chat. Money values are decimal
 * strings in `currency`.
 */
class OrderData
{
    /**
     * @param  list<OrderItem>  $items
     * @param  list<OrderFee>  $fees
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        // OmniConva's id for the order (also the Idempotency-Key).
        public readonly string $id,
        // The number the customer was told, e.g. "1042".
        public readonly ?string $number,
        public readonly bool $test,
        public readonly ?string $currency,
        public readonly OrderCustomer $customer,
        public readonly OrderAddress $shippingAddress,
        public readonly array $items,
        public readonly string $subtotal,
        public readonly string $discount,
        public readonly ?OrderShipping $shipping,
        public readonly array $fees,
        public readonly string $total,
        public readonly string $paymentMethod,
        public readonly string $paymentStatus,
        public readonly ?string $note,
        public readonly array $raw,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public static function fromPayload(array $payload): self
    {
        $id = self::str($payload['id'] ?? null);
        if ($id === null) {
            throw new InvalidArgumentException('The order has no id');
        }
        $items = array_values(array_filter(array_map(
            fn ($item) => is_array($item) ? OrderItem::fromPayload($item) : null,
            is_array($payload['items'] ?? null) ? $payload['items'] : [],
        )));
        if ($items === []) {
            throw new InvalidArgumentException('The order has no items');
        }
        $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
        $address = is_array($payload['shipping_address'] ?? null) ? $payload['shipping_address'] : [];
        $shipping = is_array($payload['shipping'] ?? null) ? $payload['shipping'] : null;
        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];

        return new self(
            id: $id,
            number: self::str($payload['order_number'] ?? null),
            test: ($payload['test'] ?? false) === true,
            currency: self::str($payload['currency'] ?? null),
            customer: new OrderCustomer(
                name: self::str($customer['name'] ?? null) ?? '',
                phone: self::str($customer['phone'] ?? null) ?? '',
            ),
            shippingAddress: new OrderAddress(
                address: self::str($address['address'] ?? null) ?? '',
                city: self::str($address['city'] ?? null),
                state: self::str($address['state'] ?? null),
                postcode: self::str($address['postcode'] ?? null),
                country: self::str($address['country'] ?? null),
            ),
            items: $items,
            subtotal: Money::format($payload['subtotal'] ?? null) ?? '0.00',
            discount: Money::format($payload['discount'] ?? null) ?? '0.00',
            shipping: $shipping === null ? null : new OrderShipping(
                title: self::str($shipping['title'] ?? null) ?? 'Delivery',
                amount: Money::format($shipping['amount'] ?? null) ?? '0.00',
            ),
            fees: array_values(array_map(
                fn ($fee) => new OrderFee(
                    name: self::str($fee['name'] ?? null) ?? 'Fee',
                    amount: Money::format($fee['amount'] ?? null) ?? '0.00',
                ),
                array_filter(is_array($payload['fees'] ?? null) ? $payload['fees'] : [], 'is_array'),
            )),
            total: Money::format($payload['total'] ?? null) ?? '0.00',
            paymentMethod: self::str($payment['method'] ?? null) ?? 'cash_on_delivery',
            paymentStatus: self::str($payment['status'] ?? null) ?? 'unpaid',
            note: self::str($payload['note'] ?? null),
            raw: $payload,
        );
    }

    /** @internal */
    public static function str(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
