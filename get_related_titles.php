<?php

ini_set("memory_limit","164M");
error_reporting(E_ERROR);
include 'Scrape.php';
include '../db/DBconfig.php'; 
include '../utils/DateConverter.php';
include '../IO/FileType.php';
include 'com/MetaDataSources.php';
include 'com/HarvestMethods.php';

///========================ACCESS TOKEN===================================
$ACCESStoken = 'm5AJjTrMyYhjLskzYJJ0EHvDRnw%3DzOs7F5N4290388ffddabf34a958a8568de4f4a4710e26f2b4af68076bc33346a47acecdf402d081dab18813d617ce8deaaa7eb69&his=&whis=';

//


$dbconfig = new DBconfig();
$scrape   = new Scrape();			
$harvest  = new HarvestMethods();
$mds      = new MetaDataSources();
$suffix   = $dbconfig->getDBsuffix();
$notes    = "";
$ubiGenreIDs = array();
$ubiGenres   = array();
$addOns   = array();
/*require_once('/opt/myubi/Log4php/Logger.php');
Logger::configure('/opt/myubi/Log4php/resources/appender_jstream.properties');
$logger = Logger::getRootLogger();
$logger->info("PHP Harvester :jstream.php Starting" );
global $logger;*/

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

$getGenres = mysql_query("Select * from genres");

while($item = mysql_fetch_array($getGenres)){
	array_push($ubiGenreIDs,strtolower($item['id']));
	array_push($ubiGenres ,strtolower($item['genre'])."-".strtolower($item['subgenre']));

}


$showList  = array();
$query = "select distinct title, myubi_id,type from episode_reference".$suffix."  where  myubi_id not in(select parent_id from related_titles)";
//$query = "select distinct title, myubi_id,type from episode_reference".$suffix;
$res   = mysql_query($query);
if (!$res) die("Could not connect to the database, mysql error: ".mysql_error());

$i = 0;

while($showData = mysql_fetch_assoc($res)){
	$seriesObj = new stdclass;
	
print "..check " . html_entity_decode($showData['title']) . "...";

	/*$imdbShort = $harvest->pullIMDB(html_entity_decode($showData['title'],ENT_QUOTES, 'UTF-8'),"title");
	//print "[[ title compare " .	levenshtein(html_entity_decode($showData['title']),$imdbShort->{'title'}) . " ]]";
	if($imdbShort != "error" && levenshtein(html_entity_decode($showData['title'],ENT_QUOTES, 'UTF-8'),$imdbShort->{'title'}) < 2){
		
		$extrating   		   = $imdbShort->{'rating'} / 10;
		$seriesObj->userRating = $extrating * 5;
		$seriesObj->title      = mysql_escape_string(htmlentities($imdbShort->{'title'},ENT_QUOTES, 'UTF-8'));
		
		$imdbgenre             = $imdbShort->{'genres'};
		$seriesObj->genre      = processGenre(strtolower($imdbgenre) . ",". strtolower($genre));
		$runtime               = split(',',$imdbShort->{'runtime'});
	
		$seriesObj->runningtime= trim(str_replace("USA:","",str_replace('min',"",str_replace("(approx.)","",$runtime[0])))) * 60;
		$imdbLink              = $imdbShort->{'imdburl'};
	
		//could be used, but the api delivers enough info as is
		$seriesObj = $mds->scrapeIMDBmain($imdbLink,$seriesObj,true);
		//print_r($seriesObj->recset);
	}else{*/
		$showid=$harvest->getHuluID(html_entity_decode($showData['title'],ENT_QUOTES, 'UTF-8'));
		
		$imdbID = $harvest->getIMDBid(html_entity_decode($showData['title'],ENT_QUOTES, 'UTF-8'));
			
			if($imdbID  != "error" && $showid == ""){
				print "..hulu didn't work but got id..".$imdbID."...";	
				$imdbLink              = "http://www.imdb.com/title/" .$imdbID ;
				//could be used, but the api delivers enough info as is
				$seriesObj = $mds->scrapeIMDBmain($imdbLink,$seriesObj,true);
				
				//print_r($seriesObj);
			}

	
		
			
		
		
		if(count($seriesObj->recset) == 0 || $seriesObj->recset[0] == ""){
			print "...... try hulu for ".html_entity_decode($showData['title'],ENT_QUOTES, 'UTF-8') ." ........";
			//$showid=$harvest->getHuluID(html_entity_decode($showData['title'],ENT_QUOTES, 'UTF-8'));
			print "..id..".$showid."..";
			$recommends = $harvest->ParseFeed("http://www.hulu.com/mozart/v1.h2o/recommended/show?show_id=".$showid."&items_per_page=8&position=0&_user_pgid=1&_content_pgid=67&_device_id=1&access_token=".$ACCESStoken);
			
			$recs = json_decode($recommends);
			
			
			
			$k=0;
			$recArr = array();
			
			$seriesObj->title = html_entity_decode($showData['title']);
			
			foreach($recs->data as $item){
				
				/*print $item->show->name;
				//$item->show->genreSet = processHuluGenres($item->show->genre,$item->show->genres);
				
				print "-----------------------------------------------------------";
				*/
				if($k<6)
					array_push($recArr,$item->show->name);
				$k++;
			}
			
			$seriesObj->recset = $recArr;
			
		}
	//}
	print_r($seriesObj->recset);
		

	if($seriesObj->recset[0] != "")
		insertRelations($seriesObj->recset,$showData);
	else
		print "...no recs available... /";

}


