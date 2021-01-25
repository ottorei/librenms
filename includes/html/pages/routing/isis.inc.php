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
            <th>State</th>
            <th>Last changed</th>
          </tr>
        </thead>';

foreach (dbFetchRows('SELECT A.`device_id` FROM `isis_adjacencies` AS `A` ORDER BY A.`device_id`') as $adj) {
    $device = device_by_id_cache($cef['device_id']);

    echo '
        <tbody>
          <tr>
            <td></td>
            <td>' . generate_device_link($device, 0, ['tab' => 'routing', 'proto' => 'isis']) . '</td>
            <td>' . $adj['device_id'] . '</td>
            <td>' . $adj['device_id'] . '</td>
            <td>' . $adj['device_id'] . '</td>
          </tr>
        </tbody>';
}
echo '</table>
    </div>
  </div>
</div>';
