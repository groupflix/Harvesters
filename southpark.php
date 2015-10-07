<?php


ini_set("memory_limit","64M");

error_reporting(E_ERROR);
include 'Scrape.php';
include 'com/HarvestMethods.php';
include '../db/DBconfig.php'; 
include '../utils/DateConverter.php';
include '../IO/FileType.php';

$dbconfig = new DBconfig();
$harvest   = new HarvestMethods();
$scrape    = new Scrape();
$dateCv    = new DateConverter();		
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
$showcount   = 0;


//ONLY USED ONCE TO CREATE REFERENCE
$seriesObj = new stdclass;

$seriesObj->title   = "South Park";
$seriesObj->imgtitle= (string)$harvest->concatTitle($seriesObj->title);
$seriesObj->fileloc = $harvest->getFileloc($seriesObj->imgtitle,1);

$entry_exists       = $harvest->dupReferenceCheck($seriesObj->fileloc,"episode");

if($entry_exists == false){
	print "....save the reference......";
	$seriesObj = GetShowData($seriesObj);
	$showcount++;

}


//=============== Get Season List =================
$fullep_page = file_get_contents("http://www.southparkstudios.com/full-episodes");

preg_match_all('/<a class="seasonbtn season_\d{1,2}[\sa-zA-Z]{0,7}" href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU',$fullep_page,$seasonList);


foreach($seasonList[2] as $seasonLink){
	$seasonPage = file_get_contents($seasonLink);
	
	$start = stripos($seasonPage,'<div class="content_carouselwrap">',1000);
	$end   = stripos($seasonPage,'<div class="content_seasonload">',$start);
	$epdata= substr($seasonPage,($start + 20),$end - ($start + 20));
	
	preg_match_all('/<li>(.*)<div class="content_epmoreinfo">(.*)<\/div>.*<\/li>/siU',$epdata,$epList);
	
	foreach($epList[2] as $epitem){
		$epObject = new stdclass;
		
		
		preg_match("/<a href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU",$epitem,$eplink);
		$urlsplit = explode('/',$eplink[2]);
		$epinfo   = explode('-',$urlsplit[count($urlsplit)-1]);
		$sesplit  = explode('e',$epinfo[0]);  //print 'episode .. '. (int)$sesplit[1];   print 'season .. '.substr($sesplit[0],1);
		if($eplink[2] != ""){
			$epObject->title   = "South Park";
			$epObject->imgtitle= (string)$harvest->concatTitle($epObject->title);
			$epObject->fileloc = $harvest->getFileloc($epObject->imgtitle,1);
			
			$urlid 		  = $harvest->buildURLIDepisode((int)substr($sesplit[0],1),(int)$sesplit[1],$epObject->fileloc);
			$entry_exists = $harvest->dupContentCheck($urlid,"episode");
			
			if($entry_exists->result == false){
				print "....save this episode..... ";
				$e = GetEpisodeData($epitem,$epObject);
				insertEpisode($e);
				$epcount++;
			}else{
				print "....this ep has already saved, check for stream .....";
				
				$dupCheck = $harvest->dupStreamCheck($entry_exists->myubi,"South Park Studios","episode");
				
				if($dupCheck == false){
					print "....[sp stream doesn't exist]...";
					$e = GetEpisodeData($epitem,$epObject);
					$e->myubi = $entry_exists->myubi;
					insertEpisodeStream($e);
				}else
					print "we already have this stream too! ...";
			
				
			}

		}else{
			print "...no embed available, skip...";
		}

		
	}
	
	

	
}
	$query = "update harvester_index set last_update='".date('Y-m-d')."' where provider='SouthPark'";
	$res   = mysql_query($query);




function GetEpisodeData($feed,$epObj){
	global $harvest;
	global $t_suffix;
	
	$eObj = new stdclass;
	$eObj = $epObj;
	
	preg_match("/<a href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU",$feed,$eplink);
	preg_match("/<h5>(.*)<\/h5>/siU",$feed,$eptitle);
	preg_match("/<h6>(.*)<\/h6>/siU",$feed,$pub);
	preg_match("/<p>(.*)<\/p>/siU",$feed,$desc);

	$eObj->myubi  		= $harvest->genID();
	$eObj->url 		    = $eplink[2];
	$eObj->description  = htmlentities($desc[1],ENT_QUOTES,'UTF-8');
	$eObj->pubDate		= getPubdate($pub);
	$eObj->episodetitle	= htmlentities($eptitle[1],ENT_QUOTES,'UTF-8');
	$year = explode('-',$eObj->pubDate);
	
	//grab embed, thumb,synopsis,episode,season
	$eObj = epPageScrape($eObj);
	
	if($eObj->synopsis == "")
		$eObj->synopsis = (substr($eObj->description,0,200) ==$eObj->description) ? $eObj->description : substr($eObj->description,0,200) . "...";
	
	$eObj->aspect  		= 9;
	$eObj->fee    		= 0;
	$eObj->rating  		= "TV-MA";
	$eObj->quality 		= "HD";
	$eObj->network 		= "Comedy Central";
	$eObj->pid          = 17;
	$eObj->year         = $year[0];
	$eObj->cid          = 0;
	$eObj->language     = 'en';
	$eObj->country      = 'us';
	$eObj->type   		= 1;
	$eObj->caption 		= 1;
	$eObj->myubi  		= $harvest->genID();
	$eObj->keywords		= "cartman, kyle marash, hanky";
	$eObj->expire  		= $harvest->addDate(date('Y-m-d'),1);
	$eObj->userrating   = 4;
	$eObj->duration		= 1380;
	$eObj->urlid 	    = $harvest->buildURLIDepisode($eObj->season,$eObj->epnum,$eObj->fileloc);
	$eObj->provider		= "South Park Studios";
	$eObj->title  		= "South Park";
	$eObj->genre  		= "Comedy, Animation, Late Night";
	
	$eObj->showthumb    = "content/images".$t_suffix."/s/show_thumbnail_south_park.jpg";
	$eObj->keyart       = "content/images".$t_suffix."/k/key_art_south_park.jpg";
	$eObj->poster       = "content/images".$t_suffix."/p/poster_south_park.jpg";
    $eObj->mobile       = $eObj->embed;
	
	$eObj->thumb     	= $harvest->saveImageNew($epObject->img_title,"",$eObj->thumb,"thumbnail",$eObj->myubi);
	//print_r($eObj);
	//exit('stop');
	return $eObj;
}







