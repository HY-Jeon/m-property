<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 5);
            $table->string('legal_dong', 40);
            $table->string('apartment_name', 100);
            $table->string('jibun', 20)->nullable();
            $table->decimal('exclusive_area', 8, 2);
            $table->smallInteger('floor')->nullable();
            $table->smallInteger('build_year')->nullable();
            $table->date('deal_date');
            $table->unsignedBigInteger('deal_amount');
            $table->string('deal_type', 10)->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->char('raw_hash', 40)->unique();
            $table->timestamps();

            $table->index(['legal_dong', 'deal_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
