<?php

use App\Models\Ipv4Address;

use LibreNMS\RRD\RrdDefinition;

// Get device model
$device_model = DeviceCache::getPrimary();


// Check if any ISIS circuits exist
$isis_circs = snmpwalk_cache_oid($device, 'ISIS-MIB::isisCirc', [], 'ISIS-MIB');
// Poll ISIS adjacencies
if (! empty($isis_circs)) {
    $isis_adjs = snmpwalk_cache_oid($device, 'ISIS-MIB::isisISAdj', [], 'ISIS-MIB');
}

foreach ($isis_adjs as $key => $value) {
    echo('isisISAdjState: ' . $value["isisISAdjState"] . '\n');
    echo('isisISAdjState: ' . $value["isisISAdjState"] . '\n');
    echo('isisISAdjNeighSNPAAddress: ' . $value["isisISAdjNeighSNPAAddress"] . '\n');
    echo('isisISAdjNeighSysType: ' . $value["isisISAdjNeighSysType"] . '\n');
    echo('isisISAdjNeighSysID: ' . $value["isisISAdjNeighSysID"] . '\n');
    echo('isisISAdjUsage: ' . $value["isisISAdjUsage"] . '\n');
    echo('isisISAdjLastUpTime: ' . $value["isisISAdjLastUpTime"] . '\n');
    echo('isisISAdjUsage: ' . $value["isisISAdjUsage"] . '\n');

}

#var_dump($isis_adjs);

echo PHP_EOL;

unset(
    $isis_adjs
);
