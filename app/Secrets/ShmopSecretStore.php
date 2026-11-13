<?php

namespace App\Secrets;

use Carbon\CarbonImmutable;
use Closure;
use InvalidArgumentException;
use RuntimeException;
use Shmop;

/**
 * Keeps secrets in a System V shared memory segment that only the owning OS
 * user may attach to (mode 0600), so every PHP pool running as that user on
 * the same host shares them. Unlike APCu, the segment survives server
 * restarts; it is only cleared by a reboot or `ipcrm -M <key>`.
 *
 * Layout: a signed header, a directory of fixed-size entries addressed by
 * the SHA-256 of the secret id (linear probing), then one fixed-size data
 * slot per entry. Every operation holds an exclusive lock on $lockPath.
 */
class ShmopSecretStore implements SecretStore
{
    private const string MAGIC = 'EPHS';

    private const int VERSION = 1;

    private const int HEADER_SIZE = 64;

    /**
     * State byte, id hash (32), expiry timestamp (8) and data length (4).
     */
    private const int ENTRY_SIZE = 45;

    private const string ENTRY_FORMAT = 'Cstate/a32hash/Jexpires/Nlength';

    private const int EMPTY = 0;

    private const int USED = 1;

    private const int TOMBSTONE = 2;

    public function __construct(
        private int $key,
        private int $slots,
        private int $slotSize,
        private string $lockPath,
        private string $signingKey,
    ) {
        if (! extension_loaded('shmop')) {
            throw new RuntimeException('The shmop extension must be installed and enabled to store secrets.');
        }

        if ($slots < 1 || $slotSize < 1) {
            throw new InvalidArgumentException('The shmop secret store needs at least one slot of at least one byte.');
        }
    }

    public function put(string $id, StoredSecret $secret): bool
    {
        $encoded = $secret->encode();

        if (strlen($encoded) > $this->slotSize) {
            return false;
        }

        return $this->locked(function (Shmop $segment) use ($id, $secret, $encoded): bool {
            $hash = $this->hash($id);
            ['index' => $index, 'free' => $free] = $this->locate($segment, $hash);

            if ($index !== null || $free === null) {
                return false;
            }

            shmop_write($segment, $encoded, $this->dataOffset($free));
            shmop_write($segment, pack('Ca32JN', self::USED, $hash, $secret->expiresAt->getTimestamp(), strlen($encoded)), $this->entryOffset($free));

            return true;
        });
    }

    public function find(string $id): ?StoredSecret
    {
        return $this->locked(function (Shmop $segment) use ($id): ?StoredSecret {
            ['index' => $index, 'length' => $length] = $this->locate($segment, $this->hash($id));

            return $index === null ? null : StoredSecret::decode(shmop_read($segment, $this->dataOffset($index), $length));
        });
    }

    public function pull(string $id): ?StoredSecret
    {
        return $this->locked(function (Shmop $segment) use ($id): ?StoredSecret {
            ['index' => $index, 'length' => $length] = $this->locate($segment, $this->hash($id));

            if ($index === null) {
                return null;
            }

            $secret = StoredSecret::decode(shmop_read($segment, $this->dataOffset($index), $length));
            $this->wipe($segment, $index, $length);

            return $secret;
        });
    }

    public function forget(string $id): void
    {
        $this->locked(function (Shmop $segment) use ($id): void {
            ['index' => $index, 'length' => $length] = $this->locate($segment, $this->hash($id));

            if ($index !== null) {
                $this->wipe($segment, $index, $length);
            }
        });
    }

    /**
     * Count live secrets, wiping expired ones from memory along the way.
     */
    public function count(): int
    {
        return $this->locked(function (Shmop $segment): int {
            $directory = shmop_read($segment, self::HEADER_SIZE, $this->slots * self::ENTRY_SIZE);
            $now = CarbonImmutable::now()->getTimestamp();
            $count = 0;

            for ($index = 0; $index < $this->slots; $index++) {
                $entry = $this->unpackEntry($directory, $index * self::ENTRY_SIZE);

                if ($entry['state'] !== self::USED) {
                    continue;
                }

                if ($entry['expires'] <= $now) {
                    $this->wipe($segment, $index, $entry['length']);
                } else {
                    $count++;
                }
            }

            return $count;
        });
    }

