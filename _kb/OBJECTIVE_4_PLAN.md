# OBJECTIVE 4: Remove Fallback DB Credentials

## Problem

Hard-coded database passwords exist in `config/database.php` as fallback values:

```php
'password' => env('DB_PASSWORD', 'wprKh9Jq63'), // TODO: Move to .env
```

**Risk:** CRITICAL
- Credentials visible in source control
- Security breach if repo is compromised
- Violates security best practices
- Cannot rotate credentials without code change

## Current State

**File:** `config/database.php`

```php
'cis' => [
    'password' => env('DB_PASSWORD', 'wprKh9Jq63'), // ❌ HARD-CODED
],

'vapeshed' => [
    'password' => env('VAPESHED_DB_PASSWORD', 'wprKh9Jq63'), // ❌ HARD-CODED
],
```

## Solution

### 1. Remove Fallback Passwords
Change to fail-fast approach - if env var missing, throw exception

```php
'cis' => [
    'password' => env('DB_PASSWORD') ?: throw new RuntimeException('DB_PASSWORD not set'),
],
```

### 2. Add Validation Function
Create helper to validate required env vars at startup

```php
function requireEnv(string $key): mixed {
    $value = env($key);
    if ($value === null || $value === '') {
        throw new RuntimeException("Required environment variable not set: {$key}");
    }
    return $value;
}
```

### 3. Update .env.example
Add clear instructions for database credentials

### 4. Add Tests
- Test that missing DB_PASSWORD throws exception
- Test that invalid credentials fail gracefully
- Test that valid credentials work

## Acceptance Criteria

- [ ] No hard-coded passwords in config/database.php
- [ ] Application fails fast with clear error if DB_PASSWORD missing
- [ ] .env.example documents all required database env vars
- [ ] Tests verify proper error handling
- [ ] Existing functionality unchanged when env vars present

## Files to Modify

1. `config/database.php` - Remove fallbacks
2. `config/env-loader.php` - Add requireEnv() helper
3. `.env.example` - Document DB credentials
4. `tests/Unit/DatabaseConfigTest.php` - New test file

## Estimated Time: 15 minutes
