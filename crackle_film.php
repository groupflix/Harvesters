<?php
error_reporting(E_ERROR);
ini_set("memory_limit","128M");
include '../IO/FileType.php';
include '../utils/DateConverter.php';
include 'com/HarvestMethods.php';
include 'com/MetaDataSources.php';
include '../db/DBconfig.php'; 
include 'Scrape.php';

require_once('/opt/myubi/Log4php/Logger.php');
Logger::configure('/opt/myubi/Log4php/resources/appender_crackle.properties');

$logger		= Logger::getRootLogger();
$dbconfig 	= new DBconfig();
$harvest 	= new HarvestMethods();
$mds	 	= new MetaDataSources();
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



$url = 'http://www.crackle.com/rss/media/Zm14PTUwMDAmZmNtdD04MiZmcD0xJmZ4PQ.rss';
$streamcount = 0;
$filmcount = 0;

$logger->info("PHP Harvester : crackle.php  Getting url Data:" . $url);
$xml= $harvest->getRSS($url);
$logger->info("PHP Harvester : crackle.php  Done pulling data from " . $url);

foreach($xml->channel->item as $citem){
	$fObj = new stdclass;
	
	$fObj->title  	= htmlentities((string) $citem->children('media', true)->category,ENT_QUOTES, 'UTF-8');
	$content	    = $citem->children('media', true)->content->attributes();
	$fObj->duration = (string) $content['duration'];
    $fObj->provider	= "Crackle";
	$fObj->img_title= $harvest->concatTitle(html_entity_decode($fObj->title,ENT_QUOTES, 'UTF-8'));
	$fObj->fileloc  = $harvest->getFileloc($fObj->img_title,2);
	
	$fObj->expire   = $harvest->addDate(date('Y-m-d'),1);
	$todays_date    = date("Y-m-d"); 
	$today          = strtotime($todays_date); 
	$expire_date    = strtotime($fObj->expire);
	
//	print $expiration_date . "  >  ". $today;
	
	if ($expire_date > $today-4) {
		
		$entry_exists = $harvest->dupReferenceCheck($fObj->fileloc,"film");
		
		$fObj = GetFilmData($citem,$fObj);
		if($entry_exists == false){
			print "save the ref " . $fObj->title . " <P>";
			$filmcount++;
			saveFilmRef($fObj);	
		}
			
					
		$fObj->urlid  = $harvest->buildURLIDfilm($fObj->fileloc,$fObj->duration);
		$entry_exists = $harvest->dupContentCheck($fObj->urlid,"film");
		
		$fObj = GetFilmDetails($citem,$fObj);
		
		if($entry_exists->result == false){
			print "save the content" .$fObj->title . "  \n \n";
			print_r($fObj);	
			saveFilm($fObj);	
		}else{
			$dup_check = $harvest->dupStreamCheck($entry_exists->myubi,$fObj->provider,"film");		
			
			if($dup_check == false){
				print "....[crackle film stream doesn't exist]...";
				$fObj->myubi = $entry_exists->myubi;
				insertFilmStream($fObj);
				$streamcount++;
			}
		}
		
		
	
	}else
		print "============content expired or empty node===========\n\n     ";

	
}