    /**
     * Find the entry holding the given id hash and the first slot a new entry
     * could use. Expired entries met along the probe are wiped.
     *
     * @return array{index: ?int, free: ?int, length: int}
     */
    private function locate(Shmop $segment, string $hash): array
    {
        $now = CarbonImmutable::now()->getTimestamp();
        $start = $this->startSlot($hash);
        $free = null;

        for ($probe = 0; $probe < $this->slots; $probe++) {
            $index = ($start + $probe) % $this->slots;
            $entry = $this->unpackEntry(shmop_read($segment, $this->entryOffset($index), self::ENTRY_SIZE));

            if ($entry['state'] === self::EMPTY) {
                return ['index' => null, 'free' => $free ?? $index, 'length' => 0];
            }

            if ($entry['state'] === self::USED && $entry['expires'] <= $now) {
                $this->wipe($segment, $index, $entry['length']);
                $entry['state'] = self::TOMBSTONE;
            }

            if ($entry['state'] === self::TOMBSTONE) {
                $free ??= $index;

                continue;
            }

            if (hash_equals($entry['hash'], $hash)) {
                return ['index' => $index, 'free' => null, 'length' => $entry['length']];
            }
        }

        return ['index' => null, 'free' => $free, 'length' => 0];
    }

    /**
     * Zero a secret's data and directory entry, leaving a tombstone.
     */
    private function wipe(Shmop $segment, int $index, int $length): void
    {
        shmop_write($segment, str_repeat("\0", $length), $this->dataOffset($index));
        shmop_write($segment, pack('Ca32JN', self::TOMBSTONE, '', 0, 0), $this->entryOffset($index));
    }

    /**
     * Decode the directory entry found at the given offset of the bytes.
     *
     * @return array{state: int, hash: string, expires: int, length: int}
     */
    private function unpackEntry(string $bytes, int $offset = 0): array
    {
        $entry = unpack(self::ENTRY_FORMAT, $bytes, $offset);

        if ($entry === false) {
            throw new RuntimeException("Shared memory segment [{$this->key}] holds an unreadable directory entry.");
        }

        return [
            'state' => $entry['state'],
            'hash' => $entry['hash'],
            'expires' => $entry['expires'],
            'length' => $entry['length'],
        ];
    }

    /**
     * Run the callback while holding an exclusive lock on the segment.
     *
     * @template TReturn
     *
     * @param  Closure(Shmop): TReturn  $callback
     * @return TReturn
     */
    private function locked(Closure $callback): mixed
    {
        $isNewLockFile = ! is_file($this->lockPath);
        $lock = fopen($this->lockPath, 'c');

        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException("Unable to lock the secret store at [{$this->lockPath}].");
        }

        try {
            if ($isNewLockFile) {
                chmod($this->lockPath, 0600);
            }

            return $callback($this->segment());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Attach to the segment, creating it owner-only on first use. An existing
     * segment is only used when it carries a header signed with the app key,
     * so secrets are never written to a segment another user created first.
     * The attachment is released once the returned handle is freed, keeping
     * well under per-process attach limits (8 by default on macOS).
     */
    private function segment(): Shmop
    {
        $size = self::HEADER_SIZE + $this->slots * (self::ENTRY_SIZE + $this->slotSize);
        $header = $this->header();

        $segment = @shmop_open($this->key, 'n', 0600, $size);

        if ($segment !== false) {
            shmop_write($segment, $header, 0);

            return $segment;
        }

        $segment = @shmop_open($this->key, 'w', 0, 0);

        if ($segment === false) {
            throw new RuntimeException("Unable to attach to shared memory segment [{$this->key}]. It may belong to another user.");
        }

        if (shmop_size($segment) !== $size || ! hash_equals($header, shmop_read($segment, 0, strlen($header)))) {
            throw new RuntimeException("Shared memory segment [{$this->key}] was not created by this application with the current configuration or APP_KEY. Remove it with `ipcrm -M {$this->key}` or use another SECRETS_SHMOP_KEY.");
        }

        return $segment;
    }

    private function header(): string
    {
        $fields = pack('a4CNN', self::MAGIC, self::VERSION, $this->slots, $this->slotSize);

        return $fields.hash_hmac('sha256', $fields, $this->signingKey, true);
    }

    private function hash(string $id): string
    {
        return hash('sha256', $id, true);
    }

    /**
     * The slot a hash's probe sequence starts at: its leading 32 bits modulo
     * the slot count.
     */
    private function startSlot(string $hash): int
    {
        $leading = unpack('N', $hash);

        if ($leading === false) {
            throw new RuntimeException('Unable to derive a directory slot from the secret id hash.');
        }

        return $leading[1] % $this->slots;
    }

    private function entryOffset(int $index): int
    {
        return self::HEADER_SIZE + $index * self::ENTRY_SIZE;
    }

    private function dataOffset(int $index): int
    {
        return self::HEADER_SIZE + $this->slots * self::ENTRY_SIZE + $index * $this->slotSize;
    }
}
