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
        Schema::create('monthly_aggregates', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 5);
            $table->string('legal_dong', 40);
            $table->char('year_month', 6);
            $table->unsignedInteger('transaction_count');
            $table->unsignedBigInteger('avg_deal_amount');
            $table->unsignedBigInteger('min_deal_amount');
            $table->unsignedBigInteger('max_deal_amount');
            $table->timestamps();

            $table->unique(['legal_dong', 'year_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_aggregates');
    }
};
