<?php
ini_set("memory_limit","128M");
error_reporting(E_ERROR);
include '../IO/FileType.php';
include '../utils/DateConverter.php';
include 'com/HarvestMethods.php';
include '../db/DBconfig.php'; 
include 'Scrape.php';

require_once('/opt/myubi/Log4php/Logger.php');
Logger::configure('/opt/myubi/Log4php/resources/appender_crackle.properties');

$logger		= Logger::getRootLogger();
$dbconfig 	= new DBconfig();
$harvest 	= new HarvestMethods();
$datecv		= &new DateConverter();

//SET DEBUG VARIABLES=======================================

$tmp = false;

if($tmp){
	$harvest->setTableSuffix('_tmp');
	$t_suffix = "_tmp";
}else
	$t_suffix ="";
//==========================================================


$res = mysql_connect($dbconfig->getHOST(),$dbconfig->getUSERNAME(), $dbconfig->getPASSWORD());
if (!$res)
    $logger->error("PHP Harvester : crackle.php Message - Could not connect to the server : ".mysql_error($res));
 
$res = mysql_select_db($dbconfig->getDATABASE());
if (!$res) 
    $logger->error("PHP Harvester : crackle.php Message - Could not connect to the server : ".mysql_error($res));
$logger->info("PHP Harvester : crackle.php Starting ");



$totalItems= 0; 
$epcount   = 0;
$showcount = 0;

$url = 'http://www.crackle.com/rss/media/Zm14PTUwMDAmZmNtdD0xMTQmZnA9MSZmeD0.rss';

$logger->info("PHP Harvester : crackle.php  Getting url Data:" . $url);
$xml= $harvest->getRSS($url);
$logger->info("PHP Harvester : crackle.php  Done pulling data from " . $url);

foreach($xml->channel->item as $crackleitem){
	$sObj    = new stdclass;
	
	$sObj->title   = (string) $crackleitem->children('media', true)->category;
	$sObj->episode = mysql_escape_string(htmlentities((string) $crackleitem->title,ENT_QUOTES, 'UTF-8'));
	
	$sObj->pubDate = $crackleitem->pubDate;
	$_pubdate	   = explode(" ",$sObj->pubDate);
	$sObj->year    = $_pubdate[3];
	$sObj->url     = (string) $crackleitem->link;
	$sObj 		   = getEpisodeSeason($sObj);

	$sObj->img_title = $harvest->concatTitle($sObj->title);
	$provider	     = "Crackle";

	if(stristr($sObj->title,"Minisode")){
		$sObj->fileloc= $harvest->getFileloc($sObj->img_title,3);
		$entry_exists = $harvest->dupReferenceCheck($sObj->fileloc,"web");
	
		if($entry_exists == false){
			print "save the series " . $sObj->title . " <P>";
			$sObj = GetShowData($crackleitem,$sObj);
			$showcount++;
		}else
			$sObj = GetShowData($crackleitem,$sObj,"false");

		$urlid 		  = $harvest->buildURLIDweb($sObj->episode,$fileloc);
		$entry_exists = $harvest->dupContentCheck($urlid,"web");
		
		if($entry_exists->result == false){
			print "save this webisode [" . $sObj->episode . "] <P>";
			$w = GetEpisodeData($crackleitem,$sObj,"web");
			insertWebisode($w);
			$epcount++;
		}else{
			print "this ep [" . $sObj->episode. "] has already been saved lets check for stream <P>";
			
			$dupcheck = $harvest->dupStreamCheck($entry_exists->myubi,$provider,"web");
			
			if($dupcheck == false){
				print "....[crackle stream doesn't exist]...";
				$e = GetEpisodeData($crackleitem,$sObj,"web");
				$e->myubi = $entry_exists->myubi;
				insertWebStream($e);
			}else
				print "we already have this stream too<P> ...";
			
		}	
		
	}else{
		$sObj->fileloc= $harvest->getFileloc($sObj->img_title,1);
		$entry_exists = $harvest->dupReferenceCheck($sObj->fileloc,"episode");
		
		if($entry_exists == false){
			print "save the series " . $sObj->title . " <P>";
			$sObj = GetShowData($crackleitem,$sObj);
			$showcount++;
		}else
			$sObj = GetShowData($crackleitem,$sObj,"false");
		
		$urlid 		  = $harvest->buildURLIDepisode($sObj->season,$sObj->epnum,$sObj->fileloc);
		$entry_exists = $harvest->dupContentCheck($urlid,"episode");
		
		if($entry_exists->result == false){
			print "save this episode of [" . $sObj->episode. "] <P>";
			$e = GetEpisodeData($crackleitem,$sObj,"episode");
			insertEpisode($e);
			$epcount++;
		}else{
			print "this ep of [" . $sObj->episode. "] has already been saved lets check for stream <P>";
			
			$dupcheck = $harvest->dupStreamCheck($entry_exists->myubi,$provider,"episode");
			
			if($dupcheck == false){
				print "....[crackle stream doesn't exist]...";
				$e = GetEpisodeData($crackleitem,$sObj,"episode");
				$e->myubi = $entry_exists->myubi;
				insertEpisodeStream($e);
			}else
				print "we already have this stream too<P> ...";	
		}

	}
	//break;

}

