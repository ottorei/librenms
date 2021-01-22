<?php

#use App\Models\Ipv4Address;
#use App\Models\OspfArea;
#use App\Models\OspfInstance;
#use App\Models\OspfNbr;
#use App\Models\OspfPort;
#use LibreNMS\RRD\RrdDefinition;

#$device_model = DeviceCache::getPrimary();


// Get circuits data
$isis_circuits_poll = snmpwalk_cache_oid($device, 'ISIS-MIB::IsisCircTable', [], 'ISIS-MIB');
d_echo($isis_circuits_poll);







echo PHP_EOL;

unset($isis_circuits_poll)