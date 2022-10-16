<?php

use App\Models\Review;
use App\Models\Statement;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('auditor_statement', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Statement::class)->constrained();
            $table->foreignIdFor(Review::class)->constrained();
            $table->foreignIdFor(User::class)->constrained();
            $table->string('guide');
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
        Schema::dropIfExists('auditor_statement');
    }
};