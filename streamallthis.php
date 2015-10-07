<?php


ini_set("memory_limit","64M");

error_reporting(E_ERROR);
include 'Scrape.php';
include 'com/HarvestMethods.php';
include '../db/DBconfig.php'; 
include '../utils/DateConverter.php';
include '../IO/FileType.php';
include 'com/MetaDataSources.php';

$dbconfig  = new DBconfig();
$harvest   = new HarvestMethods();
$scrape    = new Scrape();
$dateCv    = new DateConverter();	
$mds       = new MetaDataSources();	
//SET DEBUG VARIABLES=======================================

$tmp = false;
$t_suffix;

if($tmp){
	$harvest->setTableSuffix('_tmp');
	$t_suffix = "_tmp";
}else
	$t_suffix ="";
	
//=====================================================
$host 	   = $dbconfig->getHOST();
$username  = $dbconfig->getUSERNAME();
$password  = $dbconfig->getPASSWORD();
$db        = $dbconfig->getDATABASE();
//$suffix    = $dbconfig->getDBsuffix();


$res = mysql_connect($host, $username, $password);
if (!$res) die("Could not connect to the server, mysql error: ".mysql_error());
$res = mysql_select_db($db);
if (!$res) die("Could not connect to the database, mysql error: ".mysql_error());

$epcount     = 0;
$seasoncount = 0;


//ONLY USED ONCE TO CREATE REFERENCE
$seriesObj = new stdclass;

$seriesObj->title   = "Futurama";
$seriesObj->imgtitle= (string)$harvest->concatTitle($seriesObj->title);
$seriesObj->fileloc = $harvest->getFileloc($seriesObj->imgtitle,1);

$entry_exists       = $harvest->dupReferenceCheck($seriesObj->fileloc,"episode");

$imdbID     = $harvest->getIMDBid($seriesObj->title);

$imdbLink   = "http://www.imdb.com/title/" .$imdbID ;
$seriesObj  = $mds->scrapeIMDBmain($imdbLink,$seriesObj,true);

if($entry_exists == false){
	print "....save the reference......";
	$seriesObj = GetShowData($seriesObj);
	$showcount++;

}


//=============== Get Season List =================
$fullep_page = file_get_contents("http://streamallthis.com/watch/futurama/");

preg_match_all('/<tr class="\w{2,}">(.*)<\/tr>/siU',$fullep_page,$fullList);//<td><a href=\"(.*)\" class="\w{2,}">(.*)<\/a><\/td>


$seasonSet = processEpisodeSet(array_reverse($fullList[1]),$imdbLink,$seriesObj->title);
//$ep set contains all seasons , each season has an array of objects with all basic episode info.  Thereafter you just need to get the embed from the streamLink and appropriate images
//duration is always 22mins, rating is always TV-14

foreach($seasonSet as $epList){
	$seasoncount++;
	foreach($epList as $epObj){
		$eppage = file_get_contents($epObj->streamlink);
		
		preg_match("/<td align=\"left\">.*<iframe src=\"(.*)\" width=\"600\".*>.*<\/iframe>.*<\/td>/siU",$eppage,$iframe);

		$epObj->embed = $iframe[1];

		if($epObj->embed != ""){
			
			$epObj->provider  = 'StreamAllThis';
			$epObj->img_title = (string)$harvest->concatTitle($epObj->title);
			$epObj->fileloc   = $harvest->getFileloc($epObj->img_title,1);
			
			$urlid 		  = $harvest->buildURLIDepisode($epObj->season,$epObj->epnum,$epObj->fileloc);
			$entry_exists = $harvest->dupContentCheck($urlid,"episode");
			
			if($entry_exists->result == false){
				print "....save this episode..... ";
				$e = GetEpisodeData($seriesObj,$epObj);
				insertEpisode($e);
				$epcount++;
			}else{
				print "....this ep has already saved, check for stream .....";
				
				$dupCheck = $harvest->dupStreamCheck($entry_exists->myubi,$epObj->provider,"episode");
				
				if($dupCheck == false){
					print "....[sp stream doesn't exist]...";
					$e = GetEpisodeData($seriesObj,$epObj);
					$e->myubi = $entry_exists->myubi;
					insertEpisodeStream($e);
				}else
					print "..we already have this stream too!.";
			
				
			}

		}else{
			print "...no embed available, skip...";
		}

						
		
	}
	

	
}
	$query = "update harvester_index set last_update='".date('Y-m-d')."' and notes='".$seasoncount ." Seasons and ".$epcount ." Episodes updated' where provider='StreamAllThis'";
	$res   = mysql_query($query);




