<?php

namespace Tests\Unit\Setup;

use App\Setup\EnvironmentFile;
use Dotenv\Dotenv;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EnvironmentFileTest extends TestCase
{
    #[DataProvider('values')]
    public function test_written_value_reads_back_unchanged(string $value): void
    {
        $file = EnvironmentFile::fromString("APP_NAME=Laravel\nSECRET=old")->set('SECRET', $value);

        $this->assertSame($value, Dotenv::parse($file->render())['SECRET']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function values(): array
    {
        return [
            'plain' => ['abc123'],
            'url' => ['https://secrets.example.com:8443'],
            'empty' => [''],
            'spaces' => ['MataSecret Team'],
            'hash' => ['pa#ss'],
            'dollar interpolation' => ['p@ss${APP_NAME}$1'],
            'single quote' => ["it's"],
            'single quote, double quote, backslash and dollar' => ['it\'s "a" \\ $HOME'],
        ];
    }

    public function test_set_replaces_the_value_in_place(): void
    {
        $file = EnvironmentFile::fromString("# App\nAPP_NAME=Laravel\nAPP_ENV=local");

        $file->set('APP_NAME', 'MataSecret');

        $this->assertSame("# App\nAPP_NAME=MataSecret\nAPP_ENV=local\n", $file->render());
    }

    public function test_new_variable_follows_the_last_one_with_its_prefix(): void
    {
        $file = EnvironmentFile::fromString("AUTH_PROVIDER=google\nAUTH_CLIENT_ID=\n\nMAIL_MAILER=log");

        $file->set('AUTH_BASE_URL', 'https://acme.okta.com');

        $this->assertSame("AUTH_PROVIDER=google\nAUTH_CLIENT_ID=\nAUTH_BASE_URL=https://acme.okta.com\n\nMAIL_MAILER=log\n", $file->render());
    }

    public function test_variable_without_a_related_prefix_is_appended(): void
    {
        $file = EnvironmentFile::fromString('APP_NAME=MataSecret');

        $file->set('POSTMARK_API_KEY', 'token');

        $this->assertSame("APP_NAME=MataSecret\n\nPOSTMARK_API_KEY=token\n", $file->render());
    }

    public function test_forget_removes_every_variable_with_the_prefix_but_keeps_comments(): void
    {
        $file = EnvironmentFile::fromString("APP_NAME=MataSecret\n\n# GOOGLE_CLIENT_ID=commented\nGOOGLE_CLIENT_ID=id\nGOOGLE_CLIENT_SECRET=secret\n\n\nMAIL_MAILER=log");

        $file->forget('GOOGLE_');

        $this->assertSame("APP_NAME=MataSecret\n\n# GOOGLE_CLIENT_ID=commented\n\nMAIL_MAILER=log\n", $file->render());
    }

    public function test_line_breaks_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EnvironmentFile::quote("first\nsecond");
    }

    public function test_write_replaces_the_file_with_the_given_permissions(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($path, 'OLD=1');

        EnvironmentFile::fromString('APP_NAME=MataSecret')->writeTo($path, 0600);

        $this->assertSame("APP_NAME=MataSecret\n", file_get_contents($path));
        $this->assertSame(0600, fileperms($path) & 0777);
        $this->assertSame([basename($path)], array_values(array_filter(
            scandir(dirname($path)) ?: [],
            fn (string $name): bool => str_contains($name, basename($path)),
        )));

        unlink($path);
    }
}
