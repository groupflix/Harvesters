<?php

ini_set("memory_limit","128M");

include 'Scrape.php';
include '../db/DBconfig.php'; 
include '../utils/DateConverter.php';
include '../IO/FileType.php';
include 'com/HarvestMethods.php';
include '../utils/SimpleXMLExtended.php';

error_reporting(E_ERROR);

print "Starting Hulu Harvesters.....";
//require_once('/opt/myubi/Log4php/Logger.php');
//Logger::configure('/opt/myubi/Log4php/resources/appender_hulu.properties');
//$log = Logger::getRootLogger();
$dbconfig = new DBconfig();
$harvest  = new HarvestMethods();
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


$res = mysql_connect($host, $username, $password);
if (!$res) {
//	$log->error("mysql_connect::mysql_error - Server connection not established! ".mysql_error($res));
	die("Could not connect to the server, mysql error: ".mysql_error($res));
}
$res = mysql_select_db($db);
if (!$res) {
	//$log->error("mysql_connect::mysql_error - Database connection not established! ".mysql_error($res));
	die("Could not connect to the database, mysql error: ".mysql_error($res));
}
$totalItems= 0; 
$epcount   = 0;
$showcount = 0;
$start     = 1;
$end       = 499;

$cycle     = true;
//====================Ready to Begin Feed======================

/********Choose a Harvester Feed Type***************/
$hDateFull = "1970-01-01T00:00:00";   //This will get all videos in their db
$hDateLast = GetLastDate();           //This will just update our feed based on the last time we harvested

$guid      = "5db85beb-93cd-f865-1f69-03ad80535b03";

