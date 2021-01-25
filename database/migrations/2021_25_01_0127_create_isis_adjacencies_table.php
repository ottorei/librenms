<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateIsisAdjacenciesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('isis_adjacencies', function (Blueprint $table) {
            $table->index(['device_id', 'isisISAdjIPAddrAddress']);
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
