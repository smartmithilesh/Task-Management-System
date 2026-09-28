<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->after('id');
            $table->foreignId('organization_id')->nullable()->after('email')->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->string('phone', 40)->nullable()->after('department_id');
            $table->string('photo_path')->nullable()->after('phone');
            $table->string('employee_number', 80)->nullable()->after('photo_path');
            $table->string('status', 24)->default('active')->after('employee_number');
            $table->string('timezone', 64)->default('UTC')->after('status');
            $table->string('language', 10)->default('en')->after('timezone');
        });

        DB::table('users')
            ->whereNull('public_id')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')->where('id', $user->id)->update([
                        'public_id' => (string) Str::uuid(),
                    ]);
                }
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable(false)->change();
            $table->unique('public_id', 'users_public_id_unique');
            $table->unique(['organization_id', 'employee_number'], 'users_org_employee_number_unique');
            $table->index(['organization_id', 'status'], 'users_org_status_index');
            $table->index(['organization_id', 'department_id'], 'users_org_department_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_org_employee_number_unique');
            $table->dropIndex('users_org_status_index');
            $table->dropIndex('users_org_department_index');
            $table->dropUnique('users_public_id_unique');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn([
                'public_id',
                'phone',
                'photo_path',
                'employee_number',
                'status',
                'timezone',
                'language',
            ]);
        });
    }
};
