<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_fiscais', function (Blueprint $table) {
            $table->string('recibo', 80)->nullable()->after('protocolo');
            $table->string('xml_path', 500)->nullable()->after('xml_protocolado');
            $table->string('xml_envio_path', 500)->nullable()->after('xml_path');
            $table->string('link', 500)->nullable()->after('xml_envio_path');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_fiscais', function (Blueprint $table) {
            $table->dropColumn(['recibo', 'xml_path', 'xml_envio_path', 'link']);
        });
    }
};
