<?php

echo '
<div>
  <div class="panel panel-default">
    <div class="panel-body">
      <table class="table table-condensed table-hover" style="border-collapse:collapse;">
        <thead>
          <tr>
            <th>&nbsp;</th>
            <th>Device</th>
            <th>Adjacent</th>
            <th>System type</th>
            <th>State</th>
            <th>Last changed</th>
          </tr>
        </thead>';

foreach (dbFetchRows('SELECT A.`device_id`, A.`isisISAdjIPAddrAddress`, A.`isisISAdjState`, A.`isisISAdjLastUpTime`, A.`isisISAdjNeighSysType` FROM `isis_adjacencies` AS `A` ORDER BY A.`device_id`') as $adj) {
    $device = device_by_id_cache($adj['device_id']);

    echo '
        <tbody>
          <tr>
            <td></td>
            <td>' . generate_device_link($device, 0, ['tab' => 'routing', 'proto' => 'isis']) . '</td>
            <td>' . $adj['isisISAdjIPAddrAddress'] . '</td>
            <td>' . $adj['isisISAdjState'] . '</td>
            <td>' . $adj['isisISAdjLastUpTime'] . '</td>
          </tr>
        </tbody>';
}
echo '</table>
    </div>
  </div>
</div>';
