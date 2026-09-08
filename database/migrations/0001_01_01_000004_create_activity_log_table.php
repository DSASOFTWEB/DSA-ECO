<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration padrão do pacote spatie/laravel-activitylog, usada para a
 * auditoria (log de criação/edição/exclusão) de todo o sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = config('activitylog.database_connection');

        Schema::connection($connection)->create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))->dropIfExists('activity_log');
    }
};
