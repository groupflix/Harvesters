<?php
ini_set("memory_limit","64M");
include '../db/DBconfig.php'; 
include '../IO/FileType.php';
include 'com/HarvestMethods.php';
include 'com/MetaDataSources.php';
include 'Scrape.php';
// Report simple running errors
error_reporting(E_ERROR);

$dbconfig = new DBconfig();
$harvest  = new HarvestMethods();
$mds      = new MetaDataSources();

//SET DEBUG VARIABLES=======================================

$tmp = true;
$t_suffix;

if($tmp){
	$harvest->setTableSuffix('_tmp');
	$t_suffix = "_tmp";
}else
	$t_suffix ="";
//===================Database Connection=====================
$host = $dbconfig->getHOST();
$username = $dbconfig->getUSERNAME();
$password = $dbconfig->getPASSWORD();
$db = $dbconfig->getDATABASE();

$harvest->setHOST($host);
$harvest->setPASSWORD($password);
$harvest->setDBUSER($username);
$harvest->setDATABASE($db);

require_once('/opt/myubi/Log4php/Logger.php');
Logger::configure('/opt/myubi/Log4php/resources/appender_cartoon.properties');
$logger = Logger::getRootLogger();
global $logger;
$logger->info("PHP Harvester : cartoon.php Starting ");

$res = mysql_connect($host, $username, $password);
//if (!$res) die("Could not connect to the server, mysql error: ".mysql_error($res));
        if($res->error) {
                $logger->error("PHP Harvester : cartoon.php Message Mysql Query Error" . mysql_error());
        }
$res = mysql_select_db($db);
//if (!$res) die("Could not connect to the database, mysql error: ".mysql_error($res));
        if($res->error) {
                $logger->error("PHP Harvester : cartoon.php Message Mysql Query Error" . mysql_error());
        }

//===================Set general variables initially==========================================
$provider = "CartoonNetwork";
$network  = "Cartoon Network";
$logger->info("PHP Harvester : cartoon.php " . $network);

//===================Pull all show identifiers available on cartoon network ===================
//=============================================================================================

$showfeed ="http://adultswim.com/adultswimdynamic/asfix-svc/episodeSearch/getAllEpisodes?limit=&offset=0&categoryName=&filterByEpisodeType=EPI&filterByCollectionId=&networkName=AS&r=1202760270203&sortByDate=DESC";

$show_data = file_get_contents($showfeed);
$show_xml  = simplexml_load_string($show_data);

$showlist  = array();
$checkList = array();
$showcount = 0;	
foreach($show_xml->episode as $show){
	
	$seriesObj = new stdclass;
	
	
	$seriesObj->title    = $show->attributes()->collectionTitle;
	$seriesObj->img_title= $harvest->concatTitle($seriesObj->title);
	$seriesObj->title    = htmlentities($seriesObj->title,ENT_QUOTES, 'UTF-8');	
	$seriesObj->urltitle = trim(str_replace("_","-",$seriesObj->img_title));	
	$seriesObj->fileloc  = $harvest->getFileloc($seriesObj->img_title,1);
	$seriesObj->myubi    = $harvest->genID();
	$seriesObj->genre    = (string) $show->attributes()->collectionCategoryType;
	$seriesObj->type     = 1;
	$seriesObj->country  = "us";
	$seriesObj->network  = "Cartoon Network";
	$seriesObj->urating  = 0;
	
	$_rating  			 = $show->attributes()->rating;
	$ratingf  			 = explode(" ",$_rating);
	$seriesObj->rating   = $ratingf[0];
	
	
	$img_title_url 		= str_replace("_","-",$seriesObj->img_title);
	$backups      		= new stdclass;
	$backups->showthumb = "http://i.cdn.turner.com/adultswim/shows/".$img_title_url."/img/show-image.jpg";
	$backups->keyart    = "http://i.cdn.turner.com/adultswim/shows/".$img_title_url."/img/show-image.jpg";
	
	$imdbID = $harvest->getIMDBid(html_entity_decode($seriesObj->title,ENT_QUOTES, 'UTF-8'));
	if($imdbID  != "error"){
		
		$imdbLink        = "http://www.imdb.com/title/" .$imdbID ;

		$seriesObj  	 = $mds->scrapeIMDBmain($imdbLink,$seriesObj,true);
		$backups->poster = $seriesObj->poster;
		
	}else{
		
		$seriesObj->description = getDescription($seriesObj->img_title);
		if($seriesObj->description == ""){
			$seriesObj->description = "Sorry there is no description available for this show";
			$seriesObj->synopsis    = "Sorry there is no description available for this show";
		}
		
		$seriesObj->year   = 2010;
		$seriesObj->actors = "";
	}
	

	$imgObj 			   = getShowImages(html_entity_decode($seriesObj->title,ENT_QUOTES, 'UTF-8'),$backups);
	$seriesObj->keyart     = $imgObj->keyart;
	$seriesObj->poster     = $imgObj->poster;
	$seriesObj->showthumb  = $imgObj->showthumb;
	

	$url      	   = explode("/",$show->episodeLink->attributes()->episodeUrl);
	array_pop($url);
	$seriesObj->showurl = array_reduce($url,"joinArray");

	
	if(array_search($seriesObj->img_title,$checkList)=== false){
			array_push($checkList,$url_title);
			array_push($showlist,$seriesObj);
			
			$seriesObj->keyart    = $harvest->saveImageNew($seriesObj->img_title,"",$seriesObj->keyart,"key_art");
			$seriesObj->poster    = $harvest->saveImageNew($seriesObj->img_title,"",$seriesObj->poster,"poster");
			$seriesObj->showthumb = $harvest->saveImageNew($seriesObj->img_title,"",$seriesObj->showthumb,"show_thumbnail");

			$entry_exists = $harvest->dupReferenceCheck($seriesObj->fileloc,"episode");
			
			if($entry_exists == false){
				print_r($seriesObj);
				saveShowRef($seriesObj);	
				$showcount++;
			}
			
	}

}

