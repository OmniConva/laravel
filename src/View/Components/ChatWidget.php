<?php

namespace OmniConva\Laravel\View\Components;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use OmniConva\Laravel\OmniConva;

/**
 * <x-omniconva-chat /> — the OmniConva chat widget. Renders nothing until a
 * site key is configured. With an identity secret set, the signed-in user is
 * passed along (signed), so the AI and agents know who they're talking to;
 * pass :customer to choose the fields yourself, or :customer="false" to
 * never send one.
 *
 * @phpstan-type Customer array{id: string, name?: ?string, email?: ?string, phone?: ?string, address?: ?string, avatar_url?: ?string}
 */
class ChatWidget extends Component
{
    /** @param  array<string, mixed>|false|null  $customer */
    public function __construct(
        private readonly OmniConva $omniconva,
        private readonly AuthFactory $auth,
        public array|false|null $customer = null,
        public ?bool $showPoweredBy = null,
    ) {}

    public function render(): View|string
    {
        $widget = (array) $this->omniconva->config('widget');
        $siteKey = $widget['site_key'] ?? null;
        if (! is_string($siteKey) || $siteKey === '') {
            return '';
        }
        $apiUrl = rtrim((string) ($widget['api_url'] ?? 'https://api.omniconva.com'), '/');

        $config = ['siteKey' => $siteKey, 'apiBase' => $apiUrl];
        if ($this->showPoweredBy !== null) {
            $config['showPoweredBy'] = $this->showPoweredBy;
        }
        $customer = $this->customerPayload((string) ($widget['identity_secret'] ?? ''));
        if ($customer !== null) {
            $config['customer'] = $customer;
        }

        return view('omniconva::components.chat-widget', [
            'config' => $config,
            'scriptUrl' => $apiUrl.'/static/widget.min.js',
        ]);
    }

    /** @return array<string, mixed>|null */
    private function customerPayload(string $secret): ?array
    {
        if ($secret === '' || $this->customer === false) {
            return null;
        }
        $customer = $this->customer;
        if ($customer === null) {
            $user = $this->auth->guard()->user();
            if ($user === null) {
                return null;
            }
            $customer = [
                'id' => (string) $user->getAuthIdentifier(),
                'name' => $user->name ?? null,
                'email' => $user->email ?? null,
                'phone' => $user->phone ?? null,
            ];
        }
        if (! isset($customer['id']) || ! is_scalar($customer['id']) || (string) $customer['id'] === '') {
            return null;
        }
        $customer['id'] = (string) $customer['id'];
        $customer = array_filter($customer, fn ($value) => $value !== null && $value !== '');
        $customer['hash'] = hash_hmac('sha256', $customer['id'], $secret);

        return $customer;
    }
}