function GetShowData($xml,$temp,$save="true"){
	//SHOW INFORMATION
	global $harvest;
	global $datecv;
	$sObject   = new stdclass;
	$sObject   = $temp;
	/*$sObject->url 		= $temp->url;
	$sObject->episode	= $temp->episode;
	$sObject->title 	= $temp->title;
	$sObject->epnum		= $temp->epnum;
	$sObject->season	= $temp->season;
	$sObject->year		= $temp->year;
	$sObject->pubDate 	= $temp->pubDate;*/
	
	
    $sObject->rating 	= getRating((string) $xml->children('media', true)->rating);
    $sObject->keywords	= mysql_escape_string(htmlentities($xml->children('media', true)->keywords,ENT_QUOTES, 'UTF-8'));
	$sObject->genre     = processGenre($sObject->keywords);
    $sObject->quality 	= "SD";
    $sObject->provider	= "Crackle";
    $sObject->network 	= "Crackle";
	$sObject->pid	 	= "1";
	$sObject->cid	 	= "0";
	$sObject->synopsis	= mysql_escape_string(htmlentities($xml->children('media', true)->description,ENT_QUOTES, 'UTF-8'));
  $sObject->description = $sObject->synopsis;
    $sObject->country 	= "US";
	$sObject->caption 	= 0;
    $sObject->userRating= (string) $xml->children('media', true)->popularity;
	$sObject->myubi 	= $harvest->genID();
	
	$_pubdate			= explode(" ",$sObject->pubDate);
	$datecv->setDay($_pubdate[1]);	$datecv->setMonth($_pubdate[2]);	$datecv->setYear($_pubdate[3]);
	$sObject->pubDate   = $datecv->dateFormat($_pubdate[3],$_pubdate[2],$_pubdate[1]);
	
	$imageset           = getShowImages($sObject->title);
	$sObject->showthumb	= $harvest->saveImageNew($sObject->img_title,"",$imageset->showthumb,"show_thumbnail");
	$sObject->keyart	= $harvest->saveImageNew($sObject->img_title,"",$imageset->keyart,"key_art");
	$sObject->poster	= $harvest->saveImageNew($sObject->img_title,"",$imageset->poster,"poster");
	
	if(stristr($sObject->title,"Minisode") ){
		$sObject->title = str_replace("Minisode","",$sObject->title);
		$sObject->type  = 3;
	}else
		$sObject->type = 1;
	
	if($save == "true"){
		if($sObject->type == 1)
    		saveSeries($sObject);
		else
			saveWebShow($sObject);
	}	
	
	return $sObject;
	
}