function GetFilmData($crackle,$filmObj){
	global $harvest;
	global $mds;
	print "..GETTING FILM DATA....";
	$fObj = new stdClass;
	$fObj = $filmObj;

	$fObj->myubi 	= $harvest->genID();
	$fObj->url 		= (string)$crackle->link;
  	$fObj->rating 	= (string) $crackle->children('media', true)->rating;
 	$fObj->keywords	= htmlentities($crackle->children('media', true)->keywords,ENT_QUOTES, 'UTF-8');
	$fObj->synopsis	= htmlentities($crackle->children('media', true)->description,ENT_QUOTES, 'UTF-8');
 $fObj->description = $fObj->synopsis;
	
	$content		= $crackle->children('media', true)->content->attributes();
	$_pubdate		= explode(" ",$crackle->pubDate);
	$year       	= $_pubdate[3];
	$fObj->pubdate  = formatPubDate($_pubdate);
	$fObj->type 	= 2;
    $fObj->quality 	= "SD";
    $fObj->publisher= "Unknown";
    $fObj->country 	= "US";
    $fObj->urating  = (int)$crackle->children('media', true)->popularity;
    $fObj->embed 	= $fObj->provider .",".$fObj->url;
	
	
	$imgassets      = $harvest->googleImageCheck((string) $crackle->children('media', true)->category,"movie");
	$thumbs         = $crackle->children('media', true)->thumbnail;
	$fObj->showthumb= (string) $thumbs[2]->attributes();
    $fObj->thumbnail= $fObj->showthumb;	
	$fObj->keyart   = $imgassets[0]; 
	$fObj->poster   = $imgassets[3];
	
	//IMDB PULL, for additional metadata========================================
	$imdbShort = $harvest->pullIMDB((string) $crackle->children('media', true)->category,"title");

	if($imdbShort != "error"){
		print "imdb here " . $imdbShort->{'imdburl'};
		$extrating   	  = (int) $imdbShort->{'rating'} / 10;
		$fObj->urating    = ($fObj->urating + ($extrating * 5))/2;
		$fObj->genre      = strtolower($imdbShort->{'genres'});
		$fObj->actors     = "";
		$imdbLink         = $imdbShort->{'imdburl'};
	
		//could be used, but the api delivers enough info as is
		$fObj = $mds->scrapeIMDBmain($imdbLink,$fObj,true);

		$fObj->publisher= $fObj->network;
		
		if($fObj->synopsis == "")
			$fObj->synopsis	= substr($fObj->description,0,160) . "...";
			
	    if($year != "")
			$fObj->year	= $year ;
			
	}else{
		$fObj->genre  = parseGenre($fObj->keywords);
		$fObj->actors = parseActors($fObj->keywords);
	}
	
	if(property_exists($fObj,'trailer')){
		//print "already got trailer';
	}else
		$fObj->trailer = $harvest->getFilmTrailer(str_replace("_","-",$fObj->img_title));
	

  return $fObj;
}

function GetFilmDetails($crackle,$filmObj){
	global $harvest;
	$fObj = new stdClass;
	$fObj = $filmObj;
	
	$fObj->myubi   = $harvest->genID();
	$fObj->mobile  = $fObj->embed;
	$fObj->cid 	   = 0;
	$fObj->pid 	   = 1;
	$fObj->language= 'en';
	$fObj->caption = 0;

	return $fObj;
}





function parseGenre($keywords){
$_genres = array("Anime","Comedy","Documentary","Crime","Action","Animated","Sci-Fi","Romance","Drama","Dramedy","Classic");	//default set of genres from TV shows
	
	$genres = "";
	$first = false;  		
	for($i=0;$i<count($_genres);$i++){									
		
		$_gpos = strripos($keywords,$_genres[$i],0);
		if($_gpos && !$first){
			$genres  = $_genres[$i];
			$first = true;
		}elseif($_gpos)
			$genres .= ",".$_genres[$i];
		
	}
	
	return $genres;
}


function parseActors($keyword){
	$actors = $keyword;
	$_cleaner = array("Anime","Comedy","Documentary","Crime","Action","Animated","Sci-Fi","Romance","Drama","Horror","Thriller","Classic","Columbia Pictures","Sony Classics","Roxie Releasing");

	$_list  = explode(",",$actors);	
	$delete = array_shift($_list);
	$delete = array_shift($_list);	
	$actors = implode("+", $_list);
	$actors = str_replace($_cleaner,"",$actors);
	$actors = str_replace("+",",",$actors);
	$_list  = explode(",",$actors);
	$actors = null;
	$first = true;
	$z=0;
	while($_list[$z] != ""){
		
		if($first){
			$actors = $_list[$z];
			$first =false;
		}else{
			$actors .= ",". $_list[$z];
		}
		
		$z++;
	}
	
	return $actors;
}

function formatPubDate($p){
	global $datecv;
	//Adjust PubDate to Proper Format===========================================
	$datecv->setDay($p[1]);	$datecv->setMonth($p[2]);	$datecv->setYear($p[3]);
	$pubDate = $datecv->dateFormat($p[3],$p[2],$p[1]);
	
	return $pubDate;	
}


