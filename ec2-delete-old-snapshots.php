<?php
define('NOOP',true);

$defaultSettings = array(
  "quiet"=>0,
  "verbose"=>0,
  "clearAllBefore"=>18*30,  // 18 months, roughly
  "clearNot1stBefore"=>30,  // a month
  "clearNotSunBefore"=>7,   // a week
  "oncePerDayBefore"=>3     // 3 days
);

function optcount($options,$s) {
  if (!isset($options[$s])) {
    return 0;
  } else if (is_array($options[$s])) {
    return count($options[$s]);
  } else {
    return 1;
  }
}

$options = getopt("vqa:");
$defaultSettings['quiet'] = optcount($options,'q');
$defaultSettings['verbose'] = optcount($options,'v');
// Credit to Erik Dasque for inspiring the following function
function keepSnapShot($ts, $lastSavedTs, $settings) {
  $now = time();
  $day = 24*60*60;
  $clearNot1stAfter = $now - $settings['clearAllBefore'] * $day;
  $clearNotSunAfter = $now - $settings['clearNot1stBefore'] * $day;
  $oncePerDayAfter =  $now - $settings['clearNotSunBefore'] * $day;
  $saveAfter =        $now - $settings['oncePerDayBefore'] * $day;

  if ( $saveAfter         < $oncePerDayAfter
    || $oncePerDayAfter   < $clearNotSunAfter
    || $clearNotSunAfter  < $clearNot1stAfter)
  {
    die("Invalid order");
  }

  $verbose = $settings['verbose'];
  $quiet = $settings['quiet'];

  $logDate = "\t".date('M d, Y',$ts)."\t";

  $isDailyDupe = $lastSavedTs > 1000000000
    && date('Y-m-d',$ts) == date('Y-m-d',$lastSavedTs);
  $isSunday = intval(date("w", $ts)) == 0;
  $is1st = intval(date("d", $ts)) == 1;

  if ($ts >= $saveAfter) {
    if ($verbose) echo "{$logDate}Very recent\tKEEP\n";
    return TRUE;
  } else if ($ts >= $oncePerDayAfter && !$isDailyDupe) {
    if ($verbose) echo "{$logDate}Recent backup\tKEEP\n";
    return TRUE;
  } else if ($ts >= $clearNotSunAfter && $isSunday && !$isDailyDupe) {
    if ($verbose) echo "{$logDate}Recent Sunday\tKEEP\n";
    return TRUE;
  } else if ($ts >= $clearNot1stAfter && $is1st && !$isDailyDupe) {
    if ($verbose) echo "{$logDate}1st of month\tKEEP\n" ;
    return TRUE;
  } else {
    if ($isDailyDupe) {
      if (!$quiet) echo "{$logDate}Daily dupe\tDELETE\n";
    } else if (!$isSunday && !$is1st) {
      if (!$quiet) echo "{$logDate}Old backup\tDELETE\n";
    } else if ($isSunday && !$is1st) {
      if (!$quiet) echo "{$logDate}Old Sunday\tDELETE\n";
    } else if ($isSunday && $is1st) {
      if (!$quiet) echo "{$logDate}Ancient Sunday\tDELETE\n";
    } else if ($ts < $clearNot1stAfter) {
      if (!$quiet) echo "{$logDate}Ancient backup\tDELETE\n";
    } else {
      echo "{$logDate}UNKNOWN!!\n";
      die("ERROR: Not sure on reason for deletion?!\n");
    }
    return FALSE;
  }
}
require(dirname(__FILE__).'/sdk/sdk.class.php');

$ec2 = new AmazonEC2();

$response = $ec2->describe_snapshots(array('Owner' => 'self'));
 
if (!$response->isOK()) {
  die('REQUEST FAILED');
}

$snapshots = array();
$snapshotDates = array();

date_default_timezone_set('UTC');

foreach ($response->body->snapshotSet->item as $item) {
  $item = (array)$item;
  if ($item['status'] != "completed") {
    echo "{$item['snapshotId']} incomplete\n";
    continue;
  }
  $volId = $item['volumeId']."";
  $snapshots[$volId][] = $item;
  $snapshotDates[$volId][count($snapshots[$volId])-1] = strtotime($item['startTime']);
}
foreach ($snapshots as $volId => &$snaps) {
  if ($defaultSettings['verbose']>1) echo "Sorting '$volId' (".count($snaps).")\n";
  array_multisort($snapshotDates[$volId],SORT_DESC,$snaps);
  $snapshots[$volId] = $snaps;
}
$totalDeleted = 0;
$totalRemaining = 0;
foreach ($snapshots as $volId => &$snaps) {
  $settings = $defaultSettings;
  echo "Processing '$volId'\n";
  if (empty($snaps)) {
    continue;
  }
  $deleted = 0;
  $remaining = 0;
  $saved = array_shift($snaps);
  $remaining++;
  if ($settings['verbose']) echo "\t".date("M d, Y",strtotime($saved['startTime']))."\tFORCE SAVE\tKEEP\n";
  $lastSavedSnapshotTs = null;
  foreach ($snaps as $snap) {
    $t = strtotime($snap['startTime']);
    if ($t > 1000000000) {
      if (!keepSnapshot($t,$lastSavedSnapshotTs,$settings)) {
        $deleted++;
        if (!NOOP) {
          $response = $ec2->delete_snapshot($snap['snapshotId']);
          if (!$response->isOK()) {
            die("Deletion of '{$snap['snapshotId']}' failed\n");
          }
        } else {
          if ($settings['verbose'] > 1) echo "[NOOP]\tDelete '{$snap['snapshotId']}' ({$snap['volumeId']})\n";
        }
      } else {
        $remaining++;
        $lastSavedSnapshotTs = $t;
      }
    } else {
      die('SNAPSHOT TOO OLD!!');
    }
  }
  echo "\tDELETED: {$deleted}\n";
  echo "\tREMAIN:  {$remaining}\n";
  $totalDeleted += $deleted;
  $totalRemaining += $remaining;
}
echo "Deleted: $totalDeleted snapshots".(NOOP?" (not really - NOOP)":"").", remaining: $totalRemaining snapshots\n";