function GetEpisodeData($xml,$showObject,$type){
	global $harvest;
	$eObj = new stdclass;
	
	$img_title 	= $harvest->concatTitle($showObject->title);
	//$eObj->showthumb    = $showObject->showthumb;
	$eObj       = $showObject;
	$eObj->imgtitle     = $img_title;
	$eObj->pid          = 1;
	$eObj->cid          = 0;
	$eObj->language     = 'en';
	$eObj->url          = (string)$xml->link;
	$eObj->expire  		= $harvest->addDate(date('Y-m-d'),1);
	$content			= $xml->children('media', true)->content->attributes();
	$eObj->duration 	= (string) $content['duration'][0];
	$eObj->myubi        = $harvest->genID();
	$eObj->expire       = $harvest->addDate(date('Y-m-d'),1);
	$eObj->embed 		= strtolower($showObject->provider) .",".$showObject->url;

	$thumbs 			= $xml->children('media', true)->thumbnail;
	$eObj->thumbnail	= (string)$thumbs[2]->attributes()->url;
	

	if($eObj->rating    == "NOT RATED" || $eObj->rating == "")
		$eObj->rating   = "NR";
	
	if($eObj->type == 1)
		$eObj->urlid        = $harvest->buildURLIDepisode($eObj->season ,$eObj->epnum ,$eObj->fileloc );
	else
		$eObj->urlid        = $harvest->buildURLIDweb($eObj->episode,$eObj->fileloc );
	
	print_r($eObj);
	

	return $eObj;
}


$query = "update harvester_index set last_update='".date('Y-m-d')."' where provider='Crackle'";
$res   = mysql_query($query);


function saveSeries($s){
global $t_suffix;

	$query = "insert into episode_reference". $t_suffix . " (myubi_id,title, description, synopsis, network, url, keywords, genre, keyart, poster, showthumb, userrating, rating, fileloc, captions,type) VALUES ('".$s->myubi."','".mysql_escape_string(htmlentities($s->title,ENT_QUOTES, 'UTF-8'))."','".$s->description."','".$s->synopsis."','".$s->network."','".$s->url."','".$s->keywords."','".$s->genre."','".$s->keyart."','".$s->poster."','".$s->showthumb."','".$s->userRating."','".$s->rating."','".$s->fileloc."','".$s->caption."',".$s->type.")";
		 
		if (mysql_query($query)) {
	
			print "<p>============".$s->title." SHOW Ref INSERTED=================<p>";
			print_r($s);
						
		} else {
			echo "<strong>Something went wrong Show Ref: ".mysql_error()."</strong>";
		
		}
			
}

function saveWebShow($s){
global $t_suffix;

	$query = "insert into web_reference". $t_suffix . " (myubi_id,title, description, synopsis, network, url, keywords, genre, keyart, showthumb, userrating, rating, fileloc, captions,type) VALUES ('".$s->myubi."','".mysql_escape_string(htmlentities($s->title,ENT_QUOTES, 'UTF-8'))."','".mysql_escape_string($s->description)."','".$s->synopsis."','".$s->network."','".$s->url."','".$s->keywords."','".$s->genre."','".$s->keyart."','".$s->showthumb."','".$s->userRating."','','".$s->rating."','".$s->fileloc."','".$s->caption."',".$s->type.")";
		 
		if (mysql_query($query)) {
	
			print "<p>============".$s->title." WEbSHOW Ref INSERTED=================<p>";
			print_r($s);
						
		} else {
			echo "<strong>Something went wrong Web Show Ref: ".mysql_error()."</strong>";
		
		}
			
}


