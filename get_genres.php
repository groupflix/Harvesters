<?php

ini_set("memory_limit","164M");
error_reporting(E_ERROR);
include 'Scrape.php';
include '../db/DBconfig.php'; 
include '../utils/DateConverter.php';
include '../IO/FileType.php';
include 'com/MetaDataSources.php';
include 'com/HarvestMethods.php';


$dbconfig = new DBconfig();
$scrape   = new Scrape();			
$harvest  = new HarvestMethods();
$mds      = new MetaDataSources();
$suffix   = "_tmp";$dbconfig->getDBsuffix();
$notes    = "";
$ubiGenreIDs = array();
$ubiGenres   = array();
$ubiMainGenre= array();
$ubiSubGenre = array();
$addOns   = array();

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
	array_push($ubiMainGenre,$item['genre']);
	array_push($ubiSubGenre,$item['subgenre']);
}


$showList  = array();
$query     = "select distinct title,year, myubi_id,genre,type from episode_reference".$suffix."  where  myubi_id not in(select myubi_id from map_genre_content".$suffix.")";
//$query = "select distinct title, myubi_id,type from episode_reference".$suffix;
$res   = mysql_query($query);
if (!$res) die("Could not connect to the database, mysql error: ".mysql_error());

$i = 0;

while($showData = mysql_fetch_assoc($res)){
	
	$seriesObj = new stdclass;
	$seriesObj->genreSet = array();
	$year      = ($showData['year'] == "" || $showData['year'] == 0) ? 2000 : $showData['year'];
	$jtitle    =  str_replace('_','-',$harvest->concatTitle(preg_replace('/\([^)]+\)/si',"",html_entity_decode($showData['title']))));
	$seriesObj->title = html_entity_decode($showData['title']);
	print "..check " . $jtitle . "...";
		
		//TRY JINNI
		
		$jinni = file_get_contents('http://www.jinni.com/tv/'.$jtitle.'/');

		preg_match('/<h1 id=\"contentTitle_heading\">(.*)<\/h1>/si',html_entity_decode($jinni),$match);
		$titleparts = explode(',',strip_tags($match[1]));
		print strtolower($titleparts[0]) . " == " . strtolower(html_entity_decode($showData['title']));
		if(strtolower($titleparts[0]) == strtolower(html_entity_decode($showData['title'])) && ($year ==(int)$titleparts[1] || (int)$titleparts[1] > 2000)){
			//setSelectedCategory\("\d{1,}",".*",".*"\)
			print "....try using jinni....";
			preg_match_all('/setSelectedCategory\([^)]+\)/siU',html_entity_decode($jinni),$attr);
			
			$seriesObj->genreSet = processJinniGenres($attr);
			print_r($seriesObj);
		
		
		}


		//TRY imdb
		$showid=$harvest->getHuluID(html_entity_decode($showData['title'],ENT_QUOTES, 'UTF-8'));
		
	/*	$imdbID = $harvest->getIMDBid(html_entity_decode($showData['title'],ENT_QUOTES, 'UTF-8'));
			
		if($imdbID  != "error" && $showid == ""){
			print "..hulu didn't work but got id..".$imdbID."...";	
			$imdbLink              = "http://www.imdb.com/title/" .$imdbID ;
			//could be used, but the api delivers enough info as is
			$seriesObj = $mds->scrapeIMDBmain($imdbLink,$seriesObj,true);
			
			print_r($seriesObj);
			
		}
*/
		//try hulu
		if(count($seriesObj->genreSet) == 0 && $showid != ""){
			print "...... try hulu for ".html_entity_decode($showData['title'],ENT_QUOTES, 'UTF-8') ." ........";
			//$showid=$harvest->getHuluID(html_entity_decode($showData['title'],ENT_QUOTES, 'UTF-8'));
			print "..id..".$showid."..";
			$showinfo = $harvest->ParseFeed('http://www.hulu.com/api/2.0/videos.json?include_seasons=true&order=asc&show_id='.$showid.'&sort=original_premiere_date&video_type=episode&items_per_page=2&position=0&_user_pgid=1&_content_pgid=67&_device_id=1');
	

			$meta = json_decode($showinfo);
		
			$seriesObj->genreSet = processHuluGenres($meta->data[0]->video->show->genre,$meta->data[0]->video->show->genres);
			print_r($seriesObj);
			print "-----------------------------------------------------------";

		}
	
	
	
	if(count($seriesObj->genreSet) > 0)
		insertRelations($seriesObj->genreSet,$showData['myubi_id']);
	else
		print "...no genre info available... /";

}


