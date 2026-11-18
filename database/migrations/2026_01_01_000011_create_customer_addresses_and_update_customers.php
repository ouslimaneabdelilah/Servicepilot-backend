<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('customer_number', 40)->nullable()->after('company_id');
            $table->string('first_name', 80)->nullable()->after('customer_number');
            $table->string('last_name', 80)->nullable()->after('first_name');
            $table->string('company_name', 160)->nullable()->after('last_name');
            $table->string('customer_type', 30)->default('residential')->after('phone');
            $table->string('status', 30)->default('active')->after('customer_type');
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_type']);
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60)->default('Primary');
            $table->string('street_address', 255);
            $table->string('apartment', 60)->nullable();
            $table->string('city', 100);
            $table->string('state', 2);
            $table->string('postal_code', 20);
            $table->string('country', 3)->default('USA');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'status']);
            $table->dropIndex(['company_id', 'customer_type']);
            $table->dropSoftDeletes();
            $table->dropColumn(['customer_number', 'first_name', 'last_name', 'company_name', 'customer_type', 'status']);
        });
    }
};