function insertRelations($recs,$parent){
	global $harvest;
	global $suffix;
	$weight = .9;
	print_r("");
	foreach($recs as $child){
	//	print"[ ". $child . " ] vs [ " .$parent['title'] ." ]";
		if(html_entity_decode($child) != html_entity_decode($parent['title'])){
			//print "select distinct title, myubi_id from show_reference".$suffix." where title='".mysql_escape_string($child)."'";
			$imgtitle = $harvest->concatTitle(html_entity_decode($child));
			$fileloc  = $harvest->getFileloc($imgtitle,$parent['type']);
			
			$recdata = mysql_query("select distinct title, myubi_id from episode_reference".$suffix." where fileloc='".$fileloc."'");
			if (!$recdata)mysql_error($recdata);
			
			$rec = mysql_fetch_assoc($recdata);
			
			if(mysql_num_rows($recdata) > 0){

				print "....[\   inserting rec " . $rec['title'] ."   /] ....";
				
				$check = mysql_query("select parent_id,child_id from related_titles where (parent_id='".$rec['myubi_id']."' and child_id='".$parent['myubi_id']."') OR (child_id='".$rec['myubi_id']."' and parent_id='".$parent['myubi_id']."')");
				
				if(mysql_num_rows($check) > 0){
					
					$current = mysql_fetch_assoc($check);
					
					if($current['parent_id'] == $parent['myubi_id']){
						//ignore, this is simply a double
					}else//boost weight .05
						$update = mysql_query("UPDATE related_titles SET weight = weight + .05 WHERE parent_id='".$parent['myubi_id']."' and child_id='".$rec['myubi_id']."'");
					
				}else{
					/*print_r($rec);
					print "INSERT INTO related_titles (parent_id, child_id, weight, child_type, parent_type) VALUES ('".$parent['myubi_id']."', '".$rec['myubi_id']."', ".$weight.", 1, ".$parent['type'].")";
					exit('stop');*/
					$insert = mysql_query("INSERT INTO related_titles (parent_id, child_id, weight, child_type, parent_type) VALUES ('".$parent['myubi_id']."', '".$rec['myubi_id']."', ".$weight.", 1, ".$parent['type'].")");
					$weight = $weight - .1;
				}
				
			}//find title Id
			
		}//not a matching title
	}//rec cycle
	
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

?>
