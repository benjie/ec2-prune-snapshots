<?php
define('VERSION','0.2');
$defaultSettings = array(
  "quiet"=>0,
  "verbose"=>0,
  "clearAllBefore"=>18*30,  // 18 months, roughly
  "clearNot1stBefore"=>30,  // a month
  "clearNotSunBefore"=>7,   // a week
  "oncePerDayBefore"=>3     // 3 days
);
$volumeSettings = array();

/********** DON'T MOD BELOW HERE! **********/
/*         (unless you want to...)         */

date_default_timezone_set('UTC');

function optcount($options,$s) {
  if (!isset($options[$s])) {
    return 0;
  } else if (is_array($options[$s])) {
    return count($options[$s]);
  } else {
    return 1;
  }
}
// Credit to Erik Dasque for inspiring the following function
function keepSnapShot($ts, $lastSavedTs, $settings) {
  $now = time();
  $day = 24*60*60;
  $clearNot1stAfter = $now - $settings['clearAllBefore'] * $day;
  $clearNotSunAfter = $now - $settings['clearNot1stBefore'] * $day;
  $oncePerDayAfter =  $now - $settings['clearNotSunBefore'] * $day;
  $saveAfter =        $now - $settings['oncePerDayBefore'] * $day;

  if ($saveAfter > $now - 24*60*60) {
    echo("ERROR: This script requires all snapshots to be saved for at least 24 hours.\n");
    die(1);
  }
  if ( $saveAfter         < $oncePerDayAfter
    || $oncePerDayAfter   < $clearNotSunAfter
    || $clearNotSunAfter  < $clearNot1stAfter)
  {
    echo("Invalid options to -a: each number between the colons must be smaller than the previous one.");
    die(1);
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
    } else if ($ts < $clearNot1stAfter) {
      if (!$quiet) echo "{$logDate}Ancient backup\tDELETE\n";
    } else if (!$isSunday && !$is1st) {
      if (!$quiet) echo "{$logDate}Old backup\tDELETE\n";
    } else if ($isSunday && !$is1st) {
      if (!$quiet) echo "{$logDate}Old Sunday\tDELETE\n";
    } else {
      echo "{$logDate}UNKNOWN!!\n";
      die("ERROR: Not sure on reason for deletion?!\n");
    }
    return FALSE;
  }
}

$options = getopt("vqa:V:dh");
if (isset($options['h'])) {
  echo "ec2-prune-snapshots v".VERSION." by Benjie Gillam\n";
  echo "\n";
  echo "This script defaults to no action - specify -d to perform operations.\n";
  echo "Be sure to set your credentials in ~/.aws/sdk/config.inc.php as specified by the AWS SDK. See: https://aws.amazon.com/articles/4261#configurecredentials\n";
  echo "\n";
  echo "Usage:\n";
  echo "\t-h\t\tHelp\n";
  echo "\t-v\t\tVerbose (specify multiple times for greater verbosity)\n";
  echo "\t-q\t\tQuiet\n";
  echo "\t-d\t\tActually perform operations (delete/do it)\n";
  echo "\t-a365:30:7:3\tSet global options\n";
  echo "\t-V'vol-abcdefgh:365:30:7:3'\tSet options for specific volume\n";
  echo "\n";
  echo "Options are specified as 4 ages, in days, for each operation\n";
  echo "\t1st: delete all older snapshots\n";
  echo "\t2nd: delete older unless 1st of month\n";
  echo "\t3rd: delete older unless Sunday or 1st of month\n";
  echo "\t4th: keep only one per day older than this\n";
  echo "\n";
  echo "\tSnapshots newer than the 4th parameter will be kept.\n";
  exit();
}
$defaultSettings['quiet'] = optcount($options,'q');
$defaultSettings['verbose'] = optcount($options,'v');
define('NOOP',!isset($options['d']));
if (isset($options['a'])) {
  if (is_array($options['a'])) {
    echo("ERROR: Don't specify multiple '-a' options.\n");
    die(1);
  }
  $s = explode(":",$options['a']);
  if (count($s) != 4) {
    echo("ERROR: Invalid '-a' options\n");
    die(1);
  }
  $defaultSettings['clearAllBefore'] = intval($s[0]);
  $defaultSettings['clearNot1stBefore'] = intval($s[1]);
  $defaultSettings['clearNotSunBefore'] = intval($s[2]);
  $defaultSettings['oncePerDayBefore'] = intval($s[3]);
}
if (isset($options['V'])) {
  $V = $options['V'];
  if (!is_array($V)) {
    $V = array($V);
  }
  foreach ($V as $o) {
    $s = explode(':',$o);
    $vol = array_shift($s);
    if (substr($vol,0,4) != "vol-"||count($s) != 4) {
      echo("ERROR: Invalid -V argument: '$o' (should be 'vol-123456:365:30:7:3')\n");
      die(1);
    }
    $settings = array();
    $settings['clearAllBefore'] = intval($s[0]);
    $settings['clearNot1stBefore'] = intval($s[1]);
    $settings['clearNotSunBefore'] = intval($s[2]);
    $settings['oncePerDayBefore'] = intval($s[3]);
    foreach ($settings as $s=>$v) {
      if ($v < 1) {
        echo("ERROR: Invalid -V argument, all values must be >= 1\n");
        die(1);
      }
    }
    $volumeSettings[$vol] = $settings;
  }
}
require(dirname(__FILE__).'/sdk/sdk.class.php');

if (NOOP) {
  echo "WARNING: NOTHING WILL BE DELETED. Run this command again with -d (do it!) to actually perform the operations.\n";
}

$ec2 = new AmazonEC2();
$response = $ec2->describe_snapshots(array('Owner' => 'self'));
if (!$response->isOK()) {
  echo('REQUEST FAILED');
  die(1);
}

$snapshots = array();
$snapshotDates = array();
foreach ($response->body->snapshotSet->item as $item) {
  $item = (array)$item;
  if ($item['status'] != "completed") {
    echo "{$item['snapshotId']} incomplete\n";
    continue;
  }
  if (strpos($item['description'],'CreateImage') !== false) {
    echo "{$item['snapshotId']} is part of an AMI image and can't be deleted.\n";
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
unset($snapshotDates);
ksort($snapshots);

$totalDeleted = 0;
$totalRemaining = 0;
foreach ($snapshots as $volId => &$snaps) {
  $settings = $defaultSettings;
  if (!empty($volumeSettings[$volId])) {
    $settings = $volumeSettings[$volId] + $settings;
  }
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
            echo("Deletion of '{$snap['snapshotId']}' failed\n");
            die(1);
          }
        } else {
          if ($settings['verbose'] > 1) echo "[NOOP]\tDelete '{$snap['snapshotId']}' ({$snap['volumeId']})\n";
        }
      } else {
        $remaining++;
        $lastSavedSnapshotTs = $t;
      }
    } else {
      echo('SNAPSHOT TOO OLD!!');
      die(1);
    }
  }
  echo "\tDELETED: {$deleted}\n";
  echo "\tREMAIN:  {$remaining}\n";
  $totalDeleted += $deleted;
  $totalRemaining += $remaining;
}
echo "Deleted: $totalDeleted snapshots".(NOOP?" (not really - NOOP)":"").", remaining: $totalRemaining snapshots\n";
if (NOOP) {
  echo "WARNING: NOTHING HAPPENED. Run this command again with -d (do it!) to actually perform the operations.\n";
}
