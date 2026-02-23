# Forms

Web application for form management with workflow engine, Keycloak SSO authentication, and REST API.

## Features

- **Form Builder** - Drag-and-drop editor for creating forms
- **Workflow Engine** - Visual editor for defining automated processes
- **Keycloak SSO** - Single Sign-On authentication via Keycloak
- **REST API** - Complete API with Swagger documentation
- **Multilingual** - Support for multiple languages
- **Audit Log** - Tracking of all actions in the system
- **Email Notifications** - Automatic emails on status changes

## Technologies

| Component | Technology |
|-----------|------------|
| Backend | Laravel 11, PHP 8.3 |
| Frontend | Vue.js 3, Inertia.js, TailwindCSS |
| Database | MySQL 8.0 |
| Cache/Queue | Redis |
| Authentication | Keycloak SSO |
| Containerization | Docker, Docker Compose |

## Requirements

- Docker 20.10+
- Docker Compose 2.0+
- 2GB RAM (minimum)
- 10GB disk space

## Quick Start

### 1. Clone Repository

```bash
git clone <your-repository-url>
cd formulare
```

### 2. Configuration

```bash
# Copy the example configuration file
cp .env.example .env

# Edit configuration as needed
nano .env
```

### 3. Generate APP_KEY

```bash
# Option 1: Generate key using openssl (before first start)
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env

# Option 2: Generate using artisan (after containers are running)
docker-compose exec app php artisan key:generate
```

### 4. Start

```bash
# With integrated database (MySQL + Redis in Docker)
docker-compose --profile full up -d

# Or just the application (external database)
docker-compose up -d
```

### 5. Access

- **Application**: http://localhost:8080
- **API Documentation**: http://localhost:8080/docs

## Configuration

### Basic Settings

| Variable | Description | Default Value |
|----------|-------------|---------------|
| `APP_ENV` | Environment (production/local) | `production` |
| `APP_DEBUG` | Debug mode (true/false) | `false` |
| `APP_KEY` | Encryption key (**required**, see Quick Start) | - |
| `APP_URL` | Application URL | `http://localhost:8080` |

### Database

The application supports two modes:

#### 1. Integrated MySQL Container (default)

```bash
# Start with MySQL container
docker-compose --profile mysql up -d
```

| Variable | Description | Default Value |
|----------|-------------|---------------|
| `DB_DATABASE` | Database name | `formulare` |
| `DB_USERNAME` | Username | `formulare` |
| `DB_PASSWORD` | Password | `secret` |
| `DB_ROOT_PASSWORD` | Root password | `rootsecret` |
| `MYSQL_PORT` | External port | `3306` |

#### 2. External Database Server

```env
# .env configuration for external server
DB_CONNECTION=mysql
DB_HOST=db.example.com
DB_PORT=3306
DB_DATABASE=formulare
DB_USERNAME=app_user
DB_PASSWORD=secure_password
```

```bash
# Start without MySQL container
docker-compose up -d
```

### Redis

| Variable | Description | Default Value |
|----------|-------------|---------------|
| `REDIS_HOST` | Redis server address | `redis` |
| `REDIS_PORT` | Port | `6379` |
| `REDIS_PASSWORD` | Password (optional) | - |

### Keycloak SSO

| Variable | Description | Example |
|----------|-------------|---------|
| `KEYCLOAK_BASE_URL` | Keycloak server URL | `https://sso.example.com` |
| `KEYCLOAK_REALM` | Realm name | `MyRealm` |
| `KEYCLOAK_CLIENT_ID` | Client ID | `my-app` |
| `KEYCLOAK_CLIENT_SECRET` | Client Secret | `xxx-xxx-xxx` |

### Email (SMTP)

| Variable | Description | Example |
|----------|-------------|---------|
| `MAIL_HOST` | SMTP server | `smtp.gmail.com` |
| `MAIL_PORT` | Port | `587` |
| `MAIL_USERNAME` | Username | `user@gmail.com` |
| `MAIL_PASSWORD` | Password | `app_password` |
| `MAIL_FROM_ADDRESS` | Sender email | `noreply@example.com` |
| `MAIL_FROM_NAME` | Sender name | `Forms` |

## Docker Compose Profiles

