<?php

use App\Models\IsisAdjacency;
use LibreNMS\Util\IP;

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
    $index = explode('.', $key)[0];
    $tmp_adjacencies[$index][] = $value;
}

// Loop through all adjacencies and output their status
foreach ($tmp_adjacencies as $key => $value) {
    //var_dump($value);
    $isis_data['isisISAdjState'] = $value[0]['isisISAdjState'];
    $isis_data['isisISAdjNeighSysType'] = $value[0]['isisISAdjNeighSysType'];
    $isis_data['isisISAdjNeighSysID'] = $value[0]['isisISAdjNeighSysID'];
    $isis_data['isisISAdjNeighPriority'] = $value[0]['isisISAdjNeighPriority'];
    $isis_data['isisISAdjLastUpTime'] = $value[0]['isisISAdjLastUpTime'];
    $isis_data['isisISAdjAreaAddress'] = $value[1]['isisISAdjAreaAddress'];
    $isis_data['isisISAdjIPAddrType'] = $value[1]['isisISAdjIPAddrType'];
    $isis_data['isisISAdjIPAddrAddress'] = IP::fromHexString($value[1]['isisISAdjIPAddrAddress']);

    // Format systemID
    $isis_data['isisISAdjNeighSysID'] = str_replace(' ', '.', $isis_data['isisISAdjNeighSysID']);
    // Remove spaces from systemid and convert it into a more common display format (dddd.dddd.dddd)
    //$isis_data['isisISAdjNeighSysID'] = str_replace(' ', '', $isis_data['isisISAdjNeighSysID']);
    //$isis_data['isisISAdjNeighSysID'] = wordwrap($isis_data['isisISAdjNeighSysID'], 4, '.', true);

    // Convert uptime into seconds with an accuracy of 1 min
    $tmp_time = explode(':', $isis_data['isisISAdjLastUpTime']);
    $isis_data['isisISAdjLastUpTime'] = $tmp_time[0] * 86400;
    $isis_data['isisISAdjLastUpTime'] += $tmp_time[1] * 3600;
    $isis_data['isisISAdjLastUpTime'] += $tmp_time[2] * 60;

    $isis_data['isisISAdjAreaAddress'] = str_replace(' ', '.', $isis_data['isisISAdjAreaAddress']);
    // Convert AreaID into a more common display format (aa.aaaa)
    // Remove spaces from systemid and convert it into a more common display format (dddd.dddd.dddd)
    //$isis_data['isisISAdjAreaAddress'][2] = '.';
    //$isis_data['isisISAdjAreaAddress']= str_replace(' ', '', $isis_data['isisISAdjAreaAddress']);

    echo "\nFound adjacent " . $isis_data['isisISAdjIPAddrAddress'];

    // Get port_id from ifIndex
    $port_id = (int) $device_model->ports()->where('ifIndex', $key)->value('port_id');

    // Save data into the DB
    $adjacency = IsisAdjacency::updateOrCreate([
        'device_id' => $device['device_id'],
        'isisISAdjIPAddrAddress' => $isis_data['isisISAdjIPAddrAddress'],
    ], [
        'device_id' => $device['device_id'],
        'port_id' => $port_id,
        'isisISAdjState' => $isis_data['isisISAdjState'],
        'isisISAdjNeighSysType' => $isis_data['isisISAdjNeighSysType'],
        'isisISAdjNeighSysID' => $isis_data['isisISAdjNeighSysID'],
        'isisISAdjNeighPriority' => $isis_data['isisISAdjNeighPriority'],
        'isisISAdjLastUpTime' => $isis_data['isisISAdjLastUpTime'],
        'isisISAdjAreaAddress' => $isis_data['isisISAdjAreaAddress'],
        'isisISAdjIPAddrType' => $isis_data['isisISAdjIPAddrType'],
        'isisISAdjIPAddrAddress' => $isis_data['isisISAdjIPAddrAddress'],
    ]);

    $adjacencies->push($adjacency);
}

// DB cleanup - remove all entries from the DB that were not present during the poll
// => the adjacency no longer exists
IsisAdjacency::query()
    ->where(['device_id' => $device['device_id']])
    ->whereNotIn('isisISAdjIPAddrAddress', $adjacencies->pluck('isisISAdjIPAddrAddress'))->delete();

// TODO: Create RRD-files for each adjacency, save status and possibly uptime?

$adjacency_count = $adjacencies->count();
echo "\nTotal adjacencies: " . $adjacency_count;

echo PHP_EOL;

unset(
    $adjacencies_poll,
    $adjacencies,
    $isis_data,
    $tmp_adjacencies,
    $tmp_time,
);
