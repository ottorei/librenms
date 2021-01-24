<?php

use App\Models\Ipv4Address;

use LibreNMS\RRD\RrdDefinition;

// Get device model
$device_model = DeviceCache::getPrimary();


// Check if any ISIS circuits exist
$isis_circs = snmpwalk_cache_oid($device, 'ISIS-MIB::isisCirc', [], 'ISIS-MIB');
if (! empty($isis_circs)) {
    // Poll ISIS adjacencies
    #$isis_adjs = snmpwalk_cache_oid($device, 'ISIS-MIB::isisISAdj', [], 'ISIS-MIB');
    #$isis_adjs = snmpwalk_cache_oid($device, 'ISIS-MIB::isisISAdj', [], 'ISIS-MIB');
    $isis_adjs = snmpwalk_cache_oid($device, 'ISIS-MIB::isisISAdjState', [], 'ISIS-MIB');
    $isis_adjs = snmpwalk_cache_oid($device, 'ISIS-MIB::isisISAdjIPAddrAddress', [], 'ISIS-MIB');
}


#isisISAdjState
#isisISAdjNeighSysType
#isisISAdjHoldTimer
#isisISAdjNeighPriority
#isisISAdjLastUpTime
#isisISAdjAreaAddress
#isisISAdjIPAddrType

var_dump($isis_adjs);

foreach ($isis_adjs as $key => $value) {
    echo $value;

}

#var_dump($isis_adjs);

echo PHP_EOL;

unset(
    $isis_adjs
);
