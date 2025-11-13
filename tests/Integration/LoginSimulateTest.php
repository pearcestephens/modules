<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * @group Integration
 */
final class LoginSimulateTest extends TestCase
{
    private string $endpointPath;

    protected function setUp(): void
    {
        $this->endpointPath = dirname(__DIR__, 2) . '/base/public/login_simulate.php';
        if (!file_exists($this->endpointPath)) {
            $this->markTestSkipped('login_simulate.php not found');
        }
        // Ensure clean session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        $_GET = [];
        $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3); // approximate doc root for include path logic if needed
    }

    private function runEndpoint(array $get, array $env = []): array
    {
        $_GET = $get;
        foreach ($env as $k => $v) {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
        // Provide flags file automatically
        $flagsFile = dirname(__DIR__, 2) . '/config/feature-flags.php';
        if (!file_exists($flagsFile)) {
            file_put_contents($flagsFile, "<?php\nreturn ['auth_debug'=>false,'auth_debug_token'=>''];");
        }
        ob_start();
        include $this->endpointPath;
    $output = ob_get_clean();
        $decoded = json_decode($output, true);
        return $decoded ?? ['raw' => $output];
    }

    /** @coversNothing */
    public function testFlagDisabledForbidden(): void
    {
        // Ensure no override, feature flag default is false
        $resp = $this->runEndpoint(['user_id' => 1], ['FORCE_AUTH_DEBUG' => '0']);
        $this->assertSame('forbidden', $resp['error'] ?? null, 'Should return forbidden when auth_debug disabled and no override');
    }

    /** @coversNothing */
    public function testEnvOverrideAllowsAuth(): void
    {
        $resp = $this->runEndpoint(['user_id' => 1], ['FORCE_AUTH_DEBUG' => '1']);
        $this->assertTrue($resp['ok'] ?? false, 'Env override should permit simulated login');
        $this->assertSame(1, $resp['user_id'] ?? null);
        $this->assertArrayHasKey('flags', $resp);
        $this->assertTrue($resp['flags']['force_auth_debug'] ?? false);
    }

    /** @coversNothing */
    public function testTokenMismatch(): void
    {
        // Set expected token, send wrong one
        putenv('DEV_AUTH_SIM_TOKEN=EXPECTED');
        $_ENV['DEV_AUTH_SIM_TOKEN'] = 'EXPECTED';
        $resp = $this->runEndpoint(['user_id' => 1, 'token' => 'WRONG'], ['FORCE_AUTH_DEBUG' => '1']);
        $this->assertSame('unauthorized', $resp['error'] ?? null, 'Should reject mismatched token');
    }

    /** @coversNothing */
    public function testTokenMatch(): void
    {
        putenv('DEV_AUTH_SIM_TOKEN=EXPECTED');
        $_ENV['DEV_AUTH_SIM_TOKEN'] = 'EXPECTED';
        $resp = $this->runEndpoint(['user_id' => 5, 'token' => 'EXPECTED'], ['FORCE_AUTH_DEBUG' => '1']);
        $this->assertTrue($resp['ok'] ?? false);
        $this->assertSame(5, $resp['user_id'] ?? null);
    }
}