function saveFilmRef($f){
global $t_suffix;
global $harvest;

$f->keyart    = $harvest->saveImageNew($f->img_title,"",$f->keyart,"key_art");
$f->poster    = $harvest->saveImageNew($f->img_title,"",$f->poster,"poster");
$f->showthumb = $harvest->saveImageNew($f->img_title,"",$f->showthumb,"show_thumbnail");
	$query = "insert into film_reference".$t_suffix." (MYUBI_ID,TITLE,TYPE,DESCRIPTION,SYNOPSIS,PUBLISHER,URL,SHOWTHUMB,POSTER,KEYART,GENRE,USERRATING,RATING,KEYWORDS,FILELOC,CAPTIONS,YEAR,CAST) VALUES ('".$f->myubi."','".mysql_escape_string($f->title)."',".$f->type.",'".mysql_escape_string(htmlentities($f->description,ENT_QUOTES, 'UTF-8'))."','".mysql_escape_string(htmlentities($f->synopsis,ENT_QUOTES, 'UTF-8'))."','".$f->publisher."','".$f->url."','".$f->showthumb."','".$f->poster."','".$f->keyart."','".$f->genre."','".$f->urating."','".$f->rating."','".mysql_escape_string(htmlentities($f->keywords,ENT_QUOTES, 'UTF-8'))."','".$f->fileloc."','".$f->caption."','".$f->year."','".$f->actors."')";
		 
		if (mysql_query($query)) {
	
			print "<p>============".$f->title." FILM ref INSERTED=================<p>";
			//print_r($saveData);
		} else {
			echo "****[[[Something went wrong with Film Ref: ".mysql_error()." ]]]";
		
		}
			
}


function saveFilm($f){
global $t_suffix;
global $harvest;

$f->thumbnail = $harvest->saveImageNew($f->img_title,"",$f->thumbnail,"thumbnail",$f->myubi);
	$query = "insert into film_content".$t_suffix." (MYUBI_ID,TITLE,TYPE,DESCRIPTION,SYNOPSIS,PUBLISHER,URL,THUMB,SHOWTHUMB,POSTER,GENRE,RATING,FILELOC,YEAR,URLID,PUBDATE,DURATION,COUNTRY) VALUES ('".$f->myubi."','".mysql_escape_string($f->title)."',".$f->type.",'".mysql_escape_string(htmlentities($f->description,ENT_QUOTES, 'UTF-8'))."','".mysql_escape_string(htmlentities($f->synopsis,ENT_QUOTES, 'UTF-8'))."','".$f->publisher."','".$f->url."','".$f->thumbnail."','".$f->showthumb."','".$f->poster."','".mysql_escape_string(htmlentities($f->genre,ENT_QUOTES, 'UTF-8'))."','".$f->rating."','".$f->fileloc."','".$f->year."','".$f->urlid."','".$f->pubdate."','".$f->duration."','us')";
		 
		if (mysql_query($query)) {
	
			print "<p>============".$f->title." FILM content INSERTED=================<p>";
			//print_r($saveData);
			insertFilmStream($f);
			if($f->trailer!= "" && $f->trailer!= "error"){
				$f->myubi = $harvest->genID();
				$f->embed = $f->trailer;
				$f->mobile= $f->trailer;
				saveFilmTrailer($f);
			}
		} else {
			echo "****[[[Something went wrong with Film content: ".mysql_error()." ]]]";
		
		}
			
}

	function insertFilmStream($e){
		//global $logger;
		global $t_suffix;
		
		$query = "INSERT INTO film_streams". $t_suffix . " (myubi_id, url_hi,url_lo,captions, provider, aspect, quality, pid, cid, segment,expire,language) VALUES ('".$e->myubi."', '".$e->embed."', '".$e->mobile."','".$e->caption."', '".$e->provider."', 9, '".$e->quality."', ".$e->pid.", '".$e->cid."', '0','".$e->expire."','".$e->language."')";
		
		if (mysql_query($query)) {
			print "<p>============Film STREAM" . $e->title." INSERTED=================<p>";
		
		} else 
			print "saveFilmStream:InsertError: -> Something wrong with " . $query . " ---: ".mysql_error();
	}
	




function saveFilmTrailer($f){
global $t_suffix;
	$query = "insert into film_trailers".$t_suffix." (MYUBI_ID,TITLE,TYPE,SYNOPSIS,PUBLISHER,URL,THUMB,SHOWTHUMB,POSTER,GENRE,FILELOC,YEAR,URLID,PUBDATE,DURATION) VALUES ('".$f->myubi."','".mysql_escape_string($f->title_c)."',".$f->type.",'".mysql_escape_string(htmlentities($f->synopsis,ENT_QUOTES, 'UTF-8'))."','".$f->publisher."','".$f->url."','".$f->thumbnail."','".$f->showthumb."','".$f->poster."','".$f->genre."','".$f->fileloc."','".$f->year."','".$f->urlid."','".$f->pubdate."','".$f->duration."')";
		 
		if (mysql_query($query)) {
	
			print "<p>============".$f->title." FILM Trailer INSERTED=================<p>";
			//print_r($saveData);
			insertFilmStream($f);
			
		} else {
			echo "****[[[Something went wrong with Film trailer: ".mysql_error()." ]]]";
		
		}
			
}



?>
