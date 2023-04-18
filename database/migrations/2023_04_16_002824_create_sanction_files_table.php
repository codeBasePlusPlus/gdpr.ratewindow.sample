<?php

use App\Models\Sanction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sanction_files', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('path');
            $table->foreignIdFor(Sanction::class)->constrained();
            $table->softDeletes();
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
        Schema::dropIfExists('sanction_files');
    }
};
