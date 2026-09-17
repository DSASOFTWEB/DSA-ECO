<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('produtos', 'forma_pagamento_fiscal')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->dropColumn('forma_pagamento_fiscal');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('produtos', 'forma_pagamento_fiscal')) {
            Schema::table('produtos', function (Blueprint $table) {
                $table->string('forma_pagamento_fiscal', 2)->nullable()->after('aliq_cofins_entrada');
            });
        }
    }
};