```bash
# All services (app + mysql + redis)
docker-compose --profile full up -d

# Only MySQL
docker-compose --profile mysql up -d

# Only Redis
docker-compose --profile redis up -d

# Only application (external DB and Redis)
docker-compose up -d

# With Traefik proxy (HTTPS + Let's Encrypt)
docker-compose --profile proxy up -d

# Production deployment - hardened (Traefik + no external ports)
docker-compose --profile proxy --profile hardened up -d
```

## HTTPS with Traefik (Let's Encrypt)

The application supports automatic SSL certificates via Traefik reverse proxy.

### Basic Setup

1. **Configure domain and email in `.env`**:

```env
# Your domain (must point to the server)
TRAEFIK_DOMAIN=forms.example.com

# Email for Let's Encrypt notifications
LETSENCRYPT_EMAIL=admin@example.com

# Application URL (with HTTPS)
APP_URL=https://forms.example.com
```

2. **Start with proxy profile**:

```bash
docker-compose --profile proxy --profile full up -d
```

3. **Access**: https://forms.example.com

### Hardened Mode (production)

For maximum security, use the hardened profile - no services will be accessible from the external network except ports 80 and 443:

```bash
docker-compose --profile proxy --profile hardened up -d
```

**What hardened mode does:**
- Traefik is the only entry point (ports 80/443)
- MySQL not accessible from external network
- Redis not accessible from external network
- Nginx communicates only via internal Docker network
- Automatic HTTP -> HTTPS redirect
- Security HTTP headers (HSTS, CSP, X-Frame-Options)
- Modern TLS settings (TLS 1.2+)

### Traefik Configuration

| Variable | Description | Default Value |
|----------|-------------|---------------|
| `TRAEFIK_DOMAIN` | Application domain | - |
| `LETSENCRYPT_EMAIL` | Email for Let's Encrypt | - |
| `TRAEFIK_HTTP_PORT` | HTTP port | `80` |
| `TRAEFIK_HTTPS_PORT` | HTTPS port | `443` |
| `TRAEFIK_DASHBOARD` | Enable dashboard at /traefik/ | `false` |
| `TRAEFIK_DASHBOARD_AUTH` | Dashboard authentication (htpasswd) | - |
| `TRUSTED_PROXIES` | Trusted proxies | `*` |

### Traefik Dashboard (optional)

The dashboard is **disabled by default** (`TRAEFIK_DASHBOARD=false`).

To enable the dashboard:

1. **Generate authentication credentials**:
```bash
# Generate htpasswd
htpasswd -nb admin your_password
# Example output: admin:$apr1$xyz...
```

2. **Add to .env** (double $$ characters!):
```env
TRAEFIK_DASHBOARD=true
TRAEFIK_DASHBOARD_AUTH=admin:$$apr1$$ruca84Hq$$mbjdMZBAG.KWn7vfN/SNK/
```

3. **Restart Traefik**:
```bash
docker-compose --profile proxy --profile hardened up -d
```

4. **Access**: https://YOUR_DOMAIN/traefik/

The dashboard uses the same domain and certificate as the main application (no separate subdomain needed).

### Architecture with Traefik

```
                    +---------------------------------------------+
                    |           Docker Network                    |
Internet            |                                             |
    |               |  +----------+      +----------+             |
    |   +-------+   |  |  nginx   |      |   app    |             |
    +-->|Traefik+---+->| internal |<---->| (PHP)    |             |
        | :443  |   |  +----------+      +----+-----+             |
        +-------+   |                         |                   |
                    |  +----------+      +----v-----+             |
                    |  |  redis   |<---->|  mysql   |             |
                    |  | internal |      | internal |             |
                    |  +----------+      +----------+             |
                    +---------------------------------------------+
```

## WAF - Web Application Firewall

The application supports an optional WAF (ModSecurity with OWASP Core Rule Set) for protection against web attacks.

### Activating WAF

```bash
# With WAF (requires proxy profile)
docker-compose --profile proxy --profile waf up -d

# Full production deployment (Traefik + WAF + hardened)
docker-compose --profile proxy --profile waf --profile hardened up -d
```

### Architecture with WAF

```
Internet -> Traefik(:443) -> WAF(ModSecurity) -> nginx-internal -> app(PHP)
```

### WAF Configuration

| Variable | Description | Default Value |
|----------|-------------|---------------|
| `WAF_MODE` | Mode: `On` (blocks) / `DetectionOnly` (logs only) | `On` |
| `WAF_PARANOIA_LEVEL` | Strictness level (1-4) | `1` |
| `WAF_ANOMALY_INBOUND` | Threshold for blocking incoming requests | `5` |
| `WAF_ANOMALY_OUTBOUND` | Threshold for blocking outgoing responses | `4` |
| `WAF_MAX_BODY` | Max request body size (MB) | `100` |
| `WAF_DISABLE_RULES` | Disabled rules (rule IDs) | - |

