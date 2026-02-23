# Formulare - Projektova dokumentacia

## Obsah

1. [Prehlad projektu](#1-prehlad-projektu)
2. [Architektura a technologie](#2-architektura-a-technologie)
3. [Kontajnerova infrastruktura](#3-kontajnerova-infrastruktura)
4. [Moduly aplikacie](#4-moduly-aplikacie)
5. [Datovy model](#5-datovy-model)
6. [API rozhranie](#6-api-rozhranie)
7. [Bezpecnostne mechanizmy](#7-bezpecnostne-mechanizmy)

---

## 1. Prehlad projektu

Formulare je webova aplikacia na spravovanie dynamickych formularov, ich vyplnanie, schvalovanie a automatizaciu workflow procesov. Aplikacia je postavena na Laravel 11 (PHP 8.3) s Vue.js 3 frontendom a bezi ako sada Docker kontajnerov.

### Hlavne funkcie

- Vizualny editor formularov (drag-and-drop)
- Workflow engine s grafickym editorom (Vue Flow)
- Schvalovaci proces s email notifikaciami
- Viacjazykova podpora (SK/EN)
- Keycloak SSO autentifikacia
- REST API pre externu integraciu
- Audit log a zalohovanie

---

## 2. Architektura a technologie

### Tech Stack

| Vrstva | Technologia | Verzia | Ucel |
|--------|-------------|--------|------|
| **Backend** | Laravel | 11 | PHP framework, MVC, Eloquent ORM, Queue |
| **Runtime** | PHP-FPM | 8.3 | Spracovanie HTTP requestov |
| **Frontend** | Vue.js | 3 | Reaktivne UI s Composition API |
| **SPA Bridge** | Inertia.js | - | Prepojenie Laravel + Vue bez REST API |
| **CSS** | Tailwind CSS | 3 | Utility-first styling |
| **Bundler** | Vite | 6 | Build, code splitting, HMR |
| **Databaza** | MySQL | 8.0 | Relacna databaza |
| **Cache/Queue** | Redis | 7.4 | Cache, sessions, fronta uloh |
| **Web server** | Nginx | alpine | Staticke subory, reverse proxy na PHP-FPM |
| **Auth** | Keycloak | - | SSO autentifikacia (OpenID Connect) |
| **Reverse proxy** | Traefik | 3.0 | SSL/TLS terminacia, Let's Encrypt |
| **WAF** | ModSecurity | CRS | OWASP firewall s Core Rule Set |
| **Workflow editor** | Vue Flow | - | Vizualny node-based editor |
| **Form builder** | Vuedraggable | - | Drag-and-drop editor poli |
| **i18n** | vue-i18n | - | Frontend preklady SK/EN |

### Schema architektury

```
                          +-----------+
                          |  Browser  |
                          +-----+-----+
                                |
                         HTTPS (443)
                                |
                    +-----------v-----------+
                    |       Traefik         |
                    |  (SSL + Let's Encrypt)|
                    +-----------+-----------+
                                |
                    +-----------v-----------+
                    |    WAF (ModSecurity)  |   <- OWASP Core Rule Set
                    |    [volitelny profil] |
                    +-----------+-----------+
                                |
                    +-----------v-----------+
                    |        Nginx          |
                    |   (staticke subory,   |
                    |    proxy na PHP-FPM)  |
                    +-----------+-----------+
                                |
                       FastCGI (:9000)
                                |
                    +-----------v-----------+
                    |     APP (PHP-FPM)     |
                    |    Laravel 11 + Vue 3 |
                    +-----------+-----------+
                           /    |    \
                          /     |     \
                   +-----+  +--+--+  +---------+
                   |MySQL|  |Redis|  | Keycloak|
                   | 8.0 |  | 7.4 |  |  (SSO)  |
                   +-----+  +--+--+  +---------+
                                |
                    +-----------+-----------+
                    |   Queue Workers (2x)  |
                    |  + Scheduler (cron)   |
                    +-----------------------+
```

---

## 3. Kontajnerova infrastruktura

### Prehlad kontajnerov

Aplikacia sa sklada z 12 Docker kontajnerov, z ktorych su 5 zakladnych (vzdy bezi) a 7 volitelnych (podla deployment profilu).

```
+==============================================================+
|                    Docker Compose Setup                       |
+==============================================================+
|                                                              |
|  ZAKLADNE KONTAJNERY (vzdy bezia):                          |
|  +--------+  +-------+  +-------+  +----------+  +-------+  |
|  |  APP   |  | NGINX |  | QUEUE |  |QUEUE-HIGH|  |SCHEDU-|  |
|  |PHP-FPM |  |static |  |worker |  | priority |  | LER   |  |
|  | :9000  |  | :8080 |  |       |  |  worker  |  | cron  |  |
|  +--------+  +-------+  +-------+  +----------+  +-------+  |
|                                                              |
|  VOLITELNE KONTAJNERY (podla profilu):                       |
|  +-------+  +-------+  +---------+  +-----+  +-----------+  |
|  | MySQL |  | Redis |  | Traefik |  | WAF |  |NGINX-INT. |  |
|  | :3306 |  | :6379 |  |:80/:443 |  |     |  | (no port) |  |
|  +-------+  +-------+  +---------+  +-----+  +-----------+  |
|                                                              |
|  HARDENED VARIANTY (bez exposed portov):                     |
|  +-----------+  +------------+                               |
|  |MySQL-INT. |  | Redis-INT. |                               |
|  | (no port) |  |  (no port) |                               |
|  +-----------+  +------------+                               |
+==============================================================+
```

### Detail kontajnerov

#### Zakladne kontajnery (vzdy aktivne)

| Kontajner | Image | Port | Ucel |
|-----------|-------|------|------|
| **formulare-app** | `php:8.3-fpm` (custom) | 9000 (interny) | Laravel aplikacny server. Spracovava HTTP requesty cez FastCGI. Obsahuje cely PHP backend, Eloquent modely, controllery, services. Pri starte spusta migracie, seedy a cache. |
| **formulare-nginx** | `nginx:alpine` | 8080 → 80 | Web server. Servuje staticke subory (JS, CSS, obrazky) s 1-rocnym cache. Proxy requestov na PHP-FPM (:9000). Blokuje pristup k citlivym suborom (.env, .git, storage/). |
| **formulare-queue** | `php:8.3-fpm` (custom) | - | Queue worker pre spracovanie uloh na pozadi. Spracovava default frontu - odosielanie emailov, workflow kroky (API calls, approvals), exporty. Max 3 pokusy, 1h timeout, 256MB limit. |
| **formulare-queue-high** | `php:8.3-fpm` (custom) | - | Prioritny queue worker. Spracovava `high` frontu pred `default`. Pouziva sa pre urgentne ulohy - schvalovanie, kriticke notifikacie. Rovnake limity ako queue. |
| **formulare-scheduler** | `php:8.3-fpm` (custom) | - | Planovac uloh (cron). Kazdu minutu spusta `php artisan schedule:run`. Vykonava napalnoveane ulohy - cistenie logov, automaticke zalohy, expiraciu tokenov. |

#### Volitelne kontajnery (podla profilu)

| Kontajner | Profil | Image | Port | Ucel |
|-----------|--------|-------|------|------|
| **formulare-mysql** | `mysql`, `full` | `mysql:8.0.41` | 3306 | Relacna databaza. Uklada formulare, odpovede, workflows, uzivatelov, audit logy. Optimalizovana: 256MB buffer pool, 200 max connections. |
| **formulare-redis** | `redis`, `full` | `redis:7.4-alpine` | 6379 | In-memory cache a message broker. Pouziva sa na: cache (5min TTL), sessions, queue jobs. 256MB limit s LRU eviction, AOF persistencia. |
| **formulare-traefik** | `proxy` | `traefik:v3.0` | 80, 443 | Reverse proxy s automatickym SSL. Let's Encrypt ACME (HTTP-01 challenge), HTTP→HTTPS redirect, volitelny dashboard. |
| **formulare-waf** | `waf` | `owasp/modsecurity-crs:nginx-alpine` | - | OWASP Web Application Firewall. ModSecurity s Core Rule Set, blokuje SQL injection, XSS, path traversal. Paranoia level 1-4. |
| **formulare-nginx-internal** | `waf`, `hardened` | `nginx:alpine` | - | Interny Nginx bez exposed portov. Pouziva sa za WAF alebo v hardened mode. Pristupny len cez internu Docker siet. |
| **formulare-mysql-internal** | `hardened` | `mysql:8.0.41` | - | MySQL bez exposed portov. Nahradza `formulare-mysql` v hardened mode. Pristupny len z internej siete. |
| **formulare-redis-internal** | `hardened` | `redis:7.4-alpine` | - | Redis bez exposed portov. Nahradza `formulare-redis` v hardened mode. Pristupny len z internej siete. |

### Deployment profily

```
                    Zakladny        Full          Proxy         WAF          Hardened
                    (default)    (--profile     (--profile    (--profile   (--profile proxy
                                   full)         proxy)     proxy waf)    waf hardened)
  +-----------+
  | APP       |       x              x             x            x              x
  | NGINX     |       x              x             x            -              -
  | QUEUE     |       x              x             x            x              x
  | QUEUE-HIGH|       x              x             x            x              x
  | SCHEDULER |       x              x             x            x              x
  +-----------+
  | MySQL     |       -              x             -            -              -
  | Redis     |       -              x             -            -              -
  | Traefik   |       -              -             x            x              x
  | WAF       |       -              -             -            x              x
  | NGINX-INT |       -              -             -            x              x
  | MySQL-INT |       -              -             -            -              x
  | Redis-INT |       -              -             -            -              x
  +-----------+
```

**Prikazy na spustenie:**

```bash
docker-compose up -d                                              # Zakladny
docker-compose --profile full up -d                               # Full (s DB + Redis)
docker-compose --profile proxy up -d                              # S Traefik SSL
docker-compose --profile proxy --profile waf up -d                # S WAF
docker-compose --profile proxy --profile waf --profile hardened up -d  # Hardened
```

### Sietova topologia

```
+--[ formulare-network (bridge) ]----------------------------------+
|                                                                  |
|  Internet                                                        |
|     |                                                            |
|  [ Traefik :80/:443 ] ---> [ WAF ] ---> [ Nginx-Internal ]      |
|     |                                        |                   |
|     +-- (bez WAF) --> [ Nginx :8080 ] -------+                   |
|                                              |                   |
|                                    FastCGI :9000                 |
|                                              |                   |
|                                        [ APP (PHP-FPM) ]        |
|                                         /       |       \        |
|                                        /        |        \       |
|                               [ MySQL ]   [ Redis ]  [ Keycloak ]|
|                                :3306       :6379      (externy)  |
|                                              |                   |
|                          [ Queue ] [ Queue-High ] [ Scheduler ]  |
|                                                                  |
+------------------------------------------------------------------+
```

### Volumes (trvale ulozisko)

| Volume | Kontajnery | Obsah |
|--------|-----------|-------|
| `formulare-mysql-data` | MySQL | Databazove subory |
| `formulare-redis-data` | Redis | Persistentne Redis data (AOF) |
| `formulare-app-storage` | APP, Queue, Scheduler | Nahrate subory, backupy |
| `formulare-app-logs` | APP, Queue, Scheduler | Aplikacne logy |
| `formulare-app-public` | APP, Nginx | Skompilovany frontend (JS, CSS) |
| `formulare-traefik-certs` | Traefik | Let's Encrypt certifikaty |
| `formulare-waf-logs` | WAF | Access a error logy WAF |

---

## 4. Moduly aplikacie

### Schema modulov

```
+================================================================+
|                      FORMULARE - Moduly                         |
+================================================================+
|                                                                 |
|  +-------------------+    +--------------------+                |
|  | FORM BUILDER      |    | WORKFLOW ENGINE     |               |
|  | - Drag-drop editor|    | - Vizualny editor   |               |
|  | - Typy poli (10+) |    | - 7 typov uzlov     |               |
|  | - Podmienky       |    | - Async spracovanie |               |
|  | - Verzie          |--->| - Retry logika      |               |
|  | - Kategorie       |    | - Sablony premennych|               |
|  | - Viacjazycnost   |    | - Verzie            |               |
|  +-------------------+    +--------------------+                |
|           |                        |                            |
|           v                        v                            |
|  +-------------------+    +--------------------+                |
|  | SUBMISSIONS       |    | APPROVALS          |                |
|  | - Vyplnanie       |    | - Token-based      |                |
|  | - Stavy           |--->| - Email notifikacie |               |
|  | - Bulk operacie   |    | - 7-dnova expiracia|                |
|  | - Export CSV      |    | - Komentare        |                |
|  | - Interne poznamky|    +--------------------+                |
|  +-------------------+             |                            |
|           |                        v                            |
|           |               +--------------------+                |
|           +-------------->| EMAIL TEMPLATES    |                |
|                           | - Bilingvalne SK/EN|                |
|                           | - Sablony (6 typov)|                |
|                           | - Preview          |                |
|                           +--------------------+                |
|                                                                 |
|  +-------------------+    +--------------------+                |
|  | USER MANAGEMENT   |    | SYSTEM SETTINGS    |                |
|  | - Keycloak SSO    |    | - SMTP konfig      |                |
|  | - Role (5 urovni) |    | - Keycloak konfig  |                |
|  | - Permissions/form |    | - Branding (logo)  |                |
|  | - Nastavenia      |    | - API tokeny       |                |
|  +-------------------+    +--------------------+                |
|                                                                 |
|  +-------------------+    +--------------------+                |
|  | AUDIT LOG         |    | BACKUP & RESTORE   |                |
|  | - Vsetky akcie    |    | - JSON export      |                |
|  | - Old/new hodnoty |    | - FTP/S3 upload    |                |
|  | - IP, user-agent  |    | - Planovane zalohy |                |
|  | - Filtrovanie     |    | - Obnova dat       |                |
|  +-------------------+    +--------------------+                |
|                                                                 |
|  +-------------------+    +--------------------+                |
|  | ANNOUNCEMENTS     |    | EXTERNAL API       |                |
|  | - Casovo ohranict.|    | - REST endpoints   |                |
|  | - Typy a ikony    |    | - System tokeny    |                |
|  | - Poradie         |    | - Import/Export    |                |
|  +-------------------+    +--------------------+                |
+================================================================+
```

### 4.1 Form Builder (Editor formularov)

Vizualny drag-and-drop editor na tvorbu dynamickych formularov s viacjazycnou podporou.

**Klucove subory:**
- `Components/FormBuilder/FormBuilder.vue` - hlavny editor s vuedraggable
- `Components/FormBuilder/FieldEditor.vue` - editor poli s SK/EN tabmi
- `Models/Form.php` - model formulara (schema ako JSON)
- `Models/FormVersion.php` - automaticke verzie pri zmene
- `Models/FormCategory.php` - kategorie formularov

**Typy poli:** text, email, number, date, select, radio, checkbox, textarea, file

**Funkcie:**
- Drag-and-drop radenie poli
- Podmienene zobrazenie poli (equals, not_equals, contains, is_empty, not_empty)
- Automaticke verziovanie (max 20 verzii, rollback)
- Kategorizacia s farbami a ikonami
- Viacjazycne labely a placeholdery (SK/EN)
- Deduplikacia odpovedi

### 4.2 Workflow Engine (Automatizacia procesov)

Vizualny editor a runtime engine pre automatizaciu procesov po odoslani formulara.

**Klucove subory:**
- `Services/WorkflowEngine.php` - jadro workflow enginu
- `Jobs/ExecuteWorkflowStep.php` - async spracovanie krokov
- `Components/WorkflowEditor/WorkflowEditor.vue` - Vue Flow editor
- `Components/WorkflowEditor/NodeEditor.vue` - panel editora uzlov
- `Components/WorkflowEditor/nodes/` - vizualne uzly (7 typov)

**Typy uzlov:**

| Uzol | Ucel |
|------|------|
| **start** | Vstupny bod workflow |
| **end** | Koncovy bod workflow |
| **api_call** | HTTP volanie (GET/POST/PUT/DELETE) s template premennymi |
| **approval** | Cakanie na schvalenie + email notifikacia |
| **condition** | Vetvenie na zaklade podmienok (true/false vetvy) |
| **email** | Odoslanie emailu cez sablonu |
| **delay** | Casove oneskorenie |
| **transform** | Transformacia dat medzi krokmi |

**Template premenne:** `{{submission.field_name}}`, `{{user.email}}`, `{{last_api_response.body}}`

**Funkcie:**
- Vizualny node-based editor (Vue Flow)
- Async spracovanie cez Laravel queue (3 retry, 60s backoff)
- API call s retry, timeout (max 600s), async mode, SSL skip
- Verziovanie workflow (max 20 verzii)
- Execution logy na debugging

### 4.3 Submissions (Sprava odpovedi)

Zber, zobrazenie a sprava odpovedi na formulare.

**Klucove subory:**
- `Models/FormSubmission.php` - model odpovede
- `Models/SubmissionComment.php` - interne poznamky
- `Controllers/Admin/SubmissionController.php` - admin sprava
- `Controllers/Public/FormController.php` - verejne vyplnanie
- `Pages/Admin/Submissions/Index.vue` - zoznam s bulk operaciami
- `Pages/Admin/Submissions/Show.vue` - detail s komentarmi

**Stavy odpovede:** `submitted` → `pending` → `approved` / `rejected`

**Funkcie:**
- Filtrovanie podla stavu, formulara, full-text hladanie
- Bulk operacie (schvalit/zamietnit/vymazat)
- Interne poznamky (viditelne len pre adminov)
- CSV/Excel export s UTF-8 BOM
- Honeypot + timestamp anti-spam ochrana
- Deduplikacia (1 odpoved na uzivatela)

### 4.4 Approvals (Schvalovaci proces)

Token-based schvalovaci system integrovany s workflow engine.

**Klucove subory:**
- `Models/ApprovalRequest.php` - schvalovacia poziadavka
- `Controllers/ApprovalController.php` - schvalovanie cez token
- `Pages/Public/Approval.vue` - schvalovacia stranka

**Funkcie:**
- Kryptograficky bezpecne tokeny (64 znakov)
- 7-dnova expiracia tokenov
- Email notifikacie schvalovatelom
- Komentar pri schvaleni/zamietnutí
- No-Referrer hlavicka pre ochranu tokenov

### 4.5 Email Templates (Emailove sablony)

Bilingvalne emailove sablony pre vsetky typy notifikacii.

**Klucove subory:**
- `Models/EmailTemplate.php` - model sablony
- `Mail/FormSubmissionConfirmation.php` - potvrdenie odoslania
- `Mail/NewSubmissionNotification.php` - notifikacia adminom
- `Mail/SubmissionStatusChanged.php` - zmena stavu

**Systemove typy sablon:**
- Potvrdenie odoslania formulara
- Notifikacia o novom podani (admin)
- Schvalenie/zamietnutie odpovede
- Schvalovacia poziadavka
- Pripomienka schvalenia

**Format:** SK text → `<hr>` → EN text (v jednom tele emailu)

### 4.6 User Management (Sprava uzivatelov)

Keycloak SSO autentifikacia s hierarchickym rolam a per-formular permissions.

**Klucove subory:**
- `Models/User.php` - model uzivatela s rolami
- `Controllers/AuthController.php` - Keycloak SSO login/callback
- `Controllers/Admin/UserController.php` - admin sprava uzivatelov

**Hierarchia roli:**

```
super_admin ─┐
   admin ────┤  moze vsetko + sprava uzivatelov a nastaveni
  approver ──┤  moze schvalovat/zamietnit odpovede
   viewer ───┤  moze vidiet admin panel (read-only)
    user ────┘  zakladny uzivatel (len verejne formulare)
```

**Funkcie:**
- Automaticka synchronizacia roli z Keycloak realm roles
- Per-formular permissions (kto vidi ktory formular)
- Uzivatelske nastavenia (tema, jazyk, notifikacie)
- Session regeneracia pri prihlaseni

### 4.7 Audit Log (Audit trail)

Kompletny zaznam vsetkych akcii v systeme.

**Klucove subory:**
- `Models/AuditLog.php` - model zaznamu
- `Services/AuditService.php` - logging fasada
- `Pages/Admin/AuditLogs/Index.vue` - prehladanie logov

**Sledovane akcie:** vytvorenie, uprava, vymazanie formularov; odoslanie, schvalenie, zamietnutie odpovedi; zmeny nastaveni; prihlasenia

**Zaznamovane data:** uzivatel, akcia, stare/nove hodnoty, IP adresa, user-agent, metadata, casova peciatka

### 4.8 Backup & Restore (Zalohovanie)

Automaticke a manualne zalohovanie celej aplikacie.

**Klucove subory:**
- `Services/BackupService.php` - logika zalohovania
- `Controllers/Admin/SettingsController.php` - backup endpointy

**Obsah zalohy:** formulare, workflow, emailove sablony, kategorie, nastavenia, volitelne aj odpovede

**Format:** JSON s verziou a casovou peciatkou

**Uloziska:** lokalne, FTP server, Amazon S3

### 4.9 System Settings (Systemove nastavenia)

Centralny konfiguracny panel (len pre super_admin).

**Klucove subory:**
- `Models/Setting.php` - key-value store s auto-sifrovim
- `Pages/Admin/Settings/Index.vue` - konfiguracny panel

**Sekcie:** Keycloak SSO, SMTP (s testom), branding (logo, farby), S3/FTP zalohovanie, API tokeny, testovanie pripojeni

**Bezpecnost:** citlive hodnoty (hesla, client secret) su automaticky sifrovane v databaze

### 4.10 External API (Externe rozhranie)

REST API pre integraciu s externymi systemami (napr. CMDB).

**Klucove subory:**
- `Controllers/Api/` - API controllery
- `Models/SystemApiToken.php` - API tokeny

**Endpointy:**

| Metoda | URL | Auth | Ucel |
|--------|-----|------|------|
| GET | `/api/v1/forms/{slug}` | - | Verejny formular |
| POST | `/api/v1/forms/{slug}/submit` | - | Odoslanie odpovede |
| GET | `/api/v1/my/submissions` | session | Moje odpovede |
| GET | `/api/v1/submissions` | token | Citanie schvalenych odpovedi |
| POST | `/api/v1/submissions/import` | token | Import odpovedi z legacy |
| GET | `/api/v1/admin/forms` | session | Zoznam formularov (admin) |
| POST | `/api/v1/admin/export/forms` | session | Export formularov (JSON) |
| POST | `/api/v1/admin/import/forms` | session | Import formularov (JSON) |

### 4.11 Dalsie moduly

| Modul | Ucel | Klucove subory |
|-------|------|----------------|
| **Announcements** | Systemove oznamenia s casovym ohranicenim, typmi a ikonami | `Announcement` model, admin CRUD |
| **Categories** | Organizacia formularov do kategorii s farbami a poradim | `FormCategory` model, drag-to-reorder |
| **Form Versioning** | Automaticke verzie pri zmene schema/nastaveni (max 20) | `FormVersion` model, restore endpoint |
| **Workflow Versioning** | Verzie workflow s rollback moznostou | `WorkflowVersion` model |
| **i18n** | Viacjazycnost SK/EN (frontend vue-i18n + backend Laravel trans) | `i18n/locales/`, `lang/sk/`, `lang/en/` |

---

## 5. Datovy model

### ER diagram (hlavne entity)

```
+------------------+       +-------------------+       +--------------------+
|      User        |       |       Form        |       |    FormCategory    |
+------------------+       +-------------------+       +--------------------+
| id               |  1  N | id                |  N  1 | id                 |
| name             |<------| name (JSON)       |------>| name (JSON)        |
| email            |       | slug              |       | slug               |
| login            |       | description (JSON)|       | color              |
| role             |       | schema (JSON)     |       | icon               |
| settings (JSON)  |       | is_active         |       | order              |
+------------------+       | is_public         |       +--------------------+
        |                  | category_id (FK)  |
        |                  | created_by (FK)   |
        |                  | current_version   |
        | 1                +-------------------+
        |                     |  1          | 1
        |                     |             |
        |                  N  v          N  v
        |           +--------------+  +--------------+
        |           |FormSubmission|  | FormVersion  |
        |           +--------------+  +--------------+
        |           | id           |  | form_id (FK) |
        +---------->| form_id (FK) |  | version_number|
          N         | user_id (FK) |  | schema (JSON)|
                    | data (JSON)  |  | settings     |
                    | status       |  | change_note  |
                    | ip_address   |  | created_by   |
                    | admin_response|  +--------------+
                    +--------------+
                       | 1      | 1
                       |        |
                    N  v     N  v
         +-----------------+  +--------------------+
         |SubmissionComment|  |WorkflowExecution   |
         +-----------------+  +--------------------+
         | submission_id   |  | submission_id (FK) |
         | user_id (FK)    |  | workflow_id (FK)   |
         | content         |  | status             |
         | is_internal     |  | context (JSON)     |
         +-----------------+  | logs (JSON)        |
                              +--------------------+
                                       | 1
                                       |
                                    N  v
                              +--------------------+
                              | ApprovalRequest    |
                              +--------------------+
                              | execution_id (FK)  |
                              | token (64 char)    |
                              | email              |
                              | status             |
                              | expires_at         |
                              | comment            |
                              +--------------------+

+-------------------+     +-------------------+     +-------------------+
|     Workflow       |     |  EmailTemplate    |     |     AuditLog      |
+-------------------+     +-------------------+     +-------------------+
| id                |     | id                |     | id                |
| name              |     | name              |     | user_id (FK)      |
| nodes (JSON)      |     | subject           |     | action            |
| edges (JSON)      |     | body_html         |     | model_type        |
| is_active         |     | system_type       |     | model_id          |
| trigger_on        |     | is_active         |     | old_values (JSON) |
| current_version   |     | is_default        |     | new_values (JSON) |
+-------------------+     +-------------------+     | metadata (JSON)   |
        | 1                                         | ip_address        |
        |                                           +-------------------+
     N  v
+-------------------+     +-------------------+     +-------------------+
| WorkflowVersion   |     |    Setting         |     |  Announcement     |
+-------------------+     +-------------------+     +-------------------+
| workflow_id (FK)  |     | key               |     | id                |
| version_number    |     | value (encrypted?) |     | title             |
| nodes (JSON)      |     +-------------------+     | content           |
| edges (JSON)      |                               | type              |
| change_note       |     +-------------------+     | starts_at         |
+-------------------+     | SystemApiToken    |     | ends_at           |
                          +-------------------+     | is_dismissible    |
                          | name              |     +-------------------+
                          | token (hashed)    |
                          | abilities (JSON)  |
                          | expires_at        |
                          +-------------------+
```

---

## 6. API rozhranie

### Verejne endpointy (bez autentifikacie)

```
GET  /                                    Domovska stranka s katalogom formularov
GET  /forms/{slug}                        Zobrazenie formulara
POST /forms/{slug}/submit                 Odoslanie odpovede (rate limit: 10/min)
GET  /auth/login                          Presmerovanie na Keycloak
GET  /auth/callback                       Keycloak callback
GET  /approvals/{token}                   Schvalovacia stranka
POST /approvals/approve                   Schvalenie (token v body)
POST /approvals/reject                    Zamietnutie (token v body)
```

### Autentifikovane endpointy (session)

```
GET  /my/submissions                      Moje odpovede
GET  /my/submissions/{id}                 Detail mojej odpovede
GET  /profile/settings                    Uzivatelske nastavenia
PUT  /profile/settings                    Aktualizacia nastaveni
```

### Admin endpointy (podla role)

```
# viewer+ (zakladny pristup do admin panelu)
GET  /admin                               Dashboard
GET  /admin/forms                         Zoznam formularov
GET  /admin/submissions                   Zoznam odpovedi
GET  /admin/audit-logs                    Audit logy

# approver+ (schvalovanie)
POST /admin/submissions/{id}/approve      Schvalenie odpovede
POST /admin/submissions/{id}/reject       Zamietnutie odpovede
POST /admin/submissions/bulk-approve      Hromadne schvalenie
POST /admin/submissions/bulk-reject       Hromadne zamietnutie

# admin+ (plna sprava)
POST /admin/forms                         Vytvorenie formulara
PUT  /admin/forms/{id}                    Uprava formulara
DELETE /admin/forms/{id}                  Vymazanie formulara
CRUD /admin/workflows                     Sprava workflow
CRUD /admin/categories                    Sprava kategorii
CRUD /admin/email-templates               Sprava sablon
CRUD /admin/announcements                 Sprava oznamu

# super_admin (systemove nastavenia)
GET  /admin/users                         Sprava uzivatelov
POST /admin/settings/mail                 SMTP nastavenia
POST /admin/settings/backup/run           Spustenie zalohy
POST /admin/settings/api-tokens           Vytvorenie API tokenu
```

---

## 7. Bezpecnostne mechanizmy

| Mechanizmus | Implementacia | Ucel |
|-------------|---------------|------|
| **Keycloak SSO** | OpenID Connect, session regeneracia | Centralizovana autentifikacia |
| **Hierarchicke role** | 5 urovni (user→super_admin) | Autorizacia podla role |
| **Per-formular permissions** | M:N User-Form | Granularne pristupove prava |
| **CSRF ochrana** | Custom `ValidateCsrfToken` middleware | Ochrana pred CSRF (302 namiesto 419) |
| **XSS sanitizacia** | `strip_tags()` na filtroch, DOMPurify na frontende | Ochrana pred reflected XSS |
| **SQL injection** | Eloquent ORM + `escapeLikeWildcards()` | Ochrana pred SQL injection |
| **SSRF ochrana** | IP range validacia v Settings | Ochrana pred SSRF cez API calls |
| **Rate limiting** | `throttle` middleware na citlivych endpointoch | Ochrana pred brute-force |
| **WAF** | ModSecurity s OWASP CRS (volitelny) | Aplikacny firewall |
| **Code splitting** | Lazy loading Vue komponentov (Vite) | Admin kód nedostupny neprihlásenym |
| **Manifest blocking** | Nginx `location = /build/manifest.json` → 404 | Ochrana pred route enumeraciou |
| **Sifrovanie secrets** | Auto-encrypt v `Setting` modeli | Hesla a tokeny sifrovane v DB |
| **Audit trail** | `AuditService` + `AuditLog` model | Zaznam vsetkych akcii |
| **Anti-spam** | Honeypot + timestamp na formularoch | Ochrana pred botmi |
| **Token bezpecnost** | 64-char CSPRNG tokeny, HMAC-SHA256 hashovanie | Bezpecne approval a API tokeny |
| **File upload** | MIME whitelist, nahodne nazvy, blacklist nebezpecnych typov | Ochrana pred malicious uploads |
| **PHP hardening** | Disabled functions (exec, shell_exec, system...) | Ochrana pred RCE |
| **Session security** | HttpOnly, Secure, SameSite=Strict cookies | Ochrana session |
| **Nginx hardening** | Blokovanie .env, .git, storage/, skrytie verzii | Ochrana pred info disclosure |
