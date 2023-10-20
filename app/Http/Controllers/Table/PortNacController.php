<?php
/**
 * PortNacController.php
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
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Http\Controllers\Table;

use App\Models\PortsNac;
use LibreNMS\Util\Mac;
use LibreNMS\Util\Url;

class PortNacController extends TableController
{
    public function rules()
    {
        return [
            'device_id' => 'int',
        ];
    }

    public function searchFields($request)
    {
        return ['username', 'ip_address', 'mac_address'];
    }

    protected function sortFields($request)
    {
        return [
            'device_id',
            'port_id',
            'mac_address',
            'mac_oui',
            'ip_address',
            'vlan',
            'domain',
            'host_mode',
            'username',
            'authz_by',
            'timeout',
            'time_elapsed',
            'time_left',
            'authc_status',
            'authz_status',
            'method',
        ];
    }

    /**
     * Defines the base query for this resource
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public function baseQuery($request)
    {
        return PortsNac::select('device_id', 'port_id', 'mac_address', 'ip_address', 'vlan', 'domain', 'host_mode', 'username', 'authz_by', 'timeout', 'time_elapsed', 'time_left', 'authc_status', 'authz_status', 'method')
            ->when($request->device_id, fn ($q, $id) => $q->where('device_id', $id))
            ->hasAccess($request->user())
            ->with('port')
            ->with('device');
    }

    /**
     * @param  PortsNac  $nac
     */
    public function formatItem($nac)
    {
        $item = $nac->toArray();
        $mac = Mac::parse($item['mac_address']);
        $item['port_id'] = Url::portLink($nac->port, $nac->port->getShortLabel());
        $item['mac_oui'] = $mac->vendor();
        $item['mac_address'] = $mac->readable();
        $item['port'] = null; //free some unused data to be sent to the browser
        $item['device_id'] = Url::deviceLink($nac->device);

        return $item;
    }
}
