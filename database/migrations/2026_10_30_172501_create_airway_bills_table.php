<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airway_bills', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('reference')->nullable();
            $table->string('carrier_number')->nullable();
            $table->string('dimensions')->nullable();
            $table->decimal('volumetric_weight', 10, 3)->default(0);
            $table->decimal('declared_value', 12, 2)->default(0);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('bill_date');
            $table->string('origin');
            $table->string('destination');
            $table->string('consignee_name');
            $table->string('consignee_phone');
            $table->text('consignee_address');
            $table->text('contents');
            $table->unsignedInteger('pieces');
            $table->decimal('weight', 10, 3);
            $table->decimal('chargeable_weight', 10, 3);
            $table->string('service');
            $table->string('currency', 3)->default('INR');
            $table->decimal('freight', 12, 2);
            $table->decimal('other_charges', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airway_bills');
    }
};
