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
        Schema::table('discount_rules', function (Blueprint $table) {
            $table->string('loyalty_condition', 50)->nullable()->after('type');
            $table->string('audience', 30)->default('all_customers')->after('loyalty_condition');
            $table->index(['type', 'loyalty_condition', 'audience', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discount_rules', function (Blueprint $table) {
            $table->dropIndex(['type', 'loyalty_condition', 'audience', 'is_active']);
            $table->dropColumn(['loyalty_condition', 'audience']);
        });
    }
};
