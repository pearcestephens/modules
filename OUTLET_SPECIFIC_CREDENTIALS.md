# 🏪 OUTLET-SPECIFIC CREDENTIALS GUIDE
**Location:** Database Table `outlet_credentials`
**Purpose:** Store per-outlet API keys, secrets, and service credentials
**Security:** Encrypted at rest, accessed via Base\Config for global keys

---

## 🎯 WHY DATABASE-STORED CREDENTIALS?

### Global Credentials (.env file):
- ✅ Same across all outlets (Xero, Vend, OpenAI, SMTP)
- ✅ One API key serves entire organization
- ✅ Stored in: `/home/.../jcepnzzkmj/.env`

### Per-Outlet Credentials (Database):
- ✅ Different for each outlet/store
- ✅ NZ Post account per outlet
- ✅ CourierPost account per outlet
- ✅ Local supplier API keys
- ✅ Per-store payment terminals
- ✅ Stored in: Database table `outlet_credentials`

---

## 📋 DATABASE SCHEMA

```sql
-- Create outlet_credentials table
CREATE TABLE IF NOT EXISTS outlet_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    outlet_id INT NOT NULL,
    service_name VARCHAR(100) NOT NULL,
    credential_type VARCHAR(50) NOT NULL COMMENT 'api_key, api_secret, username, password, token',
    credential_value TEXT NOT NULL COMMENT 'Encrypted value',
    endpoint VARCHAR(255) DEFAULT NULL COMMENT 'API endpoint URL if service-specific',
    config_json TEXT DEFAULT NULL COMMENT 'Additional config as JSON',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,

    UNIQUE KEY unique_outlet_service (outlet_id, service_name, credential_type),
    KEY idx_outlet (outlet_id),
    KEY idx_service (service_name),
    KEY idx_active (is_active),

    FOREIGN KEY (outlet_id) REFERENCES outlets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Per-outlet API credentials and service keys';

-- Example data
INSERT INTO outlet_credentials
(outlet_id, service_name, credential_type, credential_value, endpoint, config_json)
VALUES
-- Auckland outlet - NZ Post
(1, 'nzpost', 'api_key', 'ENCRYPTED_KEY_HERE', 'https://api.nzpost.co.nz', '{"account_number": "12345", "site_code": "AKL001"}'),
(1, 'nzpost', 'api_secret', 'ENCRYPTED_SECRET_HERE', NULL, NULL),

-- Auckland outlet - CourierPost
(1, 'courierpost', 'api_key', 'ENCRYPTED_KEY_HERE', 'https://api.courierpost.co.nz', '{"account_number": "CP67890"}'),

-- Wellington outlet - NZ Post (different account)
(2, 'nzpost', 'api_key', 'ENCRYPTED_KEY_DIFFERENT', 'https://api.nzpost.co.nz', '{"account_number": "54321", "site_code": "WLG001"}'),

-- Supplier API (outlet-specific login)
(1, 'supplier_vapoureyes', 'username', 'ENCRYPTED_USERNAME', 'https://b2b.vapoureyes.co.nz', NULL),
(1, 'supplier_vapoureyes', 'password', 'ENCRYPTED_PASSWORD', NULL, NULL);
```

---

## 🔐 SERVICES STORED IN DATABASE

### **1. Shipping / Courier Services** (Per Outlet)
- **NZ Post** - Each outlet has own API credentials
- **CourierPost** - Per-outlet account numbers
- **Fastway Couriers** - Outlet-specific login
- **Local Courier Services** - Vary by region

### **2. Supplier APIs** (Per Outlet)
- **Supplier B2B Portals** - Outlet manager has own login
- **Dropship APIs** - Different credentials per store
- **Wholesale Accounts** - Store-specific pricing keys

### **3. Payment Terminals** (Per Outlet)
- **EFTPOS Terminal Keys** - Each store has physical terminals
- **SmartPay / Verifone** - Terminal-specific credentials
- **Tap & Go Readers** - Device pairing keys

### **4. Local Services** (Per Outlet)
- **Local Advertising APIs** - Regional campaigns
- **Delivery Service Integrations** - Uber Eats, DoorDash per store
- **Local Government APIs** - Council permits, inspections

---

## 💻 PHP CODE EXAMPLES

### **Helper Class: OutletCredentials.php**

