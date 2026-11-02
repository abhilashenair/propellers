<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('airway_bills')->where(fn ($query) => $query->whereNull('reference')->orWhere('reference', ''))->orderBy('id')->chunkById(100, function ($bills): void {
            foreach ($bills as $bill) {
                do {
                    $reference = 'REF-'.strtoupper((string) Str::ulid());
                } while (DB::table('airway_bills')->where('reference', $reference)->exists() || DB::table('shipments')->where('number', $reference)->exists() || $reference === $bill->number);
                DB::table('airway_bills')->where('id', $bill->id)->update(['reference' => $reference]);
            }
        });
        Schema::table('airway_bills', function (Blueprint $table) {
            $table->unique('reference');
        });
    }

    public function down(): void
    {
        Schema::table('airway_bills', function (Blueprint $table) {
            $table->dropUnique(['reference']);
        });
    }
};
