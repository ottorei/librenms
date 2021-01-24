<?php

use App\Models\Ipv4Address;
use App\Models\IsisAdjacency;
use LibreNMS\Util\IP;
use LibreNMS\Util\IPv4;
use LibreNMS\RRD\RrdDefinition;

// Get device model
$device_model = DeviceCache::getPrimary();

// Poll data from device
$adjacencies_poll = snmpwalk_cache_oid($device, 'ISIS-MIB::isisISAdj', [], 'ISIS-MIB');

$adjacencies = collect();

#var_dump($isis_adjs);

// Index the data by ifIndex
$tmp_adjacencies = [];
foreach ($isis_adjs as $key => $value) {
    $index = explode(".", $key)[0];
    $tmp_adjacencies[$index][] = $value;
}

// Loop through all adjacencies and output their status
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

    // Save data
    $adjacencencies->push(new IsisAdjacency([
        'device_id' => $device['device_id'],
        'port_id' = > $port_id,
        'isisISAdjState' => ,
        'isisISAdjNeighSysType',
        'isisISAdjNeighSysID',
        'isisISAdjNeighPriority',
        'isisISAdjLastUpTime', $adj[0]['']
        'isisISAdjAreaAddress',
        'isisISAdjIPAddrType',
        'isisISAdjIPAddrAddress',
    ]));
}

// Get port ID from existing data
// $port_id = (int) $device_model->ports()->where('ifIndex')->value('port_id');



#var_dump($tmp_adjs);

echo PHP_EOL;

unset(
    $isis_circs,
    $isis_adjs,
    $tmp_adjs,
    $adjs
);
