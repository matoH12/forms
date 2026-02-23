# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PHP form server - Laravel application with CMDB database, workflow engine and Keycloak SSO authentication. Runs as Docker containers.

## Technology Stack

- **Backend**: Laravel 11, PHP 8.3
- **Frontend**: Vue.js 3, Inertia.js, Tailwind CSS
- **Workflow Editor**: Vue Flow (node-based editor)
- **Form Builder**: Vuedraggable (drag-and-drop)
- **Database**: MySQL 8
- **Cache/Queue**: Redis
- **Auth**: Keycloak SSO

## Commands

### Docker

```bash
# Start
docker-compose up -d

# Rebuild
docker-compose up -d --build

# Logs
docker-compose logs -f app

# Shell into container
docker-compose exec app bash
```

### Laravel (in container)

```bash
# Migrations
php artisan migrate

# Fresh migrations
php artisan migrate:fresh --seed

# Cache clear
php artisan cache:clear && php artisan config:clear && php artisan view:clear

# Queue worker
php artisan queue:work
```

### Frontend (in container or locally in src/)

```bash
npm install
npm run dev      # development
npm run build    # production
```

## Architecture

### Directory Structure

```
/formulare
├── docker/                    # Docker configuration
├── docker-compose.yml
└── src/                       # Laravel application
    ├── app/
    │   ├── Http/Controllers/
    │   │   ├── Api/           # REST API (FormController, WorkflowController, ...)
    │   │   ├── Admin/         # Admin panel
    │   │   └── Public/        # Public forms
    │   ├── Models/            # Eloquent models
    │   ├── Services/
    │   │   └── WorkflowEngine.php   # Workflow execution engine
    │   └── Jobs/
    │       └── ExecuteWorkflowStep.php
    ├── resources/js/
    │   ├── Components/
    │   │   ├── FormBuilder/   # Drag-and-drop form builder
    │   │   └── WorkflowEditor/# Vue Flow workflow editor
    │   ├── Layouts/
    │   └── Pages/
    │       ├── Admin/         # Admin pages
    │       └── Public/        # Public pages
    └── routes/
        ├── api.php            # REST API routes
        └── web.php            # Web routes
```

### Key Models

- `Form` - form definition (schema as JSON, current_version)
- `FormSubmission` - form submissions/responses
- `FormVersion` - form versions (schema, settings, change_note)
- `SubmissionComment` - internal comments on submissions
- `Workflow` - workflow definition (nodes, edges for Vue Flow)
- `WorkflowStep` - workflow steps
- `WorkflowExecution` - running workflow instances
- `ApprovalRequest` - approval requests

### Workflow Engine

WorkflowEngine (`app/Services/WorkflowEngine.php`) processes:
- `api_call` - HTTP calls with template variables
- `approval` - waiting for approval + email notification
- `condition` - branching based on conditions
- `email` - sending emails (with EmailTemplate support)
- `transform` - data transformation

