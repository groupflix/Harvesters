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


$seriesObj = new stdclass;

$seriesObj->title   = "24";
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

//GRAB PAGE WITH ALL EPISODE LINKS
$showpage = file_get_contents('http://www.cucirca.com/2009/06/12/watch-24-online/');

preg_match_all('/<div class=\"one_half\s{1,2}\w{1,}\s{0,1}\w{0,}\">(.*)<\/div>/siU',$showpage,$sections);

$seasonSet = processEpisodeSet(array_reverse($sections[0]),$imdbLink,$seriesObj->title);
//cycle through seasons

$seasoncount =0;
foreach($seasonSet as $epList){
	$seasoncount++;
	foreach($epList as $epObj){
	

		if($epObj->embed != ""){
			
			$epObj->provider  = 'Curcirca';
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

$query = "update harvester_index set last_update='".date('Y-m-d')."' and notes='".$seasoncount ." Seasons and ".$epcount ." Episodes updated' where provider='Cucirca'";
$res   = mysql_query($query);


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
	
	if(property_exists($e,'thumbnail'))
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




function GetEpisodeData($sObj,$epObj){
	global $harvest;
	global $t_suffix;
	
	$eObj = new stdclass;
	$eObj = $epObj;

	$eObj->myubi  		= $harvest->genID();
	$eObj->aspect  		= 9;
	$eObj->fee    		= 0;
	$eObj->rating  		= "TV-14";
	$eObj->quality 		= "HD";
	$eObj->network 		= "FOX";
	$eObj->pid          = 20;
	$eObj->cid          = 0;
	$eObj->language     = 'en';
	$eObj->country      = 'us';
	$eObj->type   		= 1;
	$eObj->caption 		= 1;
	$eObj->myubi  		= $harvest->genID();
	$eObj->expire  		= $harvest->addDate(date('Y-m-d'),2);
	$eObj->userrating   = 4.5;
	$eObj->duration		= ($eObj->duration == "") ? 2600 : $eObj->duration;
	$eObj->urlid 	    = $harvest->buildURLIDepisode($eObj->season,$eObj->epnum,$eObj->fileloc);
	$eObj->provider		= "Cucirca";
	$eObj->mobile       = $eObj->embed;

	if(property_exists($sObj,'showthumb')){
		$eObj->keyart       = $sObj->keyart;
		$eObj->poster       = $sObj->poster;
		$eObj->showthumb    = $sObj->showthumb;
		if($eObj->thumbnail == "error" || $eObj->thumbnail =="")
			$eObj->thumbnail    = str_replace('s/show_thumbnail','t/thumbnail_'.$eObj->myubi.'_',$sObj->showthumb);
	}else{
		$imgObj 		   = getShowImages($sObj->title);
		$eObj->showthumb   = $sObj->showthumb = $harvest->saveImageNew($eObj->img_title,"",$imgObj->showthumb,"show_thumbnail");
		$eObj->keyart	   = $sObj->keyart    = $harvest->saveImageNew($eObj->img_title,"",$imgObj->keyart,"key_art");
		$eObj->poster	   = $sObj->poster    = $harvest->saveImageNew($eObj->img_title,"",$imgObj->poster,"poster");
		if($eObj->thumbnail == "error")
			$eObj->thumbnail   = $imgObj->showthumb;
	}
	
	
	print_r($eObj);
	return $eObj;
}


function processEpisodeSet($sections,$imdb,$title){
	global $mds;
	global $harvest;
	
	$tempSeason = 0;
	$epHold     = array();
	$seasonHold = array();
	

	for($s=0; $s < count($sections); $s++){

		preg_match_all('/<a href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU',$sections[$s],$linkset);
		
		//cycle through episodes
		$seasonNum;
		
		for($e=0; $e < count($linkset[2]); $e++){
			$epObject = new stdclass;
			
			if($e == 0 ){
				$seasonNum = $linkset[3][$e];
				//$epPage    = $linkset[2][$e];
			}else{
				
				preg_match('/Episode\s\d{1,2}\s/si',$linkset[3][$e],$epnum);
				$epObject->season  = str_replace('Season ','',$seasonNum);
				$epObject->title   = $title;
				$epObject->epnum   = trim(str_replace('Episode ','',$epnum[0]));
				$epObject->url     = $linkset[2][$e];
				
				//$prefix      = ($epObject->season >1) ? 'Day '.$epObject->season . ': ' : '';
				$epObject->eptitle = str_replace(' &#8211; ','-',preg_replace('/Episode\s\d{1,2}\s/si','',$linkset[3][$e]));
				print "..getting episode ". $epObject->epnum  ." ...";
				$epsource  = $imdb."/episodes?season=".$epObject->season;
				$epObject  = scrapeIMDBepisode($epsource,$epObject);
				
				$epObject->thumbnail = $harvest->theTVdb($epObject->title,"thumbnail",$epObject->season,$epObject->epnum,$epObject->episodetitle);
		
				if($epObject->description == ""){
					if($epObject->synopsis == ""){
						$epObject->description = "Sorry there is no description available for this show";
						$epObject->synopsis    = "Sorry there is no description available for this show";
						
					}else
						$epObject->description = str_replace('&#x27;',"'",html_entity_decode($epObject->synopsis,ENT_QUOTES, 'UTF-8'));
						
				}else{
					preg_match('/^.{1,180}\b/s', $epObject->description, $match);
					$epObject->synopsis    = ($match[0] == $epObject->description) ? $epObject->description : $match[0] . "...";
				}
				
				//print "..url for stream is " .$linkset[2][$e]."....";
				$epPage = file_get_contents($linkset[2][$e]);
				$epObject->embed  = getEmbed($epPage);
	
				if($epObject->embed == ""){print ".[no embed].";};
				
				if($tempSeason != $epObject->season){
					if($tempSeason != 0 )
						array_push($seasonHold,$epHold);
						
					$tempSeason = $epObject->season;
					$epHold     = array();
					array_push($epHold,$epObject);
				}else
					array_push($epHold,$epObject);
				
				$epObject = "";
			}
			
		}
		
		if(count($epHold) > 0)
			array_push($seasonHold,$epHold);
			
	

	}
	//print_r($seasonHold);
	return $seasonHold;
}

function getEmbed($page){
	preg_match_all('/<iframe\s[^>]*src=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/iframe>/siU',$page,$embeds);
	
	$embedReady = false;
	$streamlink = "";
	$sources    = array('veevr.com','allmyvideos.net','putlocker.com','sockshare.com');
	for($j =0; $j < count($sources); $j++){
		
		for($i=0; $i < count($embeds[2]); $i++){
			
			if(stripos($embeds[2][$i],$sources[$j],0) > 0){
				$embedReady 	 = true;
				$streamlink      = "iframe,".str_replace("'","",$embeds[2][$i]);
				break;
			}
		}
		
		if($embedReady)
			break;
	}

	return $streamlink;
}

function GetShowData($temp){
	//SHOW INFORMATION
	global $harvest;

	$sObject   = new stdclass;
	$sObject   = $temp;

	$sObject->rating  	= "TV-14";
	$sObject->quality 	= "HD";
	$sObject->network 	= "FOX";
	$sObject->type   	= 1;
	$sObject->myubi  	= $harvest->genID();
    $sObject->provider	= "Curcirca";
    $sObject->country 	= "US";
	$sObject->caption 	= 0;
    $sObject->userrating= 5;
	$sObject->pubDate   = "2001-03-09";
	$sObject->premier   = "2010-09-01";
	$sObject->timeslot  = "1260";
	$sObject->airing    = "Sunday at 9:00 pm";
	
	$imgObj 			= getShowImages($sObject->title);
/*	$sObject->keyart    = $imgObj->keyart;
	$sObject->poster    = $imgObj->poster;
	$sObject->showthumb = $imgObj->showthumb;*/
	$sObject->showthumb	= $harvest->saveImageNew($sObject->imgtitle,"",$imgObj->showthumb,"show_thumbnail");
	$sObject->keyart	= $harvest->saveImageNew($sObject->imgtitle,"",$imgObj->keyart,"key_art");
	$sObject->poster	= $harvest->saveImageNew($sObject->imgtitle,"",$imgObj->poster,"poster");

    saveEpisodeRef($sObject);

	return $sObject;
	
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



function scrapeIMDBepisode($link,$epData){
	global $mds;
	
			$epObj  = $epData;
			
			$scrape  = new Scrape();
			$scrape->fetch($link);
			
			//PULL EPISODE BLOCK
			$data   = $scrape->removeNewlines($scrape->result);
			$start  = '<div class=\"list_item(\"??)([^\" >]*?)\\1[^>]*>(.*)';
			$end    = '<\/div>';
			
			$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
			$epimdbLink;
			$epimddID;
			
			for($t = 0; $t < count($imdbdata); $t++){
				$len = strlen(trim((string)$epObj->epnum));
				//print "num is ". $epObj->epnum ." length " . $len;
				preg_match('/S\d{1,5}\,\s{0,1}Ep(\d{'.$len.',})/siU',$imdbdata[$t],$epnum);
				//print_r($epnum);
				/*$start = strripos($imdbdata[$t],", Ep".$epObj->epnum,0);
				$end   = strripos($imdbdata[$t],"</div>",$start+4);
				$str   = substr($imdbdata[$t],$start+4,$end-$start-4);
				print "try ".$str."...";
				exit('stop');*/
				if((int)$epnum[1] == (int)$epObj->epnum){
					
					preg_match('/<img\s[^>]*\"??([^\" >]*?)\\1[^>]*>/si',$imdbdata[$t],$epnames);
					preg_match('/alt="(.*)"/si',$epnames[0],$eptitle);
					
					$end   = strripos($eptitle[1],'" src=',0);
					$str   = trim(substr($eptitle[1],0,$end));
					$epObj->episodetitle = $str;
					
					if(preg_match_all("/<div\s[^>]*data-const=(\"??)([^\" >]*?)\\1[^>]*>/siU", $imdbdata[$t], $matches)) {
						$epimdbLink = "http://www.imdb.com/title/". $matches[2][0];
						$epimddID   = $matches[2][0];
					}
					break;
				}
				
			}
			
			if($epimddID != ""){
				$epObj  = $mds->scrapeIMDBmain("http://www.imdb.com/title/".$epimddID."/",$epObj,false);
				return $epObj;
			}else{
				return "error";
			}
			
}
?>