function GetEpisodeData($sObj,$epObj){
	global $harvest;
	global $t_suffix;
	
	$eObj = new stdclass;
	$eObj = $epObj;

	$eObj->myubi  		= $harvest->genID();
	$eObj->url 		    = "http://myubi.tv/v3/?id=".$eObj->myubi;
	$eObj->aspect  		= 9;
	$eObj->fee    		= 0;
	$eObj->rating  		= "TV-14";
	$eObj->quality 		= "SD";
	$eObj->network 		= "Comedy Central";
	$eObj->pid          = 19;
	$eObj->cid          = 0;
	$eObj->language     = 'en';
	$eObj->country      = 'us';
	$eObj->type   		= 1;
	$eObj->caption 		= 1;
	$eObj->myubi  		= $harvest->genID();
	$eObj->expire  		= $harvest->addDate(date('Y-m-d'),1);
	$eObj->userrating   = 4;
	$eObj->duration		= ($eObj->duration == "") ? 1380 : $eObj->duration;
	$eObj->urlid 	    = $harvest->buildURLIDepisode($eObj->season,$eObj->epnum,$eObj->fileloc);
	$eObj->provider		= "StreamAllThis";
	$eObj->mobile       = $eObj->embed;
	
	if(property_exists($sObj,'showthumb')){
		$eObj->keyart       = $sObj->keyart;
		$eObj->poster       = $sObj->poster;
		$eObj->showthumb    = $sObj->showthumb;
		$eObj->thumb        = str_replace('s/show_thumbnail','t/thumbnail_'.$eObj->myubi.'_',$sObj->showthumb);
	}else{
		$imgObj 		   = getShowImages($sObj->title);
		$eObj->showthumb   = $sObj->showthumb = $harvest->saveImageNew($eObj->img_title,"",$imgObj->showthumb,"show_thumbnail");
		$eObj->keyart	   = $sObj->keyart    = $harvest->saveImageNew($eObj->img_title,"",$imgObj->keyart,"key_art");
		$eObj->poster	   = $sObj->poster    = $harvest->saveImageNew($eObj->img_title,"",$imgObj->poster,"poster");
		$eObj->thumbnail   = $imgObj->showthumb;
	}
	
	
	print_r($eObj);
	return $eObj;
}







function saveEpisodeRef($e){
	global $t_suffix;
	//global $logger;
	
	$query = "insert into episode_reference". $t_suffix . " (myubi_id,title, description, synopsis, network, url, keywords, genre, keyart, poster, showthumb, userrating, cast, rating, fileloc,type,year,timeslot,airing,premier) VALUES ('".$e->myubi."','".mysql_escape_string(htmlentities($e->title,ENT_QUOTES, 'UTF-8'))."','".mysql_escape_string($e->description)."','".mysql_escape_string($e->synopsis)."','".$e->network."','".$e->url."','".mysql_escape_string(htmlentities($e->keywords,ENT_QUOTES, 'UTF-8'))."','".mysql_escape_string($e->genre)."','".$e->keyart."','".$e->poster."','".$e->showthumb."','".$e->userrating."','".$e->actors."','".$e->rating."','".$e->fileloc."','".$e->type."','".$e->year."','".$e->timeslot."','".$e->airing."','".$e->premier."')";
		// $logger->debug("mtv_scrape_prod.php:saveEpisodeRef -  episode_reference insert query: " . $query);
		if (mysql_query($query)) {
			
			print "[......".$e->title." SHOW REF INSERTED........]";
			//$logger->debug("mtv_scrape_prod.php:saveEpisodeRef -  episode_reference INSERTED: ");
		
		} else {
			//$logger->error("mtv_scrape_prod.php:saveEpisodeRef -  episode_reference insert: " . mysql_error());
	
		}
			
}