function insertEpisode($e){
	global $t_suffix;
	global $logger;
	global $harvest;
	
	$e->thumbnail	= $harvest->saveImageNew($e->imgtitle,"",$e->thumbnail,"thumbnail",$e->myubi);
			 $query = "insert into episode_content".$t_suffix." (MYUBI_ID,TITLE,EPISODE,SEASON,TYPE,DESCRIPTION,SYNOPSIS,NETWORK,KEYWORDS,COUNTRY,YEAR,URL,SHOWTHUMB,THUMB,POSTER,KEYART,DURATION,GENRE,EPISODETITLE,URLID,PUBDATE,RATING,USERRATING,FILELOC) VALUES ('".$e->myubi."','".mysql_escape_string(htmlentities($e->title,ENT_QUOTES, 'UTF-8'))."',".$e->epnum.",".$e->season.",".$e->type.",'".mysql_escape_string($e->description)."','".$e->synopsis."','".$e->network."','".$e->keywords."','".$e->country."','".$e->year."','".$e->url."','".$e->showthumb."','".$e->thumbnail."','".$e->poster."','".$e->keyart."','".$e->duration."','".$e->genre."','".$e->episode."','".$e->urlid."','".$e->pubDate."','".$e->rating."',".$e->userRating.",'".$e->fileloc."')";
			 
	if (mysql_query($query)) {

   	  $logger->error("PHP Harvester : crackle.php Message Mysql Query Error" . mysql_error());
	  print "<p>============ ".$e->title." EPISODE". $e->epnum ." INSERTED=================<p>";
		
	  insertEpisodeStream($e);			
	} else 
		$logger->error("PHP Harvester : crackle.php Content Query Error" . mysql_error());
	
}

	function insertEpisodeStream($e){
		global $t_suffix;
		global $logger;
	
		 $query = "INSERT INTO episode_streams".$t_suffix." (myubi_id, url_hi, url_lo, provider, aspect, quality, expire, pid, cid, segment, fee,captions,language) VALUES ('".$e->myubi."', '".$e->embed."', '".$e->embed."', '".$e->provider."', 9, '".$e->quality."', '".$e->expire."', ".$e->pid.", ".$e->cid.", '0', '0', ".$e->caption.", '".$e->language."')";
		 
		if (mysql_query($query)) {
		 print "<p>============EPISODE STREAM" . $e->epnum ." INSERTED=================<p>";
			   $logger->error("PHP Harvester : crackle.php Mysql Query Error episode_streams" . mysql_error());
			
		} else 
			$logger->error("PHP Harvester : crackle.php Mysql Query Error episode_streams" . mysql_error());
	}

function insertWebisode($e){
	global $t_suffix;
	global $logger;
	global $harvest;
    $e->thumbnail	= $harvest->saveImageNew($e->imgtitle,"",$e->thumbnail,"thumbnail",$e->myubi);
	
	 $query = "insert into web_content".$t_suffix." (MYUBI_ID,TYPE, TITLE,EPISODE,SEASON,DESCRIPTION,NETWORK,KEYWORDS,COUNTRY,YEAR,URL,THUMB,KEYART,DURATION,GENRE,EPISODETITLE,URLID,PUBDATE,RATING,USERRATING,FILELOC) VALUES ('".$e->myubi."','".$e->type."','".mysql_escape_string($e->title)."',".$e->epnum.",".$e->season.",'".mysql_escape_string($e->description)."','".$e->network."','".mysql_escape_string($e->keywords)."','".$e->country."','".$e->year."','".$e->url."','".$e->thumbnail."','".$e->keyart."','".$e->duration."','".$e->genre."','".mysql_escape_string($e->episode)."','".$e->urlid."','".$e->pubDate."','".$e->rating."',".$e->userRating.",'".$e->fileloc."')";
			
	if (mysql_query($query)) {
	 print "<p>============WEBISODE " . $e->epnum ." INSERTED=================<p>";
		   $logger->error("PHP Harvester : crackle.php Message Mysql Query Error" . mysql_error());
		
	} else 
		$logger->error("PHP Harvester : crackle.php Message Web Content Query Error" . mysql_error());
	insertWebStream($e);
}

	function insertWebStream($e){
		global $t_suffix;
		global $logger;
		$query = "INSERT INTO web_streams".$t_suffix." (myubi_id, url_hi, provider, aspect, quality, pid, cid, segment,captions,language) VALUES ('".$e->myubi."', '".$e->embed."', '".$e->provider."', 9, '".$e->quality."', '".$e->pid."', ".$e->cid.", 0,0,'en')";
		
		if (mysql_query($query)) {
		 print "<p>============WEBISODE STREAM" . $e->epnum ." INSERTED=================<p>";
			   $logger->error("PHP Harvester : crackle.php Query Error web_streams" . mysql_error());
			
		} else 
			$logger->error("PHP Harvester : crackle.php Mysql Query Error web_streams" . mysql_error());
	}