while($cycle == true){

//$feedurl = "https://partnerfeeds.hulu.com/1.1/Default.aspx?edp=5db85beb-93cd-f865-1f69-03ad80535b03&seriesTitle=grey%27s%20anatomy&videoType=full&context=1111&startIndex=1&endIndex=150&lastModified=1970-01-01T00:00:00";
$feedurl   = "https://partnerfeeds.hulu.com/1.1/Default.aspx?edp=".$guid."&context=1111&videoType=full&startIndex=".$start."&endIndex=".$end."&lastModified=".$hDateFull;
print $feedurl;

$huludata  = $harvest->ParseFeed($feedurl);



//================Save Feed and Prep For Parsing=================

/*$FT = &new FileType();						
$FT->setFileType("hulu_feed");
$FT->setFormatType("xml");
$FT->setPath("/opt/myubi/web/www/harvesters");
$FT->setDATA($huludata);
$FT->output_file();

$xml_file   = file_get_contents("/opt/myubi/web/www/harvesters/hulu_feed.xml");*/
$huluxml    = &new SimpleXMLExtended($huludata);	

if($totalItems == 0){
	$totalItems = GetTotalItems((string)$huluxml->channel->description);
}
//================Harvest Content from HuluXML===================
//This particular harvester is only for full-length episode content.
//There are two types, TV and Web.  This harvester can discern the 
//difference and place the content in the proper tables


	foreach($huluxml->channel->item as $huluitem){
	    $showObject = array();
		
		$title   = (string) $huluitem->children('hulu', true)->seriesTitle;
		$eptitle = (string) $huluitem->title;
		$season  = (string) $huluitem->children('hulu', true)->season;
		$epnum   = (string) $huluitem->children('hulu', true)->episode;
		$img_title = $harvest->concatTitle($title);
		$provider= "Hulu";

		switch(strtolower(trim($huluitem->children('hulu', true)->mediaType))){
				case("tv"):
				    $fileloc      = $harvest->getFileloc($img_title,1);
					$entry_exists = $harvest->dupReferenceCheck($fileloc,"episode");
					
					if($entry_exists == false){
						print "save the series " . $title . " <P>";
						$showObject = GetShowData($huluitem);
						$showcount++;
					}else
						$showObject = GetShowData($huluitem,"false");
					
					
					$urlid 		  = $harvest->buildURLIDepisode($season,$epnum,$fileloc);
					$entry_exists = $harvest->dupContentCheck($urlid,"episode");
					
					if($entry_exists->result == false){
						print "save this episode " . $eptitle . " <P>";
						$e = GetEpisodeData($huluitem,$showObject,"episode");
						saveEpisode($e);
						$epcount++;
					}else{
						print "this ep of " . $eptitle. "has already been saved lets check for stream <P>";
						
						$dupCheck = $harvest->dupStreamCheck($entry_exists->myubi,$provider,"episode");
						
						if($dupCheck == false){
							print "....[huluDP stream doesn't exist]...";
							$e = GetEpisodeData($huluitem,$showObject,"episode");
							$e['myubi'] = $entry_exists->myubi;
							insertEpisodeStream($e);
						}else
							print "we already have this stream too<P> ...";
						
							
					}
					
				break;
				case("web original"):
					$fileloc      = $harvest->getFileloc($img_title,3);
					$entry_exists = $harvest->dupReferenceCheck($fileloc,"web");
					
					if($entry_exists == false){
						print "save the series " . $title . " <P>";
						$showObject = GetShowData($huluitem);
						$showcount++;
					}else
						$showObject = GetShowData($huluitem,"false");
	
					$urlid 		  = $harvest->buildURLIDweb($eptitle,$fileloc);
					$entry_exists = $harvest->dupContentCheck($urlid,"web");
					
					if($entry_exists->result == false){
						print "save this webisode " . $eptitle . " <P>";
						$w = GetEpisodeData($huluitem,$showObject,"web");
						saveWebisode($w);
						$epcount++;
					}else{
						print "this ep of " . $eptitle. "has already been saved lets check for stream <P>";
						
						$dupcheck = $harvest->dupStreamCheck($entry_exists->myubi,$provider,"web");
						
						if($dupcheck == false){
							print "....[huluDP stream doesn't exist]...";
							$e = GetEpisodeData($huluitem,$showObject,"web");
							$e['myubi'] = $entry_exists->myubi;
							insertWebStream($e);
						}else
							print "we already have this stream too<P> ...";
						
					}
					
				
		}
		
	
	}
	
	$end   += 500;
	$start += 500;
	
	if($end > $totalItems){
		$remainder = $totalItems - $start;
		if($remainder > 0){
			$end = $start + $remainder;
		}else{
			print "============ $end is > $totalItems ==========================";
			$cycle  = false;
		
			break;
		}
	}
	
}
	
print "=============".$showcount." Shows  and ".$epcount." Episodes were saved.  Total number of items waiting is " . $totalItems;

$notes = $showcount." Shows  and ".$epcount." Episodes";

$query = "update harvester_index set last_update='".date('Y-m-d')."', notes='".$notes."' where provider='HuluDP'";
$res   = mysql_query($query);


//===================Functions That Save Shows and Episodes =========================================

