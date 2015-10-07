<?php


ini_set("memory_limit","64M");
error_reporting(E_ERROR);
include '../../db/DBconfig.php'; 
include '../../utils/DateConverter.php';
include '../com/HarvestMethods.php';

$dbconfig = new DBconfig();		
$harvest  = new HarvestMethods();
$suffix   = $dbconfig->getDBsuffix();

$tmp = true;
$t_suffix;

if($tmp){
	$harvest->setTableSuffix('_tmp');
	$t_suffix = "_tmp";
}else
	$t_suffix ="";

$res = mysql_connect($dbconfig->getHOST(),$dbconfig->getUSERNAME(), $dbconfig->getPASSWORD());
if (!$res) die("Could not connect to the server, mysql error: ".mysql_error());
/*if($res->error) {
		$logger->error("PHP Harvester :jstream.php Message Mysql Connection Error" . mysql_error());
}*/

$res = mysql_select_db($dbconfig->getDATABASE());
if (!$res) die("Could not connect to the database, mysql error: ".mysql_error());
/*if($res->error) {
		$logger->error("PHP Harvester : jstream.php Message Mysql Connection Error" . mysql_error());
}*/



$calendarData = file_get_contents("http://eztv.it/showlist/");
	
$newShows = array();
$regexp = '<table border="0" width="950" align="center" class="forum_header_border" cellspacing="0" cellpadding="0">(.*)<\/table>';

if(preg_match_all("/$regexp/si", $calendarData, $matches)) {
	
		
		$epexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>";
		
		if(preg_match_all("/$epexp/siU", $matches[0][0], $episodes)) {
				
				for($i = 3; $i<count($episodes[3]); $i++){  
					$showItem = new stdclass;
				
					$eptitle = strip_tags($episodes[3][$i]);
					$eptitle = strtolower($eptitle);
					if(stripos($eptitle,", the",3)){
						$eptitle = str_replace(", the","",$eptitle);
					}
					
					if(stripos($eptitle,"(",3)){
						$end = stripos($eptitle,"(",3);
						$eptitle = trim(substr($eptitle,0,$end-1));
					}
					
					$showItem->title = $eptitle;
					$showItem->link  = "http://eztv.it" . $episodes[2][$i];
					
					if($eptitle != "" && $showItem->link != "http://eztv.it")
						scrapePage($showItem);
					
				}
			
		
			print "there were " . count($newShows) . " new shows aired yesterday<P>";
			
		}
	
}
	
	function scrapePage($item){
		
		$showpage = file_get_contents($item->link);
		
		$regex = '<td rowspan=\"[0-9]\" valign=\"top\" style=\"padding: [0-9]px\;\">(.*)<\/td>';
		preg_match("/$regex/siU", $showpage, $list);
		
		$start = strripos($list[0],'GENERAL INFORMATION',0);
		$end   = strripos($list[0],'About the show',$start);
		$info  = substr($list[0],$start+24,$end-($start+24));

		$listRows = explode('<br />',$info);
		
		foreach($listRows as $row){
			$listItem = explode(':',trim($row));
			
			switch(strtolower($listItem[0])){
				case('status'):
					preg_match('/season (\d{1,}) /si',$listItem[1],$scount);
					$item->seasons = $scount[1];
				break;
				case('premiere'):
					$item->premier = date('Y-m-d',strtotime('September 2, 2008'));
				break;
				case('network'):
					$item->network = trim(str_replace('(USA)',"",$listItem[1]));
				break;
				case('airs'):
					$item->airs    = trim(str_replace('Airs:',"",trim($row)));
					$item->timeslot= processTime($item->airs);
				break;
				case('runtime'):
					$item->duration= (int)trim(str_replace('Minutes',"",$listItem[1])) * 60;
				break;
			}
		}
		
		saveData($item);
		print_r($item);
	    
	}
	
	function processTime($airdate){
		global $harvest;
		
		$parts   = explode('at',$airdate);
		$newtime = "";
		if(strripos($parts[1],"pm",0)>0){
			$time    = str_replace(' pm', "",$parts[1]);
			$newtime = (12*60) + ($harvest->convertStringToSeconds(trim($time).":00") /60);
		}else{
			$time    = str_replace(' am', "",$parts[1]);
			$newtime = $harvest->convertStringToSeconds(trim($time)) /60;
		}
		
		return $newtime;
	}
	
	function saveData($s){
		global $harvest;
		
		$fileloc = $harvest->getFileloc($harvest->concatTitle($s->title),1);
		
		$select  = mysql_query("SELECT title,network FROM episode_reference".$t_suffix." WHERE fileloc = '".$fileloc."' and (timeslot='' or timeslot=0)");
		
		if(mysql_num_rows($select) > 0){
			print '..[\  lets up date '. $s->title . ' /]..';
			$udpate = mysql_query("UPDATE episode_reference".$t_suffix." SET network='".$s->network."',airing='".$s->airs."',timeslot='".$s->timeslot."' ,premier='".$s->premier."' WHERE fileloc = '".$fileloc."'");
		}else
			print '..[/ already set \]..';
		
	}
	

?>