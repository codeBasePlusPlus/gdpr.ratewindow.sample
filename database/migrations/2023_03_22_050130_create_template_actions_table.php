<?php

use App\Models\ActionStatus;
use App\Models\ActionType;
use App\Models\Template;
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
        Schema::create('template_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Template::class)->constrained();
            $table->foreignIdFor(ActionType::class)->constrained();
            $table->foreignIdFor(ActionStatus::class)->constrained();
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
        Schema::dropIfExists('template_actions');
    }
};
