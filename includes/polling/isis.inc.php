<?php

#use App\Models\Ipv4Address;
#use App\Models\OspfArea;
#use App\Models\OspfInstance;
#use App\Models\OspfNbr;
#use App\Models\OspfPort;
#use LibreNMS\RRD\RrdDefinition;

#$device_model = DeviceCache::getPrimary();

echo("Staring isis poll");

// Get circuits data
$isis_circuits_poll = snmpwalk_cache_oid($device, 'ISIS-MIB::IsisCircTable', [], 'ISIS-MIB');

d_echo($isis_circuits_poll);
var_dump($isis_circuits_poll);

$isis_circuits_poll = collect();




echo PHP_EOL;

unset($isis_circuits_poll);