### Paranoia Levels

| Level | Description | Usage |
|-------|-------------|-------|
| 1 | Basic protection, minimal false positives | Production (recommended) |
| 2 | Medium protection | Sensitive applications |
| 3 | High protection | High security requirements |
| 4 | Maximum protection, more false positives | Special cases |

### Testing WAF

```bash
# Test SQL injection (should be blocked)
curl "https://your-domain.com/?id=1'%20OR%20'1'='1"

# Test XSS (should be blocked)
curl "https://your-domain.com/?name=<script>alert(1)</script>"

# Check WAF logs
docker-compose logs waf
```

### Resolving False Positives

If WAF blocks legitimate requests:

1. Find the rule ID from logs:
```bash
docker-compose logs waf | grep "ModSecurity"
```

2. Add rule ID to `WAF_DISABLE_RULES`:
```env
WAF_DISABLE_RULES=920350,942100
```

3. Restart WAF:
```bash
docker-compose restart waf
```

### Detection Mode (testing)

For testing without blocking:

```env
WAF_MODE=DetectionOnly
```

WAF will only log potential attacks without blocking them.

## Architecture

### Container Overview

| Container | Description | Port | Profile | Healthcheck |
|-----------|-------------|------|---------|-------------|
| `formulare-traefik` | Traefik reverse proxy | 80, 443 | proxy | - |
| `formulare-waf` | ModSecurity WAF | - (internal) | waf | - |
| `formulare-app` | PHP-FPM application server | 9000 (internal) | - | PHP-FPM ping |
| `formulare-nginx` | Nginx web server | 8080 | - | HTTP /health |
| `formulare-nginx-internal` | Nginx (hardened) | - (internal) | hardened, waf | HTTP /health |
| `formulare-mysql` | MySQL database | 3306 | mysql, full | mysqladmin ping |
| `formulare-mysql-internal` | MySQL (hardened) | - (internal) | hardened | mysqladmin ping |
| `formulare-redis` | Redis cache | 6379 | redis, full | redis-cli ping |
| `formulare-redis-internal` | Redis (hardened) | - (internal) | hardened | redis-cli ping |
| `formulare-queue` | Queue worker | - | - | - |
| `formulare-queue-high` | Priority queue worker | - | - | - |
| `formulare-scheduler` | Cron scheduler | - | - | - |

### Detailed Container Descriptions

#### Core Application Containers

##### `formulare-app` (PHP-FPM)
**Purpose:** Main application server running Laravel PHP application.

| Property | Value |
|----------|-------|
| Image | Custom (php:8.3-fpm based) |
| Listens on | Port 9000 (FastCGI) |
| Depends on | MySQL, Redis (implicit via entrypoint) |

**Responsibilities:**
- Executes all PHP/Laravel application code
- Handles business logic, form processing, workflow execution
- Manages database connections via Eloquent ORM
- Processes API requests
- Renders Inertia.js pages for Vue.js frontend

**Communication:**
- Receives FastCGI requests from Nginx on port 9000
- Connects to MySQL on port 3306 for data persistence
- Connects to Redis on port 6379 for cache, sessions, and queue

---

##### `formulare-nginx` / `formulare-nginx-internal`
**Purpose:** Web server that serves static files and proxies PHP requests to app container.

| Property | Value |
|----------|-------|
| Image | nginx:alpine |
| Listens on | Port 80 (HTTP) |
| Depends on | app (service_healthy) |

**Responsibilities:**
- Serves static assets (CSS, JS, images) from `/public` directory
- Proxies PHP requests to PHP-FPM (app container)
- Handles SSL termination (when not using Traefik)
- Provides `/health` endpoint for healthchecks

**Variants:**
- `nginx` - External access on port 8080 (development/simple deployment)
- `nginx-internal` - No external port, only accessible within Docker network (production with Traefik)

---

##### `formulare-queue` (Queue Worker)
**Purpose:** Background job processor for asynchronous tasks.

| Property | Value |
|----------|-------|
| Image | Same as app |
| Command | `php artisan queue:work` |
| Depends on | app (service_healthy) |

