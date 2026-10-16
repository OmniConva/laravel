<?php

namespace OmniConva\Laravel\Idempotency;

use Illuminate\Database\ConnectionInterface;
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
 */
class IdempotencyStore
{
    private const STALE_AFTER_SECONDS = 300;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $table,
    ) {}

    /** @return array{state: 'claimed'}|array{state: 'busy'}|array{state: 'done', response: array<string, mixed>} */
    public function claim(string $key): array
    {
        $now = Carbon::now();
        try {
            $this->db->table($this->table)->insert([
                'key' => $key,
                'status' => 'pending',
                'response' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return ['state' => 'claimed'];
        } catch (UniqueConstraintViolationException) {
            // Seen before: answered, in progress, or abandoned.
        }

        $row = $this->db->table($this->table)->where('key', $key)->first();
        if ($row === null) {
            return $this->claim($key);
        }
        if ($row->status === 'done') {
            return ['state' => 'done', 'response' => (array) json_decode((string) $row->response, true)];
        }
        $taken = $this->db->table($this->table)
            ->where('key', $key)
            ->where('status', 'pending')
            ->where('updated_at', '<', $now->copy()->subSeconds(self::STALE_AFTER_SECONDS))
            ->update(['updated_at' => $now]);

        return $taken > 0 ? ['state' => 'claimed'] : ['state' => 'busy'];
    }

    /** @param  array<string, mixed>  $response */
    public function complete(string $key, array $response): void
    {
        $this->db->table($this->table)->where('key', $key)->update([
            'status' => 'done',
            'response' => json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => Carbon::now(),
        ]);
    }

    /** The order wasn't created: forget the key so a retry can try again. */
    public function release(string $key): void
    {
        $this->db->table($this->table)->where('key', $key)->where('status', 'pending')->delete();
    }
}
