<?php

$link_array = [
    'page'   => 'device',
    'device' => $device['device_id'],
    'tab'    => 'routing',
    'proto'  => 'isis',
];

// echo(generate_link("Basic", $link_array,array('view'=>'basic')));
if (! isset($vars['view'])) {
    $vars['view'] = 'basic';
}

print_optionbar_start();

echo "<span style='font-weight: bold;'>Adjacencies</span> &#187; ";

$menu_options = ['basic' => 'Basic',
    // 'detail' => 'Detail',
];

if (! $_GET['opta']) {
    $_GET['opta'] = 'basic';
}

$sep = '';
foreach ($menu_options as $option => $text) {
    if ($vars['view'] == $option) {
        echo "<span class='pagemenu-selected'>";
    }

    echo generate_link($text, $link_array, ['view' => $option]);
    if ($vars['view'] == $option) {
        echo '</span>';
    }

    echo ' | ';
}

unset($sep);


print_optionbar_end();

echo "<div style='margin: 5px;'><table border=0 cellspacing=0 cellpadding=5 width=100%>";
$i = '0';
foreach (dbFetchRows('SELECT * FROM `vrfs` WHERE `device_id` = ? ORDER BY `vrf_name`', [$device['device_id']]) as $vrf) {
    include 'includes/html/print-vrf.inc.php';

    $i++;
}

echo '</table></div>';
