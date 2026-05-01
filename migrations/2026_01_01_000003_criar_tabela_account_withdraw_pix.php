<?php
declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_withdraw_pix', function (Blueprint $table) {
            $table->string('account_withdraw_id', 36)->primary();
            $table->string('type', 50);
            $table->string('key');

            $table->foreign('account_withdraw_id')->references('id')->on('account_withdraw');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_withdraw_pix');
    }
};