function insertEpisode($e){
global $t_suffix;
global $harvest;
	
	if(property_exists($sObj,'thumbnail'))
		$e->thumb   = $harvest->saveImageNew($e->img_title,"",$e->thumbnail,"thumbnail",$e->myubi);

 $query = "insert into episode_content". $t_suffix . "(MYUBI_ID,TITLE,EPISODE,SEASON,TYPE,DESCRIPTION,SYNOPSIS,NETWORK,KEYWORDS,COUNTRY,YEAR,URL,SHOWTHUMB,THUMB,POSTER,KEYART,DURATION,GENRE,EPISODETITLE,URLID,PUBDATE,RATING,USERRATING,FILELOC) VALUES ('".$e->myubi."','".mysql_escape_string(htmlentities($e->title,ENT_QUOTES, 'UTF-8'))."','".$e->epnum."','".$e->season."',".$e->type.",'".mysql_escape_string(htmlentities($e->description,ENT_QUOTES, 'UTF-8'))."','".mysql_escape_string($e->synopsis)."','".$e->network."','".mysql_escape_string(htmlentities($e->keywords))."','US','".$e->year."','".$e->url."','".$e->showthumb."','".$e->thumb."','".$e->poster."','".$e->keyart."','".$e->duration."','".mysql_escape_string($e->genre)."','".mysql_escape_string($e->episodetitle)."','".$e->urlid."','".$e->pubDate."','".$e->rating."','".$e->userrating."','".$e->fileloc."')";

				if (mysql_query($query)) {
					print_r($e);
					print "**==========Content for ". $e->title." INSERTED=================**";
					insertEpisodeStream($e);
				} else
					echo "[*** Content insert error : ".mysql_error()." ***]";
	

}

function insertEpisodeStream($e){
		global $t_suffix;
		//global $logger;
	
		 $query = "INSERT INTO episode_streams". $t_suffix . " (myubi_id, url_hi, url_lo, provider, aspect, quality, expire, pid, cid, segment, fee,captions,language) VALUES ('".$e->myubi."', '".$e->embed."', '', '".$e->provider."', 9, '".$e->quality."', '".$e->expire."', ".$e->pid.", ".$e->cid.", '0', '0','".$e->caption."','".$e->language."')";
		
		if (mysql_query($query)) {
		 print "**============EPISODE STREAM" . $e->epnum ." INSERTED=================**";
			   //$logger->error("mtv_scrape_prod.php Mysql Query Error episode_streams" . mysql_error());
			
		} else 
			echo "[ save episodeStream:InsertError: -> Something went wrong: ".mysql_error() . "]";
			//$logger->error("mtv_scrape_prod.php Mysql Query Error episode_streams" . mysql_error());
}




function GetShowData($temp){
	//SHOW INFORMATION
	global $harvest;

	$sObject   = new stdclass;
	$sObject   = $temp;

	$sObject->rating  	= "TV-14";
	$sObject->quality 	= "SD";
	$sObject->network 	= "Comedy Central";
	$sObject->type   	= 1;
	$sObject->myubi  	= $harvest->genID();
    $sObject->provider	= "StreamAllThis";
    $sObject->country 	= "US";
	$sObject->caption 	= 1;
    $sObject->userrating= 4;
	$sObject->pubDate   = "1999-09-10";
	$sObject->premier   = "1999-08-01";
	$sObject->timeslot  = "1260";
	$sObject->airing    = "Sunday at 9:00 pm";
	
	$imgObj 			= getShowImages($sObject->title);
	/*$eObj->keyart       = $imgObj->keyart;
	$eObj->poster       = $imgObj->poster;
	$eObj->showthumb    = $imgObj->showthumb;*/
	$sObject->showthumb	= $harvest->saveImageNew($sObject->imgtitle,"",$imgObj->showthumb,"show_thumbnail");
	$sObject->keyart	= $harvest->saveImageNew($sObject->imgtitle,"",$imgObj->keyart,"key_art");
	$sObject->poster	= $harvest->saveImageNew($sObject->imgtitle,"",$imgObj->poster,"poster");
	
print_r($sObject);
   saveEpisodeRef($sObject);


	
	return $sObject;
	
}

function getPubdate($pub){
	$pudData  = explode(":",$pub[1]);
	$pubparts = explode('.',trim($pudData[1]));
	$pubdate  = $pubparts[2] ."-". $pubparts[0] ."-". $pubparts[1];
	return $pubdate;
}



function file_get_contents_curl($url) 
{ 
    $ch = curl_init(); 
 
    curl_setopt($ch, CURLOPT_HEADER, 0); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    curl_setopt($ch, CURLOPT_URL, $url); 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); 
 
    $data = curl_exec($ch); 
    curl_close($ch); 
 
    return $data; 
} 