**Responsibilities:**
- Processes jobs from Redis queues
- Executes workflow steps asynchronously
- Sends email notifications
- Handles long-running tasks without blocking HTTP requests

**Processed job types:**
- Email sending (SendEmailJob)
- Workflow step execution (ExecuteWorkflowStepJob)
- Notification dispatch
- Audit log processing

---

##### `formulare-queue-high` (Priority Queue Worker)
**Purpose:** Dedicated worker for high-priority jobs.

| Property | Value |
|----------|-------|
| Image | Same as app |
| Command | `php artisan queue:work --queue=high,default` |
| Depends on | app (service_healthy) |

**Responsibilities:**
- Processes high-priority queue first, then default queue
- Ensures critical jobs (approvals, urgent notifications) are processed quickly

---

##### `formulare-scheduler` (Cron Scheduler)
**Purpose:** Executes scheduled tasks (Laravel scheduler).

| Property | Value |
|----------|-------|
| Image | Same as app |
| Command | Runs `schedule:run` every 60 seconds |
| Depends on | app (service_healthy) |

**Responsibilities:**
- Runs Laravel scheduled commands
- Cleans up old sessions and cache
- Processes recurring workflow triggers
- Generates reports

---

#### Infrastructure Containers

##### `formulare-mysql` / `formulare-mysql-internal`
**Purpose:** Relational database for persistent data storage.

| Property | Value |
|----------|-------|
| Image | mysql:8.0 |
| Listens on | Port 3306 |
| Volume | mysql-data |

**Stores:**
- Forms definitions and configurations
- Form submissions and responses
- Workflow definitions and execution logs
- User settings and preferences
- Audit logs

**Variants:**
- `mysql` - External access on port 3306 (development)
- `mysql-internal` - No external port (production with hardened profile)

---

##### `formulare-redis` / `formulare-redis-internal`
**Purpose:** In-memory data store for cache, sessions, and message queues.

| Property | Value |
|----------|-------|
| Image | redis:alpine |
| Listens on | Port 6379 |
| Volume | redis-data |

**Used for:**
- **Cache:** Application cache (config, routes, views)
- **Sessions:** User session storage
- **Queues:** Job queue for background processing
- **Rate limiting:** API rate limiting counters

**Variants:**
- `redis` - External access on port 6379 (development)
- `redis-internal` - No external port (production with hardened profile)

---

#### Security Containers

##### `formulare-traefik`
**Purpose:** Reverse proxy with automatic SSL certificate management.

| Property | Value |
|----------|-------|
| Image | traefik:v3.0 |
| Listens on | Ports 80 (HTTP), 443 (HTTPS) |
| Volume | traefik-certs |

**Responsibilities:**
- SSL/TLS termination with Let's Encrypt certificates
- Automatic HTTP to HTTPS redirect
- Load balancing (if multiple instances)
- Security headers injection
- Optional dashboard for monitoring

---

##### `formulare-waf`
**Purpose:** Web Application Firewall for attack protection.

| Property | Value |
|----------|-------|
| Image | owasp/modsecurity-crs:nginx-alpine |
| Depends on | nginx-internal (service_healthy) |

**Protects against:**
- SQL Injection attacks
- Cross-Site Scripting (XSS)
- Remote File Inclusion
- Command Injection
- OWASP Top 10 vulnerabilities

---

### Startup Order and Dependencies

Services start in the following order based on healthcheck dependencies:

```
┌─────────────────────────────────────────────────────────────────┐
│                      STARTUP SEQUENCE                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Phase 1: Infrastructure (parallel)                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │   mysql     │  │   redis     │  │  traefik    │             │
│  │  (healthy)  │  │  (healthy)  │  │  (started)  │             │
│  └──────┬──────┘  └──────┬──────┘  └─────────────┘             │
│         │                │                                      │
│         └────────┬───────┘                                      │
│                  ▼                                              │
│  Phase 2: Application                                           │
│  ┌─────────────────────────────────────────────────┐           │
│  │                    app                           │           │
│  │  - Waits for MySQL connection                   │           │
│  │  - Runs migrations                              │           │
│  │  - Initializes application                      │           │
│  │  - Becomes healthy (PHP-FPM ping responds)      │           │
│  └──────────────────────┬──────────────────────────┘           │
│                         │                                       │
│         ┌───────────────┼───────────────┐                      │
│         ▼               ▼               ▼                      │
│  Phase 3: Dependent services (parallel)                        │
│  ┌───────────┐  ┌───────────┐  ┌─────────────┐                │
│  │   nginx   │  │   queue   │  │  scheduler  │                │
│  │ (healthy) │  │ (started) │  │  (started)  │                │
│  └─────┬─────┘  └───────────┘  └─────────────┘                │
│        │                                                        │
│        ▼                                                        │
│  Phase 4: Security layer (if enabled)                          │
│  ┌─────────────┐                                               │
│  │     waf     │                                               │
│  │  (started)  │                                               │
│  └─────────────┘                                               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

### Communication Diagrams

#### Development Mode (Basic)
Simple deployment without proxy, direct access on port 8080.

```
                                    Docker Network (formulare-network)