function GetShowData($hulu,$save="true"){
	//SHOW INFORMATION
	global $harvest;
	$sObject   = array();
	
	$type = strtolower(trim($hulu->children('hulu', true)->mediaType));
	$sObject['provider']  = "Hulu";
	
	$sObject['network']   = (string) $hulu->children('hulu', true)->network;
	$sObject['rating']    = strtoupper((string) $hulu->children('media', true)->rating);
	$sObject['myubi']     = $harvest->genID();
	$sObject['title']     = (string) $hulu->children('hulu', true)->seriesTitle;
	$sObject['description']= (string) $hulu->children('hulu', true)->seriesDescription;
	$sObject['synopsis']  = (string) $hulu->children('hulu', true)->seriesSynopsis;
	$img_title 			  = $harvest->concatTitle((string) $hulu->children('hulu', true)->seriesTitle);
	$sObject['quality']   = CheckQuality((string) $hulu->children('media', true)->content->attributes()->framerate);
	$sObject['urating']   = 0;
	$sObject['keywords']  = (string) $hulu->children('media', true)->keywords;
	$sObject['genre']     = (string) $hulu->children('hulu', true)->category;
	$sObject['caption']   = (string) $hulu->children('hulu', true)->caption;
	$sObject['showurl']   = (string) $hulu->children('hulu', true)->seriesLink;
	$sObject['ready']     = "true";	
	
	//=============GET IMAGES===========================
	$showData = getShowIDandImage($sObject['showurl']);
	$sObject['poster']    = $harvest->theTVdb($sObject['title'],'poster');
	$imgassets            = $harvest->googleImageCheck($sObject['title'],'tv series');
	//=============SAVE IMAGES===========================
	$sObject['poster']    = $harvest->saveImageNew($img_title,$sObject['provider'],$sObject['poster'],"poster");
	$sObject['keyart']    = $harvest->saveImageNew($img_title,$sObject['provider'],(string) $hulu->children('hulu', true)->seriesKeyArt,"key_art");
	$sObject['showthumb'] = $harvest->saveImageNew($img_title,$sObject['provider'],$showData['showthumb'],"show_thumbnail");
	//=============CHECK IMAGES===========================
	if(strripos($sObject['showthumb'],"default",0) >0 && $imgassets[1] != "")
		$sObject['showthumb'] = $harvest->saveImageNew($img_title,$sObject['provider'],$imgassets[1],"show_thumbnail");
		
	if(strripos($sObject['keyart'],"default",0) >0 && $imgassets[0] != "")
		$sObject['keyart'] = $harvest->saveImageNew($img_title,$sObject['provider'],$imgassets[0],"key_art");
	
	if(strripos($sObject['poster'],"default",0) >0 && $imgassets[3] != "")
		$sObject['poster'] = $harvest->saveImageNew($img_title,$sObject['provider'],$imgassets[3],"poster");
	//====================================================
	
	if($sObject['rating']  == "NOT RATED")
		$sObject['rating'] = "NR";
	
	if($sObject['synopsis'] == "")
		$sObject['synopsis'] = (strlen($sObject['description'] > 170)) ? substr($sObject['description'],0,175) . "..." : $sObject['description'];
	
	if($sObject['network'] == "")
		$sObject['network'] = "Ubi";
	
	if($type == "tv"){
		$sObject['type']      = 1;
		$sObject['fileloc']   = $harvest->getFileloc($img_title,$sObject['type']);
		
	}else{
		$sObject['type']      = 3;
		$sObject['fileloc']   = $harvest->getFileloc($img_title,$sObject['type']);
		
	}
	
	//print_r($sObject);
	if($save == "true"){
		if($type == "tv")
			saveSeries($sObject);
		else
			saveWebShow($sObject);
	}	
	
	return $sObject;
}

function GetEpisodeData($hulu,$showObject,$type){
	global $harvest;
	
	$epObject  = array();
	$epObject  = $showObject;
	$img_title = $harvest->concatTitle((string) $hulu->children('hulu', true)->seriesTitle);
	
	
	$epObject['pid']        = 2;
	$epObject['cid']        = 0;
	$epObject['language']   = "en";
	$epObject['url']        = (string) $hulu->children('hulu', true)->siteLink;
	$epObject['expire']     = ParseExpire((string) $hulu->children('dcterms', true)->valid);
	$epObject['pubDate']    = ParsePubDate((string) $hulu->pubDate);
	$epObject['episode']    = (string) $hulu->title;
	$epObject['description']= (string) $hulu->description;
	$epObject['synopsis']   = (string) $hulu->description;
	$epObject['runningtime']= (string) $hulu->children('media', true)->content->attributes()->duration;
	$epObject['myubi']      = $harvest->genID();
	$epObject['thumb']      = $harvest->saveImageNew($img_title,$epObject['provider'],SelectThumbnail($hulu->children('media', true)->thumbnail),"thumbnail",$epObject['myubi']);
	if($epObject['expire'] == "")
		$epObject['expire']     = $harvest->addDate(date('Y-m-d'),1);
	$epObject['season']     = (string) $hulu->children('hulu', true)->season;
	$epObject['epnum']      = (string) $hulu->children('hulu', true)->episode;
	$epObject['urating']    = "0";
	$epObject['year']       = ParseYear((string) $hulu->pubDate);
	
	$epObject['embed']      = "HuluDP," . PullEmbed((string) $hulu->children('hulu', true)->embed);
	$epObject['mobile']     = (string) $hulu->children('hulu', true)->portableEmbed;
	
	preg_match('/src=\"(.*)\"/U', $epObject['mobile'], $mobile);
	$epObject['mobile']     = $mobile[1];
	
		
	if($type == "episode")
		$epObject['urlid']      = $harvest->buildURLIDepisode($epObject['season'],$epObject['epnum'],$epObject['fileloc']);
	else
		$epObject['urlid']      = $harvest->buildURLIDweb($epObject['episode'],$epObject['fileloc']);
		
	
	return $epObject;
}


