# Age Verification Privacy & Security Policy

## Document Classification: HIGHLY CONFIDENTIAL

**Effective Date:** November 5, 2025
**Policy Owner:** IT Manager & Security Lead
**Review Schedule:** Quarterly

---

## 1. DATA CLASSIFICATION

### Personal Identifiable Information (PII) Collected
- **Passport Photos** - Government-issued ID containing photo, full name, date of birth, passport number
- **Driver License Photos** - Government-issued ID containing photo, full name, date of birth, license number, address
- **Customer Data** - Email, phone number, residential address linked to verification

**Classification Level:** **HIGHLY SENSITIVE** - Breach could result in identity theft, fraud, legal action

---

## 2. STORAGE SECURITY REQUIREMENTS

### File System Security ✅

#### Storage Location
```
/secure/id-photos/
```

**Permissions:**
- Directory: `0700` (owner read/write/execute ONLY)
- Files: `0600` (owner read/write ONLY)
- Owner: `www-data` or web server user
- Group: NO group access
- Other: NO public access

**Enforcement:**
```bash
chmod 0700 /secure/id-photos/
chown www-data:www-data /secure/id-photos/
```

#### File Naming Convention
```
{verification_id}_{timestamp}_{type}.jpg
Example: 1234_1699142400_passport.jpg
```

**NO customer names, emails, or identifiable information in filenames**

### Database Security ✅

#### Encrypted Storage Path
```sql
-- Store ONLY relative path, not absolute
id_photo_path VARCHAR(255) ENCRYPTED

-- Example stored value:
-- '1234_1699142400_passport.jpg' (filename only)
-- NOT: '/secure/id-photos/1234_1699142400_passport.jpg'
```

#### Access Control
- Photo paths ONLY accessible to staff with `age_verification_review` permission
- Audit log ALL access to verification records
- IP address logging for all photo views

---

## 3. ACCESS CONTROL

### Who Can Access ID Photos?

**PERMITTED:**
- ✅ Staff with `age_verification_review` role
- ✅ IT Manager for technical support
- ✅ Security Lead for audits
- ✅ Director/Owner for compliance review

**DENIED:**
- ❌ General staff members
- ❌ Store managers without specific permission
- ❌ Third-party contractors (unless NDA + background check)
- ❌ External systems (no API access to photos)

### Web Access Protection

**NO DIRECT WEB ACCESS:**
```apache
# .htaccess in /secure/id-photos/
<FilesMatch ".*">
    Order Deny,Allow
    Deny from all
</FilesMatch>
```

**Photos served ONLY through:**
- PHP script with authentication check
- Session-based access token
- Time-limited URLs (expire after 5 minutes)
- Watermarked with "CONFIDENTIAL - CIS INTERNAL USE ONLY"

---

## 4. RETENTION POLICY

### Automatic Deletion Schedule

| Verification Status | Retention Period | Action |
|-------------------|------------------|---------|
| **Approved** | 7 days after approval | Auto-delete photo, keep approval record |
| **Rejected** | 30 days after rejection | Auto-delete photo, keep rejection reason |
| **Pending** | 14 days if no upload | Auto-expire verification request |
| **Abandoned** | 30 days if uploaded but not reviewed | Auto-delete and cancel order |

### Manual Deletion
- Customer can request immediate deletion via privacy request form
- Response time: 24 hours maximum
- Confirmation email sent when deleted

### Audit Trail
- Deletion logs stored for 7 years (compliance)
- Log includes: verification_id, customer_id, deleted_by, deleted_at, reason

---

## 5. TRANSMISSION SECURITY

### Customer Upload (Website → CIS)

**Requirements:**
- ✅ HTTPS/TLS 1.3 minimum
- ✅ Certificate pinning on mobile apps
- ✅ End-to-end encryption in transit
- ✅ File size limit: 10MB maximum
- ✅ File type validation: JPEG, PNG only
- ✅ Virus/malware scan before storage
- ✅ EXIF data stripped (remove GPS, camera info)

**Upload Endpoint:**
```
POST https://staff.vapeshed.co.nz/modules/ecommerce-ops/api/age-verification/upload-id.php

Headers:
- Content-Type: multipart/form-data
- X-Verification-Token: {unique_token_from_email}

Body:
- photo: (binary file)
- type: passport|license
```

### Internal Access (CIS Staff)

**Requirements:**
- ✅ VPN required for remote access
- ✅ MFA enforced for all age verification reviewers
- ✅ Session timeout: 15 minutes of inactivity
- ✅ IP whitelist: Only office/store networks + approved VPN

---

## 6. PRIVACY COMPLIANCE

### New Zealand Privacy Act 2020

