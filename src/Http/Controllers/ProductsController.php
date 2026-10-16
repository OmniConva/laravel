<?php

namespace OmniConva\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OmniConva\Laravel\OmniConva;
use Throwable;

class ProductsController
{
    public function __construct(private readonly OmniConva $omniconva) {}

    /** GET {prefix}/products?page=1&per_page=50[&updated_since=ISO-8601] */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min(100, max(1, (int) $request->query('per_page', '50')));
        $since = null;
        if (is_string($request->query('updated_since')) && $request->query('updated_since') !== '') {
            try {
                $since = Carbon::parse($request->query('updated_since'));
            } catch (Throwable) {
                return new JsonResponse(['message' => 'updated_since must be an ISO 8601 date'], 422);
            }
        }

        $result = $this->omniconva->products()->paginate($page, $perPage, $since);

        return new JsonResponse($result->toArray($this->omniconva->currency()));
    }

    /** GET {prefix}/products/{id} */
    public function show(string $id): JsonResponse
    {
        $product = $this->omniconva->products()->find($id);
        if ($product === null) {
            return new JsonResponse(['message' => 'Product not found'], 404);
        }

        return new JsonResponse($product->toArray($this->omniconva->currency()));
    }
}
