<?php

namespace OmniConva\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OmniConva\Laravel\OmniConva;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets through only requests OmniConva signed: X-OmniConva-Signature is an
 * HMAC of "<t>.<METHOD>.<path?query>.<body>" with your signing secret, and t
 * must be within a few minutes of now.
 */
class VerifyOmniConvaSignature
{
    public function __construct(private readonly OmniConva $omniconva) {}

    public function handle(Request $request, Closure $next): Response
    {
        $valid = $this->omniconva->signer()->verifyRequest(
            $request->header('X-OmniConva-Signature'),
            $request->getMethod(),
            $request->getRequestUri(),
            $request->getContent(),
        );

        if (! $valid) {
            return new JsonResponse(
                ['message' => 'Missing or invalid X-OmniConva-Signature. Check OMNICONVA_SIGNING_SECRET.'],
                401,
            );
        }

        return $next($request);
    }
}
