<?php

namespace sgpublic\scit\tool;

header('Content-Type: application/json; charset=utf8');
if (isset($_SERVER['HTTP_ORIGIN'])){
    header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Credentials: true');

$date1 = date_create("2021-02-28 00:00");
$date2 = date_create(date('y-m-d H:i',time()));
$interval = date_diff($date1, $date2);

echo json_encode([
    'code' => 0,
    'direct' => $interval->format('%R'),
    'day_count' => (int)$interval->format('%a'),
    'date' => "2021/02/28",
    'semester' => 2,
    'school_year' => "2020-2021",
    'evaluation' => false
]);