┌──────────────────────────────────────────────────────────────────────────────┐
│                                                                              │
│  User Browser                                                                │
│       │                                                                      │
│       │ HTTP :8080                                                           │
│       ▼                                                                      │
│  ┌─────────┐     FastCGI :9000      ┌─────────┐                             │
│  │  nginx  │ ──────────────────────>│   app   │                             │
│  │  :8080  │<──────────────────────│ (PHP)   │                             │
│  └─────────┘     PHP Response       └────┬────┘                             │
│       │                                  │                                   │
│       │ Static files                     │ TCP :3306 (MySQL)                │
│       │ (CSS, JS, images)                │ TCP :6379 (Redis)                │
│       │                                  │                                   │
│       ▼                                  ▼                                   │
│  ┌─────────────┐               ┌─────────┐  ┌─────────┐                     │
│  │   /public   │               │  mysql  │  │  redis  │                     │
│  │   volume    │               │  :3306  │  │  :6379  │                     │
│  └─────────────┘               └─────────┘  └─────────┘                     │
│                                     │            │                           │
│                          ┌──────────┴────────────┴──────────┐               │
│                          │        Background Workers         │               │
│                          │  ┌───────┐ ┌──────────┐ ┌──────┐ │               │
│                          │  │ queue │ │queue-high│ │sched.│ │               │
│                          │  └───────┘ └──────────┘ └──────┘ │               │
│                          └──────────────────────────────────┘               │
│                                                                              │
└──────────────────────────────────────────────────────────────────────────────┘

External Ports: 8080 (HTTP), 3306 (MySQL), 6379 (Redis)
```

#### Production Mode (Hardened with Traefik)
Secure deployment with SSL, no external database ports.

```
                                    Docker Network (formulare-network)
┌──────────────────────────────────────────────────────────────────────────────┐
│                                                                              │
│  Internet                                                                    │
│     │                                                                        │
│     │ HTTPS :443                                                             │
│     ▼                                                                        │
│  ┌─────────────┐                                                            │
│  │   traefik   │  ◄── Let's Encrypt SSL                                     │
│  │  :80/:443   │  ◄── HTTP→HTTPS redirect                                   │
│  └──────┬──────┘  ◄── Security headers                                      │
│         │                                                                    │
│         │ HTTP (internal)                                                    │
│         ▼                                                                    │
│  ┌──────────────┐    FastCGI :9000     ┌─────────┐                          │
│  │nginx-internal│ ────────────────────>│   app   │                          │
│  │  (no port)   │<────────────────────│ (PHP)   │                          │
│  └──────────────┘    PHP Response      └────┬────┘                          │
│         │                                   │                                │
│         │ Static files                      │                                │
│         ▼                                   ▼                                │
│  ┌─────────────┐                  ┌────────────────┐  ┌────────────────┐    │
│  │   /public   │                  │ mysql-internal │  │ redis-internal │    │
│  │   volume    │                  │   (no port)    │  │   (no port)    │    │
│  └─────────────┘                  └────────────────┘  └────────────────┘    │
│                                          │                   │               │
│                               ┌──────────┴───────────────────┴──────┐       │
│                               │         Background Workers          │       │
│                               │  ┌───────┐ ┌──────────┐ ┌────────┐ │       │
│                               │  │ queue │ │queue-high│ │scheduler│ │       │
│                               │  └───────┘ └──────────┘ └────────┘ │       │
│                               └─────────────────────────────────────┘       │
│                                                                              │
└──────────────────────────────────────────────────────────────────────────────┘

External Ports: 80, 443 (Traefik only)
Internal Only: MySQL, Redis, Nginx, App
```

#### Production Mode with WAF
Maximum security with Web Application Firewall.

```
                                    Docker Network (formulare-network)
