<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 60)->unique();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('origin');
            $table->string('destination');
            $table->timestamps();
        });
        foreach (DB::table('airway_bills')->orderBy('id')->cursor() as $bill) {
            DB::table('shipments')->insert(['number' => $bill->number, 'vendor_id' => $bill->vendor_id, 'created_by' => $bill->created_by, 'origin' => $bill->origin, 'destination' => $bill->destination, 'created_at' => $bill->created_at, 'updated_at' => $bill->updated_at]);
        }
        Schema::table('tracking_updates', function (Blueprint $table) {
            $table->string('shipment_number', 60)->nullable();
        });
        foreach (DB::table('tracking_updates')->join('airway_bills', 'airway_bills.id', '=', 'tracking_updates.airway_bill_id')->select('tracking_updates.id', 'airway_bills.number')->orderBy('tracking_updates.id')->cursor() as $update) {
            DB::table('tracking_updates')->where('id', $update->id)->update(['shipment_number' => $update->number]);
        }
        Schema::table('tracking_updates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('airway_bill_id');
            $table->foreign('shipment_number')->references('number')->on('shipments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tracking_updates', function (Blueprint $table) {
            $table->dropForeign(['shipment_number']);
            $table->dropColumn('shipment_number');
            $table->foreignId('airway_bill_id')->nullable()->constrained()->cascadeOnDelete();
        });
        Schema::dropIfExists('shipments');
    }
};
