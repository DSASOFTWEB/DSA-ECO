<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ncms', function (Blueprint $table) {
            $table->id();
            $table->string('ncm', 8);
            $table->string('ex', 10)->default('');
            $table->string('descricao', 500);
            $table->string('fonte', 20)->default('API');
            $table->timestamps();

            $table->unique(['ncm', 'ex']);
            $table->index('ncm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ncms');
    }
};
