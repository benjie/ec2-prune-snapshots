<?php
define('NOOP',true);
// Credit to Erik Dasque for the following function (modified)
function keepSnapShot($ts) {
	$now = time();
	$older_than = $now - 14 * 24 * 60 * 60;
	$older_than_month = $now - 30 * 24 * 60 * 60;
	
	//	echo 'Day of month: '.date("d",$ts)."\n";
  //	echo 'Day of week: '.date("w",$ts)."\n";
  echo "\t";
	echo date('M d, Y',$ts)."\t";
	
	if ($ts>=$older_than) { 
		echo "Recent backup\tKEEP\n" ;
		return(TRUE); 
		} 
	if (date("d",$ts)==1) { 
		echo "1st of month\tKEEP\n" ; 
		return(TRUE); 
		}
	if ((date("w",$ts)==0) && $ts>$older_than_month) { 
		echo "Recent Sunday\tKEEP\n" ;
		return(TRUE); 
		} 
	if ((date("w",$ts)==0) && $ts<=$older_than_month) { 
		echo "Old Sunday\tDELETE\n" ;
		return(FALSE); 
		} 
	if ($ts<$older_than) { 
		echo "Old backup\tDELETE\n" ; 
		return(FALSE); 
		} 
		
	
	echo "Unknown condition on ".date('F d, Y',$ts)."\n"; exit(0);
	return(FALSE); 
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
  echo "Sorting '$volId' (".count($snaps).")\n";
  array_multisort($snapshotDates[$volId],SORT_ASC,$snaps);
  $snapshots[$volId] = $snaps;
}
$deleted = 0;
$remaining = 0;
foreach ($snapshots as $volId => &$snaps) {
  echo "Processing '$volId'\n";
  if (empty($snaps)) {
    continue;
  }
  $saved = array_pop($snaps);
  $remaining++;
  echo "\t".date("M d, Y")."\tFORCE SAVE\tKEEP\n";
  foreach ($snaps as $snap) {
    $t = strtotime($snap['startTime']);
    if ($t > 1000000000) {
      if (!keepSnapshot($t)) {
        $deleted++;
        if (!NOOP) {
          $response = $ec2->delete_snapshot($snap['snapshotId']);
          if (!$response->isOK()) {
            die("Deletion of '{$snap['snapshotId']}' failed\n");
          }
        } else {
          echo "[NOOP]\tDelete '{$snap['snapshotId']}' ({$snap['volumeId']})\n";
        }
      } else {
        $remaining++;
      }
    } else {
      die('SNAPSHOT TOO OLD!!');
      $remaining++;
    }
  }
}
echo "Deleted: $deleted snapshots".(NOOP?" (not really - NOOP)":"").", remaining: $remaining snapshots\n";