function saveSeries($saveData){
global $t_suffix;

	$query = "insert into episode_reference". $t_suffix . " (myubi_id,title, description, synopsis, network, url, keywords, genre, keyart, poster, showthumb, userrating, cast, rating, fileloc,type) VALUES ('".$saveData['myubi']."','".mysql_escape_string(htmlentities($saveData['title']))."','".mysql_escape_string($saveData['description'])."','".mysql_escape_string($saveData['synopsis'])."','".$saveData['network']."','".$saveData['showurl']."','".mysql_escape_string(htmlentities($saveData['keywords']))."','".$saveData['genre']."','".$saveData['keyart']."','".$saveData['poster']."','".$saveData['showthumb']."','".$saveData['urating']."','','".$saveData['rating']."','".$saveData['fileloc']."','".$saveData['type']."')";
		 
		if (mysql_query($query)) {
	
			print "<p>============".$saveData['title']." SHOW REF INSERTED=================<p>";
			print_r($saveData);
						
		} else {
			echo "<strong>Something went wrong Show Reference: ".mysql_error()."</strong>";
		
		}
			
}

function saveWebShow($saveData){
global $t_suffix;

	$query = "insert into web_reference". $t_suffix . " (myubi_id,title, description, synopsis, network, url, keywords, genre, keyart, showthumb, userrating, rating, fileloc,type) VALUES ('".$saveData['myubi']."','".mysql_escape_string(htmlentities($saveData['title']))."','".mysql_escape_string($saveData['description'])."','".mysql_escape_string($saveData['synopsis'])."','".$saveData['network']."','".$saveData['showurl']."','".mysql_escape_string(htmlentities($saveData['keywords']))."','".$saveData['genre']."','".$saveData['keyart']."','".$saveData['showthumb']."','".$saveData['urating']."','".$saveData['rating']."','".$saveData['fileloc']."','".$saveData['type']."')";
		 
		if (mysql_query($query)) {
	
			print "<p>============".$saveData['title']." WEB REF INSERTED=================<p>";
			print_r($saveData);
						
		} else {
			echo "<strong>Something went wrong Web Reference: ".mysql_error()."</strong>";
		
		}
			
}