**API Call configuration:**
- `method` - GET, POST, PUT, PATCH, DELETE
- `url` - URL with variables `{{submission.field}}`
- `headers` - HTTP headers (JSON object)
- `body` - Request body (JSON string)
- `timeout` - Timeout in seconds (max 600)
- `async` - Fire-and-forget mode (doesn't wait for response)
- `insecure` - Skip SSL verification (for HTTP and self-signed certs)
- `retry_count` - Number of retries on error
- `retry_delay` - Delay between attempts

Variables in templates: `{{submission.field_name}}`, `{{user.email}}`, `{{last_api_response.body}}`

### API Endpoints

```
# Public
POST /api/v1/forms/{slug}/submit
GET  /api/v1/forms/{slug}

# Authenticated
GET  /api/v1/my/submissions
GET  /api/v1/my/forms

# Admin
GET/POST     /api/v1/admin/forms
PUT/DELETE   /api/v1/admin/forms/{id}
GET/POST     /api/v1/admin/workflows
GET          /api/v1/admin/submissions

# Approvals
POST /api/v1/approvals/{token}/approve
POST /api/v1/approvals/{token}/reject
```

## Keycloak Configuration

```
URL: https://sso.example.com
Realm: YourRealm
Client ID: your-client-id
```

Admin role is checked via `realm_access.roles` containing `admin` or `formulare-admin`.

## Environment Variables

Main variables in `.env`:
- `KEYCLOAK_*` - Keycloak configuration
- `DB_*` - MySQL connection
- `REDIS_*` - Redis connection
- `MAIL_*` - SMTP for email notifications

## Internationalization (i18n)

The application supports Slovak (SK) and English (EN).

### Frontend Translations

- **Library**: vue-i18n
- **Configuration**: `resources/js/i18n/index.js`
- **Translations**: `resources/js/i18n/locales/sk.json`, `en.json`

```javascript
// Usage in component
import { useI18n } from 'vue-i18n';
const { t } = useI18n();
// In template: {{ t('forms.title') }}
```

### Multilingual Content (Database)

Forms, categories and fields support multilingual content stored as JSON object:

```json
{
  "name": {"sk": "Name SK", "en": "Name EN"},
  "description": {"sk": "Description SK", "en": "Description EN"}
}
```

### useLocalized Composable

For displaying localized content use `useLocalized`:

```javascript
import { useLocalized } from '@/composables/useLocalized';

const { getLocalized, getFieldLabel, getFieldPlaceholder,
        getOptionLabel, getFormName, getFormDescription } = useLocalized();

// Automatically returns value based on current language with fallback to SK
```

### Backend Locale

- **Middleware**: `SetLocale` - sets Laravel locale based on user preferences
- **Translations**: `lang/sk/`, `lang/en/` (validation, messages, auth, pagination)

### Email Templates

Email templates are bilingual - Slovak version above, English below with separator:
- **Seeder**: `database/seeders/EmailTemplateSeeder.php`
- **Format**: SK text → `<hr>` → EN text

## Form Schema Format

```json
{
  "fields": [
    {
      "id": "field_1",
      "type": "text|email|number|date|select|radio|checkbox|textarea|file",
      "name": "field_name",
      "label": {"sk": "Label SK", "en": "Label EN"},
      "placeholder": {"sk": "Enter...", "en": "Enter..."},
      "required": true,
      "options": [
        {"label": {"sk": "Option 1 SK", "en": "Option 1 EN"}, "value": "opt1"}
      ],
      "conditions": [
        {"field": "other_field_name", "operator": "equals", "value": "expected_value"}
      ]
    }
  ]
}
```

**Note**: For backwards compatibility `label` can also be a string - useLocalized handles this.

### Conditional Logic

Fields can have display conditions (`conditions`). A field is shown only if all conditions are met.

**Operators:**
- `equals` - value equals
- `not_equals` - value does not equal
- `contains` - value contains text
- `is_empty` - value is empty
- `not_empty` - value is not empty

**Implementation:**
- `FieldEditor.vue` - UI for setting conditions
- `Form.vue` (Public) - `isFieldVisible()` function checks conditions
- `FormBuilder.vue` - visual indicator for fields with conditions

## Key Vue Components

### Form Builder
- `Components/FormBuilder/FormBuilder.vue` - main builder with drag-and-drop
- `Components/FormBuilder/FieldEditor.vue` - field editor with language tabs (SK/EN)

### Workflow Editor
- `Components/WorkflowEditor/WorkflowEditor.vue` - Vue Flow canvas
- `Components/WorkflowEditor/NodeEditor.vue` - panel for editing nodes (including API insecure mode)
- `Components/WorkflowEditor/nodes/` - node types (ApiCallNode, ApprovalNode, ConditionNode...)

### Admin Pages
- `Pages/Admin/Forms/Create.vue`, `Edit.vue` - forms with multilingual name/description
- `Pages/Admin/Categories/` - categories with multilingual support
- `Pages/Admin/Workflows/` - workflow editor

### Public Pages
- `Pages/Public/Form.vue` - public form display (uses useLocalized)
- `Pages/Public/MySubmissions.vue` - my submissions

### Composables
- `composables/useLocalized.js` - helper for localized content

## Workflow Node Format (Vue Flow)

```json
{
  "nodes": [
    {"id": "start", "type": "start", "position": {"x": 0, "y": 0}, "data": {"label": "Start"}},
    {"id": "node_1", "type": "api_call", "position": {...}, "data": {"method": "POST", "url": "..."}}
  ],
  "edges": [
    {"id": "e1", "source": "start", "target": "node_1"}
  ]
}
```

## Bulk Operations

Admin can bulk approve, reject or delete submissions.

**Backend:**
- `SubmissionController::bulkApprove()` - bulk approval
- `SubmissionController::bulkReject()` - bulk rejection
- `SubmissionController::bulkDelete()` - bulk deletion

**Routes:**
```
POST /admin/submissions/bulk-approve
POST /admin/submissions/bulk-reject
POST /admin/submissions/bulk-delete
```

**Frontend (`Pages/Admin/Submissions/Index.vue`):**
- Checkboxes for selecting submissions
- Select all checkbox in header
- Floating action bar with buttons (shown on selection)
- Modal for entering response for all applicants

## Submission Comments

Internal notes on submissions visible only to admins.

**Model:** `SubmissionComment`
- `submission_id` - relation to submission
- `user_id` - comment author
- `content` - comment text
- `is_internal` - always true (internal notes)

**Routes:**
```
POST   /admin/submissions/{submission}/comments
PUT    /admin/submissions/{submission}/comments/{comment}
DELETE /admin/submissions/{submission}/comments/{comment}
```

**Frontend (`Pages/Admin/Submissions/Show.vue`):**
- "Internal notes" section below workflow executions
- Adding new comments
- Editing/deleting own comments
- Displaying author and time

## Form Versioning

Automatic version creation when form schema or settings change.

**Model:** `FormVersion`
- `form_id` - relation to form
- `version_number` - version number
- `schema` - JSON schema in this version
- `settings` - settings in this version
- `change_note` - change note
- `created_by` - change author

**Form model methods:**
- `createVersion($note, $userId)` - creates new version
- `restoreToVersion($version, $note, $userId)` - restores to version

**Routes:**
```
GET  /admin/forms/{form}/versions              # list versions
GET  /admin/forms/{form}/versions/{version}    # version detail
POST /admin/forms/{form}/versions/{version}/restore  # restore
```

**Frontend (`Pages/Admin/Forms/Edit.vue`):**
- "History" button with current version number
- Slide-over panel with version history
- "Restore" button for each version (except current)
- Display of fields and note for each version

**Automatic versioning:**
- On `store()` - creates version 1 with note "Form created"
- On `update()` - if schema or settings change, creates new version
