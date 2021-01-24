<?php

use App\Models\Ipv4Address;

use LibreNMS\RRD\RrdDefinition;

// Get device model
$device_model = DeviceCache::getPrimary();


// Check if any ISIS circuits exist
$isis_circs = snmpwalk_cache_oid($device, 'ISIS-MIB::isisCirc', [], 'ISIS-MIB');
if (! empty($isis_circs)) {
    // Poll data from adjacencies
    $isis_adjs = snmpwalk_cache_oid($device, 'ISIS-MIB::isisISAdj', [], 'ISIS-MIB');
    // Poll 
}

$adjs = collect();

#var_dump($isis_adjs);

// Index the data by ifIndex
$tmp_adjs = [];
foreach ($isis_adjs as $key => $value) {
    $index = explode(".", $key)[0];
    $tmp_adjs[$index][] = $value;
}

foreach ($tmp_adjs as $key => $value) {
    #var_dump($value);
    echo "\nisisISAdjState: " . $value[0]['isisISAdjState'];
    echo "\nisisISAdjNeighSysType: " . $value[0]['isisISAdjNeighSysType'];
    echo "\nisisISAdjNeighSysID: " . $value[0]['isisISAdjNeighSysID'];
    echo "\nisisISAdjNeighPriority: " . $value[0]['isisISAdjNeighPriority'];
    echo "\nisisISAdjLastUpTime: " . $value[0]['isisISAdjLastUpTime'];
    echo "\nisisISAdjAreaAddress: " . $value[1]['isisISAdjAreaAddress'];
    echo "\nisisISAdjIPAddrType: " . $value[1]['isisISAdjIPAddrType'];
    echo "\nisisISAdjIPAddrAddress: " . $value[1]['isisISAdjIPAddrAddress'];
    echo "\n";
}
// Get port ID from existing data
// $port_id = (int) $device_model->ports()->where('ifIndex')->value('port_id');

#$adjs->push(new IsisAdj[
#'device_id' => $device['device_id'],
#'port_id' = > $port_id,
#'isisISAdjState' => ,
#'isisISAdjNeighSysType',
#'isisISAdjNeighSysID',
#'isisISAdjNeighPriority',
#'isisISAdjLastUpTime', $adj[0]['']
#'isisISAdjAreaAddress',
#'isisISAdjIPAddrType',
#'isisISAdjIPAddrAddress',


#]
#)

#var_dump($tmp_adjs);

echo PHP_EOL;

unset(
    $isis_circs,
    $isis_adjs,
    $tmp_adjs,
    $adjs
);