function saveEpisode($saveData){
global $t_suffix;

 $query = "insert into episode_content". $t_suffix . "(MYUBI_ID,TITLE,EPISODE,SEASON,TYPE,DESCRIPTION,SYNOPSIS,NETWORK,KEYWORDS,COUNTRY,YEAR,URL,SHOWTHUMB,THUMB,POSTER,KEYART,DURATION,GENRE,EPISODETITLE,URLID,PUBDATE,RATING,USERRATING,FILELOC) VALUES ('".$saveData['myubi']."','".mysql_escape_string(htmlentities($saveData['title'],ENT_QUOTES, 'UTF-8'))."','".$saveData['epnum']."','".$saveData['season']."',".$saveData['type'].",'".mysql_escape_string(htmlentities($saveData['description'],ENT_QUOTES, 'UTF-8'))."','".mysql_escape_string(htmlentities($saveData['synopsis'],ENT_QUOTES, 'UTF-8'))."','".$saveData['network']."','".mysql_escape_string(htmlentities($saveData['keywords']))."','US','".$saveData['year']."','".$saveData['url']."','".$saveData['showthumb']."','".$saveData['thumb']."','".$saveData['poster']."','".$saveData['keyart']."','".$saveData['runningtime']."','".$saveData['genre']."','".mysql_escape_string(htmlentities($saveData['episode'],ENT_QUOTES, 'UTF-8'))."','".$saveData['urlid']."','".$saveData['pubDate']."','".$saveData['rating']."','".$saveData['urating']."','".$saveData['fileloc']."')";
			
	
				if (mysql_query($query)) {
					print "<p>==========Content for ". $saveData['title']." EPISODE " .$saveData['episode']  ." INSERTED=================<p>";
					//print_r($saveData);
					insertEpisodeStream($saveData);
				} else
					echo "<strong>Content insert error : ".mysql_error()."</strong>";
	

}

	function insertEpisodeStream($e){
		global $t_suffix;
		//global $logger;
	
		 $query = "INSERT INTO episode_streams". $t_suffix . " (myubi_id, url_hi, url_lo, provider, aspect, quality, expire, pid, cid, segment, fee,captions,language) VALUES ('".$e['myubi']."', '".$e['embed']."', '".$e['mobile']."', '".$e['provider']."', 9, '".$e['quality']."', '".$e['expire']."', ".$e['pid'].", ".$e['cid'].", '0', '0','".$e['caption']."','".$e['language']."')";
		 
		if (mysql_query($query)) {
		 print "<p>============EPISODE STREAM" . $e['epnum'] ." INSERTED=================<p>";
			   //$logger->error("PHP Harvester : crackle.php Mysql Query Error episode_streams" . mysql_error());
			
		} else 
			echo "saveepisodeStream:InsertError: -> Something went wrong: ".mysql_error();
			//$logger->error("PHP Harvester : crackle.php Mysql Query Error episode_streams" . mysql_error());
	}

function saveWebisode($saveData){
	global $t_suffix;
//	print "saving webisode ";
	$query = "insert into web_content". $t_suffix . " (MYUBI_ID, TITLE,EPISODE,SEASON,DESCRIPTION,NETWORK,KEYWORDS,COUNTRY,YEAR,URL,THUMB,SHOWTHUMB,KEYART,DURATION,GENRE,EPISODETITLE,URLID,PUBDATE,RATING,USERRATING,FILELOC,TYPE) VALUES ('".$saveData['myubi']."','".mysql_escape_string(htmlentities($saveData['title'],ENT_QUOTES, 'UTF-8'))."','".$saveData['epnum']."','".$saveData['season']."','".mysql_escape_string(htmlentities($saveData['description'],ENT_QUOTES, 'UTF-8'))."','".$saveData['network']."','".mysql_escape_string(htmlentities($saveData['keywords'],ENT_QUOTES, 'UTF-8'))."','US','".$saveData['year']."','".$saveData['url']."','".$saveData['thumb']."','".$saveData['showthumb']."','".$saveData['keyart']."','".$saveData['runningtime']."','".$saveData['genre']."','".mysql_escape_string(htmlentities($saveData['episode'],ENT_QUOTES, 'UTF-8'))."','".$saveData['urlid']."','".$saveData['pubDate']."','".$saveData['rating']."','".$saveData['urating']."','".$saveData['fileloc']."',".$saveData['type'].")";

 	
				if (mysql_query($query)) {
					
						print "<p>==========Content for ". $saveData['title']." Webisode " .$saveData['episode']  ." INSERTED=================<p>";
						//print_r($saveData);
					insertWebStream($saveData);
								
				} else 
					print "saveWebisode:InsertError: -> Something went wrong: ".mysql_error();

}

	function insertWebStream($e){
		//global $logger;
		global $t_suffix;
		
		$query = "INSERT INTO web_streams". $t_suffix . " (myubi_id, url_hi, provider, aspect, quality, pid, cid, segment, fee,captions,language) VALUES ('".$e['myubi']."', '".$e['embed']."', '".$e['provider']."', 9, '".$e['quality']."', ".$e['pid'].", '".$e['cid']."', '0',0,".$e['caption'].",'".$e['language']."')";
		
		if (mysql_query($query)) {
			print "<p>============WEBISODE STREAM" . $e['epnum'] ." INSERTED=================<p>";
		
		} else 
			print "saveWebisodeStream:InsertError: -> Something went wrong: ".mysql_error();
	}