//==================Begin harvesting invidual episodes for each show===========================
//=============================================================================================
$epcount = 0;	

for($k=0; $k<count($showlist);$k++){


	$cid = getCollectionID($showlist[$k]->urltitle);
	
$epFeed ='http://adultswim.com/adultswimdynamic/asfix-svc/episodeSearch/getAllEpisodes?limit=&offset=0&categoryName=&filterByEpisodeType=EPI&filterByCollectionId='.$cid.'&networkName=AS&r=1202760270203&sortByVideoRequest=DESC';
	
	$ep_data = file_get_contents($epFeed);
	$ep_xml  = simplexml_load_string($ep_data);

	foreach($ep_xml->episode as $episode){
		
		$epObj = new stdclass;
		$epObj = $showlist[$k];

		$epObj->season   = (int)$episode->attributes()->epiSeasonNumber;
		$epObj->epnum    = (int)$episode->attributes()->episodeNumber;
		$epObj->urlid    = $harvest->buildURLIDepisode($epObj->season,$epObj->epnum,$epObj->fileloc);
		$epObj->provider = "Cartoon Network";		
		$epObj->url 	 = (string)$episode->episodeLink->attributes()->episodeUrl;
		
		$pubdata 		 = explode(" ",(string)$episode->attributes()->launchDate);
		$epObj->pubdate  = $pubdata[0];
		$date_data		 = explode("/",$epObj->pubdate);
		$epObj->year     = $date_data[2];
		
		$expdata  		 = explode(" ",(string)$episode->attributes()->expirationDate);
		$_expire 		 = explode("/",$expdata[0]);
		$epObj->expire   = $_expire[2] . "-" . $_expire[0] . "-" . $_expire[1];
		$epObj->episode  = (string)	$episode->attributes()->title;

		$rating  		    = explode(" ",$episode->attributes()->rating);
		$epObj->rating      = $rating[0];
		$description        = htmlentities(strip_tags((string) trim($episode->description)),ENT_QUOTES, 'UTF-8');
		$epObj->description = (strlen($description) > 200 ) ? substr($description,0,200) . "..." : $description; 
		$epObj->synopsis    = $epObj->description;
		$epObj->urating     = '0';
		$epObj->thumbnail   = (string)$episode->attributes()->thumbnailUrl;
		$epObj->keywords    = htmlentities(strip_tags((string) trim($episode->keywords)),ENT_QUOTES, 'UTF-8');	
		$epObj->type        = 1;
		$epObj->totalsg     = count($episode->segments->segment);
		
		$videoData          = getVideoInfo($episode->segments->segment);
		
		$epObj->duration = $videoData->runtime;
		$epObj->embed    = $videoData->embed;
		$epObj->mobile   = $videoData->mobile;
		
		$epObj->myubi    = $harvest->genID();
		$epObj->language = "en";
		$epObj->quality  = "SD";
		$epObj->caption  = 0;
		$epObj->pid      = 5;
		$epObj->cid      = $cid;

		if(stripos($epObj->title,"(Unauth)",0)>0)
			$epObj->title = trim(str_replace("(Unauth)","",$epObj->title));

	
	 		
		$entry_exists = $harvest->dupContentCheck($epObj->urlid,"episode");

			if($entry_exists->result == false){	
				print "[ " .$epObj->title. "  " .$epObj->season. " : ".$epObj->epnum ." doesn't exist in db ]";
				print_r($epObj);
				saveEpisode($epObj);
					$epcount++;			
			}else{
				print "..episode content must already exist, lets check for the stream....";
				
				$dupcheck = $harvest->dupStreamCheck($entry_exists->myubi,$epObj->provider,"episode");
						
					if($dupcheck == false){
						$epObj->myubi = $entry_exists->myubi;
						print "....[creating new CN stream with id " .$epObj->myubi." ]...";
						insertEpisodeStream($epObj);
					}else
						print "...we already have this stream too<P> ...";	
			}
			
	
	}
	
}

