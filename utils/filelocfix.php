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

$fix = mysql_query("select title,fileloc from episode_content where fileloc LIKE '%\_.xml'");

while($item = mysql_fetch_assoc($fix)){
	
	$newfileloc = trim(str_replace("_.xml",".xml",$item['fileloc']));
	
	$update = mysql_query("UPDATE episode_content SET fileloc='".$newfileloc."' WHERE fileloc='".$item['fileloc']."'");
	

}

?>