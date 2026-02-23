<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return !empty($result);
    }

    public function up(): void
    {
        // Forms indexes
        Schema::table('forms', function (Blueprint $table) {
            if (!$this->indexExists('forms', 'forms_is_active_index')) {
                $table->index('is_active');
            }
            if (!$this->indexExists('forms', 'forms_is_public_index')) {
                $table->index('is_public');
            }
            if (!$this->indexExists('forms', 'forms_is_active_is_public_index')) {
                $table->index(['is_active', 'is_public']);
            }
            if (!$this->indexExists('forms', 'forms_slug_index')) {
                $table->index('slug');
            }
        });

        // Form submissions indexes for status filtering
        Schema::table('form_submissions', function (Blueprint $table) {
            if (!$this->indexExists('form_submissions', 'form_submissions_status_index')) {
                $table->index('status');
            }
            if (!$this->indexExists('form_submissions', 'form_submissions_form_id_status_index')) {
                $table->index(['form_id', 'status']);
            }
            if (!$this->indexExists('form_submissions', 'form_submissions_form_id_user_id_index')) {
                $table->index(['form_id', 'user_id']);
            }
            if (!$this->indexExists('form_submissions', 'form_submissions_status_created_at_index')) {
                $table->index(['status', 'created_at']);
            }
        });

        // Workflows indexes
        Schema::table('workflows', function (Blueprint $table) {
            if (!$this->indexExists('workflows', 'workflows_is_active_index')) {
                $table->index('is_active');
            }
            if (!$this->indexExists('workflows', 'workflows_trigger_on_index')) {
                $table->index('trigger_on');
            }
        });

        // Workflow executions indexes
        Schema::table('workflow_executions', function (Blueprint $table) {
            if (!$this->indexExists('workflow_executions', 'workflow_executions_status_index')) {
                $table->index('status');
            }
            if (!$this->indexExists('workflow_executions', 'workflow_executions_workflow_id_status_index')) {
                $table->index(['workflow_id', 'status']);
            }
        });

        // Users indexes
        Schema::table('users', function (Blueprint $table) {
            if (!$this->indexExists('users', 'users_is_admin_index')) {
                $table->index('is_admin');
            }
            if (!$this->indexExists('users', 'users_keycloak_id_index')) {
                $table->index('keycloak_id');
            }
        });

        // Audit logs additional indexes
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!$this->indexExists('audit_logs', 'audit_logs_user_id_created_at_index')) {
                $table->index(['user_id', 'created_at']);
            }
            if (!$this->indexExists('audit_logs', 'audit_logs_action_created_at_index')) {
                $table->index(['action', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['is_public']);
            $table->dropIndex(['is_active', 'is_public']);
            $table->dropIndex(['slug']);
        });

        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['form_id', 'status']);
            $table->dropIndex(['form_id', 'user_id']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['trigger_on']);
        });

        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['workflow_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_admin']);
            $table->dropIndex(['keycloak_id']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['action', 'created_at']);
        });
    }
};
