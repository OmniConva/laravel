<?php

namespace OmniConva\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool configured()
 * @method static \OmniConva\Laravel\Contracts\ProductSource products()
 * @method static \OmniConva\Laravel\Contracts\OrderHandler orders()
 * @method static \OmniConva\Laravel\Data\ProductData|null mapProduct(\Illuminate\Database\Eloquent\Model $product)
 * @method static void productUpdated(\Illuminate\Database\Eloquent\Model|\OmniConva\Laravel\Data\ProductData $product)
 * @method static void productDeleted(int|string|\Illuminate\Database\Eloquent\Model $product)
 * @method static void orderUpdated(int|string $orderId, string $status, ?\OmniConva\Laravel\Data\Tracking $tracking = null, ?string $note = null)
 * @method static array ping()
 * @method static void send(string $topic, array $payload)
 * @method static \OmniConva\Laravel\Signature\Signer signer()
 *
 * @see \OmniConva\Laravel\OmniConva
 */
class OmniConva extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \OmniConva\Laravel\OmniConva::class;
    }
}
