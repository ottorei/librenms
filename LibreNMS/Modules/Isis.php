<?php
/**
 * Mpls.php
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
 * @copyright  2019 Vitali Kari
 * @copyright  2019 Tony Murray
 * @author     Vitali Kari <vitali.kari@gmail.com>
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Modules;

use App\Observers\ModuleModelObserver;
use LibreNMS\DB\SyncsModels;
use LibreNMS\Interfaces\Discovery\MplsDiscovery;
use LibreNMS\Interfaces\Module;
use LibreNMS\Interfaces\Polling\MplsPolling;
use LibreNMS\OS;

class Mpls implements Module
{
    use SyncsModels;

    /**
     * Discover this module. Heavier processes can be run here
     * Run infrequently (default 4 times a day)
     *
     * @param OS $os
     */
    public function discover(OS $os)
    {
        if ($os instanceof IsisDiscovery) {
            echo "\nISIS SYSTEMS: ";
            ModuleModelObserver::observe('\App\Models\IsisSystem');
            $lsps = $this->syncModels($os->getDevice(), 'IsisSystems', $os->discoverIsisSystems());

            echo PHP_EOL;
        }
    }

    /**
     * Poll data for this module and update the DB / RRD.
     * Try to keep this efficient and only run if discovery has indicated there is a reason to run.
     * Run frequently (default every 5 minutes)
     *
     * @param OS $os
     */
#    public function poll(OS $os)
#    {
#        if ($os instanceof IsisPolling) {
#            $device = $os->getDevice();
#
#            if ($device->IsisISAdjs()->exists()) {
#                echo "\nISIS ADJACENTS: ";
#                ModuleModelObserver::observe('\App\Models\IsisISAdj');
#                $lsps = $this->syncModels($device, 'IsisISAdjs', $os->pollIsisISAdjs());
#            }
#
#           echo PHP_EOL;
#        }
#    }

    /**
     * Remove all DB data for this module.
     * This will be run when the module is disabled.
     *
     * @param OS $os
     */
    public function cleanup(OS $os)
    {
        $os->getDevice()->IsisAdjs()->delete();
    }
}