```php
<?php
/**
 * Outlet Credentials Manager
 * Manages per-outlet API credentials stored in database
 */

namespace Base;

class OutletCredentials
{
    private $db;
    private $encryptionKey;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $config = Config::getInstance();
        $this->encryptionKey = $config->get('ENCRYPTION_KEY');
    }

    /**
     * Get credential for specific outlet and service
     *
     * @param int $outletId Outlet ID
     * @param string $serviceName Service name (e.g., 'nzpost', 'courierpost')
     * @param string $credentialType Type (e.g., 'api_key', 'api_secret')
     * @return string|null Decrypted credential value
     */
    public function get(int $outletId, string $serviceName, string $credentialType): ?string
    {
        $stmt = $this->db->prepare("
            SELECT credential_value, endpoint, config_json
            FROM outlet_credentials
            WHERE outlet_id = ?
            AND service_name = ?
            AND credential_type = ?
            AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([$outletId, $serviceName, $credentialType]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        // Decrypt credential
        return $this->decrypt($result['credential_value']);
    }

    /**
     * Get all credentials for a service at an outlet
     *
     * @param int $outletId
     * @param string $serviceName
     * @return array ['api_key' => 'value', 'api_secret' => 'value', ...]
     */
    public function getAll(int $outletId, string $serviceName): array
    {
        $stmt = $this->db->prepare("
            SELECT credential_type, credential_value, endpoint, config_json
            FROM outlet_credentials
            WHERE outlet_id = ?
            AND service_name = ?
            AND is_active = 1
        ");

        $stmt->execute([$outletId, $serviceName]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $credentials = [];
        foreach ($results as $row) {
            $credentials[$row['credential_type']] = $this->decrypt($row['credential_value']);

            // Add endpoint if present
            if ($row['endpoint']) {
                $credentials['endpoint'] = $row['endpoint'];
            }

            // Add config if present
            if ($row['config_json']) {
                $credentials['config'] = json_decode($row['config_json'], true);
            }
        }

        return $credentials;
    }

    /**
     * Set credential (creates or updates)
     *
     * @param int $outletId
     * @param string $serviceName
     * @param string $credentialType
     * @param string $credentialValue (will be encrypted)
     * @param string|null $endpoint
     * @param array|null $config
     * @return bool
     */
    public function set(
        int $outletId,
        string $serviceName,
        string $credentialType,
        string $credentialValue,
        ?string $endpoint = null,
        ?array $config = null
    ): bool {
        $encryptedValue = $this->encrypt($credentialValue);
        $configJson = $config ? json_encode($config) : null;

        $stmt = $this->db->prepare("
            INSERT INTO outlet_credentials
            (outlet_id, service_name, credential_type, credential_value, endpoint, config_json)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                credential_value = VALUES(credential_value),
                endpoint = VALUES(endpoint),
                config_json = VALUES(config_json),
                updated_at = CURRENT_TIMESTAMP
        ");

        return $stmt->execute([
            $outletId,
            $serviceName,
            $credentialType,
            $encryptedValue,
            $endpoint,
            $configJson
        ]);
    }

    /**
     * Delete credential
     */
    public function delete(int $outletId, string $serviceName, string $credentialType): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM outlet_credentials
            WHERE outlet_id = ?
            AND service_name = ?
            AND credential_type = ?
        ");

        return $stmt->execute([$outletId, $serviceName, $credentialType]);
    }

    /**
     * Encrypt credential value
     */
    private function encrypt(string $value): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($value, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt credential value
     */
    private function decrypt(string $encryptedValue): string
    {
        $data = base64_decode($encryptedValue);
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        return openssl_decrypt($encrypted, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
    }
}
```

---

## 🚀 USAGE EXAMPLES

### **Example 1: Get NZ Post API Key for Outlet**

```php
require_once __DIR__ . '/../base/bootstrap.php';

// Get outlet credentials manager
$outletCreds = new Base\OutletCredentials();

// Get NZ Post API key for outlet #1 (Auckland)
$outletId = 1;
$apiKey = $outletCreds->get($outletId, 'nzpost', 'api_key');
$apiSecret = $outletCreds->get($outletId, 'nzpost', 'api_secret');

// Use credentials
$nzPostClient = new NZPostAPI($apiKey, $apiSecret);
```

### **Example 2: Get All CourierPost Credentials**

