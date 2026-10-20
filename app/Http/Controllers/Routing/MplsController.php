<?php

/**
 * MplsController.php
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
use App\Models\MplsLsp;
use App\Models\MplsLspPath;
use App\Models\MplsSap;
use App\Models\MplsSdp;
use App\Models\MplsSdpBind;
use App\Models\MplsService;
use App\View\Components\Routing\MplsTable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MplsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $request->validate([
            'view' => 'nullable|in:' . implode(',', MplsTable::VIEWS),
            'per_page' => 'nullable|integer',
            'page' => 'nullable|integer',
        ]);

        $view = (string) $request->query('view', 'lsp');
        $user = $request->user();
        $perPage = $request->query('per_page');

        $query = match ($view) {
            'paths' => MplsLspPath::hasAccess($user)
                ->select('mpls_lsp_paths.*')
                ->leftJoin('mpls_lsps', 'mpls_lsps.lsp_id', '=', 'mpls_lsp_paths.lsp_id')
                ->with('lsp')
                ->orderBy('mpls_lsp_paths.device_id')
                ->orderBy('mpls_lsps.mplsLspName'),
            'sdps' => MplsSdp::hasAccess($user)->orderBy('device_id')->orderBy('sdp_oid'),
            'sdpbinds' => MplsSdpBind::hasAccess($user)->orderBy('device_id')->orderBy('sdp_oid')->orderBy('svc_oid'),
            'services' => MplsService::hasAccess($user)->orderBy('device_id')->orderBy('svc_oid'),
            'saps' => MplsSap::hasAccess($user)->orderBy('device_id')->orderBy('svc_oid')->orderBy('sapPortId')->orderBy('sapEncapValue'),
            default => MplsLsp::hasAccess($user)->orderBy('device_id')->orderBy('mplsLspName'),
        };
        $items = $query->paginate($request->integer('per_page', 50))->withQueryString();

        return view('routing.mpls', [
            'view' => $view,
            'mpls_options' => MplsTable::options(fn (string $option) => route('routing.mpls', ['view' => $option, 'per_page' => $perPage])),
            'items' => $items->getCollection(),
            'paginator' => $items,
        ]);
    }
}
