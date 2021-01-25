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
//var_dump($adjacencies_poll);
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

    // Get port ID from existing data. If port does not exist, use Null value
    // Return 0 if the port does not exist
    $port_id = (int) $device_model->ports()->where('ifIndex', $key)->value('port_id');
 
    /*
    echo "\nPort ID: ";
    var_dump($port_id);
    echo "\nifIndex: ";
    var_dump($key);
    */

    // Save data to the DB
    $adjacency = IsisAdjacency::updateOrCreate([
        'device_id' => $device['device_id'],
        'isisISAdjIPAddrAddress' => $isis_data["isisISAdjIPAddrAddress"],
    ],[
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
    ]);

    $adjacencies->push($adjacency);
}

    // DB cleanup - remove all entries from the DB that were not present during the poll 
    // => the adjacency no longer exists
    IsisAdjacency::query()
    ->where(['device_id' => $device['device_id']])
    ->whereNotIn('isisISAdjIPAddrAddress', $adjacencies->pluck('isisISAdjIPAddrAddress'))->delete();

    // TODO: Create RRD-files for each adjacency, save status and possibly uptime


echo PHP_EOL;

unset(
    $adjacencies_poll,
    $adjacencies,
    $isis_data,
    $tmp_adjacencies,
);
