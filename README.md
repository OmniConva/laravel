# OmniConva for Laravel

Connect your Laravel store to [OmniConva](https://omniconva.com), the AI sales agent that answers your customers on your website, Messenger, Instagram and WhatsApp, and takes their orders.

This package does the integration work for you:

- **Products API.** OmniConva reads your catalog from routes the package registers.
- **Order API.** Orders customers confirm in chat land in your store, once each, even when they're retried.
- **Webhooks.** Saving or deleting a product updates OmniConva automatically. Order status updates let the AI answer "where is my order?".
- **Security.** Every request from OmniConva is signature-checked, and every webhook you send is signed.
- **Chat widget.** One Blade component.

You write two small classes: which products to share, and how to save an order.

**Requirements:** PHP 8.2+, Laravel 12 or 13.

## Install

```bash
composer require omniconva/laravel
php artisan omniconva:install
php artisan migrate
```

`omniconva:install` publishes `config/omniconva.php` and a migration, creates `app/OmniConva/Products.php` and `app/OmniConva/Orders.php`, and writes your credentials to `.env`:

```dotenv
OMNICONVA_WEBHOOK_URL=https://api.omniconva.com/webhooks/custom-website/...
OMNICONVA_WEBHOOK_TOKEN=...
OMNICONVA_SIGNING_SECRET=...
OMNICONVA_SITE_KEY=...          # optional, for the chat widget
```

To get them, in OmniConva open **Settings → Connection → Custom website**, choose **Laravel package**, and press **Create credentials**.

## 1. Share your products

`app/OmniConva/Products.php` says which products OmniConva may sell and how each one maps:

```php
namespace App\OmniConva;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use OmniConva\Laravel\Data\ProductData;
use OmniConva\Laravel\Data\VariantData;
use OmniConva\Laravel\Sources\EloquentProductSource;

class Products extends EloquentProductSource
{
    public function query(): Builder
    {
        // Only what OmniConva may sell; eager-load what map() reads.
        return Product::query()->where('is_active', true)->with(['variants', 'images', 'categories']);
    }

    public function map(Model $product): ProductData
    {
        return ProductData::make($product->id, $product->name)
            ->url(route('products.show', $product))
            ->description($product->description)
            ->sku($product->sku)
            ->price($product->price)
            ->salePrice($product->sale_price)       // only counts when below the price
            ->stockQuantity($product->stock)        // or ->inStock(true) if you don't count stock
            ->images($product->images->pluck('url'))
            ->categories($product->categories->pluck('name'))
            ->brand($product->brand)
            ->variants($product->variants->map(
                fn ($variant) => VariantData::make($variant->id, $variant->name)
                    ->sku($variant->sku)
                    ->price($variant->price)
                    ->stockQuantity($variant->stock)
                    ->image($variant->image_url)
                    ->options(['Size' => $variant->size, 'Color' => $variant->color])
            ))
            ->updatedAt($product->updated_at);
    }
}
```

**Keep these rules in mind:**
- Ids must never change, and variants need ids of their own.
- Images must be absolute `https://` URLs.
- `status('draft')` or `active(false)` keeps a product listed but stops the AI selling it.
- A product filtered out of `query()` isn't shared at all.

Not using Eloquent? Implement `OmniConva\Laravel\Contracts\ProductSource` (`paginate()` and `find()`) yourself.

## 2. Save orders

`app/OmniConva/Orders.php` creates the order in your store:

```php
namespace App\OmniConva;

use App\Models\Order;
use OmniConva\Laravel\Contracts\OrderHandler;
use OmniConva\Laravel\Data\OrderData;
use OmniConva\Laravel\Data\OrderResult;

class Orders implements OrderHandler
{
    public function createOrder(OrderData $order): OrderResult
    {
        $saved = Order::create([
            'customer_name' => $order->customer->name,
            'phone' => $order->customer->phone,
            'address' => $order->shippingAddress->address,
            'city' => $order->shippingAddress->city,
            'shipping' => $order->shipping?->amount ?? 0,
            'discount' => $order->discount,
            'total' => $order->total,
            'payment_method' => 'cod',
        ]);

        foreach ($order->items as $item) {
            $saved->items()->create([
                'product_id' => $item->productId,   // your product id
                'variant_id' => $item->variantId,   // your variant id, or null
                'quantity' => $item->quantity,
                'price' => $item->unitPrice,
            ]);
        }

        return OrderResult::make($saved->id, $saved->number);
    }
}
```

**How orders behave:**
- **Once per order.** `createOrder()` runs once per order. If OmniConva retries after a timeout, the package answers with the first result.
- **One transaction.** It runs inside a database transaction together with that bookkeeping.
- **Refusing an order.** Throw an exception to refuse an order, for example when the address is outside your delivery area. The message is shown to the store owner in OmniConva, and the order can be retried.
- **The id you return.** The first argument of `OrderResult::make()` is the order reference the customer is told in chat, and the one `OmniConva::orderUpdated()` uses, so return what your customers know (your id or your order number).
- **Test orders.** Test orders sent from the OmniConva dashboard (`$order->test`) are answered without calling your class. Set `'pass_test_orders' => true` to receive them.

## 3. Keep the catalog in sync

```php
use OmniConva\Laravel\Concerns\SyncsWithOmniConva;

class Product extends Model
{
    use SyncsWithOmniConva;
}
```

Every save sends `product.updated`, mapped with your `Products::map()`, and every delete sends `product.deleted`. A product that falls out of your `query()`, for example one deactivated, is sent as deleted.

Webhooks go through your queue and are dispatched after the database commits, so a rolled-back save sends nothing. They are signed and retried with backoff. Override `shouldSyncToOmniConva()` to skip some changes.

For a variant, price or stock model, re-send the parent product:

```php
use OmniConva\Laravel\Concerns\SyncsParentWithOmniConva;

class ProductVariant extends Model
{
    use SyncsParentWithOmniConva; // uses $this->product; override omniConvaParent() otherwise
}
```

Changes made outside Eloquent, such as bulk updates or imports, can be sent by hand:

```php
use OmniConva\Laravel\Facades\OmniConva;

OmniConva::productUpdated($product);   // a model, or a ProductData
OmniConva::productDeleted($productId);
```

## 4. Order status (optional)

```php
use OmniConva\Laravel\Data\Tracking;
use OmniConva\Laravel\Facades\OmniConva;

OmniConva::orderUpdated($order->id, 'shipped', Tracking::make('TRK123', 'Pathao', $trackingUrl));
```

Use the order id your `Orders` class returned. Statuses are your own words: `confirmed`, `processing`, `shipped`, `delivered`, `cancelled` and so on. OmniConva shows them on its Orders page, and the AI uses them to answer customers.

## 5. Chat widget

```blade
<x-omniconva-chat />
```

This renders nothing until `OMNICONVA_SITE_KEY` is set. With `OMNICONVA_IDENTITY_SECRET` set, the signed-in user is sent along with an HMAC, so the AI and your agents know who they're talking to.

To choose the fields yourself:

```blade
<x-omniconva-chat :customer="['id' => $user->id, 'name' => $user->name, 'phone' => $user->phone]" />
```

## Check it

```bash
php artisan omniconva:test   # credentials, your classes, the routes (signed), and a ping to OmniConva
php artisan omniconva:ping   # only the webhook token and signing secret
```

Then, in OmniConva, press **Check package**, then **Test products**, then **Connect website**.

## Routes

The package registers these routes, all of which require a valid `X-OmniConva-Signature`:

| Method | Path | |
|---|---|---|
| GET | `/omniconva/products?page=&per_page=&updated_since=` | Your catalog, page by page |
| GET | `/omniconva/products/{id}` | One product (404 when gone) |
| POST | `/omniconva/orders` | Create an order |
| GET | `/omniconva/health` | Package and Laravel version, for OmniConva's **Check package** |

To move them, change `routes.prefix` (or `OMNICONVA_ROUTE_PREFIX`) and the **Route prefix** in OmniConva. They use the `api` middleware group by default.

## Configuration

See [`config/omniconva.php`](config/omniconva.php):
- route prefix and middleware
- signature tolerance
- queue connection and name for webhooks
- default currency
- idempotency table
- widget settings

## How it's secured

- **Requests from OmniConva** carry `X-OmniConva-Signature: t=<unix>,v1=<hex>`, an HMAC-SHA256 of `"<t>.<METHOD>.<path?query>.<body>"` made with your signing secret. Requests with a missing or wrong signature, or one older than 5 minutes, get `401`.
- **Webhooks you send** carry `Authorization: Bearer <token>` and an HMAC of `"<t>.<body>"`.
- **Keep the token and secret out of version control.** If one leaks, rotate it in OmniConva and update `.env`.

## Testing

```bash
composer test
```

## Releasing (maintainers)

1. Bump `OmniConva::VERSION` in `src/OmniConva.php`, add a CHANGELOG entry, and commit.
2. Create a GitHub release with the tag `vX.Y.Z`. Release tags can only be created by repository admins, and releases are immutable once published.
3. The `release` workflow checks that the tag matches `OmniConva::VERSION` and has a changelog entry, then runs the full test matrix.
4. Approve the **packagist** deployment in the workflow run (GitHub asks the required reviewer). Only then is Packagist told to update.

## License

MIT. See [LICENSE](LICENSE).
