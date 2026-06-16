<?php
$keys = [
  'x15hf_selenium',
  'x16rc_1_testcomplete', 'x16rc_2_testcomplete', 'x16rc_3_testcomplete',
  'x17rc_1_testcomplete', 'x16dev_selenium', 'x16rc_selenium', 'x16hf_selenium',
  'x17dev_selenium', 'x17rc_selenium', 'x17hf_selenium',
  'x15hf_release',
  'x16dev_release', 'x16rc_release', 'x16hf_release',
  'x17dev_release', 'x17rc_release', 'x17hf_release',
  'wedev_smartwe', 'werc_smartwe', 'wehf_smartwe'
  
];

$results = [];

foreach ($keys as $key) {
    $filename = __DIR__ . "/$key.txt";
    if (file_exists($filename)) {
        $results[$key] = trim(file_get_contents($filename));
    } else {
        $results[$key] = "unchecked";
    }
}

header('Content-Type: application/json');
echo json_encode($results);
?>