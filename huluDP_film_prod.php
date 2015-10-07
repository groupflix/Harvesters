<?php

ini_set("memory_limit","128M");

include 'Scrape.php';
include '../db/DBconfig.php'; 
include '../utils/DateConverter.php';
include '../IO/FileType.php';
include 'com/HarvestMethods.php';
include 'com/MetaDataSources.php';
include '../utils/SimpleXMLExtended.php';

error_reporting(E_ERROR);

print "Starting Hulu Film Harvesters.....";
//require_once('/opt/myubi/Log4php/Logger.php');
//Logger::configure('/opt/myubi/Log4php/resources/appender_hulu.properties');
//$log = Logger::getRootLogger();
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

$streamcount= 0;
$filmcount = 0;
$start     = 1;
$end       = 499;
$totalItems= 0; 
$cycle     = true;
//====================Ready to Begin Feed======================

/********Choose a Harvester Feed Type***************/
$hDateFull = "1970-01-01T00:00:00";   //This will get all videos in their db
$hDateLast = GetLastDate();           //This will just update our feed based on the last time we harvested
//$hDateLast = "2012-09-15T00:00:00";
$guid      = "5db85beb-93cd-f865-1f69-03ad80535b03";


while($cycle == true){

//$feedurl = "https://partnerfeeds.hulu.com/1.1/Default.aspx?edp=5db85beb-93cd-f865-1f69-03ad80535b03&seriesTitle=zatoichi%20the%20blind%20swordsman&mediaType=Film&videoType=full&context=1111&startIndex=1&endIndex=150&lastModified=1970-01-01T00:00:00";
$feedurl   = "https://partnerfeeds.hulu.com/1.1/Default.aspx?edp=".$guid."&context=1110&mediaType=Film&videoType=full&startIndex=".$start."&endIndex=".$end."&lastModified=".$hDateFull;

$huludata  = $harvest->ParseFeed($feedurl);


$huluxml    = &new SimpleXMLExtended($huludata);	

if($totalItems == 0)
	$totalItems = GetTotalItems((string)$huluxml->channel->description);

//================Harvest Content from HuluXML===================
//This particular harvester is only for full-length episode content.
//There are two types, TV and Web.  This harvester can discern the 
//difference and place the content in the proper tables

$i=0;
	foreach($huluxml->channel->item as $huluitem){
	    $sArr = array();
		
		$title     = (string) $huluitem->children('hulu', true)->seriesTitle;
		$duration  = (string) $huluitem->children('media', true)->content->attributes()->duration;
		$img_title = $harvest->concatTitle($title);
		$fileloc   = $harvest->getFileloc($img_title,2);
		
		
		$expire      = ParseExpire((string) $huluitem->children('dcterms', true)->valid);
		$todays_date = date("Y-m-d"); 
		$today       = strtotime($todays_date); 
		$expire_date = strtotime($expire);
		
	//	print $expiration_date . "  >  ". $today;
		
		if ($expire_date > $today-4) {
			
			$entry_exists = $harvest->dupReferenceCheck($fileloc,"film");
					
			if($entry_exists == false){
				print "save the film reference to " . $title . " <P>";
				$sArr = GetFilmData($huluitem);
				$filmcount++;
				saveFilmRef($sArr);	
			}else
				$sArr = GetFilmData($huluitem);
						
			$sArr['urlid']= $harvest->buildURLIDfilm($fileloc,$duration);
			$entry_exists = $harvest->dupContentCheck($sArr['urlid'],"film");
				
			
			if($entry_exists->result == false){
				print "save the film " . $title . "  \n \n";
				$sArr = GetFilmDetails($huluitem,$sArr);
				print_r($sArr);
				saveFilm($sArr);	
				
			}else{
				
				$dupcheck = $harvest->dupStreamCheck($entry_exists->myubi,$sArr['provider'],"film");		
				
				if($dupcheck == false){
					print "....[huluDP film stream doesn't exist]...";
					$sArr = GetFilmDetails($huluitem,$sArr);
					$sArr['myubi'] = $entry_exists->myubi;
					print_r($sArr);
					insertFilmStream($sArr);
					$streamcount++;
				}
			}
			
			//exit("got one done");
		
		}else{
				//print_r($huluitem);
			print "============content expired or empty node (possible hulu plus title)===========\n\n     ";
			
		}
		
	}
	
	$end   += 500;
	$start += 500;
	
	if($end > $totalItems){
		print "============ $end is > $totalItems ==========================   ";
		$cycle  = false;
		
	}
	
	
}
	