┌──────────────────────────────────────────────────────────────────────────────┐
│                                                                              │
│  Internet                                                                    │
│     │                                                                        │
│     │ HTTPS :443                                                             │
│     ▼                                                                        │
│  ┌─────────────┐                                                            │
│  │   traefik   │  ◄── SSL/TLS termination                                   │
│  │  :80/:443   │                                                            │
│  └──────┬──────┘                                                            │
│         │                                                                    │
│         │ HTTP (internal)                                                    │
│         ▼                                                                    │
│  ┌─────────────┐                                                            │
│  │     waf     │  ◄── ModSecurity + OWASP CRS                               │
│  │(ModSecurity)│  ◄── SQL injection protection                              │
│  └──────┬──────┘  ◄── XSS protection                                        │
│         │                                                                    │
│         │ HTTP (if allowed)                                                  │
│         ▼                                                                    │
│  ┌──────────────┐    FastCGI :9000     ┌─────────┐                          │
│  │nginx-internal│ ────────────────────>│   app   │                          │
│  │  (no port)   │<────────────────────│ (PHP)   │                          │
│  └──────────────┘                      └────┬────┘                          │
│                                             │                                │
│                                             ▼                                │
│                                   ┌────────────────┐  ┌────────────────┐    │
│                                   │ mysql-internal │  │ redis-internal │    │
│                                   └────────────────┘  └────────────────┘    │
│                                                                              │
└──────────────────────────────────────────────────────────────────────────────┘

Request Flow: Internet → Traefik → WAF → Nginx → App → MySQL/Redis
```

---

### Data Flow

#### Form Submission Flow
```
┌────────────────────────────────────────────────────────────────────────────┐
│                         FORM SUBMISSION FLOW                               │
├────────────────────────────────────────────────────────────────────────────┤
│                                                                            │
│  1. User submits form                                                      │
│     Browser ──POST /api/forms/{slug}/submit──> Nginx ──> App               │
│                                                                            │
│  2. App validates and stores submission                                    │
│     App ──INSERT──> MySQL (form_submissions table)                         │
│                                                                            │
│  3. App triggers workflow (if configured)                                  │
│     App ──dispatch(ExecuteWorkflowStepJob)──> Redis Queue                  │
│                                                                            │
│  4. Queue worker picks up job                                              │
│     Queue Worker <──poll──> Redis Queue                                    │
│                                                                            │
│  5. Workflow step executes (e.g., API call, email)                         │
│     Queue Worker ──HTTP──> External API                                    │
│     Queue Worker ──SMTP──> Email Server                                    │
│                                                                            │
│  6. Result logged                                                          │
│     Queue Worker ──UPDATE──> MySQL (workflow_executions)                   │
│                                                                            │
└────────────────────────────────────────────────────────────────────────────┘
```

#### Authentication Flow (Keycloak SSO)
```
┌────────────────────────────────────────────────────────────────────────────┐
│                       KEYCLOAK SSO AUTHENTICATION                          │
├────────────────────────────────────────────────────────────────────────────┤
│                                                                            │
│  1. User clicks "Login"                                                    │
│     Browser ──GET /auth/redirect──> App                                    │
│                                                                            │
│  2. App redirects to Keycloak                                              │
│     App ──302 Redirect──> Browser ──> Keycloak                            │
│                                                                            │
│  3. User authenticates at Keycloak                                         │
│     Browser <──Login Form──> Keycloak                                      │
│                                                                            │
│  4. Keycloak redirects back with code                                      │
│     Keycloak ──302 + code──> Browser ──> App /auth/callback               │
│                                                                            │
│  5. App exchanges code for tokens                                          │
│     App ──POST /token──> Keycloak                                         │
│     Keycloak ──access_token, id_token──> App                              │
│                                                                            │
│  6. App creates/updates user and session                                   │
│     App ──UPSERT user──> MySQL                                            │
│     App ──SET session──> Redis                                            │
│                                                                            │
│  7. User is logged in                                                      │
│     App ──302 + session cookie──> Browser                                  │
│                                                                            │
└────────────────────────────────────────────────────────────────────────────┘
```

---

### Volume Mapping

| Volume | Purpose | Mounted in |
|--------|---------|------------|
| `mysql-data` | Database files | mysql, mysql-internal |
| `redis-data` | Redis persistence (AOF) | redis, redis-internal |
| `app-storage` | Uploaded files, generated docs | app, queue, queue-high, scheduler |
| `app-logs` | Application logs | app, queue, queue-high, scheduler |
| `app-public` | Public assets (shared with nginx) | app, nginx, nginx-internal |
| `traefik-certs` | Let's Encrypt certificates | traefik |
| `waf-logs` | WAF/ModSecurity logs | waf |

---

### Network Configuration

All containers communicate through a single Docker bridge network:

```
Network: formulare-network (bridge)

