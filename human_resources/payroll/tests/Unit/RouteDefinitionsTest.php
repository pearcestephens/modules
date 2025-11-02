<?php
declare(strict_types=1);

namespace HumanResources\Payroll\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Route Definitions Test Suite
 *
 * Validates payroll module route definitions for:
 * - Required fields presence
 * - CSRF protection consistency
 * - Permission naming standards
 * - Authentication coverage
 * - Documentation completeness
 * - Route uniqueness
 *
 * @package HumanResources\Payroll\Tests\Unit
 */
class RouteDefinitionsTest extends TestCase
{
    private array $routes;
    private array $requiredFields = ['controller', 'action', 'description'];
    private string $permissionPrefix = 'payroll.';

    protected function setUp(): void
    {
        parent::setUp();

        // Load routes from routes.php
        $routesFile = __DIR__ . '/../../routes.php';
        $this->assertFileExists($routesFile, 'routes.php file must exist');

        $this->routes = require $routesFile;
        $this->assertIsArray($this->routes, 'routes.php must return an array');
        $this->assertNotEmpty($this->routes, 'routes.php must not be empty');
    }

    /**
     * Test 1: All routes have required fields
     */
    public function testAllRoutesHaveRequiredFields(): void
    {
        foreach ($this->routes as $path => $config) {
            foreach ($this->requiredFields as $field) {
                $this->assertArrayHasKey(
                    $field,
                    $config,
                    "Route '{$path}' missing required field '{$field}'"
                );

                $this->assertNotEmpty(
                    $config[$field],
                    "Route '{$path}' has empty '{$field}' field"
                );
            }
        }

        $this->assertEquals(
            57,
            count($this->routes),
            'Expected exactly 57 routes in routes.php'
        );
    }

    /**
     * Test 2: All POST routes have CSRF protection
     */
    public function testAllPostRoutesHaveCsrf(): void
    {
        $postRoutes = $this->filterRoutesByMethod('POST');
        $postCount = count($postRoutes);
        $csrfCount = 0;

        foreach ($postRoutes as $path => $config) {
            $this->assertArrayHasKey(
                'csrf',
                $config,
                "POST route '{$path}' missing 'csrf' field"
            );

            $this->assertTrue(
                $config['csrf'],
                "POST route '{$path}' must have csrf=true"
            );

            $csrfCount++;
        }

        $this->assertEquals(
            $postCount,
            $csrfCount,
            "All {$postCount} POST routes must have CSRF protection"
        );

        // Expected: 29 POST routes
        $this->assertEquals(
            29,
            $postCount,
            'Expected exactly 29 POST routes'
        );
    }

    /**
     * Test 3: Permission naming follows conventions
     */
    public function testPermissionNamingConsistency(): void
    {
        $routesWithPermissions = array_filter($this->routes, function($config) {
            return isset($config['permission']);
        });

        $this->assertNotEmpty(
            $routesWithPermissions,
            'At least some routes should have permissions'
        );

        $uniquePermissions = [];

        foreach ($routesWithPermissions as $path => $config) {
            $permission = $config['permission'];

            // Must start with payroll.
            $this->assertStringStartsWith(
                $this->permissionPrefix,
                $permission,
                "Route '{$path}' permission must start with '{$this->permissionPrefix}'"
            );

            // Must be lowercase with underscores
            $this->assertMatchesRegularExpression(
                '/^[a-z_\.]+$/',
                $permission,
                "Route '{$path}' permission must be lowercase with underscores"
            );

            // Track unique permissions
            $uniquePermissions[$permission] = true;
        }

        // Expected: 22 unique permissions
        $this->assertEquals(
            22,
            count($uniquePermissions),
            'Expected exactly 22 unique permissions'
        );
    }

    /**
     * Test 4: Authentication coverage
     */
    public function testAuthenticationCoverage(): void
    {
        $authRoutes = 0;
        $noAuthRoutes = [];

        foreach ($this->routes as $path => $config) {
            if (isset($config['auth']) && $config['auth'] === true) {
                $authRoutes++;
            } else {
                $noAuthRoutes[] = $path;
            }
        }

        // Expected: 56/57 routes authenticated
        $this->assertEquals(
            56,
            $authRoutes,
            'Expected exactly 56 authenticated routes'
        );

        $this->assertCount(
            1,
            $noAuthRoutes,
            'Expected exactly 1 route without auth (OAuth callback)'
        );

        // Verify the one exception is the OAuth callback
        $this->assertContains(
            'GET /api/payroll/xero/oauth/callback',
            $noAuthRoutes,
            'The one non-auth route should be the OAuth callback'
        );
    }

    /**
     * Test 5: All routes have descriptions
     */
    public function testDescriptionCompleteness(): void
    {
        foreach ($this->routes as $path => $config) {
            $this->assertArrayHasKey(
                'description',
                $config,
                "Route '{$path}' missing description"
            );

            $description = $config['description'];

            $this->assertIsString($description);
            $this->assertNotEmpty($description);

            // Description should be at least 10 characters
            $this->assertGreaterThanOrEqual(
                10,
                strlen($description),
                "Route '{$path}' description too short: '{$description}'"
            );

            // Description should not be placeholder text
            $this->assertStringNotContainsString(
                'TODO',
                $description,
                "Route '{$path}' has placeholder description"
            );
        }
    }

