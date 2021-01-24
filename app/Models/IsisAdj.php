<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class OspfPort extends PortRelatedModel
{
    use HasFactory;

    public $timestamps = false;
    protected $fillable = [
        'device_id',
        'port_id',
        'isisISAdjState',
        'isisISAdjNeighSysType',
        'isisISAdjNeighSysID',
        'isisISAdjNeighPriority',
        'isisISAdjLastUpTime',
        'isisISAdjAreaAddress',
        'isisISAdjIPAddrType',
        'isisISAdjIPAddrAddress',
    ];

    // ---- Define Relationships ----

    public function device()
    {
        return $this->belongsTo(\App\Models\Device::class, 'device_id');
    }
}