┌─────────────────────────────────────────────────────────────────┐
│                    formulare-network                            │
│                                                                 │
│  DNS Resolution (Docker internal DNS):                          │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  app            → formulare-app:9000                    │   │
│  │  mysql          → formulare-mysql:3306                  │   │
│  │                   OR formulare-mysql-internal:3306      │   │
│  │  redis          → formulare-redis:6379                  │   │
│  │                   OR formulare-redis-internal:6379      │   │
│  │  nginx-internal → formulare-nginx-internal:80           │   │
│  │  nginx-backend  → alias for nginx-internal              │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Note: *-internal containers have network aliases               │
│        matching non-internal service names                      │
│        (e.g., mysql-internal has alias "mysql")                 │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## Automatic Initialization

On first start, the following happens automatically:

1. Waits for MySQL availability
2. Checks for table existence
3. Runs migrations (if needed)
4. Generates APP_KEY (if not set)
5. Creates storage symlink
6. Runs seeders (if `RUN_SEEDERS=true`)

## Development

### Local Development with Live Reload

```bash
# Copy override file
cp docker-compose.override.yml.example docker-compose.override.yml

# Start with bind mounts
docker-compose --profile full up -d
```

### Useful Commands

```bash
# Application logs
docker-compose logs -f app

# Run artisan commands
docker-compose exec app php artisan migrate
docker-compose exec app php artisan cache:clear

# Access container shell
docker-compose exec app bash

# Restart services
docker-compose restart

# Stop
docker-compose down

# Stop and remove volumes
docker-compose down -v
```

### Rebuild After Code Changes

```bash
docker-compose build --no-cache app
docker-compose up -d
```

## API Documentation

Swagger documentation is available at `/docs`:

- **Local**: http://localhost:8080/docs
- **Production**: https://your-domain.com/docs

### API Authentication

The API uses system tokens. Create a token in Admin Panel -> Settings -> API Tokens.

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     https://your-domain.com/api/v1/submissions
```

## Backup

### Database

```bash
# Backup
docker-compose exec mysql mysqldump -u root -p formulare > backup.sql

# Restore
docker-compose exec -T mysql mysql -u root -p formulare < backup.sql
```

### Volumes

```bash
# Backup all volumes
docker run --rm -v formulare-app-storage:/data -v $(pwd):/backup \
    alpine tar czf /backup/storage-backup.tar.gz -C /data .
```

## Troubleshooting

### Application Won't Start

```bash
# Check logs
docker-compose logs app

# Check MySQL healthcheck
docker-compose ps
```

### Migrations Fail

```bash
# Manual migration
docker-compose exec app php artisan migrate --force

# Reset database (WARNING: deletes data!)
docker-compose exec app php artisan migrate:fresh --seed
```

### Permission Denied

```bash
# Fix volume permissions
docker-compose exec app chmod -R 775 storage bootstrap/cache
```

### ContainerConfig Error (docker-compose 1.29.x)

If you see `KeyError: 'ContainerConfig'` error, this is a known bug with older docker-compose versions.

**Solution 1: Clean restart**
```bash
# Stop everything
docker-compose --profile proxy --profile hardened down

# Remove all formulare containers (including broken ones)
docker rm -f $(docker ps -aq --filter "name=formulare") 2>/dev/null

# Remove dangling images
docker image prune -f

# Start fresh
docker-compose --profile proxy --profile hardened up -d
```

**Solution 2: Upgrade docker-compose (recommended)**
```bash
# Remove old version
sudo apt remove docker-compose

# Install latest version
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Verify
docker-compose --version
```

### Redis Connection Error

If you see `DENIED Redis is running in protected mode`:

```bash
# Restart Redis container
docker-compose restart redis-internal

# Or restart all services
docker-compose --profile proxy --profile hardened down
docker-compose --profile proxy --profile hardened up -d
```

## License

MIT License

## Contact

- **Repository**: <your-repository-url>