    /**
     * Test 6: No conflicting route definitions
     */
    public function testNoConflictingRoutes(): void
    {
        $routePaths = array_keys($this->routes);
        $uniquePaths = array_unique($routePaths);

        $this->assertEquals(
            count($routePaths),
            count($uniquePaths),
            'Duplicate route definitions found'
        );

        // Check for similar routes that might conflict (e.g., :id vs :periodKey)
        $apiRoutes = $this->filterRoutesByPrefix('/api/payroll');
        $conflictGroups = [];

        foreach ($apiRoutes as $path => $config) {
            // Normalize path parameters for comparison
            $normalized = preg_replace('/:[a-zA-Z]+/', ':param', $path);
            $conflictGroups[$normalized][] = $path;
        }

        foreach ($conflictGroups as $normalized => $paths) {
            if (count($paths) > 1) {
                // Multiple routes with same pattern - check they have different methods
                $methods = [];
                foreach ($paths as $path) {
                    $method = $this->extractMethod($path);
                    $methods[] = $method;
                }

                $uniqueMethods = array_unique($methods);
                $this->assertEquals(
                    count($methods),
                    count($uniqueMethods),
                    "Conflicting routes found: " . implode(', ', $paths)
                );
            }
        }
    }

    /**
     * Test 7: All referenced controllers exist
     */
    public function testControllerClassesExist(): void
    {
        $controllers = [];

        foreach ($this->routes as $path => $config) {
            $controllerName = $config['controller'];
            $controllers[$controllerName] = true;
        }

        $this->assertCount(
            10,
            $controllers,
            'Expected exactly 10 unique controllers'
        );

        $expectedControllers = [
            'AmendmentController',
            'PayrollAutomationController',
            'XeroController',
            'WageDiscrepancyController',
            'BonusController',
            'VendPaymentController',
            'LeaveController',
            'DashboardController',
            'PayRunController',
            'ReconciliationController'
        ];

        foreach ($expectedControllers as $controller) {
            $this->assertArrayHasKey(
                $controller,
                $controllers,
                "Expected controller '{$controller}' not found in routes"
            );
        }
    }

    /**
     * Test 8: Page routes don't have /api/ prefix
     */
    public function testPageRoutesLackApiPrefix(): void
    {
        $pageRoutes = array_filter($this->routes, function($config, $path) {
            return !str_starts_with($path, 'GET /api/') &&
                   !str_starts_with($path, 'POST /api/') &&
                   !str_starts_with($path, 'PUT /api/') &&
                   !str_starts_with($path, 'DELETE /api/') &&
                   !str_starts_with($path, 'PATCH /api/');
        }, ARRAY_FILTER_USE_BOTH);

        // Expected: 10 page routes
        $this->assertCount(
            10,
            $pageRoutes,
            'Expected exactly 10 page routes (non-API)'
        );

        foreach ($pageRoutes as $path => $config) {
            // Page routes should start with GET
            $this->assertStringStartsWith(
                'GET /',
                $path,
                "Page route '{$path}' should use GET method"
            );

            // Page routes should NOT contain /api/
            $this->assertStringNotContainsString(
                '/api/',
                $path,
                "Page route '{$path}' should not contain /api/"
            );
        }
    }

    /**
     * Test 9: API routes have /api/ prefix
     */
    public function testApiRoutesHaveApiPrefix(): void
    {
        $apiRoutes = $this->filterRoutesByPrefix('/api/payroll');

        // Expected: 47 API routes
        $this->assertCount(
            47,
            $apiRoutes,
            'Expected exactly 47 API routes'
        );

        foreach ($apiRoutes as $path => $config) {
            $this->assertMatchesRegularExpression(
                '#^(GET|POST|PUT|DELETE|PATCH) /api/payroll/#',
                $path,
                "API route '{$path}' must start with method and /api/payroll/"
            );
        }
    }

    /**
     * Test 10: OAuth callback exception is valid
     */
    public function testOAuthCallbackException(): void
    {
        $oauthCallback = null;

        foreach ($this->routes as $path => $config) {
            if (str_contains($path, '/xero/oauth/callback')) {
                $oauthCallback = [
                    'path' => $path,
                    'config' => $config
                ];
                break;
            }
        }

        $this->assertNotNull(
            $oauthCallback,
            'OAuth callback route must exist'
        );

        // OAuth callback should NOT have auth=true
        $this->assertArrayHasKey(
            'auth',
            $oauthCallback['config'],
            'OAuth callback must explicitly set auth field'
        );

        $this->assertFalse(
            $oauthCallback['config']['auth'],
            'OAuth callback must have auth=false (external redirect)'
        );

        // OAuth callback should NOT have CSRF (it's a GET from external service)
        if (isset($oauthCallback['config']['csrf'])) {
            $this->assertFalse(
                $oauthCallback['config']['csrf'],
                'OAuth callback should not require CSRF (external redirect)'
            );
        }

        // OAuth callback should have a descriptive note
        $this->assertStringContainsString(
            'OAuth',
            $oauthCallback['config']['description'],
            'OAuth callback description should mention OAuth'
        );
    }

    // ============================================================================
    // HELPER METHODS
    // ============================================================================

    /**
     * Filter routes by HTTP method
     */
    private function filterRoutesByMethod(string $method): array
    {
        return array_filter($this->routes, function($config, $path) use ($method) {
            return str_starts_with($path, $method . ' ');
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Filter routes by path prefix
     */
    private function filterRoutesByPrefix(string $prefix): array
    {
        return array_filter($this->routes, function($config, $path) use ($prefix) {
            return str_contains($path, $prefix);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Extract HTTP method from route path
     */
    private function extractMethod(string $path): string
    {
        return explode(' ', $path)[0];
    }
}