$query = "update harvester_index set last_update='".date('Y-m-d')."' where provider='Cartoon Network'";
$res   = mysql_query($query);


	if($res->error) {
                $logger->error("PHP Harvester : cartoon.php Message Mysql Query Error" . mysql_error());
        }

function getVideoInfo($segs){
	$res = new stdclass;
	$runtime  = 0;
	$embed    = "";
	$mobile   = "";
	
	foreach($segs as $segtime){
		$runtime += (int) $segtime->attributes()->duration;
		$stream = "http://asfix.adultswim.com/asfix-svc/episodeservices/getVideoPlaylist?networkName=AS&id=".$segtime->attributes()->id;
		//print "stream link is " . $stream;
		$stream_data = file_get_contents($stream);
		$stream_xml  = simplexml_load_string($stream_data);
		
		foreach($stream_xml->entry as $link){
			//print "<p> " . substr($link->ref->attributes()->href,0,24) . "<br>";
			if(trim(substr($link->ref->attributes()->href,0,24)) == "http://ht.cdn.turner.com"){
				$embed .= $link->ref->attributes()->href . "+";
			}
		
			if(substr($link->ref->attributes()->href,strlen($link->ref->attributes()->href)-4)== ".3gp" && $link->param[2]->attributes()->value > 300){
				$mobile .= $link->ref->attributes()->href. "+";
			}
		}
		
	}
	$res->runtime  = $runtime;
	$res->embed    = "cn,".substr($embed,0,strlen($embed)-1);
	$res->mobile   = "cn,".substr($mobile,0,strlen($mobile)-1);
	
	return $res;
}

function joinArray($v1,$v2){
	if($v1==""){
		return $v2;
	}else{
		return $v1 . "/" . $v2;
	}
		
}

function getCollectionID($url){
	$showvars = "http://www.adultswim.com/shows/". $url ."/vars.js";
	$showvars = file_get_contents($showvars);
	$IDdata = explode(';',$showvars);
	$showID = $IDdata[5];
	$showID = substr($showID,16);
	$showID = substr($showID,0,strlen($showID)-1);
	preg_match('/collectionId: \"([0-9a-z]+)\"/si',$showID,$cid);
	return $cid[1];
}

function getDescription($_title){
	
  		$q = str_replace("_","+",$_title);
	$huluquery = "http://www.hulu.com/search?query=".$q . "&st=1&fs=";
		
	
		$checkPage = new Scrape();
		$checkPage->fetch($huluquery);
		
		$data  = $checkPage->removeNewlines($checkPage->result);
		$start = "<script type=\'text\/javascript\' charset=\'(.*?)\'>";
		$end   = "<\/script>";
		$showData = $checkPage->fetchAllBetween($start,$end,$data,true);
		//print_r($showData);
		$_desc = explode('<span class="video-info" style="clear:both;padding-top: 15px;font-size:12px;">',$showData[0]);
		//print_r($_desc);
		$end = stripos($_desc[1],"<a href",0);
	
		$desc = (string) trim(substr($_desc[1],0,$end-2));
		//$desc  = explode('"',$_desc[7]);
		
		$description = mysql_real_escape_string($desc);

		
		return $description;

}