function getShowImages($title){
	global $harvest;
	$seriesObj = new stdclass;
	print "...getting images for " . $title ." ...";
	$showthumb 		= $harvest->theTVdb($title,"showthumb");
	$poster			= $harvest->theTVdb($title,'poster');
	//$keyart         = $harvest->theTVdb($title,"keyart");
	$imgassets = googleImageCheck($title);
	

	if($imgassets[0] == "")
		$seriesObj->keyart     = ($harvest->theTVdb($title,"keyart") == "error") ? "" : $harvest->theTVdb($title,"keyart");
	else
		$seriesObj->keyart     = $imgassets[0];
	
	
	if($showthumb == "error")
		$seriesObj->showthumb  = $imgassets[1];
	else
		$seriesObj->showthumb  = $showthumb;
		
	
	if($poster == "error")
		$seriesObj->poster     = $imgassets[3];
	else
		$seriesObj->poster     = $poster;
	
	return $seriesObj;
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

function processGenre($keywords){
	$_genres = array("Anime","Comedy","Documentary","Crime","Action","Animated","Sci-Fi","Romance","Drama","Dramedy","Classic");	//default set of genres from TV shows
	
	$first = false;   //used to tell when the first entry is made, allowing delimitation to begin thereafter
	$genreList;
	for($i=0;$i<count($keywords);$i++){									
		
		$_gpos = strripos($keywords,$_genres[$i],0);
		if($_gpos && !$first){
			$genreList = $_genres[$i];
			$first = true;
		}elseif($_gpos)
			$genreList .= ", ".$_genres[$i];
		
	}
	
	return $genreList;
}

function getEpisodeSeason($e){
	$t = $e;
	$scrape = new Scrape();
	$scrape->fetch($t->url);
	$data = $scrape->removeNewlines($scrape->result);
	$rows = $scrape->fetchAllBetween('<div class="title">','</div>',$data,true);
	
	$i=0;
	while($entry = $rows[$i]){ 
		 if(strripos($entry,"Season",0))
			  break;
		 else
			 $i++;
		 	
	}

	$data = trim($rows[$i]);
	if(stristr($data,"<h3>")){
		$start = strripos($data,"<h3>",0);
		
		$data = substr($data,($start+4),(strlen($data)-11-$start-4));
		$data = explode(",",$data);
		
		$t->epnum  = trim(str_replace("Episode ","",$data[1]));
		$t->season = trim(str_replace("Season ","",$data[0]));

	}else{
		$t->epnum = "x";
		$t->season = $t->year;
	}
	
	return $e;
}

function getRating($rate){
	$rating;
	if(strtolower(trim($rate)) == "pg"){
		$rating = "TV-PG";
	}else if(strtolower(trim($rate)) == "pg-13"){
		$rating = "TV-14";
	}else if(strtolower(trim($rate)) == "r"){
		$rating = "TV-MA";
	}else if(strtolower(trim($rate)) == "na"){
		$rating = "NR";
	}else{
		$rating = "NR";
	}
	
	return $rating;
}

?>
