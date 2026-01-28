# TechLake Technical Documentation

**Version:** 1.0
**Last Updated:** 28 January 2026
**Status:** Living Document

---

## Table of Contents

1. [System Architecture](#1-system-architecture)
2. [Database Schema](#2-database-schema)
3. [API Specification](#3-api-specification)
4. [Integration Requirements](#4-integration-requirements)
5. [Data Synchronisation](#5-data-synchronisation)
6. [Security and Compliance](#6-security-and-compliance)
7. [Performance Requirements](#7-performance-requirements)
8. [Monitoring and Logging](#8-monitoring-and-logging)
9. [Testing Requirements](#9-testing-requirements)
10. [Deployment & CI/CD](#10-deployment--cicd)
11. [Edge Cases and Error Handling](#11-edge-cases-and-error-handling)
12. [Future Considerations](#12-future-considerations)
13. [Appendix](#13-appendix)

---

## 1. System Architecture

### 1.1 Overview

TechLake is a server-rendered marketing and lead-capture website for a B2B SaaS company targeting small businesses. The site is built with static HTML/CSS and vanilla PHP with no framework, hosted on Apache shared hosting (cPanel).

```
                        Internet
                           |
                     [Cloudflare CDN]          <-- NOT YET IMPLEMENTED
                           |
                  [Apache / cPanel]
                     (mod_rewrite)
                           |
            +--------------+--------------+
            |              |              |
     [Static HTML]   [PHP Handlers]  [.htaccess]
     index.html      form-handler    Security headers
     products/*      deploy.php      HTTPS redirect
     services/*      ibraheem/*      IP allowlisting
            |              |
     [CSS/Images]    [File Storage]
     css/style.css   .login_attempts.json
     images/*        .rate-limit
                     tawk-tickets.log
                     deploy.log
                     .security.log
```

### 1.2 Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Frontend | HTML5, CSS3 | Current |
| Backend | PHP | 7.4+ (vanilla, no framework) |
| Web Server | Apache | 2.4+ with mod_rewrite, mod_headers |
| Hosting | cPanel Shared Hosting | -- |
| Version Control | Git / GitHub | -- |
| Chat Widget | Tawk.to | Embedded script |
| Email | PHP `mail()` function | -- |
| SSL | Let's Encrypt (via cPanel) | TLS 1.2+ |

### 1.3 Directory Structure

```
techlake_website/
|-- .env                         # Secrets (not in git)
|-- .env.example                 # Template for .env
|-- .htaccess                    # Apache security config
|-- .gitignore                   # Git exclusions
|-- env-loader.php               # Loads .env into getenv()
|-- index.html                   # Homepage
|-- form-handler.php             # Contact form API
|-- deploy.php                   # GitHub webhook handler (not in git)
|-- css/
|   +-- style.css                # Design system (2,848 lines)
|-- images/
|   |-- techlake_logo.png
|   +-- logo.png
|-- products/
|   |-- index.html               # Products landing
|   |-- cashflow-toolkit.html
|   |-- charity-tracker.html
|   +-- personal-tracker.html
|-- services/
|   |-- it-consulting.html
|   |-- data-consulting.html
|   |-- solutions-architecture.html
|   +-- it-project-development.html
+-- ibraheem/                    # Admin portal
    |-- index.php                # Login
    +-- dashboard.php            # Dashboard
```

### 1.4 Request Flow

**Public Page Request:**
```
Browser --> Apache --> .htaccess (HTTPS redirect, headers) --> Static HTML --> Browser
```

**Contact Form Submission:**
```
Browser --> JS fetch() --> Apache --> .htaccess (headers) --> form-handler.php
  --> CSRF check --> Honeypot check --> Timing check --> Rate limit check
  --> Input validation --> mail() --> tawk-tickets.log --> JSON response
```

**Admin Login:**
```
Browser --> Apache --> ibraheem/index.php
  --> CSRF check --> Rate limit check --> password_verify() --> session
  --> Redirect to dashboard.php
```

**GitHub Deploy:**
```
GitHub push --> POST /deploy.php --> .htaccess (IP allowlist)
  --> User-Agent check --> HMAC signature check --> Branch check
  --> git fetch && git reset --hard --> deploy.log
```

### 1.5 Components Not Yet Implemented

| Component | Status | Notes |
|-----------|--------|-------|
| CDN (Cloudflare) | NOT IMPLEMENTED | Recommended for DDoS protection and caching |
| Relational Database | NOT IMPLEMENTED | Currently using flat files |
| Application Framework | NOT IMPLEMENTED | Running vanilla PHP |
| Frontend Framework | NOT IMPLEMENTED | Static HTML with inline JS |
| Background Job Queue | NOT IMPLEMENTED | All processing is synchronous |
| Caching Layer (Redis/Memcached) | NOT IMPLEMENTED | No server-side caching |
| Load Balancer | NOT IMPLEMENTED | Single-server deployment |
| Containerisation (Docker) | NOT IMPLEMENTED | Direct cPanel hosting |
| Microservices | NOT IMPLEMENTED | Monolithic architecture |
| WebSocket Server | NOT IMPLEMENTED | Tawk.to handles real-time chat |
| Search Engine | NOT IMPLEMENTED | Static content only |
| User Accounts / Registration | NOT IMPLEMENTED | Admin-only authentication |

---

## 2. Database Schema

### 2.1 Current State

**There is no relational database.** All persistent data is stored in flat JSON and log files on the filesystem. This is sufficient for the current scale but will not scale beyond a single server.

### 2.2 Current File-Based Storage

#### `.login_attempts.json`
```json
{
  "192.168.1.1": {
    "count": 3,
    "last_attempt": 1706400000
  }
}
```
- **Purpose:** Brute-force rate limiting for admin login
- **TTL:** Entries older than 15 minutes are purged on read
- **Concurrency:** No file locking (race condition risk)

#### `.rate-limit`
```json
{
  "192.168.1.1": [1706400000, 1706400060, 1706400120]
}
```
- **Purpose:** Contact form submission rate limiting
- **Threshold:** 5 submissions per IP per hour
- **TTL:** Timestamps older than 1 hour are pruned on read

#### `tawk-tickets.log`
```
[2026-01-28 10:30:00] New Lead: John Smith
Email: john@example.com
Business: Plumbing
Problem: need-website
Message: I need a website for my plumbing business
---
```
- **Purpose:** Backup record of all contact form submissions
- **Format:** Pipe-delimited entries separated by `---`
- **Size limit:** None (grows unbounded)
- **Rotation:** Manual via admin dashboard "Clear All Logs"

#### `deploy.log`
```
2026-01-28 10:00:00 - Deployed (exit code: 0)
Already up to date.
```
- **Purpose:** Deployment audit trail
- **Rotation:** None

#### `.security.log` (NOT YET IMPLEMENTED in current deployed version)
```
[2026-01-28 10:30:00] [192.168.1.1] LOGIN_FAILED: IP: 192.168.1.1, attempt #3
```
- **Purpose:** Security event audit trail
- **Events:** LOGIN_SUCCESS, LOGIN_FAILED, LOCKOUT_ACTIVE, CSRF_FAILED

### 2.3 Proposed Database Schema (NOT YET IMPLEMENTED)

When migrating to a relational database (MySQL/PostgreSQL), the following schema is recommended:

#### `contact_submissions`
```sql
CREATE TABLE contact_submissions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL,
    business        VARCHAR(255) DEFAULT NULL,
    problem         VARCHAR(100) DEFAULT NULL,
    message         TEXT DEFAULT NULL,
    ip_address      VARCHAR(45) NOT NULL,
    user_agent      TEXT DEFAULT NULL,
    csrf_token_used VARCHAR(64) NOT NULL,
    email_sent      BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_created_at (created_at),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `admin_users`
```sql
CREATE TABLE admin_users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(100) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL,
    role            ENUM('admin', 'viewer') DEFAULT 'admin',
    is_active       BOOLEAN DEFAULT TRUE,
    last_login_at   TIMESTAMP NULL,
    last_login_ip   VARCHAR(45) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `login_attempts`
```sql
CREATE TABLE login_attempts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    ip_address      VARCHAR(45) NOT NULL,
    username_tried  VARCHAR(100) DEFAULT NULL,
    success         BOOLEAN DEFAULT FALSE,
    user_agent      TEXT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, created_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `rate_limits`
```sql
CREATE TABLE rate_limits (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    ip_address      VARCHAR(45) NOT NULL,
    endpoint        VARCHAR(100) NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_endpoint_time (ip_address, endpoint, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `security_events`
```sql
CREATE TABLE security_events (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    event_type      ENUM('login_success', 'login_failed', 'lockout', 'csrf_failure',
                         'rate_limited', 'deploy_success', 'deploy_failed') NOT NULL,
    ip_address      VARCHAR(45) NOT NULL,
    details         TEXT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `deployments`
```sql
CREATE TABLE deployments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    commit_sha      VARCHAR(40) NOT NULL,
    branch          VARCHAR(100) NOT NULL,
    pusher          VARCHAR(100) DEFAULT NULL,
    exit_code       INT NOT NULL,
    output          TEXT DEFAULT NULL,
    ip_address      VARCHAR(45) NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 3. API Specification

### 3.1 Implemented Endpoints

#### `GET /form-handler.php?action=csrf`

**Purpose:** Fetch a CSRF token for the contact form.

**Authentication:** None (public)

**Request:**
```
GET /form-handler.php?action=csrf HTTP/1.1
Host: techlake.co
```

**Response (200):**
```json
{
  "csrf_token": "a1b2c3d4e5f6...64_hex_chars",
  "timestamp": 1706400000
}
```

**Behaviour:**
- Returns existing session token if one exists
- Generates new token with `bin2hex(random_bytes(32))` if none exists
- Token is bound to the PHP session

**Rate Limiting:** None currently. **Recommended:** 20 requests/minute per session.

---

#### `POST /form-handler.php`

**Purpose:** Submit the contact form.

**Authentication:** CSRF token required

**Content-Type:** `multipart/form-data` (via FormData)

**Request Body:**

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `csrf_token` | string | Yes | Must match session token (constant-time comparison) |
| `submit_time` | integer | Yes | Unix timestamp; `now - submit_time >= 2` seconds |
| `website` | string | No | Honeypot; must be empty |
| `name` | string | Yes | Non-empty after trim |
| `email` | string | Yes | Must pass `FILTER_VALIDATE_EMAIL` |
| `business` | string | No | Free text |
| `problem` | string | No | Enum: `need-website`, `more-customers`, `keep-customers`, `payroll`, `hiring`, `bookkeeping`, `no-time`, `marketing`, `other` |
| `message` | string | No | Free text |

**Success Response (200):**
```json
{
  "success": true,
  "message": "Form submitted successfully"
}
```

**Error Responses:**

| Code | Condition | Response |
|------|-----------|----------|
| 400 | Missing name or email | `{"success": false, "message": "Name and email are required"}` |
| 400 | Invalid email format | `{"success": false, "message": "Invalid email address"}` |
| 403 | Invalid CSRF token | `{"success": false, "message": "Security validation failed"}` |
| 403 | Honeypot triggered | `{"success": false, "message": "Form validation failed"}` |
| 403 | Submitted too fast (<2s) | `{"success": false, "message": "Please take your time to fill out the form"}` |
| 429 | Rate limited (5/hr) | `{"success": false, "message": "Too many submissions. Please try again later"}` |
| 500 | `mail()` failure | `{"success": false, "message": "Failed to send email"}` |

**Side Effects:**
1. Sends email to `info@techlake.co`
2. Appends entry to `tawk-tickets.log`
3. Resets rate limit counter on success

---

#### `POST /deploy.php`

**Purpose:** GitHub webhook receiver for auto-deployment.

**Authentication:** Multi-layer
1. `.htaccess` IP allowlist (GitHub IP ranges)
2. `User-Agent` must start with `GitHub-Hookshot/`
3. `X-Hub-Signature-256` header must contain valid HMAC-SHA256

**Request Headers:**

| Header | Required | Description |
|--------|----------|-------------|
| `X-Hub-Signature-256` | Yes | `sha256=<hmac_hex>` |
| `User-Agent` | Yes | Must match `GitHub-Hookshot/*` |
| `Content-Type` | Yes | `application/json` |

**Request Body:** GitHub push event JSON payload

**Responses:**

| Code | Condition | Response |
|------|-----------|----------|
| 200 | Deploy successful | `{"status": "success", "message": "Deployed successfully"}` |
| 200 | Wrong branch | `{"status": "skipped", "message": "Push was not to main branch"}` |
| 403 | Bad signature | `{"error": "Unauthorized request"}` |
| 403 | Bad User-Agent | `{"error": "Unauthorized request"}` |
| 500 | Missing secret | `{"error": "Server configuration error"}` |
| 500 | Deploy command failed | `{"status": "error", "message": "Deployment failed"}` |

---

#### `POST /ibraheem/index.php`

**Purpose:** Admin login.

**Authentication:** Password + CSRF token

**Content-Type:** `application/x-www-form-urlencoded`

**Request Body:**

| Field | Type | Required |
|-------|------|----------|
| `csrf_token` | string | Yes |
| `password` | string | Yes |

**Behaviour:**
- On success: Sets `$_SESSION['admin_logged_in'] = true`, redirects to `dashboard.php`
- On failure: Renders login page with error message
- On lockout: Renders login page with lockout message

---

#### `POST /ibraheem/dashboard.php`

**Purpose:** Clear submission logs.

**Authentication:** Session (must be logged in) + CSRF token

**Request Body:**

| Field | Type | Required |
|-------|------|----------|
| `csrf_token` | string | Yes |
| `clear_logs` | any | Yes (presence check) |

**Behaviour:** Truncates `tawk-tickets.log` to empty.

---

### 3.2 Endpoints Not Yet Implemented

| Endpoint | Purpose | Priority |
|----------|---------|----------|
| `GET /api/submissions` | JSON API for submissions (paginated) | Medium |
| `DELETE /api/submissions/:id` | Delete individual submission | Medium |
| `POST /api/auth/login` | RESTful login endpoint (returns JWT) | Low |
| `POST /api/auth/logout` | RESTful logout | Low |
| `GET /api/health` | Health check for monitoring | High |
| `GET /api/stats` | Dashboard statistics JSON | Medium |
| `POST /api/newsletter/subscribe` | Email newsletter signup | Low |
| `GET /sitemap.xml` | Dynamic sitemap generator | Medium |
| `GET /robots.txt` | SEO robots file | Medium |

---

## 4. Integration Requirements

### 4.1 Current Integrations

#### 4.1.1 Tawk.to Live Chat

| Detail | Value |
|--------|-------|
| **Status** | IMPLEMENTED |
| **Type** | Client-side JavaScript embed |
| **Widget ID** | `587cc162e8239e1d977994c5` |
| **Loading** | Async script injection on page load |
| **CSP Requirements** | `script-src`, `connect-src` (WebSocket), `frame-src`, `style-src`, `font-src`, `img-src`, `child-src` for `*.tawk.to` |
| **Data Flow** | Visitor <--> Tawk.to servers (no data passes through TechLake backend) |
| **Configuration** | Managed via Tawk.to dashboard |

**Integration Code (index.html):**
```html
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
  var s1=document.createElement("script"),
      s0=document.getElementsByTagName("script")[0];
  s1.async=true;
  s1.src='https://embed.tawk.to/587cc162e8239e1d977994c5/default';
  s1.charset='UTF-8';
  s1.setAttribute('crossorigin','*');
  s0.parentNode.insertBefore(s1,s0);
})();
</script>
```

**Post-Form Submission Hook:**
```javascript
if (window.Tawk_API) {
    Tawk_API.toggle(); // Opens chat widget after form submission
}
```

#### 4.1.2 PHP `mail()` / SMTP

| Detail | Value |
|--------|-------|
| **Status** | IMPLEMENTED |
| **Recipient** | `info@techlake.co` |
| **From** | `noreply@techlake.co` (fixed, to prevent header injection) |
| **Reply-To** | Submitter's email (CRLF-sanitised) |
| **Transport** | PHP `mail()` (uses server's sendmail/MTA) |
| **Template** | Plain text body with field values |

#### 4.1.3 GitHub Webhooks

| Detail | Value |
|--------|-------|
| **Status** | IMPLEMENTED |
| **Event** | `push` to `main` branch |
| **Authentication** | HMAC-SHA256 signature + IP allowlist |
| **Action** | `git fetch origin main && git reset --hard origin/main` |
| **Endpoint** | `POST /deploy.php` |

#### 4.1.4 Google Fonts

| Detail | Value |
|--------|-------|
| **Status** | IMPLEMENTED |
| **Font** | Inter (weights: 400, 500, 600, 700) |
| **Loading** | `<link>` with `preconnect` hints |
| **Domains** | `fonts.googleapis.com`, `fonts.gstatic.com` |

### 4.2 Integrations Not Yet Implemented

| Integration | Purpose | Priority | Notes |
|-------------|---------|----------|-------|
| **Stripe / Payment Gateway** | Accept payments for SaaS products | High | Required before product launch |
| **PHPMailer / SendGrid / Mailgun** | Reliable email delivery | High | PHP `mail()` has deliverability issues |
| **Google Analytics / Plausible** | Traffic analytics | High | Currently no analytics |
| **Google Search Console** | SEO monitoring | Medium | No `sitemap.xml` yet |
| **Cloudflare** | CDN, DDoS protection, WAF | High | Currently no CDN |
| **MySQL / PostgreSQL** | Relational data storage | High | Currently flat-file storage |
| **CRM (HubSpot / Pipedrive)** | Lead management pipeline | Medium | Leads only go to email + log file |
| **Zapier / Make** | Workflow automation (form -> CRM -> email sequence) | Medium | No automation |
| **Slack / Teams Webhook** | Real-time lead notifications | Low | Currently only email |
| **Cookie Consent Banner** | GDPR cookie compliance | High | No consent mechanism |
| **reCAPTCHA / Turnstile** | Advanced bot protection | Medium | Currently honeypot + timing only |
| **OAuth / SSO** | Admin authentication | Low | Currently password-only |
| **Sentry / Bugsnag** | Error tracking | Medium | No error reporting |
| **Uptime Robot / Pingdom** | Uptime monitoring | High | No monitoring |

---

## 5. Data Synchronisation

### 5.1 Current Data Flow

```
[Contact Form] --POST--> [form-handler.php]
                              |
                   +----------+----------+
                   |                     |
            [PHP mail()]          [tawk-tickets.log]
                   |                     |
          [info@techlake.co]     [Admin Dashboard]
                                 (manual review)
```

There is **no automated data synchronisation** between systems. Data flows are:

1. **Form submission -> Email:** Immediate, synchronous, fire-and-forget
2. **Form submission -> Log file:** Immediate, synchronous append
3. **Log file -> Admin dashboard:** Read on page load (no real-time)
4. **GitHub push -> Server files:** Webhook-triggered `git reset --hard`

### 5.2 Data Consistency Model

- **Eventual consistency:** Not applicable (no distributed systems)
- **Conflict resolution:** Last-write-wins for all flat files
- **Atomicity:** None. `file_put_contents()` with `LOCK_EX` on some files but not all
- **Durability:** Dependent on filesystem. No backups configured.

### 5.3 Known Synchronisation Gaps

| Gap | Impact | Mitigation |
|-----|--------|------------|
| Email fails silently | Lead lost if `mail()` fails but log write succeeds | Log file acts as backup |
| Log file corruption | Concurrent writes could corrupt JSON | Use `LOCK_EX` on all writes |
| Rate limit file race | Two simultaneous requests could both pass rate check | Acceptable at current traffic |
| No real-time dashboard | Admin must refresh page to see new submissions | Acceptable at current traffic |
| No email-to-CRM sync | Leads stay in inbox, no pipeline | Manual process |

### 5.4 Proposed Synchronisation Architecture (NOT YET IMPLEMENTED)

```
[Contact Form] --POST--> [form-handler.php]
                              |
                   +----------+----------+----------+
                   |          |          |          |
              [Database]  [SendGrid]  [CRM API]  [Slack]
                   |          |          |          |
              [Dashboard]  [Inbox]   [Pipeline]  [#leads]
                              |
                        [Email Sequence]
                        (drip campaign)
```

---

## 6. Security and Compliance

### 6.1 Authentication

#### Admin Authentication

| Property | Implementation |
|----------|---------------|
| Method | Password-based (single shared password) |
| Hashing | bcrypt, cost factor 12 |
| Storage | Hash in `.env` file (not in source code) |
| Session | PHP native sessions, HttpOnly, Secure, SameSite=Strict |
| Timeout | 60 minutes of inactivity |
| MFA | NOT IMPLEMENTED |
| Account lockout | 5 failed attempts = 15-minute lockout |

#### What's Not Implemented

| Feature | Priority | Notes |
|---------|----------|-------|
| Multi-factor authentication (TOTP) | High | Single password is a weak point |
| Per-user accounts | Medium | Currently one shared password |
| Password complexity requirements | Medium | No enforcement beyond what admin chooses |
| Password rotation policy | Low | No expiry mechanism |
| Login notification emails | Medium | No alerting on successful login |
| IP whitelisting for admin | Medium | Anyone can access login page |
| Account recovery | Low | No "forgot password" flow |

### 6.2 Authorisation

| Resource | Access Control |
|----------|---------------|
| Public pages | None required |
| Contact form | CSRF token (session-bound) |
| Admin login | Rate-limited password |
| Admin dashboard | Session-based (`admin_logged_in` flag) |
| Clear logs | Session + CSRF token |
| Deploy endpoint | IP allowlist + HMAC signature |

### 6.3 Input Validation & Output Encoding

| Vector | Protection |
|--------|-----------|
| XSS | `htmlspecialchars()` on all PHP output; CSP header |
| Email header injection | CRLF stripping; fixed `From` address |
| SQL injection | N/A (no database) |
| Command injection | `escapeshellarg()` in deploy.php |
| Path traversal | Regex validation on `DEPLOY_PATH` |
| CSRF | Per-session tokens validated with `hash_equals()` |

### 6.4 Transport Security

| Feature | Status |
|---------|--------|
| HTTPS enforcement | `.htaccess` 301 redirect |
| HSTS | Enabled, max-age 1 year, includeSubDomains |
| TLS version | Determined by server config (recommended: TLS 1.2+) |
| Certificate | Let's Encrypt via cPanel (assumed) |
| Certificate pinning | NOT IMPLEMENTED |

### 6.5 HTTP Security Headers

All set via `.htaccess`:

| Header | Value | Purpose |
|--------|-------|---------|
| `X-Content-Type-Options` | `nosniff` | Prevent MIME sniffing |
| `X-Frame-Options` | `SAMEORIGIN` | Prevent clickjacking |
| `X-XSS-Protection` | `1; mode=block` | Legacy XSS filter |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Limit referrer leakage |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Force HTTPS |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` | Disable device APIs |
| `Content-Security-Policy` | See Appendix 13.1 | Script/style/frame restrictions |

### 6.6 File Access Security

| Rule | Mechanism |
|------|-----------|
| `.env`, `.log`, `.json` files blocked | `<FilesMatch>` directive |
| Hidden files/dirs blocked | RewriteRule `(^\.|/\.)` |
| Directory listing disabled | `Options -Indexes` |
| `deploy.php` IP-restricted | GitHub IP ranges only |
| PHP in uploads blocked | RewriteRule on `uploads/*.php` |

### 6.7 Data Protection & Privacy

#### Data Collected

| Data Point | Source | Storage | Retention |
|------------|--------|---------|-----------|
| Name | Contact form | Log file + email | Indefinite |
| Email address | Contact form | Log file + email | Indefinite |
| Business name | Contact form | Log file + email | Indefinite |
| Problem category | Contact form | Log file + email | Indefinite |
| Free-text message | Contact form | Log file + email | Indefinite |
| IP address | Server | Rate limit files | 1 hour |
| IP address | Login attempts | JSON file | 15 minutes |
| Session ID | PHP session | Server /tmp | Until timeout |

#### Compliance Status

| Regulation | Status | Gaps |
|------------|--------|------|
| GDPR | PARTIAL | No privacy policy page, no cookie consent banner, no data deletion mechanism, no DPO, no data processing agreement |
| UK Data Protection Act 2018 | PARTIAL | Same as GDPR gaps |
| PECR (cookies) | NOT COMPLIANT | Tawk.to sets cookies without consent |
| PCI DSS | N/A | No payment processing |
| SOC 2 | NOT IMPLEMENTED | No formal controls |

#### Not Yet Implemented

| Feature | Priority |
|---------|----------|
| Privacy policy page | Critical |
| Cookie consent banner | Critical |
| Data subject access request (DSAR) workflow | High |
| Right to erasure (data deletion) | High |
| Data retention policy (auto-purge) | Medium |
| Data processing agreement with Tawk.to | High |
| Encryption at rest for log files | Medium |

---

## 7. Performance Requirements

### 7.1 Current Performance Characteristics

| Metric | Current | Target | Notes |
|--------|---------|--------|-------|
| Page load (static HTML) | ~1-2s | <1.5s | No server-side rendering delay |
| TTFB (static) | ~200-500ms | <400ms | Depends on hosting |
| TTFB (PHP endpoints) | ~300-800ms | <500ms | bcrypt adds ~100ms |
| CSS file size | ~85KB | <100KB | Single file, no minification |
| Image (logo) | 158-241KB | <100KB | Not optimised |
| Contact form submission | ~1-3s | <2s | Includes `mail()` call |
| Total page weight | ~500KB+ | <300KB | No compression configured |

### 7.2 Performance Bottlenecks

| Bottleneck | Impact | Fix |
|------------|--------|-----|
| No CSS/JS minification | Larger transfer size | Build step with minifier |
| No asset compression (gzip/brotli) | Larger transfer size | Enable `mod_deflate` in `.htaccess` |
| No image optimisation | 241KB logo | Convert to WebP, compress |
| No browser caching headers | Repeat downloads | Add `Cache-Control` / `Expires` headers |
| No CDN | Higher latency for distant users | Add Cloudflare |
| `mail()` is synchronous | Blocks response until sent | Async queue or API-based email |
| `password_hash()` on every login page load | ~100ms wasted | Already fixed (loads hash from .env) |
| Google Fonts external request | Render-blocking | Self-host fonts or use `font-display: swap` |
| No lazy loading for images | Loads all images upfront | Add `loading="lazy"` |
| No HTTP/2 server push | Sequential loading | Configure on server |

### 7.3 Proposed `.htaccess` Performance Rules (NOT YET IMPLEMENTED)

```apache
# Enable Gzip compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css text/javascript
    AddOutputFilterByType DEFLATE application/javascript application/json
</IfModule>

# Browser caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType text/html "access plus 0 seconds"
</IfModule>
```

### 7.4 Scalability Limits

| Resource | Limit | Constraint |
|----------|-------|------------|
| Concurrent users | ~50-100 | Shared hosting CPU/RAM |
| Form submissions/hour | ~100 | `mail()` throughput |
| Log file size | ~10MB practical | File read into memory for dashboard |
| Rate limit file | ~1000 IPs | JSON parsing time |
| Admin sessions | ~10 concurrent | PHP session file storage |

---

## 8. Monitoring and Logging

### 8.1 Current Logging

| Log | Location | Format | Rotation |
|-----|----------|--------|----------|
| Contact submissions | `tawk-tickets.log` | Plaintext, `---` delimited | Manual (admin dashboard) |
| Deployments | `deploy.log` | Plaintext, append-only | None |
| PHP errors | Server error log | Apache default | Server-managed |
| Login attempts | `.login_attempts.json` | JSON | Auto-purge after 15 min |
| Rate limiting | `.rate-limit` | JSON | Auto-purge after 1 hour |
| Security events | `.security.log` | Plaintext | Not yet active on server |

### 8.2 What Is NOT Logged

| Event | Impact |
|-------|--------|
| Page views / traffic | No analytics data |
| 404 errors | Unknown broken links |
| Form submission details in structured format | Cannot query or analyse leads |
| Admin actions (besides clear logs) | No audit trail |
| API response times | No performance visibility |
| Server resource usage (CPU, RAM, disk) | No capacity planning |
| SSL certificate expiry | Could expire unnoticed |
| Uptime/downtime | No availability tracking |

### 8.3 Proposed Monitoring Stack (NOT YET IMPLEMENTED)

| Tool | Purpose | Priority |
|------|---------|----------|
| **Uptime Robot** (free tier) | Uptime monitoring, alert on downtime | Critical |
| **Google Analytics / Plausible** | Traffic analytics, conversion tracking | High |
| **Sentry** | PHP error tracking with stack traces | High |
| **Cloudflare Analytics** | Request volume, threat detection, WAF logs | High |
| **Custom `/api/health` endpoint** | Automated health checks (disk, mail, sessions) | Medium |
| **Log rotation (logrotate)** | Prevent disk exhaustion from unbounded logs | High |
| **Slack/Email alerts** | Notify on: deploy fail, login lockout, high error rate | Medium |
| **Google Search Console** | SEO monitoring, indexing issues | Medium |

### 8.4 Proposed Health Check Endpoint (NOT YET IMPLEMENTED)

```
GET /api/health

Response 200:
{
  "status": "healthy",
  "checks": {
    "disk_space": "ok",
    "env_loaded": true,
    "log_writable": true,
    "mail_configured": true,
    "php_version": "8.2.0",
    "session_active": true
  },
  "timestamp": "2026-01-28T12:00:00Z"
}
```

---

## 9. Testing Requirements

### 9.1 Current Testing

**There are no automated tests.** All testing is manual.

### 9.2 Proposed Test Plan

#### 9.2.1 Unit Tests (NOT YET IMPLEMENTED)

Using PHPUnit:

| Test | File | Cases |
|------|------|-------|
| `sanitize_input()` | `form-handler.php` | Empty string, XSS payload, normal text, unicode, very long string |
| `strip_header_injection()` | `form-handler.php` | Clean email, CRLF injection, URL-encoded CRLF, null bytes |
| `get_client_ip()` | `form-handler.php` | Normal REMOTE_ADDR, missing header |
| `check_rate_limit()` | `form-handler.php` | Under limit, at limit, over limit, expired entries, empty file, corrupted file |
| `load_env()` | `env-loader.php` | Valid file, missing file, comments, quoted values, empty values, special chars |
| `check_rate_limit()` | `ibraheem/index.php` | Under limit, locked out, expired lockout, missing file |

#### 9.2.2 Integration Tests (NOT YET IMPLEMENTED)

| Test | Steps | Expected |
|------|-------|----------|
| Contact form happy path | GET CSRF -> POST form with valid data | 200, email sent, log entry created |
| Contact form CSRF failure | POST form without token | 403 |
| Contact form rate limit | POST 6 times in 1 hour | 5th succeeds, 6th returns 429 |
| Admin login success | GET login -> POST valid password | 302 redirect to dashboard |
| Admin login failure | POST wrong password 5 times | 5th shows lockout message |
| Admin dashboard view | Login -> GET dashboard | 200 with log entries |
| Admin clear logs | Login -> POST clear_logs with CSRF | Logs cleared, success message |
| Admin session timeout | Login -> wait 61 min -> GET dashboard | 302 redirect to login |
| Deploy webhook valid | POST with valid signature + GitHub UA | 200, code updated |
| Deploy webhook invalid sig | POST with wrong signature | 403 |
| Deploy webhook wrong branch | Push to `develop` | 200 with "skipped" |

#### 9.2.3 Security Tests (NOT YET IMPLEMENTED)

| Test | Method | Expected |
|------|--------|----------|
| XSS in form fields | Submit `<script>alert(1)</script>` in name | Escaped in log display and email |
| Email header injection | Submit email with `\r\n` | CRLF stripped, single recipient |
| CSRF bypass | POST form without token | 403 rejection |
| Brute force login | Attempt 10 passwords rapidly | Lockout after 5 |
| Directory traversal | Request `/../.env` | 403 blocked by .htaccess |
| `.env` access | Request `/.env` directly | 403 blocked |
| Log file access | Request `/tawk-tickets.log` | 403 blocked |
| SQL injection | N/A | No database |
| Command injection via deploy | Craft payload with shell chars | Blocked by `escapeshellarg` + regex |
| Session fixation | Set session ID in URL | Rejected (`use_only_cookies`) |
| Clickjacking | Embed in iframe | Blocked by `X-Frame-Options` |

#### 9.2.4 Cross-Browser Tests (NOT YET IMPLEMENTED)

| Browser | Versions | Tests |
|---------|----------|-------|
| Chrome | Latest, Latest-1 | Layout, form, Tawk.to |
| Firefox | Latest, Latest-1 | Layout, form, Tawk.to |
| Safari | Latest (macOS + iOS) | Layout, form, Tawk.to |
| Edge | Latest | Layout, form |
| Mobile Chrome | Android | Responsive layout, mobile menu |
| Mobile Safari | iOS | Responsive layout, mobile menu |

#### 9.2.5 Accessibility Tests (NOT YET IMPLEMENTED)

| Tool | Standard | Target |
|------|----------|--------|
| axe DevTools | WCAG 2.1 AA | Zero critical/serious violations |
| Lighthouse | Accessibility score | >= 90 |
| Keyboard navigation | Manual | All interactive elements reachable |
| Screen reader | NVDA/VoiceOver | All content readable |

---

## 10. Deployment & CI/CD

### 10.1 Current Deployment Process

```
Developer pushes to main
        |
        v
GitHub fires webhook (push event)
        |
        v
POST https://techlake.co/deploy.php
        |
        v
.htaccess checks source IP (GitHub ranges)
        |
        v
deploy.php validates:
  1. User-Agent: GitHub-Hookshot/*
  2. X-Hub-Signature-256 (HMAC-SHA256)
  3. Branch: refs/heads/main
        |
        v
Executes: cd /home/<user>/public_html
          && git fetch origin main
          && git reset --hard origin/main
        |
        v
Logs result to deploy.log
```

### 10.2 Deployment Configuration

| Setting | Value |
|---------|-------|
| Trigger | Git push to `main` branch |
| Strategy | Overwrite (`git reset --hard`) |
| Rollback | Manual (`git reset --hard <commit>`) |
| Zero-downtime | No (files replaced in-place) |
| Pre-deploy hooks | None |
| Post-deploy hooks | None |
| Health check after deploy | None |
| Notifications | None |
| Environments | Production only |

### 10.3 Files Excluded from Git

These must be uploaded manually to the server:

| File | Reason |
|------|--------|
| `.env` | Contains secrets |
| `deploy.php` | Contains deployment logic (in `.gitignore`) |

### 10.4 Server Requirements

| Requirement | Minimum |
|-------------|---------|
| PHP | 7.4+ (8.x recommended) |
| Apache | 2.4+ |
| Modules | `mod_rewrite`, `mod_headers`, `mod_deflate` |
| Git | Installed and accessible to PHP user |
| `mail()` | Functional MTA (sendmail/postfix) |
| Disk | 100MB+ free |
| Outbound HTTPS | Required for GitHub fetch |

### 10.5 Proposed CI/CD Pipeline (NOT YET IMPLEMENTED)

```yaml
# .github/workflows/deploy.yml
name: Deploy

on:
  push:
    branches: [main]

jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: PHP Lint
        run: find . -name "*.php" -exec php -l {} \;

  security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Run PHPStan
        run: vendor/bin/phpstan analyse
      - name: Check for secrets
        uses: trufflesecurity/trufflehog@main

  test:
    runs-on: ubuntu-latest
    needs: [lint]
    steps:
      - uses: actions/checkout@v4
      - name: Run PHPUnit
        run: vendor/bin/phpunit

  deploy:
    runs-on: ubuntu-latest
    needs: [lint, security, test]
    steps:
      - name: Deploy via SSH
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.HOST }}
          username: ${{ secrets.USERNAME }}
          key: ${{ secrets.SSH_KEY }}
          script: |
            cd ~/public_html
            git fetch origin main
            git reset --hard origin/main
```

### 10.6 Proposed Environments (NOT YET IMPLEMENTED)

| Environment | Purpose | URL |
|-------------|---------|-----|
| Development | Local testing | `localhost:8000` |
| Staging | Pre-production testing | `staging.techlake.co` |
| Production | Live site | `techlake.co` |

---

## 11. Edge Cases and Error Handling

### 11.1 Contact Form Edge Cases

| Edge Case | Current Handling | Recommended |
|-----------|-----------------|-------------|
| `mail()` returns false | Returns 500 JSON error | Add retry logic or queue |
| Log file write fails (disk full) | Silent failure | Check `file_put_contents` return value, alert admin |
| Extremely long input (10MB name) | PHP `post_max_size` limit | Add explicit max-length validation (e.g., 500 chars) |
| Unicode/emoji in fields | Passes through `htmlspecialchars` | Works but email encoding may break. Add `Content-Type: text/plain; charset=UTF-8` |
| Concurrent submissions from same IP | Race condition on `.rate-limit` file | Use `LOCK_EX` (partially implemented) or database |
| JavaScript disabled | Form POSTs directly to `form-handler.php` | Returns raw JSON. Should redirect or show HTML error |
| Session expired mid-form | CSRF token invalid | Clear error message shown. User must refresh. |
| Bot submits in < 2 seconds | Rejected (403) | Working as designed |
| Email with `+` or `.` variations | Passes `FILTER_VALIDATE_EMAIL` | May want to normalise (`john+spam@gmail.com` = `john@gmail.com`) |

### 11.2 Admin Portal Edge Cases

| Edge Case | Current Handling | Recommended |
|-----------|-----------------|-------------|
| `.env` file missing on server | `die('Server configuration error')` | Correct. Fails safe. |
| `.login_attempts.json` corrupted | `json_decode` returns null, falls back to `[]` | Works but loses lockout state |
| Admin opens two tabs | Each tab gets different CSRF token, last one wins | Store array of valid tokens |
| Session file deleted server-side | Logged out on next request | Expected behaviour |
| Massive log file (100MB+) | `file_get_contents` loads entire file into memory | Add pagination or streaming read |
| Browser back button after logout | Session check redirects to login | Correct |
| `tawk-tickets.log` deleted externally | Dashboard shows "No submissions" | Correct |

### 11.3 Deploy Webhook Edge Cases

| Edge Case | Current Handling | Recommended |
|-----------|-----------------|-------------|
| GitHub changes IP ranges | Webhook blocked by `.htaccess` | Monitor GitHub meta API and update ranges |
| `git` command not in PATH | `exec()` fails with non-zero exit | Logged to `deploy.log`. Use absolute path to git. |
| Disk full during git fetch | Git fails, logged | Alert admin |
| Deploy during active request | Files may be inconsistent mid-deploy | Acceptable for current traffic. Use symlink swap for zero-downtime. |
| Webhook replayed (duplicate delivery) | Deploys again (idempotent: `reset --hard` is safe) | Could deduplicate by delivery ID |
| Non-push event (e.g., ping) | Signature validates, branch check skips it | Correct |

### 11.4 Error Response Conventions

**PHP Endpoints:**
```json
{
  "success": false,
  "message": "Human-readable error description"
}
```

**HTTP Status Codes Used:**

| Code | Meaning |
|------|---------|
| 200 | Success |
| 400 | Client validation error |
| 403 | Authentication/authorisation failure |
| 405 | Wrong HTTP method |
| 429 | Rate limited |
| 500 | Server error |

---

## 12. Future Considerations

### 12.1 Short-Term (1-3 Months)

| Item | Priority | Effort | Description |
|------|----------|--------|-------------|
| Privacy policy & terms page | Critical | Low | Required for GDPR/PECR compliance |
| Cookie consent banner | Critical | Low | Tawk.to sets cookies without consent |
| Uptime monitoring | Critical | Low | Add Uptime Robot or similar |
| Replace `mail()` with SendGrid/Mailgun | High | Medium | Improve email deliverability |
| Add gzip compression | High | Low | `.htaccess` `mod_deflate` rules |
| Add browser cache headers | High | Low | `.htaccess` `mod_expires` rules |
| Optimise images (WebP) | High | Low | Reduce 241KB logo to ~30KB |
| Add Google Analytics / Plausible | High | Low | Currently zero traffic visibility |
| `sitemap.xml` and `robots.txt` | Medium | Low | SEO fundamentals |
| Add error tracking (Sentry) | Medium | Low | Currently errors are invisible |
| Log rotation | High | Low | Prevent disk exhaustion |
| Self-host Google Fonts | Medium | Low | Remove external dependency, improve TTFB |

### 12.2 Medium-Term (3-6 Months)

| Item | Priority | Effort | Description |
|------|----------|--------|-------------|
| Migrate to MySQL/PostgreSQL | High | High | Replace flat-file storage |
| Add Cloudflare | High | Medium | CDN, WAF, DDoS protection |
| Payment integration (Stripe) | High | High | Required for SaaS revenue |
| CRM integration | Medium | Medium | Automated lead pipeline |
| Email drip campaigns | Medium | Medium | Nurture leads automatically |
| Multi-user admin | Medium | Medium | Per-user accounts with roles |
| MFA for admin | High | Medium | TOTP-based second factor |
| GitHub Actions CI/CD | Medium | Medium | Automated lint, test, deploy |
| Staging environment | Medium | Medium | Test before production |
| API versioning (`/api/v1/`) | Medium | Medium | Future-proof endpoints |
| PHPUnit test suite | Medium | High | Automated regression testing |
| DSAR workflow | High | Medium | GDPR right of access |
| Data retention policy | Medium | Low | Auto-purge after N days |

### 12.3 Long-Term (6-12 Months)

| Item | Priority | Effort | Description |
|------|----------|--------|-------------|
| Migrate to PHP framework (Laravel/Slim) | Medium | High | Better structure, ORM, middleware |
| Customer portal / SaaS dashboard | High | Very High | Users manage their own tools |
| REST API for products | High | High | Backend for SaaS features |
| WebSocket notifications | Low | Medium | Real-time admin alerts |
| Docker containerisation | Medium | Medium | Consistent environments |
| Kubernetes / auto-scaling | Low | Very High | Only if traffic demands it |
| Internationalisation (i18n) | Low | Medium | Multi-language support |
| A/B testing framework | Low | Medium | Optimise conversion |
| Blog / content management | Medium | High | SEO content strategy |
| SOC 2 compliance | Low | Very High | Enterprise customer requirement |

### 12.4 Technical Debt

| Debt | File | Impact | Fix |
|------|------|--------|-----|
| Obsolete `stripslashes()` | `form-handler.php:171` | Corrupts backslash input | Remove call |
| DevTools blocking script | `index.html:857-897` | Annoys users, zero security value | Delete entire block |
| `crossorigin='*'` on Tawk.to | `index.html:904` | Overly permissive | Change to `'anonymous'` |
| XSS-prone error display | `index.html:844` | Reflects server response in `alert()` | Use allowlist of safe messages |
| No `LOCK_EX` on some file writes | `ibraheem/index.php:75,80` | Race condition on concurrent writes | Add `LOCK_EX` flag |
| No input length limits | `form-handler.php` | Memory abuse via huge payloads | Add `mb_strlen()` checks |
| Inline CSS in PHP files | `ibraheem/*.php` | Duplicated styles, hard to maintain | Extract to shared stylesheet |

---

## 13. Appendix

### 13.1 Content Security Policy (Full)

```
default-src 'self';
script-src 'self' 'unsafe-inline' 'unsafe-eval' https://*.tawk.to;
style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://*.tawk.to;
font-src 'self' https://fonts.gstatic.com https://*.tawk.to;
img-src 'self' data: https://*.tawk.to;
connect-src 'self' https://*.tawk.to wss://*.tawk.to;
frame-src https://*.tawk.to;
child-src https://*.tawk.to;
```

**Trade-offs:**
- `'unsafe-inline'` required for inline `<script>` and `<style>` tags
- `'unsafe-eval'` required by Tawk.to's internal JS
- These weaken CSP. Migration to nonce-based CSP is recommended long-term.

### 13.2 GitHub Webhook IP Ranges

Source: [GitHub Meta API](https://api.github.com/meta)

```
140.82.112.0/20
185.199.108.0/22
192.30.252.0/22
143.55.64.0/20
```

These ranges may change. Monitor `https://api.github.com/meta` periodically.

### 13.3 Environment Variables Reference

| Variable | Required | Example | Used By |
|----------|----------|---------|---------|
| `ADMIN_PASSWORD_HASH` | Yes | `$2b$12$...` | `ibraheem/index.php` |
| `GITHUB_WEBHOOK_SECRET` | Yes | `a1b2c3d4...` (64 hex chars) | `deploy.php` |
| `DEPLOY_PATH` | Yes | `/home/username/public_html` | `deploy.php` |
| `APP_ENV` | No | `production` | Reserved for future use |

### 13.4 File Permissions Reference

| File/Directory | Permission | Owner | Notes |
|----------------|-----------|-------|-------|
| `.env` | `600` | cPanel user | Secrets file |
| `.htaccess` | `644` | cPanel user | Apache reads as world-readable |
| `*.php` | `644` | cPanel user | Apache executes as cPanel user |
| `*.html` | `644` | cPanel user | Served statically |
| `*.css` | `644` | cPanel user | Served statically |
| `images/` | `755` | cPanel user | Directory listing disabled |
| `tawk-tickets.log` | `600` | cPanel user | Contains PII |
| `.login_attempts.json` | `600` | cPanel user | Security data |
| `.rate-limit` | `600` | cPanel user | Rate limit state |
| `.security.log` | `600` | cPanel user | Security audit trail |
| `deploy.log` | `600` | cPanel user | Deployment details |

### 13.5 HTTP Response Headers Example

```
HTTP/2 200
Content-Type: text/html; charset=UTF-8
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Strict-Transport-Security: max-age=31536000; includeSubDomains
Permissions-Policy: camera=(), microphone=(), geolocation=()
Content-Security-Policy: default-src 'self'; ...
```

### 13.6 Contact Form HTML Structure

```html
<form class="contact-form" action="form-handler.php" method="POST" id="contactForm">
    <input type="hidden" name="csrf_token" id="csrfToken">
    <input type="hidden" name="submit_time" id="submitTime">

    <!-- Honeypot (hidden from users) -->
    <div style="display:none;">
        <input type="text" name="website" tabindex="-1" autocomplete="off">
    </div>

    <input type="text" name="name" required>          <!-- Required -->
    <input type="email" name="email" required>         <!-- Required, validated -->
    <input type="text" name="business">                <!-- Optional -->
    <select name="problem">...</select>                <!-- Optional, enum -->
    <textarea name="message"></textarea>               <!-- Optional -->
    <button type="submit">Get Free Help</button>
</form>
```

### 13.7 Rate Limiting Summary

| Endpoint | Limit | Window | Key | Storage |
|----------|-------|--------|-----|---------|
| Contact form | 5 submissions | 1 hour | IP address (REMOTE_ADDR) | `.rate-limit` JSON file |
| Admin login | 5 attempts | 15 minutes | IP address (REMOTE_ADDR) | `.login_attempts.json` |
| CSRF token | None | -- | -- | -- |
| Deploy webhook | None | -- | IP allowlist | `.htaccess` |

### 13.8 Glossary

| Term | Definition |
|------|-----------|
| **CSRF** | Cross-Site Request Forgery. Attack where a malicious site submits requests on behalf of an authenticated user. |
| **HMAC** | Hash-based Message Authentication Code. Verifies both data integrity and authenticity. |
| **bcrypt** | Password hashing algorithm with configurable cost factor. |
| **CSP** | Content Security Policy. HTTP header that restricts which resources can load. |
| **HSTS** | HTTP Strict Transport Security. Forces browsers to use HTTPS. |
| **SameSite** | Cookie attribute that prevents cross-site request sending. |
| **Honeypot** | Hidden form field that bots fill in, allowing detection. |
| **CRLF** | Carriage Return + Line Feed (`\r\n`). Used in email header injection attacks. |
| **MTA** | Mail Transfer Agent. Server software that routes email (e.g., sendmail, postfix). |
| **PII** | Personally Identifiable Information. Data that can identify an individual. |
| **DSAR** | Data Subject Access Request. GDPR right to access personal data held about you. |
| **WAF** | Web Application Firewall. Filters malicious HTTP traffic. |
| **TOTP** | Time-based One-Time Password. Used in MFA apps like Google Authenticator. |

---

*End of Technical Documentation*
