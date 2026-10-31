<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_options', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['type', 'name']);
            $table->index(['type', 'enabled']);
        });
        foreach (['origin', 'destination', 'service'] as $type) {
            foreach (DB::table('airway_bills')->whereNotNull($type)->distinct()->pluck($type) as $name) {
                DB::table('booking_options')->insertOrIgnore(['type' => $type, 'name' => $name, 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_options');
    }
};