print "   =============".$filmcount." Films saved.  Total number of items waiting is " . $totalItems;

$notes = $filmcount." films";

$query = "update harvester_index set last_update='".date('Y-m-d')."', notes='".$notes."' where provider='HuluFilms'";
$res   = mysql_query($query);


//===================Functions That Save Films and Episodes =========================================

function GetFilmData($hulu){
	//FILM INFORMATION
	global $harvest;
	global $mds;
	$filmArr   = array();
	
	$img_title 			  = $harvest->concatTitle((string) $hulu->children('hulu', true)->seriesTitle);
	$filmArr['provider']  = "Hulu";
	$filmArr['publisher'] = (string) $hulu->children('hulu', true)->network;
	$filmArr['rating']    = strtoupper((string) $hulu->children('media', true)->rating);
	$filmArr['myubi']     = $harvest->genID();
	$filmArr['title']     = htmlentities((string) $hulu->children('hulu', true)->seriesTitle,ENT_QUOTES, 'UTF-8');
	$filmArr['description']= htmlentities((string) $hulu->children('hulu', true)->seriesDescription,ENT_QUOTES, 'UTF-8');
	$filmArr['urating']   = 0;
	$filmArr['keywords']  = htmlentities((string) $hulu->children('media', true)->keywords,ENT_QUOTES, 'UTF-8');
	$filmArr['genre']     = (string) $hulu->children('hulu', true)->category;
	$filmArr['caption']   = (string) $hulu->children('hulu', true)->caption;
	$filmArr['type']      = 2;
	$filmArr['country']   = "us";	
	$filmArr['url']       = (string) $hulu->children('hulu', true)->siteLink;
	$filmArr['pubDate']   = ParsePubDate((string) $hulu->pubDate);
	$filmArr['year']      = ParseYear((string) $hulu->pubDate);
	$filmArr['title_c']   = htmlentities((string) $hulu->title,ENT_QUOTES, 'UTF-8');
	$filmArr['fileloc']   = $harvest->getFileloc($img_title,$filmArr['type']);
	
	$imdbID = $harvest->getIMDBid(html_entity_decode($filmArr['title']));
	if($imdbID  != "error"){
		$imdbLink = "http://www.imdb.com/title/" .$imdbID ;

		$filmArr  = $mds->scrapeIMDBmain_arr($imdbLink,$filmArr,true);
	}else
		$filmArr['synopsis'] = substr($filmArr['description'],0,200)."...";
	
	
	$filmArr['poster']    = "http://ib1.huluim.com/movie/".(string) $hulu->children('hulu', true)->id."?size=220x318";
	$filmArr['thumb']     = SelectThumbnail($hulu->children('media', true)->thumbnail);
	$filmArr['showthumb'] = SelectFilmShowthumb($filmArr['url']);
	$imgbase              = explode('=',$filmArr['showthumb']);
	$filmArr['keyart']    = $imgbase[0] . "=512x288";
	
	$filmArr['keyart']    = $harvest->saveImageNew($img_title,$filmArr['provider'],$filmArr['keyart'],"key_art");
	
	$filmArr['poster']    = $harvest->saveImageNew($img_title,$filmArr['provider'],$filmArr['poster'],"poster");

	$filmArr['showthumb'] = $harvest->saveImageNew($img_title,$filmArr['provider'],$filmArr['showthumb'],"show_thumbnail");
	
	$filmArr['thumb']      = $harvest->saveImageNew($img_title,$filmArr['provider'],$filmArr['thumb'],"thumbnail",$filmArr['myubi']);
	
	if(array_key_exists('trailer')){
		//print "already got trailer';
	}else
		$filmArr['trailer'] = $harvest->getFilmTrailer(str_replace("_","-",$img_title));
	
	
	if($filmArr['rating']  == "NOT RATED")
		$filmArr['rating'] = "NR";
    	
	return $filmArr;
}

