<?php

use App\Models\Ipv4Address;

use LibreNMS\RRD\RrdDefinition;

// Get device model
$device_model = DeviceCache::getPrimary();


// Check if any ISIS circuits exist
$isis_circs = snmpwalk_cache_multi_oid($device, 'ISIS-MIB::isisCirc', [], 'ISIS-MIB');
// Poll ISIS adjacencies
if (! empty($isis_circs)) {
    $isis_circs = snmpwalk_cache_multi_oid($device, 'ISIS-MIB::isisISAdj', [], 'ISIS-MIB');
}

var_dump($isis_circs);

foreach ($isis_circs as $key => $value) {
    echo $value;

}

#var_dump($isis_adjs);

echo PHP_EOL;

unset(
    $isis_adjs
);
