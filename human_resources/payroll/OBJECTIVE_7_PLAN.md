# OBJECTIVE 7: Xero OAuth Token Encryption

## Problem Statement
OAuth tokens (access_token, refresh_token) are stored in **plaintext** in the `oauth_tokens` database table. These tokens provide full API access to Xero payroll data and should be encrypted at rest.

## Current State
**File:** `lib/XeroTokenStore.php`
- ✅ Good architecture: Token refresh logic, expiry tracking
- ❌ **SECURITY RISK:** Tokens stored as plaintext VARCHAR in database
- ❌ No encryption/decryption layer
- ❌ Direct database compromise = full Xero API access

**Database Schema:**
```sql
CREATE TABLE oauth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    access_token VARCHAR(2000),      -- ❌ PLAINTEXT
    refresh_token VARCHAR(2000),     -- ❌ PLAINTEXT
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_provider (provider)
);
```

## Security Impact
**Current Risk:** HIGH
- Database backup leak → Attacker gains Xero API access
- SQL injection → Token extraction
- Insider threat → No encryption barrier
- Compliance: OAuth tokens should be treated like passwords (encrypted at rest)

## Required Changes

### 1. Create EncryptionService (NEW FILE)
**Location:** `services/EncryptionService.php`
**Purpose:** Secure encryption/decryption using AES-256-GCM

**Features:**
- AES-256-GCM (authenticated encryption)
- Random IV per encryption (prevent pattern analysis)
- Key from environment (ENCRYPTION_KEY)
- Base64 encoding for database storage
- Fail-fast on missing key

**Methods:**
```php
encrypt(string $plaintext): string
decrypt(string $ciphertext): string
```

### 2. Update XeroTokenStore to use encryption
**Changes:**
- Inject `EncryptionService` into constructor
- **saveTokens():** Encrypt before INSERT/UPDATE
- **getAccessToken():** Decrypt after SELECT
- **getRefreshToken():** Decrypt after SELECT
- Maintain backward compatibility (detect plaintext, migrate on read)

### 3. Add migration utility
**Location:** `cli/migrate_encrypt_tokens.php`
**Purpose:** One-time migration of existing plaintext tokens

**Process:**
1. Read all oauth_tokens
2. For each token:
   - Encrypt access_token
   - Encrypt refresh_token
   - Update database
3. Log migration progress
4. Verify decryption works

### 4. Environment Variables (.env.example)
```
# Encryption Configuration (REQUIRED for OAuth token security)
ENCRYPTION_KEY=[generate with: openssl rand -base64 32]
ENCRYPTION_CIPHER=AES-256-GCM
```

### 5. Key Generation Script
**Location:** `cli/generate_encryption_key.php`
**Purpose:** Generate cryptographically secure encryption key

```bash
php cli/generate_encryption_key.php
# Output: ENCRYPTION_KEY=base64_encoded_32_byte_key
```

### 6. Comprehensive Tests
**File:** `tests/Unit/EncryptionServiceTest.php`
**Tests:**
- Encryption produces different output each time (random IV)
- Decryption recovers original plaintext
- Tampering detection (GCM authentication)
- Invalid key throws exception
- Empty/null input handling
- Large data encryption (2000 char tokens)
- Key rotation support

**File:** `tests/Unit/XeroTokenStoreEncryptionTest.php`
**Tests:**
- Tokens encrypted before storage
- Tokens decrypted on retrieval
- Backward compatibility (plaintext detection)
- saveTokens() → getAccessToken() round-trip
- Token refresh preserves encryption

## Acceptance Criteria
1. ✅ EncryptionService implements AES-256-GCM encryption
2. ✅ Random IV generated per encryption (no IV reuse)
3. ✅ Encryption key from environment (ENCRYPTION_KEY)
4. ✅ XeroTokenStore encrypts tokens before database save
5. ✅ XeroTokenStore decrypts tokens after database read
6. ✅ Backward compatibility: Detect plaintext, encrypt on next save
7. ✅ Migration script encrypts existing tokens
8. ✅ Key generation utility provided
9. ✅ Comprehensive tests (15+ test cases)
10. ✅ .env.example documents ENCRYPTION_KEY requirement

## Time Estimate: 30 minutes
- EncryptionService creation: 10 minutes
- XeroTokenStore refactor: 8 minutes
- Migration script: 5 minutes
- Key generation utility: 2 minutes
- Tests: 10 minutes
- .env.example update: 2 minutes

## Technical Details

### AES-256-GCM Encryption

**Cipher:** AES-256-GCM (Galois/Counter Mode)
**Key Size:** 256 bits (32 bytes)
**IV Size:** 96 bits (12 bytes) - recommended for GCM
**Tag Size:** 128 bits (16 bytes) - authentication tag

