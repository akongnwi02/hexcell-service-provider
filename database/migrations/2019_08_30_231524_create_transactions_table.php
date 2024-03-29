<?php

use App\Services\Constants;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('meter_code');
            $table->string('meter_id');
            $table->string('internal_id');
            $table->string('external_id');
            $table->enum('status', [
                Constants::CREATED,
                Constants::PROCESSING,
                Constants::SUCCESS,
                Constants::FAILED
            ]);
            $table->string('energy')->nullable();
            $table->string('amount')->nullable();
            $table->string('token')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
