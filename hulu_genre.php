<?php
//$doc = file_get_contents('http://www.imdb.com/find?q=pinks&s=all');
ini_set("memory_limit","164M");
error_reporting(E_ERROR);
include 'Scrape.php';
include '../db/DBconfig.php'; 
include '../utils/DateConverter.php';
include '../IO/FileType.php';
include 'com/HarvestMethods.php';

$dbconfig = new DBconfig();
$scrape   = new Scrape();			
$harvest  = new HarvestMethods();
$notes    = "";

$ubiGenreIDs = array();
$ubiGenres   = array();

$addOns   = array();
$res = mysql_connect($dbconfig->getHOST(),$dbconfig->getUSERNAME(), $dbconfig->getPASSWORD());
if (!$res) die("Could not connect to the server, mysql error: ".mysql_error($res));
/*if($res->error) {
		$logger->error("PHP Harvester :jstream.php Message Mysql Connection Error" . mysql_error());
}*/

$res = mysql_select_db($dbconfig->getDATABASE());
if (!$res) die("Could not connect to the database, mysql error: ".mysql_error($res));

$getGenres = mysql_query("Select * from genres");

while($item = mysql_fetch_array($getGenres)){
	array_push($ubiGenreIDs,strtolower($item['id']));
	array_push($ubiGenres ,strtolower($item['genre'])."-".strtolower($item['subgenre']));

}


$title = "Saturday Night Live";
$showid=$harvest->getHuluID($title);

$res = $harvest->ParseFeed("http://www.hulu.com/mozart/v1.h2o/recommended/show?show_id=".$showid."&items_per_page=8&position=0&_user_pgid=1&_content_pgid=67&_device_id=1&access_token=P78jeDuxPC6o8VO8ZskA9k0JWIg%3DyC2xxgIb442a6bf97b1afc6ac28cbae628e24fcf83d57a7d982a6a4524948921b58fe12546845568a6018f6b228f3dae4264ec2b&his=&whis=");

$recs = json_decode($res);
$i=0;
foreach($recs->data as $item){
	print $item->show->name;
	$item->show->genreSet = processHuluGenres($item->show->genre,$item->show->genres);

	print "-----------------------------------------------------------";
	
	if($i>9)
		break;
	else
		$i++;
}




function processHuluGenres($primary,$set){
	global $ubiGenres;
	global $ubiSubGenres;
	global $ubiGenreIDs;
	global $addOns;
	$genreSet =	explode('|',$set);
	$weight   = 1;
	
	$finalGenres = array();
	
	
	
	foreach($genreSet as $genre){
		
		if(strripos($genre,'~',0) > 0){
			
			$data =	explode('~',$genre);
			print_r($data);
			$data = checkNomenclature($data,$primary);
			print_r($data);
			$confirm =0;
			
			
			if(array_search(strtolower($data[0])."-".strtolower($data[1]), $ubiGenres))
				$confirm++;
			
			
			/*if(array_search(strtolower($data[1]), $ubiSubGenres))
				$confirm++;*/
			
			$ele = array_search(strtolower($data[0])."-".strtolower($data[1]), $ubiGenres);
			
			if($confirm == 1)
				array_push($finalGenres,array($ubiGenreIDs[$ele],$weight));
			$weight = $weight - .2;
		}
		
	}

	foreach($addOns as $addgenre){
		
		$ele = array_search(strtolower($addgenre[0])."-".strtolower($addgenre[1]), $ubiGenres);
		array_push($finalGenres,array($ubiGenreIDs[$ele],$addgenre[2]));
	}
	$addOns = null;
	$addOns = array();
	return $finalGenres;
}

function checkNomenclature($info,$primary){
	global $addOns;
	$names = $info;
	
	if($names[0] == "Reality and Game Shows"){
		$temp = $names;
		$names[0] = "Reality";
		if($names[1] == "Competition")
			$names[1] = "Game-Show";
			
		 if($primary == "Business" && $temp[0] == "Reality and Game Shows")
			 $names[1] = "Game-Show";
		 
		 if($primary == "Business" && $temp[1] == "Real Life Drama"){
			  $names[0] = "informational";
			  $names[1] = "life learning";
		 }
		 
		  if($primary == "Reality and Game Shows" && $temp[1] == "Real Life Drama")
			  $names[1] = "Situational Living";
		  
		  
		   if($primary == "Reality and Game Shows" && $temp[1] == "Guilty Pleasure"){
			   $names[1] = "skip";
			   print 'adding additional';
			   $add = array("Reality","celebrity",.3);
				array_push($addOns,$add);

		   }
		  
		 
	}
	
	if($names[0] == "Documentaries"){
		$names[0] = "documentary";
	}
	
	if($names[0] == "Science Fiction"){
		$names[0] = "sci-fi";
	}
	
	if($names[0] == "Anime"){
		$names[0] = "animated";
		$names[1] = "anime";
	}
	
	if($names[0] == "Food"){
		$temp = $names;
		$names[0] = "health and leisure";
		$names[1] = "Food and Beverage";
		
		if($temp[1] == "Cooking Competitions"){
			$names[0] = "Reality";
			$names[1] = "Occupational";
			
			
			$add = array("health and leisure","Food and Beverage",.3);
			array_push($addOns,$add);
		}
	}
	
	if($names[0] == "Action and Adventure"){
		$temp = $names;
		$names[0] = "action";
		if($temp[1] == "Espionage")
			$names[1] = "Die-Hard Scenario";
		
		if($primary == "Animation and Cartoons")
			$names[1] = "Comic-Cartoon Based";

	 }
	 
	 if($names[0] == "Business"){
		 $names[0] = "informational";
		 $names[1] = "business";
		 
		
	 }
	 
	 if($names[0] == "News and Information" && $primary == "business"){
		 $names[0] = "skip";
	 }
	 
	 if($names[0] == "Lifestyle"){
		 $temp = $names;
		 $names[0] = "health and leisure";
		 
		if($temp [1] == "Fashion and Beauty")
		   $names[1] = "fashion";
		
		if($primary == "Reality and Game Shows"){
			$names[1] = "skip";
			$add = array("health and leisure","fashion",.3);
			array_push($addOns,$add);
		}
		
		if($primary == "Food"){
			$names[1] = "skip";
			$add = array("health and leisure","Food and Beverage",.3);
			array_push($addOns,$add);
		}
		
	 }
	 
	 if($names[0] == "Horror & Suspense"){
		 $names[0] = "horror";
		 
	 }
	 
	  if($names[0] == "Health and Wellness"){
		 $names[0] = "health and leisure";
		 
	 }
	 
	
	return $names;
}

function ArrayToString($arr){
	
	$str = $arr[0];
	
	for($i=1; $i<count($arr);$i++){
		
		$str .= ", " . $arr[$i];
		
	}
	
	return $str;
}


function processGenre($gList){
   $genreSet =	split(',',$gList);
   $finalSet =  ArrayToString(array_unique($genreSet));
   
   if(stripos($finalSet,", ",0) == 0)
   		$finalSet = trim(substr($finalSet,2));
   
   return $finalSet;
}
?>