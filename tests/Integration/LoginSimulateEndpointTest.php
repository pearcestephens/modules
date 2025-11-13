<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class LoginSimulateEndpointTest extends TestCase
{
    private string $endpointPath;

    protected function setUp(): void
    {
        $this->endpointPath = dirname(__DIR__, 2) . '/base/public/login_simulate.php';
        if (!file_exists($this->endpointPath)) {
            $this->markTestSkipped('login_simulate.php not found');
        }
        // Clean session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        $_GET = [];
        // Ensure flags file exists
        $flagsFile = dirname(__DIR__, 2) . '/config/feature-flags.php';
        if (!file_exists($flagsFile)) {
            file_put_contents($flagsFile, "<?php\nreturn ['auth_debug'=>false,'auth_debug_token'=>''];");
        }
    }

    private function invoke(array $get, array $env = []): array
    {
        $_GET = $get;
        foreach ($env as $k => $v) {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
        ob_start();
        include $this->endpointPath;
        $out = ob_get_clean();
        $json = json_decode($out, true);
        return $json ?? ['raw' => $out];
    }

    public function testFlagDisabled(): void
    {
        $resp = $this->invoke(['user_id' => 1], ['FORCE_AUTH_DEBUG' => '0']);
        $this->assertSame('forbidden', $resp['error'] ?? null);
    }

    public function testForceOverrideEnables(): void
    {
        $resp = $this->invoke(['user_id' => 2], ['FORCE_AUTH_DEBUG' => '1']);
        $this->assertTrue($resp['ok'] ?? false);
        $this->assertSame(2, $resp['user_id'] ?? null);
    }

    public function testTokenMismatch(): void
    {
        putenv('DEV_AUTH_SIM_TOKEN=EXPECTED');
        $_ENV['DEV_AUTH_SIM_TOKEN'] = 'EXPECTED';
        $resp = $this->invoke(['user_id' => 3, 'token' => 'WRONG'], ['FORCE_AUTH_DEBUG' => '1']);
        $this->assertSame('unauthorized', $resp['error'] ?? null);
    }

    public function testTokenMatch(): void
    {
        putenv('DEV_AUTH_SIM_TOKEN=EXPECTED');
        $_ENV['DEV_AUTH_SIM_TOKEN'] = 'EXPECTED';
        $resp = $this->invoke(['user_id' => 4, 'token' => 'EXPECTED'], ['FORCE_AUTH_DEBUG' => '1']);
        $this->assertTrue($resp['ok'] ?? false);
        $this->assertSame(4, $resp['user_id'] ?? null);
    }
}
