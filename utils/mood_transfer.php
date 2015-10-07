<?php

	include '../../IO/FileType.php';
	include '../../db/DBconfig.php';  
	include '../com/SortArray.php';
	include '../com/HarvestMethods.php';

//-----Link into dB------------------------
//-----------------------------------------
$dbconfig = new DBconfig();
$sort     = new SortArray();
$FT 	  = new FileType();
$harvest  = new HarvestMethods();			

$host 	  = $dbconfig->getHOST();
$username = $dbconfig->getUSERNAME();
$password = $dbconfig->getPASSWORD();
$db       = $dbconfig->getDATABASE();
$suffix   = $dbconfig->getDBsuffix();

$res = mysql_connect($host, $username, $password);
if (!$res) die("Could not connect to the server, mysql error: ".mysql_error($res));
$res = mysql_select_db($db);
if (!$res) die("Could not connect to the database, mysql error: ".mysql_error($res));


//pull all current genre information stored as well as the fileloc to reference
$subquery = "select sr.fileloc,sr.title, mmc.id from moods, map_mood_content as mmc, show_reference as sr where genres.id = mmc.genre_id and mmc.myubi_id = sr.myubi_id";
$subres   = mysql_query($subquery);

while($entry = mysql_fetch_assoc($subres)){
	
	//we have a new genre schema so we have to convert to a new fileloc before we ty to match the title in our new table
	$fileloc = $harvest->getFileloc($harvest->concatTitle(html_entity_decode($entry['title'],ENT_QUOTES, 'UTF-8')),1);

	$getshow = "select myubi_id from episode_reference".$suffix." where fileloc='".$fileloc."'";
	$res     = mysql_query($getshow);
	
	if(mysql_num_rows($res) > 0){
		$showId = mysql_fetch_assoc($res);
		
		$update = "UPDATE map_mood_content SET myubi_id='".$showId['myubi_id']."' WHERE id=".$entry['id'];
		$res     = mysql_query($update);
		print "...[\  Update Genre for ".$entry['title']."  id: " .$showId['myubi_id'] ."   /]...";
		
	}else{
		print "....".$entry['title']." is not in our current show selection.....";
		
	}
	
}

?>