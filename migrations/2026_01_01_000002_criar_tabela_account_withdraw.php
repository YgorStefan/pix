<?php
declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_withdraw', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('account_id', 36);
            $table->string('method', 50);
            $table->decimal('amount', 15, 2);
            $table->boolean('scheduled')->default(false);
            $table->dateTime('scheduled_for')->nullable();
            $table->boolean('done')->default(false);
            $table->boolean('error')->default(false);
            $table->string('error_reason')->nullable();
            $table->dateTime('processing_since')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('account_id')->references('id')->on('account');
            $table->index(['scheduled', 'done', 'error', 'processing_since', 'scheduled_for'], 'idx_agendados_pendentes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_withdraw');
    }
};
