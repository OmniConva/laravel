<?php

use OmniConva\Laravel\Signature\Signer;

// Produced by OmniConva's own signer (api: common/webhooks/timestamped-signature.ts),
// so the package is checked against what the service really sends.
const VECTORS = [
    ['GET', '/omniconva/products?page=2&per_page=50&updated_since=2026-10-01T00%3A00%3A00.000Z', '', 't=1700000000,v1=3d8daff9f24c7e62ec493c7666f0fda64f8843d71494a7ba4d57274623451616'],
    ['POST', '/omniconva/orders', '{"id":"abc"}', 't=1700000000,v1=2e74a27c3283a4e23a68ec029d6faf8cdd1d52b8130590d1f368c62ac154f712'],
];

it('signs requests exactly like OmniConva does', function (string $method, string $path, string $body, string $expected) {
    expect((new Signer('test-secret'))->signRequest($method, $path, $body, 1700000000))->toBe($expected);
})->with(VECTORS);

it('verifies OmniConva request signatures', function (string $method, string $path, string $body, string $header) {
    expect((new Signer('test-secret'))->verifyRequest($header, $method, $path, $body, 1700000100))->toBeTrue();
})->with(VECTORS);

it('signs webhooks exactly like OmniConva verifies them', function () {
    expect((new Signer('test-secret'))->signWebhook('{"id":"1"}', 1700000000))
        ->toBe('t=1700000000,v1=9fe0764af9e888d6648e4fb2ad331add3f30860b2a000745d00c316d5399e953');
});

it('rejects tampering, other secrets, old timestamps and junk', function () {
    $signer = new Signer('test-secret');
    $header = $signer->signRequest('POST', '/omniconva/orders', '{"id":"abc"}', 1700000000);

    expect($signer->verifyRequest($header, 'POST', '/omniconva/orders', '{"id":"xyz"}', 1700000000))->toBeFalse()
        ->and($signer->verifyRequest($header, 'GET', '/omniconva/orders', '{"id":"abc"}', 1700000000))->toBeFalse()
        ->and($signer->verifyRequest($header, 'POST', '/omniconva/other', '{"id":"abc"}', 1700000000))->toBeFalse()
        ->and((new Signer('other'))->verifyRequest($header, 'POST', '/omniconva/orders', '{"id":"abc"}', 1700000000))->toBeFalse()
        ->and($signer->verifyRequest($header, 'POST', '/omniconva/orders', '{"id":"abc"}', 1700000400))->toBeFalse()
        ->and($signer->verifyRequest(null, 'GET', '/', '', 1700000000))->toBeFalse()
        ->and($signer->verifyRequest('garbage', 'GET', '/', '', 1700000000))->toBeFalse()
        ->and((new Signer(''))->verifyRequest($header, 'POST', '/omniconva/orders', '{"id":"abc"}', 1700000000))->toBeFalse();
});
