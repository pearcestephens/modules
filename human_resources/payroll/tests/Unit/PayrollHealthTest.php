<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../health/index.php';

final class PayrollHealthTest extends TestCase
{
    public function testHealthEndpointReturnsJson(): void
    {
        ob_start();
        include __DIR__ . '/../health/index.php';
        $output = ob_get_clean();

        $this->assertJson($output);

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('ok', $data);
        $this->assertArrayHasKey('checks', $data);
        $this->assertIsBool($data['ok']);
        $this->assertIsArray($data['checks']);
    }
}