**Information Privacy Principles (IPPs) Compliance:**

1. **IPP 1 - Purpose of Collection** ✅
   - Clear purpose: Age verification for R18 product sales
   - Disclosed in order confirmation email
   - Customer consent obtained at checkout

2. **IPP 2 - Source of Personal Information** ✅
   - Directly from customer via upload portal
   - Customer aware of collection

3. **IPP 3 - Collection of Information** ✅
   - Only collect what's necessary (photo of ID)
   - No excessive data collection

4. **IPP 4 - Manner of Collection** ✅
   - Lawful, fair, not intrusive
   - Clear notification of purpose

5. **IPP 5 - Storage and Security** ✅
   - Secure storage (0700 permissions, encrypted DB)
   - Access controls enforced
   - Regular security audits

6. **IPP 6 - Access to Personal Information** ✅
   - Customer can request access to their verification record
   - Response within 20 working days

7. **IPP 7 - Correction of Personal Information** ✅
   - Customer can request re-verification if error
   - Correction logged in audit trail

8. **IPP 8 - Accuracy of Personal Information** ✅
   - Manual review by trained staff
   - AI as assistant, not sole decision maker

9. **IPP 9 - Retention of Personal Information** ✅
   - Auto-deletion after retention period
   - Audit logs retained for compliance

10. **IPP 10 - Limits on Use** ✅
    - ONLY used for age verification
    - NOT used for marketing, profiling, or other purposes

11. **IPP 11 - Limits on Disclosure** ✅
    - NOT disclosed to third parties
    - NOT shared with suppliers, marketing partners
    - Only internal staff with proper authorization

12. **IPP 12 - Unique Identifiers** ✅
    - Verification ID is internal only
    - Not used as customer identifier across systems

### GDPR Compliance (if applicable)

**Right to be Forgotten:**
- Customer can request deletion via privacy@vapeshed.co.nz
- 24-hour response time
- Deletion confirmation sent

**Data Portability:**
- Customer can request copy of verification record (without photo for security)

**Breach Notification:**
- Office of the Privacy Commissioner notified within 72 hours
- Affected customers notified within 72 hours
- Incident report filed

---

## 7. STAFF TRAINING REQUIREMENTS

### Mandatory Training (Before Access Granted)

1. **Privacy Act 2020 Overview** (30 minutes)
   - Understanding IPPs
   - Legal obligations
   - Penalties for breach

2. **Age Verification Process** (45 minutes)
   - How to review ID photos
   - Spotting fake IDs
   - Fraud indicators

3. **Security Protocols** (30 minutes)
   - Password security
   - MFA usage
   - Phishing awareness
   - Social engineering

4. **Incident Response** (20 minutes)
   - What to do if breach suspected
   - Who to contact (IT Manager, Security Lead)
   - Documentation requirements

**Frequency:** Annual refresher training + audit

### Access Certification
- Staff access reviewed quarterly
- Unused accounts disabled after 30 days
- Access revoked immediately upon role change/termination

---

## 8. AUDIT & MONITORING

### Automated Monitoring

**Real-Time Alerts:**
- ⚠️ Photo accessed outside business hours (9am-5pm NZT)
- ⚠️ Same photo accessed >5 times in 24 hours
- ⚠️ Photo downloaded/exported
- ⚠️ Bulk verification queries (>50 in 1 hour)
- ⚠️ Failed authentication attempts (>3 in 5 minutes)

**Weekly Reports:**
- Number of verifications processed
- Average processing time
- Staff access patterns
- Expired verifications auto-deleted

### Manual Audits

**Quarterly Review:**
- Random sample of 50 verifications
- Check for proper approval/rejection justification
- Verify photos deleted per retention policy
- Review staff access logs

**Annual Security Audit:**
- Penetration testing of upload portal
- File system permissions check
- Database encryption verification
- Access control review

---

## 9. INCIDENT RESPONSE PLAN

### Suspected Breach

**IMMEDIATE ACTIONS (within 1 hour):**
1. Isolate affected system (disable upload endpoint)
2. Preserve evidence (logs, snapshots)
3. Notify IT Manager and Security Lead
4. Document timeline

**INVESTIGATION (within 24 hours):**
1. Determine scope of breach
2. Identify affected customers
3. Assess risk level
4. Determine cause (technical, human error, malicious)

**NOTIFICATION (within 72 hours):**
1. Office of the Privacy Commissioner (if required)
2. Affected customers (email + SMS)
3. Internal stakeholders (Director, Finance Lead)
4. Law enforcement (if criminal activity suspected)

**REMEDIATION (ongoing):**
1. Fix vulnerability
2. Enhanced monitoring
3. Staff re-training
4. Policy updates

