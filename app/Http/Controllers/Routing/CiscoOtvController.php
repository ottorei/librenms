<?php

/**
 * CiscoOtvController.php
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2026 LibreNMS
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Http\Controllers\Routing;

use App\Http\Controllers\Controller;
use App\Models\Component;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CiscoOtvController extends Controller
{
    public function __invoke(Request $request): View
    {
        $request->validate([
            'per_page' => 'nullable|integer',
            'page' => 'nullable|integer',
        ]);

        $otv = fn () => Component::hasAccess($request->user())
            ->where('type', 'Cisco-OTV')
            ->where('ignore', 0);

        // paginate on devices, then load all the components of the devices on this page
        $devices = $otv()
            ->select('device_id')
            ->groupBy('device_id')
            ->orderBy('device_id')
            ->paginate($request->integer('per_page', 50))
            ->withQueryString();

        $components = $otv()
            ->whereIn('device_id', $devices->getCollection()->pluck('device_id'))
            ->with(['prefs', 'device'])
            ->get()
            ->groupBy('device_id');

        return view('routing.cisco-otv', [
            'devices' => $components,
            'paginator' => $devices,
        ]);
    }
}