**Advantages:**
- Authenticated encryption (tamper detection)
- Fast performance (hardware acceleration)
- No padding oracle attacks (stream cipher mode)
- Industry standard (TLS 1.3, NIST approved)

**Format:**
```
Encrypted Data = Base64( IV || Ciphertext || Tag )
                         12 bytes + variable + 16 bytes
```

### Encryption Process
```php
$key = base64_decode(env('ENCRYPTION_KEY')); // 32 bytes
$iv = random_bytes(12); // 96-bit IV
$ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
$encrypted = base64_encode($iv . $ciphertext . $tag);
```

### Decryption Process
```php
$decoded = base64_decode($encrypted);
$iv = substr($decoded, 0, 12);
$tag = substr($decoded, -16);
$ciphertext = substr($decoded, 12, -16);
$plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
```

### Backward Compatibility Strategy

**Problem:** Existing tokens are plaintext
**Solution:** Lazy migration on read

```php
public function getAccessToken(): ?string
{
    $stored = $this->fetchFromDB();

    // Detect plaintext (not base64, or too short for encrypted format)
    if ($this->isPlaintext($stored)) {
        // Return plaintext for now, will encrypt on next save
        return $stored;
    }

    // Decrypt encrypted token
    return $this->encryption->decrypt($stored);
}
```

## Security Considerations

### ✅ Best Practices Implemented
1. **AES-256-GCM:** Authenticated encryption (tamper-proof)
2. **Random IV:** New IV per encryption (prevents pattern analysis)
3. **Key from environment:** Not hard-coded in source
4. **Base64 encoding:** Safe for VARCHAR storage
5. **Fail-fast:** Missing key throws exception (no silent fallback)
6. **GCM tag verification:** Detects tampering or corruption

### ⚠️ Key Management Requirements
1. **Generate secure key:**
   ```bash
   openssl rand -base64 32
   ```
2. **Store key securely:**
   - Production: AWS Secrets Manager / Azure Key Vault
   - Dev/Staging: .env file (not committed)
3. **Rotate keys periodically:** (Annual recommended)
4. **Backup key securely:** Loss = permanent data loss

### 🔒 Threat Mitigation

| Threat | Before | After |
|--------|--------|-------|
| Database backup leak | ❌ Tokens exposed | ✅ Encrypted (useless without key) |
| SQL injection | ❌ Direct token extraction | ✅ Encrypted data only |
| Insider threat | ❌ DBA can read tokens | ✅ Requires encryption key |
| Token tampering | ❌ No detection | ✅ GCM tag fails on tamper |
| Pattern analysis | ❌ Same token = same ciphertext | ✅ Random IV = different ciphertext |

## Deployment Steps

### 1. Generate Encryption Key (Production)
```bash
openssl rand -base64 32
# Output: 3kN9L2mP8qR5sT6vW7xY1zA2bC4dE5fG6hI7jK8lM9n=
```

### 2. Add to Production .env
```bash
ENCRYPTION_KEY=3kN9L2mP8qR5sT6vW7xY1zA2bC4dE5fG6hI7jK8lM9n=
```

### 3. Deploy Code
```bash
git checkout payroll-hardening-20251101
# Deploy to production
```

### 4. Migrate Existing Tokens
```bash
php cli/migrate_encrypt_tokens.php
# Encrypts all plaintext oauth_tokens
```

### 5. Verify Encryption
```bash
# Check database - tokens should be base64 (not readable)
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj \
  -e "SELECT provider, LEFT(access_token, 50) FROM oauth_tokens;"

# Should show: xero | 3kN9L2mP8qR5sT6vW7xY1zA2bC4dE... (not plaintext)
```

### 6. Test OAuth Flow
```bash
# Test Xero connection
curl https://staff.vapeshed.co.nz/api/payroll/xero/connect
# Should redirect to Xero OAuth (tokens decrypt correctly)
```

## Risk Assessment
**LOW RISK** - Adding security layer, not changing behavior
- New files: EncryptionService.php, migration script, tests
- Modifications: XeroTokenStore.php (add encryption), .env.example
- Backward compatible: Plaintext tokens still work (encrypted on next save)
- No breaking changes to OAuth flow
- Rollback: Remove ENCRYPTION_KEY = plaintext mode

## Notes
- AES-256-GCM chosen over AES-256-CBC (no padding oracle, authenticated)
- IV size 12 bytes (NIST recommendation for GCM)
- Key rotation requires re-encrypting all tokens with new key
- Consider AWS KMS for production key management (future enhancement)
