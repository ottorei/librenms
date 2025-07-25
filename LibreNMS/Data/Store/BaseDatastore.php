<?php

/**
 * BaseDatastore.php
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2020 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Data\Store;

use App\Facades\DeviceCache;
use App\Models\Device;
use App\Polling\Measure\Measurement;
use App\Polling\Measure\MeasurementCollection;
use LibreNMS\Interfaces\Data\Datastore as DatastoreContract;

abstract class BaseDatastore implements DatastoreContract
{
    private MeasurementCollection $stats;

    public function __construct()
    {
        $this->stats = new MeasurementCollection();
    }

    public function getStats(): MeasurementCollection
    {
        return $this->stats;
    }

    /**
     * Record statistics for operation
     *
     * @param  Measurement  $stat
     */
    protected function recordStatistic(Measurement $stat): void
    {
        $this->stats->record($stat);
    }

    protected function getDevice(array $meta): Device
    {
        if (isset($meta['device']) && $meta['device'] instanceof Device) {
            return $meta['device'];
        }

        return DeviceCache::getPrimary();
    }

    public function __destruct()
    {
        $this->terminate();
    }

    public function terminate(): void
    {
        // do nothing
    }
}
