<?php
require_once __DIR__ . '/../config/db_mysqli.php';

$res1 = $mysqli->query("SELECT COUNT(*) FROM tahfidz_memorization");
$count1 = $res1 ? $res1->fetch_row()[0] : 'Table not found/error';

$res2 = $mysqli->query("SELECT COUNT(*) FROM memorization_entries");
$count2 = $res2 ? $res2->fetch_row()[0] : 'Table not found/error';

echo "tahfidz_memorization count: $count1\n";
echo "memorization_entries count: $count2\n";

$res3 = $mysqli->query("SELECT COUNT(*) FROM memorization_baselines");
$count3 = $res3 ? $res3->fetch_row()[0] : 'Table not found/error';
echo "memorization_baselines count: $count3\n";

$res4 = $mysqli->query("SELECT COUNT(*) FROM semester_snapshots");
$count4 = $res4 ? $res4->fetch_row()[0] : 'Table not found/error';
echo "semester_snapshots count: $count4\n";
