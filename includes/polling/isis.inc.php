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

$isis_data = [];

// Index the data by ifIndex
$tmp_adjacencies = [];
foreach ($adjacencies_poll as $key => $value) {
    $index = explode(".", $key)[0];
    $tmp_adjacencies[$index][] = $value;
}

// Loop through all adjacencies and output their status
foreach ($tmp_adjacencies as $key => $value) {
    #var_dump($value);
    $isis_data["isisISAdjState"] = $value[0]['isisISAdjState'];
    $isis_data["isisISAdjNeighSysType"] = $value[0]['isisISAdjNeighSysType'];
    $isis_data["isisISAdjNeighSysID"] = $value[0]['isisISAdjNeighSysID'];
    $isis_data["isisISAdjNeighPriority"] = $value[0]['isisISAdjNeighPriority'];
    $isis_data["isisISAdjLastUpTime"] = $value[0]['isisISAdjLastUpTime'];
    $isis_data["isisISAdjAreaAddress"] = $value[1]['isisISAdjAreaAddress'];
    $isis_data["isisISAdjIPAddrType"] = $value[1]['isisISAdjIPAddrType'];
    $isis_data["isisISAdjIPAddrAddress"] = IP::fromHexString($value[1]['isisISAdjIPAddrAddress']);

    echo "\nisisISAdjState: " . $isis_data["isisISAdjState"];
    echo "\nisisISAdjNeighSysType: " . $isis_data["isisISAdjNeighSysType"];
    echo "\nisisISAdjNeighSysID: " . $isis_data["isisISAdjNeighSysID"];
    echo "\nisisISAdjNeighPriority: " . $isis_data["isisISAdjNeighPriority"];
    echo "\nisisISAdjLastUpTime: " . $isis_data["isisISAdjLastUpTime"];
    echo "\nisisISAdjAreaAddress: " . $isis_data["isisISAdjAreaAddress"];
    echo "\nisisISAdjIPAddrType: " . $isis_data["isisISAdjIPAddrType"];
    echo "\nisisISAdjIPAddrAddress: " . $isis_data["isisISAdjIPAddrAddress"];
    echo "\n";

    // Save data
    $adjacencencies->push(new IsisAdjacency([
        'device_id' => $device['device_id'],
        'port_id' => $port_id,
        'isisISAdjState' => $isis_data["isisISAdjState"],
        'isisISAdjNeighSysType' => $isis_data["isisISAdjNeighSysType"],
        'isisISAdjNeighSysID' => $isis_data["isisISAdjNeighSysID"],
        'isisISAdjNeighPriority' => $isis_data["isisISAdjNeighPriority"],
        'isisISAdjLastUpTime' => $isis_data["isisISAdjLastUpTime"],
        'isisISAdjAreaAddress' => $isis_data["isisISAdjAreaAddress"],
        'isisISAdjIPAddrType' => $isis_data["isisISAdjIPAddrType"],
        'isisISAdjIPAddrAddress' => $isis_data["isisISAdjIPAddrAddress"],
    ]));
}

// Get port ID from existing data
// $port_id = (int) $device_model->ports()->where('ifIndex')->value('port_id');



#var_dump($tmp_adjs);

echo PHP_EOL;

unset(
    $adjacencies_poll
    $adjacencies
    $isis_data
    $tmp_adjacencies
);