function saveEpisodeRef($e){
	global $t_suffix;
	//global $logger;
	$query = "insert into episode_reference". $t_suffix . " (myubi_id,title, description, synopsis, network, url, keywords, genre, keyart, poster, showthumb, userrating, cast, rating, fileloc,type,year,timeslot,airing,premier) VALUES ('".$e->myubi."','".mysql_escape_string(htmlentities($e->title,ENT_QUOTES, 'UTF-8'))."','".mysql_escape_string($e->description)."','".mysql_escape_string($e->synopsis)."','".$e->network."','".$e->url."','".mysql_escape_string(htmlentities($e->keywords))."','".mysql_escape_string($e->genre)."','".$e->keyart."','".$e->poster."','".$e->showthumb."','".$e->userrating."','".$e->actors."','".$e->rating."','".$e->fileloc."','".$e->type."','".$e->year."','".$e->timeslot."','".$e->airing."','".$e->premier."')";
		 $logger->debug("mtv_scrape_prod.php:saveEpisodeRef -  episode_reference insert query: " . $query);
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

	
  

	$sObject->rating  	= "TV-MA";
	$sObject->quality 	= "HD";
	$sObject->network 	= "Comedy Central";
	$sObject->country   = 'us';
	$sObject->type   	= 1;
	$sObject->myubi  	= $harvest->genID();
    $sObject->keywords	= "cartman, kyle Brofloski, stan marsh,kenny, hanky";
	$sObject->genre     = "Comedy, Animation, Late Night";
    $sObject->quality 	= "HD";
	$sObject->actors 	=
    $sObject->provider	= "South Park Studios";
	$sObject->synopsis	= "Follows the misadventures of four irreverent grade-schoolers in the quiet, dysfunctional town of South Park, Colorado.";
 	$sObject->description = "In South Park, Colorado, we follow the adventures of four foul-mouthed fourth-graders: Eric Cartman, the rude, obnoxious, sadistic, racist, fat kid; Stan Marsh, a quiet kid with a huge crush on Wendy Testaberger (he usually vomits whenever he talks to her or when something gross occurs); Kyle Brofloski, the religious Jewish kid who Cartman picks on for being Jewish; and Kenny, the orange parka-hooded kid who dies every episode. Of course, most of the time, something weird usually happens including aliens planting a chip in Cartman's anus and Michael Jackson moves into the neighborhood. Other wild adventures include: the boys blaming their parents for child molestation and Cartman feeding Scott Tenorman his parents in chili. A movie was given in the early seasons in 1999. And the boys enjoy their Terrence & Philip, a Canadian comedy duo (which the parents were against in the movie";
    $sObject->country 	= "US";
	$sObject->caption 	= 1;
    $sObject->userrating= 4;
	$sObject->pubDate   = "2009-09-10";
	$sObject->premier   = "1997-08-01";
	$sObject->timeslot  = "1320";
	$sObject->airing    = "Wednesdays at 10:00 pm";
	
	$sObject->showthumb	= $harvest->saveImageNew($sObject->imgtitle,"",'http://thetvdb.com/banners/_cache/fanart/original/75897-17.jpg',"show_thumbnail");
	$sObject->keyart	= $harvest->saveImageNew($sObject->imgtitle,"",'http://collider.com/wp-content/uploads/south-park-01.jpg',"key_art");
	$sObject->poster	= $harvest->saveImageNew($sObject->imgtitle,"",'http://thetvdb.com/banners/_cache/posters/75897-5.jpg',"poster");
	

    saveShow($sObject);

exit('stop');
	
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
		
function epPageScrape($obj){
	$ep_page = file_get_contents($obj->url);
	
	preg_match('/swfobject\.embedSWF\(\"(.*)\",.*\)/siU',$ep_page,$swf);
	$obj->embed = "mtv,".$swf[1];
	
	$html		 = file_get_contents_curl($obj->url); 
	$doc		 = new DOMDocument(); 
	@$doc->loadHTML($html); 
	
	$metas 	     = $doc->getElementsByTagName('meta'); 

	for ($i = 0; $i < $metas->length; $i++){ 
		$meta = $metas->item($i); 
		
		switch($meta->getAttribute('property')){
			case('og:image') :
				$obj->thumb 		 = $meta->getAttribute('content')."?width=240"; 
			break;
			case('og:description') :
			print "synopsis  ";
				$obj->synopsis     = $meta->getAttribute('content'); 
			break;
			case('og:title') :
			
				preg_match('/\([^)]+\)/si',$meta->getAttribute('content'),$sep);
				$data = explode(",",$sep[0]);
				preg_match('/\d{1,}/s',$data[0],$season);
				preg_match('/\d{1,}/s',$data[1],$ep);
				$obj->season  =  $season[0];
				$obj->epnum   =  $ep[0];
			break;
		}
	
	} 
	return $obj;
}
			




?>