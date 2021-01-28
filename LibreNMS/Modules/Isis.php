<?php
/**
 * Isis.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @link       http://librenms.org
 * @copyright  2021 Otto Reinikainen
 * @author     Otto Reinikainen <otto@ottorei.fi>
 */

namespace LibreNMS\Modules;

use App\Models\Device;
use App\Models\IsisAdjacency;
use LibreNMS\Interfaces\Module;
use LibreNMS\OS;
use LibreNMS\Util\IP;

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
        // Poll all ISIS enabled circuits from the device
        $circuits_poll = $os->getCacheTable('ISIS-MIB::isisCirc', 'ISIS-MIB');

        // Poll all available adjacencies
        $adjacencies_poll = $os->getCacheTable('ISIS-MIB::isisISAdj', 'ISIS-MIB');
        $adjacencies = collect();
        $isis_data = [];

        dd($circuits_poll);
        foreach ($circuits_poll as $circuit => $circuit_data)
        {
            
        }

        // Loop through all adjacencies and output their status
        foreach ($adjacencies_poll as $key => $value) {
            //var_dump($value);
            //dd($value);
            $isis_data['isisISAdjState'] = end($value['isisISAdjState']);
            $isis_data['isisISAdjNeighSysType'] = end($value['isisISAdjNeighSysType']);
            $isis_data['isisISAdjNeighSysID'] = end($value['isisISAdjNeighSysID']);
            $isis_data['isisISAdjNeighPriority'] = end($value['isisISAdjNeighPriority']);
            $isis_data['isisISAdjLastUpTime'] = end($value['isisISAdjLastUpTime']);
            $isis_data['isisISAdjAreaAddress'] = end(end($value['isisISAdjAreaAddress']));
            $isis_data['isisISAdjIPAddrType'] = end(end($value['isisISAdjIPAddrType']));
            $isis_data['isisISAdjIPAddrAddress'] = IP::fromHexString(end(end($value['isisISAdjIPAddrAddress'])));

            // Translate system state codes into meaningful strings
            $isis_codes['1'] = 'L1';
            $isis_codes['2'] = 'L2';
            $isis_codes['3'] = 'L1L2';
            $isis_codes['4'] = 'unknown';

            /** 
            * Translate state codes into meaningful strings
            * The most likely state is 'up' since the adjacency is lost after a configurable hold-time 
            * this means that the state changes but it is possible that the polling is not completed in time
            * to reflect this change.
            */
            $adjacency_state_codes['1'] = 'down';
            $adjacency_state_codes['2'] = 'initializing';
            $adjacency_state_codes['3'] = 'up';
            $adjacency_state_codes['4'] = 'failed';

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
                'isisISAdjState' => $adjacency_state_codes[$isis_data['isisISAdjState']],
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
