<?php

use App\Models\Ipv4Address;
use LibreNMS\Util\IP;
use LibreNMS\Util\IPv4;
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

$adjacencies_poll = snmpwalk_group($device, 'ISIS-MIB::isisCirc', [], 'ISIS-MIB');
var_dump($adjacencies_poll);
/*
// Loop through all adjacencies
foreach ($tmp_adjs as $key => $value) {
    #var_dump($value);
    $isisISAdjState = $value[0]['isisISAdjState'];
    $isisISAdjNeighSysType = $value[0]['isisISAdjNeighSysType'];
    $isisISAdjNeighSysID = $value[0]['isisISAdjNeighSysID'];
    $isisISAdjNeighPriority = $value[0]['isisISAdjNeighPriority'];
    $isisISAdjLastUpTime = $value[0]['isisISAdjLastUpTime'];
    $isisISAdjAreaAddress = $value[1]['isisISAdjAreaAddress'];
    $isisISAdjIPAddrType = $value[1]['isisISAdjIPAddrType'];
    $isisISAdjIPAddrAddress = IP::fromHexString($value[1]['isisISAdjIPAddrAddress']);

    echo "\nisisISAdjState: " . $isisISAdjState;
    echo "\nisisISAdjNeighSysType: " . $isisISAdjNeighSysType;
    echo "\nisisISAdjNeighSysID: " . $isisISAdjNeighSysID;
    echo "\nisisISAdjNeighPriority: " . $isisISAdjNeighPriority;
    echo "\nisisISAdjLastUpTime: " . $isisISAdjLastUpTime;
    echo "\nisisISAdjAreaAddress: " . $isisISAdjAreaAddress;
    echo "\nisisISAdjIPAddrType: " . $isisISAdjIPAddrType;
    echo "\nisisISAdjIPAddrAddress: " . $isisISAdjIPAddrAddress;
    echo "\n";
}

*/
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
