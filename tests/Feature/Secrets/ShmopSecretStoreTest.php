<?php

namespace Tests\Feature\Secrets;

use App\Secrets\SecretStore;
use App\Secrets\ShmopSecretStore;
use App\Secrets\StoredSecret;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Runs the store contract against a System V shared memory segment. The
 * local PHP CLI usually lacks shmop; run it with FrankenPHP's embedded PHP:
 * ./frankenphp php-cli vendor/bin/phpunit --filter=ShmopSecretStoreTest
 */
class ShmopSecretStoreTest extends ArraySecretStoreTest
{
    private int $segmentKey;

    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('shmop')) {
            $this->markTestSkipped('The shmop extension is not loaded.');
        }

        $this->segmentKey = random_int(1, 2 ** 31 - 1);
        $this->lockPath = sys_get_temp_dir().'/ephesecret-test-'.bin2hex(random_bytes(8)).'.lock';
    }

    protected function tearDown(): void
    {
        if (isset($this->segmentKey) && ($segment = @shmop_open($this->segmentKey, 'w', 0, 0)) !== false) {
            shmop_delete($segment);
        }

        if (isset($this->lockPath) && is_file($this->lockPath)) {
            unlink($this->lockPath);
        }

        parent::tearDown();
    }

    public function test_segment_and_lock_file_are_only_accessible_by_their_owner(): void
    {
        $this->store()->put('first', $this->secret());

        $this->assertSame(0600, fileperms($this->lockPath) & 0777);

        exec('ipcs -m 2>/dev/null', $lines, $exitCode);

        if ($exitCode !== 0) {
            $this->markTestSkipped('ipcs is not available to inspect the segment mode.');
        }

        $key = sprintf('0x%08x', $this->segmentKey);
        $line = collect($lines)->first(fn (string $line): bool => str_contains($line, $key));

        $this->assertNotNull($line, "Segment {$key} not listed by ipcs.");
        $this->assertMatchesRegularExpression('/\s(-*rw-------|600)\s/', $line);
    }

    public function test_secret_larger_than_a_slot_is_rejected(): void
    {
        $secret = new StoredSecret(str_repeat('x', 5000), 'creator', CarbonImmutable::now()->addHour(), true);

        $this->assertFalse($this->store(slotSize: 4096)->put('first', $secret));
    }

    public function test_full_store_rejects_secrets_until_one_expires(): void
    {
        $store = $this->store(slots: 2);

        $this->assertTrue($store->put('first', $this->secret(minutes: 5)));
        $this->assertTrue($store->put('second', $this->secret(minutes: 10)));
        $this->assertFalse($store->put('third', $this->secret()));

        $this->travel(6)->minutes();

        $this->assertTrue($store->put('third', $this->secret()));
        $this->assertSame(2, $store->count());
    }

    public function test_forget_wipes_the_secret_from_shared_memory(): void
    {
        $store = $this->store();
        $store->put('first', new StoredSecret('top-secret-payload', 'creator', CarbonImmutable::now()->addHour(), true));

        $store->forget('first');

        $segment = shmop_open($this->segmentKey, 'a', 0, 0);

        $this->assertStringNotContainsString('top-secret-payload', shmop_read($segment, 0, shmop_size($segment)));
        $this->assertStringNotContainsString(hash('sha256', 'first', true), shmop_read($segment, 0, shmop_size($segment)));
    }

    public function test_store_shares_secrets_between_instances(): void
    {
        $this->store()->put('first', $this->secret());

        $this->assertNotNull($this->store()->pull('first'));
        $this->assertNull($this->store()->find('first'));
    }

    public function test_segment_created_by_someone_else_is_refused(): void
    {
        $this->store()->put('first', $this->secret());
        shmop_delete(shmop_open($this->segmentKey, 'w', 0, 0));

        $foreign = shmop_open($this->segmentKey, 'n', 0666, 64 + 8 * (45 + 4096));

        $this->expectException(RuntimeException::class);

        try {
            $this->store()->find('first');
        } finally {
            shmop_delete($foreign);
        }
    }

    public function test_segment_with_another_configuration_is_refused(): void
    {
        $this->store()->put('first', $this->secret());

        $this->expectException(RuntimeException::class);

        $this->store(slots: 4)->find('first');
    }

    public function test_segment_signed_with_another_app_key_is_refused(): void
    {
        $this->store()->put('first', $this->secret());

        $this->expectException(RuntimeException::class);

        $this->store(signingKey: 'another-key')->find('first');
    }

    protected function store(int $slots = 8, int $slotSize = 4096, string $signingKey = 'signing-key'): SecretStore
    {
        return new ShmopSecretStore($this->segmentKey, $slots, $slotSize, $this->lockPath, $signingKey);
    }
}