function GetFilmDetails($hulu,$sarr){
	global $harvest;
	
	$fArr = array();
	$fArr = $sarr;
	
	$fArr['myubi']     = $harvest->genID();
	$fArr['expire']    = ParseExpire((string) $hulu->children('dcterms', true)->valid);
	$fArr['duration']  = (string) $hulu->children('media', true)->content->attributes()->duration;
	$fArr['embed']     = "HuluDP," . PullEmbed((string) $hulu->children('hulu', true)->embed);
	$fArr['mobile']    = (string) $hulu->children('hulu', true)->portableEmbed;
	
	preg_match('/src=\"(.*)\"/U', $fArr['mobile'], $mobile);
	$fArr['mobile']    = $mobile[1];
	$fArr['quality']   = CheckQuality((string) $hulu->children('media', true)->content->attributes()->framerate);
	$fArr['cid']  	   = 0;
	$fArr['pid']  	   = 2;
	$fArr['language']  = "en";

	return $fArr;
}




function saveFilmRef($saveData){
global $t_suffix;
	$query = "insert into film_reference".$t_suffix." (MYUBI_ID,TITLE,TYPE,DESCRIPTION,SYNOPSIS,PUBLISHER,URL,SHOWTHUMB,POSTER,KEYART,GENRE,USERRATING,RATING,KEYWORDS,FILELOC,CAPTIONS,YEAR,CAST,COUNTRY) VALUES ('".$saveData['myubi']."','".mysql_escape_string($saveData['title'])."',".$saveData['type'].",'".mysql_escape_string($saveData['description'])."','".mysql_escape_string($saveData['synopsis'])."','".$saveData['publisher']."','".$saveData['url']."','".$saveData['showthumb']."','".$saveData['poster']."','".$saveData['keyart']."','".$saveData['genre']."','".$saveData['urating']."','".$saveData['rating']."','".mysql_escape_string($saveData['keywords'])."','".$saveData['fileloc']."','".$saveData['caption']."','".$saveData['year']."','".$saveData['actors']."','".$saveData['country']."')";
		 
		if (mysql_query($query)) {
	
			print "<p>============".$saveData['title']." FILM ref INSERTED=================<p>";
			//print_r($saveData);
		} else {
			echo "****[[[Something went wrong with Film Ref: ".mysql_error()." ]]]";
		
		}
			
}


function saveFilm($saveData){
global $t_suffix;
global $harvest;
	$query = "insert into film_content".$t_suffix." (MYUBI_ID,TITLE,TYPE,DESCRIPTION,SYNOPSIS,PUBLISHER,URL,THUMB,SHOWTHUMB,POSTER,GENRE,RATING,FILELOC,YEAR,URLID,PUBDATE,DURATION,USERRATING) VALUES ('".$saveData['myubi']."','".mysql_escape_string($saveData['title_c'])."',".$saveData['type'].",'".mysql_escape_string($saveData['description'])."','".mysql_escape_string($saveData['synopsis'])."','".$saveData['publisher']."','".$saveData['url']."','".$saveData['thumb']."','".$saveData['showthumb']."','".$saveData['poster']."','".$saveData['genre']."','".$saveData['rating']."','".$saveData['fileloc']."','".$saveData['year']."','".$saveData['urlid']."','".$saveData['pubDate']."','".$saveData['duration']."','".$saveData['urating']."')";
		 
		if (mysql_query($query)) {
	
			print "<p>============".$saveData['title']." FILM content INSERTED=================<p>";
			//print_r($saveData);
			insertFilmStream($saveData);
			if($saveData['trailer'] != "" && $saveData['trailer'] != "error"){
				$saveData['myubi'] = $harvest->genID();
				$saveData['embed'] = $saveData['trailer'];
				$saveData['mobile']= $saveData['trailer'];
				saveFilmTrailer($saveData);
			}
				
		} else {
			echo "****[[[Something went wrong with Film content: ".mysql_error()." ]]]";
		
		}
			
}