function processEpisodeSet($allvids,$imdb,$title){
	global $mds;
	
	$tempSeason = 0;
	$epHold     = array();
	$seasonHold = array();
	
	foreach($allvids as $ep){
		$epObj = new stdclass;

		preg_match("/<a href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU",$ep,$eplink);
		preg_match("/(s\d{1,}e.*)\./siU",$eplink[2],$epinfo);
	
		$core = substr($epinfo[1],1);
		$sEp  = explode('e',$core);
		
		$epObj->title      = $title;
		$epObj->epnum      = (int)$sEp[1];
		$epObj->season 	   = (int)$sEp[0];
		$epObj->streamlink = $eplink[2];
		
		$epsource  = $imdb."/episodes";
		$epObj     = $mds->scrapeIMDBepisode($epsource,$epObj);

		if($epObj->description == ""){
			if($epObj->synopsis == ""){
				$epObj->description = "Sorry there is no description available for this show";
				$epObj->synopsis    = "Sorry there is no description available for this show";
				
			}else
				$epObj->description = str_replace('&#x27;',"'",html_entity_decode($epObj->synopsis,ENT_QUOTES, 'UTF-8'));
				
		}else
			$epObj->synopsis    = $epObj->description;
		 
		print_r($epObj);
		if($tempSeason != $epObj->season){
			if($tempSeason != 0 )
				array_push($seasonHold,$epHold);
				
			$tempSeason = $epObj->season;
			$epHold     = array();
			array_push($epHold,$epObj);
		}else
			array_push($epHold,$epObj);
	
	}
			
		if(count($epHold) > 0)
			array_push($seasonHold,$epHold);
	
	return $seasonHold;
}
		
function getShowImages($title){
	global $harvest;
	$imgObj = new stdclass;
	print "...getting images for " . $title ." ...";
	$showthumb 		= $harvest->theTVdb($title,"showthumb");
	$poster			= $harvest->theTVdb($title,'poster');
	$keyart			= $harvest->theTVdb($title,"keyart");
	
	$imgassets = googleImageCheck($title);
	

	if($keyart == "error")
		$imgObj->keyart     = $imgassets[0];
	else
		$imgObj->keyart     = $keyart;
	
	
	if($showthumb == "error")
		$imgObj->showthumb  = $imgassets[1];
	else
		$imgObj->showthumb  = $showthumb;
		
	
	if($poster == "error")
		$imgObj->poster     = $imgassets[3];
	else
		$imgObj->poster     = $poster;
	
	return $imgObj;
}

function googleImageCheck($title){
	$query= $title . " tv series";
	$query = rawurlencode($query);
		
	//could implement  imgsz=xxlarge restricts results to large images    

	// now, process the JSON string
	$results = json_decode(search($query,0)); 
	$search  = 0;
	$keyart_src    = "";
	$showthumb_src = "";
	$thumbnail_src = "";
	$poster_src    = "";
	$keepsearching = true;
	
	while($keepsearching === true){
		foreach($results->responseData->results as $img){
	
			$ratio  = $img->{'height'} / $img->{'width'};  //official key art ratio is .39
			//print "..[image ratio is ".$ratio." ]..";
			if($ratio > .37 && $ratio < .505 && $keyart_src =="")
				$keyart_src = $img->{'url'};
				
			if($ratio > .44 && $ratio < .61 && $showthumb_src =="")
				$showthumb_src = $img->{'url'};
			
			if($ratio > .44 && $ratio < .61 && $thumbnail_src =="")
				$thumbnail_src = $img->{'url'};
				
			if($ratio > 1.37 && $ratio < 1.47 && $poster_src =="")
				$poster_src = $img->{'url'};
		}

		if(($showthumb_src != "" && $keyart_src !="" && $thumbnail_src!="" && $poster_src!="") || $search == 6){
			$keepsearching = false;
		}else{
			$search++;
			$results = json_decode(search($query,8));
		}
	}
	
	$imgset = array($keyart_src,$showthumb_src,$thumbnail_src,$poster_src);

	return $imgset;
	
}

function search($q,$s){
		$google = "https://ajax.googleapis.com/ajax/services/search/images?v=1.0&q=".$q."&rsz=7&start=".$s."&key=ABQIAAAAt4pgIc58Uhow9LYHI2PQnxTNf4uIZ55bLVUnRqbVOhPznVTqGBTBPkMo6PRiTZoM_ME1gslO8EendA&userip=";

		// sendRequest
		// note how referer is set manually
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $google);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_REFERER, "http://myubi.tv/");
		$body = curl_exec($ch);
		
		if(curl_exec($ch) === false)
			print '<p>Curl error: ' . curl_error($ch);
		else
			return $body;
}

function processGenre($gList){
   $genreSet =	split(',',$gList);
   $finalSet =  ArrayToString(array_unique($genreSet));
   
   if(stripos($finalSet,", ",0) == 0)
   		$finalSet = trim(substr($finalSet,2));
   
   return $finalSet;
}

			




?>