//==========================Misc Functions that Support Code Above==========================

function GetTotalItems($response){
	
	$response = str_replace("A feed containing","",$response);
	$response = trim(str_replace("videos from Hulu","",$response));
	
	$itemSplit= explode(" of ",$response);
	
	return $itemSplit[1];
}

function GetLastDate(){
	$query = "select last_update from harvester_index where PROVIDER='HuluDP'";
	$date  = mysql_query($query);
	$_date = mysql_fetch_array($date);
	
	$dataparts = explode("-",$_date['last_update']);
	$lastdate  = $dataparts[0]."-".$dataparts[1]."-".$dataparts[2]."T12:00:00";
	
	return $lastdate;
}

function ParseGenre($cat){
	//not in use yet
}

function ParsePubDate($age){
	    $_pubdate = explode(" ",$age);
	
		//CREATE PUBDATE IN PROPER DATE FORMAT
		$datecv = &new DateConverter();
		$datecv->setDay($_pubdate[1]);
		$datecv->setMonth($_pubdate[2]);
		$datecv->setYear($_pubdate[3]);
		$pubDate = $datecv->dateFormat($_pubdate[3],$_pubdate[2],$_pubdate[1]);
		
		return $pubDate;
}

function ParseYear($age){
	    $_pubdate = explode(" ",$age);
		
		return $_pubdate[3];
}


function ParseExpire($dates){
	$dateSplit  = explode("end=",$dates);
	$specifyEnd = explode("T",$dateSplit[1]);
	$ParseDate  = explode("-", $specifyEnd[0]);
	
	$expiration = $ParseDate[1] . "-" .$ParseDate[2] . "-" .$ParseDate[0] ;
	
	return $expiration;
}

function PullEmbed($html){
	
	$regexp = "<param\s[^>]*name=(\"??)([^\" >]*?)\\1[^>]* value=(\"??)([^\" >]*?)\\1[^>]*>"; 
	$embed  = "";
	
	if(preg_match_all("/$regexp/siU", $html, $matches)) {			
			$embed = $matches[4][0]; //."?".$matches[4][3];
	}
	
	return $embed;
}

function getShowIDandImage($url){
		
	$showpage = file_get_contents($url);

	preg_match_all('/content=\"http:\/\/[a-z][a-z][0-9].huluim.com\/show\/(.*)\?(.*)\"/siU',$showpage,$res);
	
	$data = array();
	$img  = substr($res[0][0],9,strlen($res[0][0])-1-9);
	$data['showthumb'] = substr($img,0,strripos($img,"=",0)+1) . "476x268";
	$data['showid'] = $res[1][0];
	//print "image ". $data['showthumb'];
	return $data;
	
}
	

function SelectThumbnail($imgfeed){
	$thumburl ="";
	
	foreach($imgfeed as $thumb){
		
		if($thumb->attributes()->width == "384"){
			$thumburl = (string)$thumb->attributes()->url;
		}
	}
	
	return $thumburl;
}

function CheckQuality($frame){
	$q ="";
	
	switch($frame){
		case('24'):
			$q	= "HD";
		break;
		case('30'):
			$q	= "SD";	
		break;
		default: 
			$q	= "SD";
		break;
	}
	
	return $q;

}
?>
