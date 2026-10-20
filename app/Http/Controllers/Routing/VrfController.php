<?php

/**
 * VrfController.php
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
use App\Models\Vrf;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VrfController extends Controller
{
    public function __invoke(Request $request): View
    {
        $request->validate([
            'view' => 'nullable|in:basic,graphs',
            'graph' => 'nullable|in:bits,pkts,upkts,nupkts,errors',
            'vrf' => 'nullable|string',
            'per_page' => 'nullable|integer',
            'page' => 'nullable|integer',
        ]);

        $view = $request->query('view', 'basic');
        $graph = $request->query('graph', 'bits');
        $name = $request->query('vrf');
        $perPage = $request->integer('per_page', 50);

        $link = fn (array $params) => route('routing.vrf', array_filter(['vrf' => $name, 'per_page' => $request->query('per_page')] + $params));
        $options = [
            'basic' => ['text' => __('Basic'), 'link' => $link([])],
            'graphs' => ['text' => __('Graphs'), 'link' => $link(['view' => 'graphs', 'graph' => $graph])],
        ];

        if ($view === 'graphs') {
            $graphs = ['bits' => __('Bits'), 'pkts' => __('Packets'), 'upkts' => __('Ucast'), 'nupkts' => __('NUcast'), 'errors' => __('Errors')];
            foreach ($graphs as $type => $text) {
                $options[$type] = ['text' => $text, 'link' => $link(['view' => 'graphs', 'graph' => $type])];
            }
        }

        // paginate on the distinct vrf name + rd groups, then load only the vrfs on this page
        $groups = Vrf::hasAccess($request->user())
            ->when($name, fn ($query) => $query->where('vrf_name', $name))
            ->select(['vrf_name', 'mplsVpnVrfRouteDistinguisher'])
            ->groupBy(['vrf_name', 'mplsVpnVrfRouteDistinguisher'])
            ->orderBy('vrf_name')
            ->orderBy('mplsVpnVrfRouteDistinguisher')
            ->paginate($perPage)
            ->withQueryString();

        $groupKey = fn (Vrf $vrf) => $vrf->vrf_name . '|' . $vrf->mplsVpnVrfRouteDistinguisher;
        $keys = $groups->getCollection()->map($groupKey);

        $vrfs = Vrf::hasAccess($request->user())
            ->whereIn('vrf_name', $groups->getCollection()->pluck('vrf_name')->unique())
            ->with(['device', 'ports' => fn ($query) => $query->orderBy('ifDescr')->with('device')])
            ->orderBy('vrf_name')
            ->orderBy('mplsVpnVrfRouteDistinguisher')
            ->get()
            ->groupBy($groupKey);

        // a vrf name may span pages with different rds, keep only the groups on this page
        $groups->setCollection($keys->map(fn (string $key) => $vrfs->get($key))->filter()->values());

        return view('routing.vrf', [
            'vrfs' => $groups,
            'view' => $view,
            'graph' => $view === 'graphs' ? $graph : null,
            'name' => $name,
            'options' => $options,
            'selected_option' => $view === 'graphs' ? $graph : $view,
        ]);
    }
}
