<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 다른 지역의 동명 법정동과 충돌하지 않도록 키에 region_code를 포함한다.
     */
    public function up(): void
    {
        Schema::table('monthly_aggregates', function (Blueprint $table) {
            $table->dropUnique(['legal_dong', 'year_month']);
            $table->unique(['region_code', 'legal_dong', 'year_month']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['legal_dong', 'deal_date']);
            $table->index(['region_code', 'legal_dong', 'deal_date']);
        });
    }

    /**
     * 주의: 동일한 (legal_dong, year_month)가 여러 지역에 존재하면 기존 유니크 키 복원이 실패한다.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['region_code', 'legal_dong', 'deal_date']);
            $table->index(['legal_dong', 'deal_date']);
        });

        Schema::table('monthly_aggregates', function (Blueprint $table) {
            $table->dropUnique(['region_code', 'legal_dong', 'year_month']);
            $table->unique(['legal_dong', 'year_month']);
        });
    }
};
