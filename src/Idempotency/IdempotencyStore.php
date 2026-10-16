<?php

namespace OmniConva\Laravel\Idempotency;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Remembers each order OmniConva sent, by its Idempotency-Key, so a retry
 * gets the first answer instead of creating the order again.
 *
 * A key is claimed (row inserted as pending) before your OrderHandler runs,
 * and completed with the response in the same transaction as the order. A
 * key still pending means another request is creating it right now, unless
 * it was left behind by a crashed one, which is taken over after a while.
 * Each claim carries an owner token, so a request whose claim was taken
 * over can neither complete nor release the claim that replaced it.
 */
class IdempotencyStore
{
    private const STALE_AFTER_SECONDS = 300;

    private const CLAIM_ATTEMPTS = 3;

    /** @var array<string, bool> */
    private static array $hasOwnerColumn = [];

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $table,
    ) {}

    /** @return array{state: 'claimed', owner: string}|array{state: 'busy'}|array{state: 'done', response: array<string, mixed>} */
    public function claim(string $key): array
    {
        $owner = bin2hex(random_bytes(12));
        for ($attempt = 0; $attempt < self::CLAIM_ATTEMPTS; $attempt++) {
            $now = Carbon::now();
            try {
                $this->db->table($this->table)->insert($this->withOwner([
                    'key' => $key,
                    'status' => 'pending',
                    'response' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $owner));

                return ['state' => 'claimed', 'owner' => $owner];
            } catch (UniqueConstraintViolationException) {
                // Seen before: answered, in progress, or abandoned.
            }

            $row = $this->db->table($this->table)->where('key', $key)->first();
            if ($row === null) {
                // Released between the insert and the read; insert again.
                continue;
            }
            if ($row->status === 'done') {
                return ['state' => 'done', 'response' => (array) json_decode((string) $row->response, true)];
            }
            $taken = $this->db->table($this->table)
                ->where('key', $key)
                ->where('status', 'pending')
                ->where('updated_at', '<', $now->copy()->subSeconds(self::STALE_AFTER_SECONDS))
                ->update($this->withOwner(['updated_at' => $now], $owner));

            return $taken > 0 ? ['state' => 'claimed', 'owner' => $owner] : ['state' => 'busy'];
        }

        return ['state' => 'busy'];
    }

    /** @param  array<string, mixed>  $response */
    public function complete(string $key, array $response, ?string $owner = null): void
    {
        $this->owned($key, $owner)->update([
            'status' => 'done',
            'response' => json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => Carbon::now(),
        ]);
    }

    /** The order wasn't created: forget the key so a retry can try again. */
    public function release(string $key, ?string $owner = null): void
    {
        $this->owned($key, $owner)->where('status', 'pending')->delete();
    }

    /** @return Builder */
    private function owned(string $key, ?string $owner)
    {
        $query = $this->db->table($this->table)->where('key', $key);
        if ($owner !== null && $this->hasOwnerColumn()) {
            $query->where('owner', $owner);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withOwner(array $values, string $owner): array
    {
        return $this->hasOwnerColumn() ? $values + ['owner' => $owner] : $values;
    }

    // The column arrived in 1.0.1; an app that has not run the new migration
    // yet keeps the 1.0.0 behaviour instead of failing every order.
    private function hasOwnerColumn(): bool
    {
        return self::$hasOwnerColumn[$this->table] ??= $this->db->getSchemaBuilder()->hasColumn($this->table, 'owner');
    }
}
