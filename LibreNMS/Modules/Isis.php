<?php

namespace LibreNMS\Modules;

use App\Models\IsisAdjacency;
use App\Facades\DeviceCache;
use App\Observers\ModuleModelObserver;
use LibreNMS\Interfaces\Module;
use LibreNMS\Util\IP;
use LibreNMS\OS;
use App\Models\Device;

class Isis implements Module
{
    /**
     * Discover this module. Heavier processes can be run here
     * Run infrequently (default 4 times a day)
     *
     * @param OS $os
     */
    public function discover(OS $os)
    {
        // not implemented
    }

    /**
     * Poll data for this module and update the DB / RRD.
     * Try to keep this efficient and only run if discovery has indicated there is a reason to run.
     * Run frequently (default every 5 minutes)
     *
     * @param OS $os
     */
    public function poll(OS $os)
    {
        // Get device object
        $device = $os->getDevice();
        $device_id = $os->getDeviceId();

        // Poll data from device
        $adjacencies_poll = $os->getCacheTable('ISIS-MIB::isisISAdj', 'ISIS-MIB');
        $adjacencies = collect();
        $isis_data = [];

        // Loop through all adjacencies and output their status
        foreach ($adjacencies_poll as $key => $value) {
            //var_dump($value);
            $isis_data['isisISAdjState'] = $value['isisISAdjState'][1];
            $isis_data['isisISAdjNeighSysType'] = $value['isisISAdjNeighSysType'][1];
            $isis_data['isisISAdjNeighSysID'] = $value['isisISAdjNeighSysID'][1];
            $isis_data['isisISAdjNeighPriority'] = $value['isisISAdjNeighPriority'][1];
            $isis_data['isisISAdjLastUpTime'] = $value['isisISAdjLastUpTime'][1];
            $isis_data['isisISAdjAreaAddress'] = $value['isisISAdjAreaAddress'][1][1];
            $isis_data['isisISAdjIPAddrType'] = $value['isisISAdjIPAddrType'][1][1];
            $isis_data['isisISAdjIPAddrAddress'] = IP::fromHexString($value['isisISAdjIPAddrAddress'][1][1]);

            // Translate system state codes into meaningful strings
            $isis_codes['1'] = 'L1';
            $isis_codes['2'] = 'L2';
            $isis_codes['3'] = 'L1L2';
            $isis_codes['4'] = 'unknown';

            // Translate state codes into meaningful strings
            $state_codes['1'] = 'down';
            $state_codes['2'] = 'initializing';
            $state_codes['3'] = 'up';
            $state_codes['4'] = 'failed';

            // Remove spaces
            $isis_data['isisISAdjNeighSysID'] = str_replace(' ', '.', $isis_data['isisISAdjNeighSysID']);

            // Convert uptime into seconds
            $isis_data['isisISAdjLastUpTime'] = (int) $isis_data['isisISAdjLastUpTime'] / 100;

            // Format area address
            $isis_data['isisISAdjAreaAddress'] = str_replace(' ', '.', $isis_data['isisISAdjAreaAddress']);

            echo "\nFound adjacent " . $isis_data['isisISAdjIPAddrAddress'];

            // Get port_id from ifIndex
            $port_id = (int) $device->ports()->where('ifIndex', $key)->value('port_id');

            // Save data into the DB
            $adjacency = IsisAdjacency::updateOrCreate([
                'device_id' => $device_id,
                'isisISAdjIPAddrAddress' => $isis_data['isisISAdjIPAddrAddress'],
            ], [
                'device_id' => $device_id,
                'port_id' => $port_id,
                'isisISAdjState' => $state_codes[$isis_data['isisISAdjState']],
                'isisISAdjNeighSysType' => $isis_codes[$isis_data['isisISAdjNeighSysType']],
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
        // => the adjacency no longer exists and should not be saved
        IsisAdjacency::query()
            ->where(['device_id' => $device['device_id']])
            ->whereNotIn('isisISAdjIPAddrAddress', $adjacencies->pluck('isisISAdjIPAddrAddress'))->delete();

        // TODO: Create RRD-files for some of the data?

        $adjacency_count = $adjacencies->count();
        echo "\nTotal adjacencies: " . $adjacency_count;

    }

    /**
     * Remove all DB data for this module.
     * This will be run when the module is disabled.
     *
     * @param OS $os
     */
    public function cleanup(OS $os)
    {
        $os->getDevice()->isisAdjacencies()->delete();
    }
}