### Incident Severity Levels

**Level 1 - CRITICAL**
- Unauthorized access to ID photos by external party
- Bulk download of photos
- Photos posted publicly online

**Level 2 - HIGH**
- Unauthorized access by staff member without permission
- Accidental disclosure to customer (wrong photo sent)
- Photos not deleted per retention policy

**Level 3 - MEDIUM**
- Failed login attempts exceeding threshold
- Photos stored in wrong directory
- Missing audit log entries

**Level 4 - LOW**
- Staff accessing own verification record
- Minor permission configuration drift

---

## 10. CUSTOMER COMMUNICATION

### Order Confirmation Email (When Verification Required)

**Subject:** Age Verification Required - Order #{order_id}

**Body:**
```
Hi {customer_name},

Thank you for your order! Before we can dispatch it, we need to verify your age.

**What we need:**
A clear photo of your NZ Passport or Driver License showing:
- Your photo
- Your full name
- Your date of birth

**Upload here:** {secure_link}
(This link expires in 7 days)

**Your privacy matters:**
- Your ID photo is encrypted and stored securely
- Only trained staff can access it for verification
- It's automatically deleted 7 days after approval
- We never share it with third parties
- Read our full privacy policy: https://www.vapeshed.co.nz/privacy

**Questions?**
Email: support@vapeshed.co.nz
Phone: 0800 VAPE SHED

Thanks,
The Vape Shed Team
```

### Privacy Policy Section (Website)

**Age Verification Privacy**

"When age verification is required, we collect a photo of your government-issued ID. This is used ONLY to confirm you are 18+ and is securely stored with military-grade encryption. Your ID photo is automatically deleted 7 days after approval. We never sell, share, or use this information for any other purpose. You can request deletion at any time by emailing privacy@vapeshed.co.nz."

---

## 11. TECHNICAL IMPLEMENTATION CHECKLIST

### Before Going Live

- [ ] File permissions set to 0700 for `/secure/id-photos/`
- [ ] .htaccess blocking direct web access
- [ ] HTTPS/TLS 1.3 enforced on upload endpoint
- [ ] MFA enabled for all age verification reviewers
- [ ] Automated deletion cron job configured
- [ ] Audit logging enabled for all photo access
- [ ] EXIF data stripping function tested
- [ ] Virus scanning integration tested
- [ ] Watermarking function tested
- [ ] Backup encryption verified
- [ ] Disaster recovery plan documented
- [ ] Staff training completed
- [ ] Privacy policy updated on website
- [ ] Incident response team contacts confirmed

### Monthly Checks

- [ ] Review access logs for anomalies
- [ ] Verify automated deletion working
- [ ] Check disk space in `/secure/id-photos/`
- [ ] Test backup restore process
- [ ] Review pending verifications >14 days old
- [ ] Audit staff access certifications

---

## 12. PENALTIES FOR NON-COMPLIANCE

### Staff Violations

**Unauthorized Access:**
- 1st offense: Written warning + re-training
- 2nd offense: Suspension (unpaid, 1 week)
- 3rd offense: Termination + legal action

**Disclosure to Unauthorized Party:**
- Immediate termination
- Legal action (breach of employment contract)
- Referral to Office of the Privacy Commissioner

**Failure to Follow Security Protocols:**
- 1st offense: Re-training
- 2nd offense: Written warning
- 3rd offense: Access revoked

### Company Penalties (Regulatory)

**Privacy Act 2020 Breach:**
- Fines up to $10,000 per breach
- Director liability for serious breaches
- Reputational damage

**Criminal Liability:**
- Obtaining/disclosing personal information with malicious intent
- Up to 2 years imprisonment or $10,000 fine

---

## 13. CONTACT INFORMATION

### Privacy Requests
**Email:** privacy@vapeshed.co.nz
**Response Time:** 24 hours for deletion requests, 20 working days for access requests

### Incident Reporting
**IT Manager:** [Contact TBC]
**Security Lead:** [Contact TBC]
**Director:** pearce.stephens@ecigdis.co.nz

### Regulatory Authority
**Office of the Privacy Commissioner**
Website: www.privacy.org.nz
Phone: 0800 803 909

---

## 14. POLICY APPROVAL

**Approved By:**
- [ ] Director/Owner: _________________ Date: _______
- [ ] IT Manager: _________________ Date: _______
- [ ] Security Lead: _________________ Date: _______
- [ ] Legal Counsel: _________________ Date: _______ (if required)

**Next Review Date:** February 5, 2026 (Quarterly)

---

**Document Version:** 1.0
**Last Updated:** November 5, 2025
**Classification:** INTERNAL USE ONLY
