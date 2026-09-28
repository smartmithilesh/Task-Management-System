<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('website')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('language', 10)->default('en');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->text('description')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->text('description')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'department_id']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->string('scope_key', 180)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'name']);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name', 120)->unique();
            $table->string('label', 160);
            $table->string('group_name', 80)->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role_id']);
            $table->index(['role_id', 'user_id']);
        });

        Schema::create('team_users', function (Blueprint $table): void {
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->primary(['team_id', 'user_id']);
            $table->index(['user_id', 'team_id']);
        });

        Schema::create('task_statuses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100);
            $table->string('scope_key', 180)->unique();
            $table->string('color', 20)->default('#64748b');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
            $table->index(['organization_id', 'position']);
        });

        Schema::create('task_priorities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100);
            $table->string('scope_key', 180)->unique();
            $table->string('color', 20)->default('#64748b');
            $table->unsignedSmallInteger('weight')->default(0);
            $table->timestamps();
            $table->index(['organization_id', 'weight']);
        });

        Schema::create('task_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->string('color', 20)->default('#64748b');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('organization_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key', 120);
            $table->json('value')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique();
            $table->json('value')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('notification_types', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('slug', 100)->unique();
            $table->string('name', 140);
            $table->string('category', 80)->index();
            $table->text('description')->nullable();
            $table->boolean('default_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_type_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 32);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'notification_type_id', 'channel'], 'notification_preferences_unique');
        });

        Schema::create('integrations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 80);
            $table->string('connection_name', 100)->default('primary');
            $table->string('status', 24)->default('disconnected');
            $table->json('configuration')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'provider', 'connection_name'], 'integrations_org_provider_name_unique');
            $table->index(['organization_id', 'status']);
        });

        Schema::create('integration_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->text('encrypted_value');
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();
            $table->unique(['integration_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_credentials');
        Schema::dropIfExists('integrations');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_types');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('organization_settings');
        Schema::dropIfExists('task_categories');
        Schema::dropIfExists('task_priorities');
        Schema::dropIfExists('task_statuses');
        Schema::dropIfExists('team_users');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('organizations');
    }
};