```php
$outletId = 2; // Wellington
$credentials = $outletCreds->getAll($outletId, 'courierpost');

// Returns:
// [
//     'api_key' => 'decrypted_key',
//     'api_secret' => 'decrypted_secret',
//     'endpoint' => 'https://api.courierpost.co.nz',
//     'config' => ['account_number' => 'CP67890']
// ]

$courierClient = new CourierPostAPI(
    $credentials['api_key'],
    $credentials['api_secret'],
    $credentials['endpoint']
);
```

### **Example 3: Add New Credential**

```php
// Add supplier credentials for outlet
$outletCreds->set(
    outletId: 3,
    serviceName: 'supplier_vapoureyes',
    credentialType: 'api_key',
    credentialValue: 'actual_api_key_here',
    endpoint: 'https://b2b.vapoureyes.co.nz/api',
    config: ['account_number' => 'VE12345', 'pricing_tier' => 'premium']
);
```

---

## 📊 CREDENTIAL HIERARCHY

```
┌─────────────────────────────────────────────────────────────┐
│ GLOBAL CREDENTIALS (.env file)                             │
│ - Xero API (all outlets use same Xero account)             │
│ - Vend API (all outlets in same Vend instance)             │
│ - OpenAI API (organization-wide)                           │
│ - SMTP (organization email)                                │
└─────────────────────────────────────────────────────────────┘
                            ↓
                  Accessed via Base\Config
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ PER-OUTLET CREDENTIALS (Database)                          │
│ - NZ Post API (outlet 1 ≠ outlet 2)                        │
│ - CourierPost API (different accounts per outlet)          │
│ - Supplier logins (store manager specific)                 │
│ - Payment terminals (physical device keys)                 │
└─────────────────────────────────────────────────────────────┘
                            ↓
              Accessed via Base\OutletCredentials
```

---

## ✅ BENEFITS OF THIS APPROACH

### **Security:**
✅ Credentials encrypted at rest (AES-256-CBC)
✅ Separate encryption key in .env
✅ Database access controlled via permissions
✅ Audit trail (created_by, updated_at)

### **Flexibility:**
✅ Each outlet can have different credentials
✅ Easy to add new services
✅ Config JSON for service-specific settings
✅ Can disable credentials (is_active flag)

### **Centralization:**
✅ All outlet credentials in one table
✅ Consistent access pattern
✅ Easy to update without code changes
✅ Can manage via admin UI

---

## 🔧 ADMIN UI (Future Enhancement)

Create admin page for managing outlet credentials:

**Location:** `/admin/outlet-credentials.php`

**Features:**
- List all credentials per outlet
- Add/Edit/Delete credentials
- Test credential connectivity
- View audit log (who changed what when)
- Bulk import from CSV
- Export for backup (encrypted)

---

## 📋 MIGRATION CHECKLIST

### Phase 1: Create Infrastructure
- [ ] Create `outlet_credentials` table
- [ ] Create `Base\OutletCredentials` class
- [ ] Add `ENCRYPTION_KEY` to .env
- [ ] Test encryption/decryption

### Phase 2: Migrate Existing Credentials
- [ ] Identify all hardcoded outlet-specific credentials
- [ ] Encrypt and insert into database
- [ ] Update code to use `OutletCredentials` class
- [ ] Test each outlet still works

### Phase 3: Admin Interface
- [ ] Create admin page for credential management
- [ ] Add audit logging
- [ ] Create backup/restore tools
- [ ] Document for staff

---

## 🆘 TROUBLESHOOTING

**Q: Credential not found**
```php
$cred = $outletCreds->get(1, 'nzpost', 'api_key');
if ($cred === null) {
    // Check: Does this outlet/service exist in database?
    // Check: Is is_active = 1?
    // Check: Spelling of service_name?
}
```

**Q: Decryption fails**
- Check `ENCRYPTION_KEY` in .env matches key used to encrypt
- Ensure key hasn't changed since encryption
- Verify base64 encoding is intact

**Q: Performance concerns**
- Add caching layer (Redis/Memcached)
- Cache decrypted credentials for 1 hour
- Invalidate cache when credentials updated

---

**STATUS: DATABASE CREDENTIAL SYSTEM DOCUMENTED ✅**
**Next: Implement OutletCredentials.php class in base/ directory**