function insertRelations($genres,$parent){
	global $harvest;
	global $suffix;

	foreach($genres as $info){
		
			$update  = "INSERT map_genre_content".$suffix." (myubi_id, genre_id, value, weight) VALUES ('".$parent."', ".$info[0].", ".$info[1].", 1)";
			$res     = mysql_query($update);
			if (!$res) die("Could not update genre info, mysql error: ".mysql_error());
	}
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

function processJinniGenres($meta){
	global $ubiGenres;
	global $ubiMainGenre;
	global $ubiGenreIDs;
	global $addOns;
	
	
	$_weight    = 1;
	$primarySet = array();
	$subSet     = array();
	foreach($meta[0] as $attr){
		preg_match('/\("\d{1,}","(.*)","(.*)"\)/si',$attr,$data);
		
		if(strtolower($data[2]) == "genres"){
			array_push($primarySet,array(strtolower($data[1]),$_weight));
			$_weight = $_weight-.1;
		}
			
		if(strtolower($data[2]) == "plots" || strtolower($data[2]) == "mood")
			array_push($subSet,strtolower($data[1]));
			
		
	}
	
	$finalGenres = array();
	print_r($primarySet);
		print_r($subSet);
	foreach($primarySet as $genre){
		
		if(array_search($genre,$ubiMainGenre)==false){
			//genre not used in ubi
			if($genre[0] == "thriller")
				 array_push($addOns,array("drama","thriller",.6));
				 
			if($genre[0] == "mockumentary" && $primarySet[0] == "comedy")
				 array_push($addOns,array("comedy","narrative",.8));
				 
			if($genre[0] == "parody")
				 array_push($addOns,array("comedy","spoof parody",.6));
				
			if($genre[0] == "sitcom" && $primarySet[0] == "comedy")
				 array_push($addOns,array("comedy","sitcom",.7));
				 
			if($genre[0] == "romance")
				 array_push($addOns,array("drama","romance",.7));
			 
			print "....***genre not available in ubi***....";
		}else{
			$weight = $genre[1];
			
			foreach($subSet as $subgenre){
				$confirm = 0;
				
				$data = jinniLangCheck($subgenre,$genre[0]);
					
				if(array_search($data[0]."-".$data[1], $ubiGenres))
					$confirm++;
				
				$ele = array_search($data[0]."-".$data[1], $ubiGenres);
				
				if($confirm == 1){
					array_push($finalGenres,array($ubiGenreIDs[$ele],$weight));
					$weight = $weight - .2;
				}else
					$weight = $weight - .1;
				
			}
			
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


function jinniLangCheck($sub,$primary){
	global $addOns;
	global $ubiMainGenre;
	global $ubiSubGenre;
	$names    = array();
	$names[1] = $sub;
	$names[0] = $primary;
	
	if($sub == "suspenseful" && $primary != "action"){
		$names[1] = "thriller"; 
		$names[0] = "drama"; 
	}else if($sub == "suspenseful"){
		$add = array("action","die-hard scenario",.6);
		array_push($addOns,$add);
	}
	
	if($sub == "exciting" && $primary == "action"){
		$names[1] = "adventure"; 
	}
	
	if($sub == "offbeat" && $primary == "comedy"){
		$names[1] = "dark satire"; 
	}
	
	if($primary == "reality" && (stripos($sub,"showbiz",0)>0 || stripos($sub,"fame",0)>0)){
		$names[1] = "celebrity"; 
	}
	
	if($primary == "reality" && (stripos($sub,"competition",0)>0 || stripos($sub,"game",0)>0)){
		$names[1] = "talent"; 
		
		if(stripos($sub,"game",0)>0){
			$add = array("reality","game-show",.6);
			array_push($addOns,$add);
		}
	}
	
	if($primary == "reality" && $sub== 'lifestyle'){
		$names[1] = "situational living"; 
	}
	
	if($primary == "reality" && $sub== 'rivalry'){
		$names[1] = "relationships"; 
	}
	
	if($sub == "stylized" && $primary == "crime")
		$names[1] = "film noir"; 
	
	if(stripos($sub,"ighting",0)>0 && $primary == "sports")
		$names[1] = "extreme"; 
		
	if($sub == "emotional" && $primary == "drama")
		$names[1] = "melodrama"; 
	
	if($sub == "gangsters"){
		$names[1] = "mob story"; 
		
		if($primary == "animation"){
			 $add = array("action","comic-cartoon based",.3);
			 array_push($addOns,$add);
		}
	}
		
	if($sub == "espionage"){
		$names[1] = "die-hard scenario";
		$names[0] = "action";
		
		if($primary == "animation"){
			 $add = array("action","comic-cartoon based",.6);
			 array_push($addOns,$add);
		}
	}
	
	if(($sub == "undercover" || $sub == "law enforcement") && $primary == "crime"){
		$names[1] = "police procedure";
	}
	
	if($sub == "hotshot hero" && $primary == "animation"){
		 $add = array("action","adventure",.4);
		 array_push($addOns,$add);
	}
	
	if(strripos($sub,"dystopia",0) > 0){
		$names[1] = "dystopian";
		$names[0] = "sci-fi";
	}
	
	if(strripos($sub,"dystopia",0) > 0){
		$names[1] = "dystopian";
		$names[0] = "sci-fi";
	}
	
	
	return $names;
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
			
			if($confirm == 1){
				array_push($finalGenres,array($ubiGenreIDs[$ele],$weight));
				$weight = $weight - .2;
			}else
				$weight = $weight - .1;
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
	
	if(strripos($names[1],$names[0],0) == 0){
		$names[1] = trim(str_replace('-','',str_replace($names[0],"",$names[1])));
	}
	
	
	if(strripos($names[1],$names[0],0) > 0){
		$names[1] = trim(str_replace($names[0],"",$names[1]));
	}
	
	if($names[1] == "Medical Drama")
		$names[1] = "Medical";
	
	if($names[1] == "Soap Operas")
		$names[1] = "Melodrama";
	
	if($names[0] == "Documentaries"){
		$names[0] = "documentary";
	}
	
	if($names[0] == "Science Fiction"){
		$names[0] = "sci-fi";
	}
	
	if($names[1] == "Science Fiction" && $names[0] == "Anime"){
		$names[0] = "sci-fi";
		$names[1] = "fantasy";
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
	
	if($names[0] == "Action and Adventure" || $names[1] == "Action and Adventure" ){
		$temp = $names;
		$names[0] = "action";
		if($temp[1] == "Espionage"){
			$names[1] = "Die-Hard Scenario";
		}else if($primary == "Animation and Cartoons")
			$names[1] = "Comic-Cartoon Based";
		else if($names[1] == "Action and Adventure")
			$names[1] ="adventure";

	 }
	 
	 if($names[0] == "Business"){
		 $names[0] = "informational";
		 $names[1] = "business";
		 
		
	 }
	 
     if(trim($names[0]) == "News and Information"){
		 $names[0] = "informational";
	 }
	 
	 if($names[0] == "News and Information" && $primary == "business"){
		 $names[0] = "skip";
	 }
	 
	 if($names[1] == "Sitcoms")
	 	$names[1] = "sitcom";
	 
	 if($names[1] == "Current News")
	 	$names[1]  = "current events";
		
	 if((stripos(strtolower($names[1]),"courtroom",0)>0 || stripos(strtolower($names[1]),"crime",0)==0) && $names[0] == "drama")
	 	$names[1]  = "legal";
	 
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
	 
	 if($names[0] == "Horror & Suspense" || $names[0] == "Horror and Suspense"){
		 $names[0] = "horror";
		 
	 }
	 
	  if($names[0] == "Health and Wellness"){
		 $names[0] = "health and leisure";
		 
	 }
	 
	  if($names[1] == "Science and Technology"){
		  $names[1] = "science";
		  
		  if($names[0] == "informational"){
		    $add = array("informational","technology",.6);
			array_push($addOns,$add);
		  }
	  }
	 
	 if(stripos("skit sketch",$names[1],0) ===false){
	 }else
		 $names[1] = "skit sketch";
	 
	 
	  if($names[0] == "Sports"){
		  
		  if(stripos(strtolower($names[1]),"college",0) === false){
		  }else
			  $names[1] = "amateur";
			  
		  if(stripos(strtolower($names[1]),"cars",0) === false){
		  }else
			  $names[1] = "automotive";
			  
		   if(stripos(strtolower($names[1]),"nfl",0) === false && stripos(strtolower($names[1]),"nba",0) === false && stripos(strtolower($names[1]),"nhl",0) === false ){
		  }else
			  $names[1] = "automotive";
		  
		  if(stripos(strtolower($names[1]),"fighting",0)===false){
		  }else
			$names[1] = "extreme"; 
	  }
	 
	
	return $names;
}

?>
