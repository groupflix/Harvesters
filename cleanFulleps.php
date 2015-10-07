<?php
header('Access-Control-Allow-Origin: *');
ini_set("memory_limit","164M");
error_reporting(E_ERROR);
include 'Scrape.php';
include '../db/DBconfig.php'; 
include '../utils/DateConverter.php';
include '../IO/FileType.php';
include 'com/HarvestMethods.php';
include 'com/MetaDataSources.php';


$dbconfig = new DBconfig();
$scrape   = new Scrape();			
$harvest  = new HarvestMethods();
$mds      = new MetaDataSources();
$notes    = "";
$epcount  = 0;
$showcount= 0;
//SET DEBUG VARIABLES=======================================

$tmp = false;
$t_suffix;

if($tmp){
	$harvest->setTableSuffix('_tmp');
	$t_suffix = "_tmp";
}else
	$t_suffix ="";
//===================Database Connection=====================

$res = mysql_connect($dbconfig->getHOST(),$dbconfig->getUSERNAME(), $dbconfig->getPASSWORD());
if (!$res) die("Could not connect to the server, mysql error: ".mysql_error($res));
/*if($res->error) {
		$logger->error("PHP Harvester :jstream.php Message Mysql Connection Error" . mysql_error());
}*/

$res = mysql_select_db($dbconfig->getDATABASE());
if (!$res) die("Could not connect to the database, mysql error: ".mysql_error($res));
/*if($res->error) {
		$logger->error("PHP Harvester : jstream.php Message Mysql Connection Error" . mysql_error());
}*/


//$get = 'select distinct c.fileloc, ref.myubi_id,ref.title from episode_content as c, episode_streams as s,episode_reference as ref where ref.fileloc=c.fileloc and s.myubi_id = c.myubi_id and s.provider="jStream" and ref.title not in('Criminal Minds','CSI: Miami','CSI: NY','Dinner Impossible','Top Gear','Dream Machines','Flipping Out','Futurama','Hardcore Pawn','Hoarders','Its Always Sunny in Philadelphia','Million Dollar Listing','Motorcity','NCIS','Pawn Stars','Talking Dead','The Apprentice','The Mentalist','The Real Housewives of New Jersey','The Ultimate Fighter','Top Gear US','Veep')';

$get = "select distinct c.fileloc, s.myubi_id,c.title from episode_content".$t_suffix." as c, episode_streams".$t_suffix." as s where s.myubi_id = c.myubi_id and s.provider='streamallthis' and c.title='Futurama'";

$items = mysql_query($get); 

$cleared = array();
while($ref = mysql_fetch_assoc($items)){
	array_push($cleared,$ref);

	$delete = mysql_query("delete from episode_streams".$t_suffix." where myubi_id='".$ref['myubi_id']."' and provider='streamallthis'");
	print "...[\  remove streams from " .$show['title'] . "  /]...";
}
print_r($cleared);
$loop = 0;
foreach($cleared as $show){
	$check = mysql_query("SELECT s.url_hi FROM episode_streams".$t_suffix." as s, episode_content".$t_suffix." as c WHERE c.myubi_id='".$show['myubi_id']."' and c.myubi_id=s.myubi_id");
	
	if(mysql_num_rows($check) == 0){
		$delete = mysql_query("delete from episode_content".$t_suffix." where myubi_id='".$show['myubi_id']."'");
		print "...[\  remove episode from " .$show['title'] . "  /]...";
	}
	
	$loop++;
}

$loop = 0;
foreach($cleared as $show){
	$check = mysql_query("SELECT c.title FROM episode_reference".$t_suffix." as ref, episode_content".$t_suffix." as c WHERE c.fileloc='".$show['fileloc']."' and c.fileloc=ref.fileloc");
	
	if(mysql_num_rows($check) == 0){
		$delete = mysql_query("delete from episode_reference".$t_suffix." where fileloc='".$show['fileloc']."'");
		print "...[\  remove reference to " .$show['title'] . "  /]...";
	}
	
	$loop++;
}

?>