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

$tmp = true;
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
$get = "select c.title,c.myubi_id from episode_content as c, episode_streams as s where s.myubi_id = c.myubi_id and s.provider='Fullepinfo' and ref.title not in('Criminal Minds','CSI: Miami','CSI: NY','Dinner Impossible','Top Gear','Dream Machines','Flipping Out','Futurama','Hardcore Pawn','Hoarders','Its Always Sunny in Philadelphia','Million Dollar Listing','Motorcity','NCIS','Pawn Stars','Talking Dead','The Apprentice','The Mentalist','The Real Housewives of New Jersey','The Ultimate Fighter','Top Gear US','Veep')";
$items = mysql_query($get); 

$test = array();
while($ref = mysql_fetch_assoc($items)){
	array_push($test,$ref['title']);
	$delete = mysql_query("delete from episode_content where myubi_id='".$ref['myubi_id']."'");
}
print_r($test);

?>