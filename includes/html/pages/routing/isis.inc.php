<?php

use App\Models\IsisAdjacency;
use App\Models\Port;

echo '
<div>
  <div class="panel panel-default">
    <div class="panel-body">
      <table class="table table-condensed table-hover" style="border-collapse:collapse;">
        <thead>
          <tr>
            <th>&nbsp;</th>
            <th>Local device</th>
            <th>Local interface</th>
            <th>Adjacent</th>
            <th>System ID</th>
            <th>Area</th>
            <th>System type</th>
            <th>State</th>
            <th>Last uptime</th>
          </tr>
        </thead>';

foreach (IsisAdjacency::all() as $adj) {
    $device = device_by_id_cache($adj->device_id);
    //dd($adj);
    if ($adj->isisISAdjState == 'up') {
        $color = 'green';
    } else {
        $color = 'red';
    }
dd($adj->Device);
    echo '
        <tbody>
        <tr>
            <td></td>
            <td>' . generate_device_link($device, 0, ['tab' => 'routing', 'proto' => 'isis']) . '</td>
            <td><a href="' . generate_url([
        'page'=>'device',
        'device'=>$adj->device_id,
        'tab'=>'port',
        'port'=>$adj->port_id,
    ]) . '">' . $adj->Device->port->ifName . '</a></td>
            <td>' . $adj->isisISAdjIPAddrAddress . '</td>
            <td>' . $adj->isisISAdjNeighSysID . '</td>
            <td>' . $adj->isisISAdjAreaAddress . '</td>
            <td>' . $adj->isisISAdjNeighSysType . '</td>
            <td><strong><span style="color: ' . $color . ';">' . $adj->isisISAdjState . '</span></strong></td>
            <td>' . formatUptime($adj->isisISAdjLastUpTime) . '</td>
        </tr>
        </tbody>';
}
echo '</table>
    </div>
  </div>
</div>';
