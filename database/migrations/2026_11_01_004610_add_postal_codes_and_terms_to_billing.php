<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('postal_code', 20)->nullable();
        });
        Schema::table('airway_bills', function (Blueprint $table) {
            $table->string('consignee_postal_code', 20)->nullable();
            $table->text('terms_and_conditions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('airway_bills', function (Blueprint $table) {
            $table->dropColumn(['consignee_postal_code', 'terms_and_conditions']);
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('postal_code');
        });
    }
};
