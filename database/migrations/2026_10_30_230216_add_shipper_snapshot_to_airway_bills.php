<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('airway_bills', function (Blueprint $table) {
            $table->json('shipper_details')->nullable();
        });
        foreach (DB::table('airway_bills')->orderBy('id')->cursor() as $bill) {
            $customer = DB::table('customers')->where('id', $bill->customer_id)->first();
            DB::table('airway_bills')->where('id', $bill->id)->update(['shipper_details' => json_encode(['name' => $customer->name, 'address' => $customer->address, 'phone' => $customer->phone, 'tax_number' => $customer->tax_number])]);
        }
    }

    public function down(): void
    {
        Schema::table('airway_bills', function (Blueprint $table) {
            $table->dropColumn('shipper_details');
        });
    }
};
