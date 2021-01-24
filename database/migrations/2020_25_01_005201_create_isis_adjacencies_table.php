<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class CreateCacheTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('isis_adjacencies', function ($table) {
            $table->increments('adjacency_id');
            $table->integer('device_id');
            $table->integer('port_id');
            $table->text('isisISAdjState');
            $table->text('isisISAdjNeighSysType');
            $table->text('isisISAdjNeighSysID');
            $table->text('isisISAdjNeighPriority');
            $table->text('isisISAdjLastUpTime');
            $table->text('isisISAdjAreaAddress');
            $table->text('isisISAdjIPAddrType');
            $table->text('isisISAdjIPAddrAddress');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('isis_adjacencies');
    }
}
