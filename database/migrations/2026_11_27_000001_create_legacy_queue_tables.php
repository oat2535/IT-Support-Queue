<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the legacy intra_tl schema tables only when absent (e.g., SQLite test DB).
     * Existing production PostgreSQL tables are never dropped or altered.
     */
    public function up(): void
    {
        if (! Schema::hasTable('queue_app_queuestatus')) {
            Schema::create('queue_app_queuestatus', function (Blueprint $table) {
                $table->id();
                $table->string('name', 50);
                $table->string('code', 20)->unique();
                $table->string('color', 20)->default('secondary');
            });
        }

        if (! Schema::hasTable('queue_app_queueitem')) {
            Schema::create('queue_app_queueitem', function (Blueprint $table) {
                $table->id();
                $table->string('queue_number', 20)->unique();
                $table->string('user_name', 100);
                $table->string('user_department', 100);
                $table->text('issue_description');
                $table->text('comment')->nullable();
                $table->integer('is_urgent')->default(0);
                $table->dateTime('created_at');
                $table->integer('linked_job_no')->nullable()->unique();
                $table->foreignId('status_id')
                    ->nullable()
                    ->constrained('queue_app_queuestatus')
                    ->nullOnDelete();
                $table->dateTime('call_queue_date')->nullable();
                $table->integer('is_adhoc')->default(0);
            });
        }

        if (! Schema::hasTable('jobs_bms')) {
            Schema::create('jobs_bms', function (Blueprint $table) {
                $table->id();
                $table->integer('jobno')->unique();
                $table->string('catagory', 50)->nullable();
                $table->text('description')->nullable();
                $table->string('dept_tech', 50)->nullable();
                $table->string('name', 100)->nullable();
                $table->dateTime('jobdate')->nullable();
                $table->dateTime('assign_date')->nullable();
                $table->dateTime('arrive_date')->nullable();
                $table->dateTime('req_date')->nullable();
                $table->string('caller', 100)->nullable();
                $table->string('sap_code', 50)->nullable();
                $table->string('aname', 255)->nullable();
                $table->text('note')->nullable();
                $table->dateTime('act_dstart')->nullable();
                $table->dateTime('act_dfin')->nullable();
                $table->string('job_status', 10)->nullable();
                $table->dateTime('return_date')->nullable();
                $table->dateTime('enterdate')->nullable();
                $table->string('enterby', 100)->nullable();
                $table->dateTime('outsource_date')->nullable();
                $table->string('abb_desc', 100)->nullable();
                $table->text('descriptions')->nullable();
                $table->integer('difficulty')->nullable();
                $table->string('job_category_type', 50)->nullable();
            });
        }

        if (! Schema::hasTable('queue_app_shiftclosure')) {
            Schema::create('queue_app_shiftclosure', function (Blueprint $table) {
                $table->id();
                $table->dateTime('closed_at')->nullable();
                $table->string('closed_by', 100);
                $table->dateTime('opened_at')->nullable();
                $table->string('opened_by', 100)->nullable();
            });
        }

        if (! Schema::hasTable('members')) {
            Schema::create('members', function (Blueprint $table) {
                $table->id();
                $table->string('password', 128);
                $table->string('username', 150)->unique();
                $table->string('first_name', 150);
                $table->string('last_name', 150);
                $table->integer('is_staff')->default(0);
            });
        }
    }

    /**
     * Intentionally no-op so down() cannot drop existing intra_tl tables.
     */
    public function down(): void
    {
        // Preserved intentionally.
    }
};
