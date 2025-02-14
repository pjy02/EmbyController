<?php

$enableMap = true;
if (env('TENCENT_MAP_KEY', '') == '' || env('TENCENT_MAP_SK', '') == '') {
    $enableMap = false;
}

return [
    'enable' => $enableMap,
    'key' => env('TENCENT_MAP_KEY', ''),
    'sk' => env('TENCENT_MAP_SK', ''),
];