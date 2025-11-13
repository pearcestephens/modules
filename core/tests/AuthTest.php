<?php
declare(strict_types=1);

namespace CIS\Core\Tests;

use PHPUnit\Framework\TestCase;
use CIS\Core\Controllers\AuthController;

/**
 * Authentication Tests
 */
class AuthTest extends TestCase
{
    private $auth;
    
    protected function setUp(): void
    {
        $this->auth = new AuthController();
    }
    
    public function testLoginWithValidCredentials(): void
    {
        // TODO: Implement test
        $this->assertTrue(true);
    }
    
    public function testLoginWithInvalidCredentials(): void
    {
        // TODO: Implement test
        $this->assertTrue(true);
    }
    
    public function testLogout(): void
    {
        // TODO: Implement test
        $this->assertTrue(true);
    }
    
    public function testIsAuthenticated(): void
    {
        // TODO: Implement test
        $this->assertFalse($this->auth->isAuthenticated());
    }
}