function saveFilmTrailer($saveData){
global $t_suffix;
	$query = "insert into film_trailers".$t_suffix." (MYUBI_ID,TITLE,TYPE,SYNOPSIS,PUBLISHER,URL,THUMB,SHOWTHUMB,POSTER,GENRE,FILELOC,YEAR,URLID,PUBDATE,DURATION) VALUES ('".$saveData['myubi']."','".mysql_escape_string($saveData['title_c'])."',".$saveData['type'].",'".mysql_escape_string($saveData['synopsis'])."','".$saveData['publisher']."','".$saveData['url']."','".$saveData['thumb']."','".$saveData['showthumb']."','".$saveData['poster']."','".$saveData['genre']."','".$saveData['fileloc']."','".$saveData['year']."','".$saveData['urlid']."','".$saveData['pubDate']."','".$saveData['duration']."')";
		 
		if (mysql_query($query)) {
	
			print "<p>============".$saveData['title']." FILM Trailer INSERTED=================<p>";
			//print_r($saveData);
			insertFilmStream($saveData);
			
		} else {
			echo "****[[[Something went wrong with Film content: ".mysql_error()." ]]]";
		
		}
			
}

	function insertFilmStream($e){
		//global $logger;
		global $t_suffix;
		
		$query = "INSERT INTO film_streams". $t_suffix . " (myubi_id, url_hi,url_lo,captions, provider, aspect, quality, pid, cid, segment,expire,language) VALUES ('".$e['myubi']."', '".$e['embed']."', '".$e['mobile']."','".$saveData['caption']."', '".$e['provider']."', 9, '".$e['quality']."', ".$e['pid'].", '".$e['cid']."', '0','".$e['expire']."','".$e['language']."')";
		
		if (mysql_query($query)) {
			print "<p>============Film STREAM" . $e['title'] ." INSERTED=================<p>";
		
		} else 
			print "saveFilmStream:InsertError: -> Something wrong with " . $query . " ---: ".mysql_error();
	}

//==========================Misc Functions that Support Code Above==========================

function GetTotalItems($response){
	
	$response = str_replace("A feed containing","",$response);
	$response = trim(str_replace("videos from Hulu","",$response));
	
	$itemSplit= explode(" of ",$response);
	
	return $itemSplit[1];
}

function GetLastDate(){
	$query = "select last_update from harvester_index where PROVIDER='HuluFilms'";
	$date  = mysql_query($query);
	$_date = mysql_fetch_array($date);
	
	$dataparts = explode("-",$_date['last_update']);
	$lastdate  = $dataparts[2]."-".$dataparts[0]."-".$dataparts[1]."T00:00:00";
	
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

function SelectThumbnail($imgfeed){
	$thumburl ="";
	
	foreach($imgfeed as $thumb){
		
		if($thumb->attributes()->width == "384"){
			$thumburl = (string)$thumb->attributes()->url;
		}
	}
	
	return $thumburl;
}

function SelectFilmShowthumb($url){
	$str = file_get_contents($url);
	preg_match_all('/<meta property=\"og:url\" content=\"(\'??)([^\' >]*?)\\1[^>]*\"\/>/siU', $str, $metaOg);
	$urlp = explode('/',$metaOg[2][0]);
	
	preg_match('/\"video_id\":\"'.$urlp[4].'\",\"show_id\":(.*),\"dp_identifier\":\"hulu\"/U', $str, $matches)	;
	$image = 'http://ib1.huluim.com/show/'.$matches[1].'?size=220x124';
	
	return $image;
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

