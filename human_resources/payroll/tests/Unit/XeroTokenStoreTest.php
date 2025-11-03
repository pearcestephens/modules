<?php
/**
 * Unit Test for XeroTokenStore
 *
 * @package CIS\Payroll\Tests\Unit
 */

declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PDO;
use PDOStatement;
use HumanResources\Payroll\Services\EncryptionService;
use XeroTokenStore;

final class XeroTokenStoreTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $pdo;
    private $encryptionService;

    protected function setUp(): void
    {
        $this->pdo = Mockery::mock(PDO::class);
        $this->encryptionService = Mockery::mock(EncryptionService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testGetAccessTokenFromPrimaryStore()
    {
        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('fetch')->with(PDO::FETCH_ASSOC)->andReturn(['access_token' => 'encrypted_token']);
        $this->pdo->shouldReceive('query')->andReturn($statement);
        $this->encryptionService->shouldReceive('isEncrypted')->with('encrypted_token')->andReturn(true);
        $this->encryptionService->shouldReceive('decrypt')->with('encrypted_token')->andReturn('decrypted_token');

        $tokenStore = new XeroTokenStore($this->pdo, $this->encryptionService);
        $this->assertEquals('decrypted_token', $tokenStore->getAccessToken());
    }

    public function testGetAccessTokenFromFallbackStore()
    {
        $oauthStatement = Mockery::mock(PDOStatement::class);
        $oauthStatement->shouldReceive('fetch')->with(PDO::FETCH_ASSOC)->andReturn(null);

        $xeroStatement = Mockery::mock(PDOStatement::class);
        $xeroStatement->shouldReceive('fetch')->with(PDO::FETCH_ASSOC)->andReturn(['access_token' => 'fallback_token']);

        $this->pdo->shouldReceive('query')
            ->with("SELECT access_token FROM oauth_tokens WHERE provider = 'xero' LIMIT 1")
            ->andReturn($oauthStatement);
        $this->pdo->shouldReceive('query')
            ->with("SELECT access_token FROM xero_tokens ORDER BY created_at DESC LIMIT 1")
            ->andReturn($xeroStatement);

        $this->encryptionService->shouldReceive('isEncrypted')->with('fallback_token')->andReturn(false);

        $tokenStore = new XeroTokenStore($this->pdo, $this->encryptionService);
        $this->assertEquals('fallback_token', $tokenStore->getAccessToken());
    }

    public function testGetAccessTokenFromEnv()
    {
        $oauthStatement = Mockery::mock(PDOStatement::class);
        $oauthStatement->shouldReceive('fetch')->with(PDO::FETCH_ASSOC)->andReturn(null);
        $xeroStatement = Mockery::mock(PDOStatement::class);
        $xeroStatement->shouldReceive('fetch')->with(PDO::FETCH_ASSOC)->andReturn(null);

        $this->pdo->shouldReceive('query')->andReturn($oauthStatement, $xeroStatement);

        putenv('XERO_ACCESS_TOKEN=env_token');
        $tokenStore = new XeroTokenStore($this->pdo, $this->encryptionService);
        $this->assertEquals('env_token', $tokenStore->getAccessToken());
        putenv('XERO_ACCESS_TOKEN');
    }

    public function testSaveTokens()
    {
        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('execute')->once();
        $this->pdo->shouldReceive('prepare')->andReturn($statement);
        $this->encryptionService->shouldReceive('encrypt')->andReturn('encrypted_access', 'encrypted_refresh');

        $tokenStore = new XeroTokenStore($this->pdo, $this->encryptionService);
        $tokenStore->saveTokens('access', 'refresh', time() + 3600);
    }
}
