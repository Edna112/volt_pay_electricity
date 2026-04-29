<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('platform_fee', 10, 2)->default(0)->after('amount');
            $table->decimal('net_amount', 10, 2)->default(0)->after('platform_fee');

            // Settlement to ENEO bookkeeping
            $table->string('settlement_status')->default('pending')->after('status'); // pending|settled|skipped
            $table->timestamp('settled_at')->nullable()->after('settlement_status');
            $table->string('settlement_reference')->nullable()->after('settled_at');

            $table->index(['status', 'settlement_status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['status', 'settlement_status']);

            $table->dropColumn([
                'platform_fee',
                'net_amount',
                'settlement_status',
                'settled_at',
                'settlement_reference',
            ]);
        });
    }
};