function getShowImages($title,$b){
	global $harvest;
	$imgObj = new stdclass;
	print "...getting images for " . $title ." ...";
	$showthumb 		= $harvest->theTVdb($title,"showthumb");
	$poster			= $harvest->theTVdb($title,'poster');
	$keyart			= $harvest->theTVdb($title,"keyart");
	
	$imgassets = $harvest->googleImageCheck($title,"tv series");
	

	if($keyart == "error" && $imgassets[0] != "")
		$imgObj->keyart     = $imgassets[0];
	elseif($keyart == "error" && property_exists($b,"keyart"))
		$imgObj->keyart     = $b->keyart;
	else
		$imgObj->keyart     = $keyart;
	
	
	if($showthumb == "error" && $imgassets[1] != "")
		$imgObj->showthumb  = $imgassets[1];
	elseif($showthumb == "error" && property_exists($b,"showthumb"))
		$imgObj->showthumb  = $b->showthumb;
	else
		$imgObj->showthumb  = $showthumb;
		
	
	if($poster == "error" && $imgassets[3] != "")
		$imgObj->poster     = $imgassets[3];
	elseif($poster == "error" && property_exists($b,"poster"))
		$imgObj->poster  = $b->poster;
	else
		$imgObj->poster     = $poster;
	
	
	return $imgObj;
}

function saveShowRef($s){
	global $t_suffix;
	global $notes;
	global $harvest;
	//global $logger;

	//save images
	
	
	
	$query = "insert into episode_reference".$t_suffix." (MYUBI_ID,TITLE,TYPE,DESCRIPTION,NETWORK,URL,SHOWTHUMB,KEYART,POSTER,GENRE,USERRATING,RATING,FILELOC,KEYWORDS,CAST,YEAR,SYNOPSIS) VALUES ('".$s->myubi."','".mysql_escape_string($s->title)."','".$s->type."','".mysql_escape_string($s->description)."','".$s->network."','".$s->showurl."','".$s->showthumb."','".$s->keyart."','".$s->poster."','".$s->genre."',".$s->urating.",'".$s->rating."','".$s->fileloc."','".mysql_escape_string($s->keywords)."','".mysql_escape_string($s->actors)."',".$s->year .",'".mysql_escape_string($s->synopsis)."')";
			 
		
		if (mysql_query($query)) {
			print "=================INSERT Reference ".$s->title ."=================\n ";
				
		} else {
			echo "<strong>Something went wrong with ref insert: ".mysql_error()."</strong>";
			$notes .= $s->title . " show insert failed";
			/*$logger->error("PHP Harvester : cwtv.php Message Mysql Query Error" . mysql_error());
			$repeat = true;*/
		}

}

function saveEpisode($e){
	global $notes;
		global $harvest;
	global $t_suffix;
	
	$e->thumbnail = $harvest->saveImageNew($e->img_title,"",$e->thumbnail,"thumbnail",$e->myubi);
	
	 $query = "insert into episode_content".$t_suffix." (MYUBI_ID,TITLE,EPISODE,SEASON,TYPE,DESCRIPTION,NETWORK,KEYWORDS,USERRATING,RATING,COUNTRY,YEAR,URL,SHOWTHUMB,THUMB,DURATION,GENRE,EPISODETITLE,URLID,KEYART,PUBDATE,FILELOC,SYNOPSIS) VALUES ('".$e->myubi."','".mysql_escape_string($e->title)."',".$e->epnum.",".$e->season.",".$e->type.",'".mysql_escape_string($e->description)."','".$e->network."','".mysql_escape_string($e->keywords)."',".$e->urating.",'".$e->rating."','".$e->country."',".$e->year.",'".$e->url."','".$e->showthumb."','".$e->thumbnail."',".$e->duration.",'".mysql_escape_string($e->genre)."','".mysql_escape_string($e->episode)."','".$e->urlid."','".$e->keyart."','".$e->pubdate."','".$e->fileloc."','".mysql_escape_string($e->synopsis)."')";

 
			     if (mysql_query($query)) {
					print "=================INSERT EPISODE CONTENT ".$e->epnum ." SUCCESS ".$e->title ."=================\n ";
						insertEpisodeStream($e);
				} else {
					echo "<strong>Something went wrong: ".mysql_error()."</strong>";
					$notes .= $e->title . " show insert failed";
					/*$logger->error("PHP Harvester : cwtv.php Message Mysql Query Error" . mysql_error());
					$repeat = true;*/
				}

}


	function insertEpisodeStream($e){
		global $t_suffix;
		global $logger;
	
		 $query = "INSERT INTO episode_streams".$t_suffix." (myubi_id, url_hi, url_lo, provider, aspect, quality, expire, pid, cid, segment, fee,language,captions) VALUES ('".$e->myubi."', '".$e->embed."', '".$e->mobile."', '".$e->provider."', 9, '".$e->quality."', '".$e->expire."', ".$e->pid.", '".$e->cid."', '0', '0', '".$e->language."', '".$e->caption."')";
		 
		if (mysql_query($query)) {
		 print "<p>============EPISODE STREAM" . $e->title ." INSERTED=================<p>";
			   
		} else 
			print_r("PHP Harvester : episode_streams error" . mysql_error());
	}




?>
