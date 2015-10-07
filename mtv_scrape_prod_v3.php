<?php
ini_set("memory_limit","128M");
error_reporting(E_ERROR);
include 'com/HarvestMethods.php';
include 'com/MetaDataSources.php';
include 'Scrape.php';
include '../db/DBconfig.php'; 
include '../utils/DateConverter.php';
include '../IO/FileType.php';
include '/opt/myubi/Log4php/Logger.php';

//===================GLOBAL VARIABLES====================
//global $logger;
global $mtv;
$mtv = "http://www.mtv.com";
$dbconfig   = new DBconfig();
$harvest 	= new HarvestMethods();
$datecv		= &new DateConverter();	
$mds        = new MetaDataSources();	

$host = $dbconfig->getHOST();
$username = $dbconfig->getUSERNAME();
$password = $dbconfig->getPASSWORD();
$db = $dbconfig->getDATABASE();

//require_once('Logger.php');
//Logger::configure('C:\log4php_config.properties');
Logger::configure('/opt/myubi/Log4php/resources/appender_mtv.properties');
$logger = Logger::getRootLogger();

//===================SET DEBUG VARIABLES====================

$tmp = true;

if($tmp){
	$harvest->setTableSuffix('_tmp');
	$t_suffix = "_tmp";
}else
	$t_suffix ="";
	

//===================DATABASE CONNECTION====================

$logger->info("mtv_scrape_prod.php: Starting..." );

$res = mysql_connect($host, $username, $password);
if (!$res) die("Could not connect to the server, mysql error: ".mysql_error());

$res = mysql_select_db($db);
if (!$res) die("Could not connect to the database, mysql error: ".mysql_error());


//==========================================================
$scrape = new Scrape();
// TODO switch back to 9 after
for($i=2; $i<9 ;$i++){
	
	$url = "http://www.mtv.com/ontv/all/?page=".$i;
	$logger->info("\n\n [**************************" . $url . "**************************]");
		//=============PULL SHOWS FROM PAGE===========================================================
		$scrape->fetch($url);

		$data = $scrape->removeNewlines($scrape->result);
		$start = '<li class="group">';
		$end = "<\/li>";

		$rows = $scrape->fetchAllBetween($start,$end,$data,true);

		//=============PULL FULL EP LINK AND IMAGES===================================================	
		$regexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>";
		$showObject    			= new stdclass;
	
		foreach($rows as $showpage){
			$logger->info("\n\n _____________________________________________________________________________________________");
			$obj    			= new stdclass;
			
		
				if(preg_match_all("/$regexp/siU", $showpage, $matches)) {
						
	
					$obj->title			 = findTitle($matches[3][0]);
					$obj->link 			 = $mtv.$matches[2][0];
					$obj->img_string	 = findImageString($matches[0][0], $obj->link);
					$obj->img_title 	 = $harvest->concatTitle($obj->title);
					$obj->fileloc 	     = $harvest->getFileloc($obj->img_title,1);	//consider this as episode content
					
			
					$res = getShowData($obj,"episode");
					
				}
		
			
		}
		
}

$query = "update harvester_index set last_update='".date('Y-m-d')."' where provider='MTV'";
$res   = mysql_query($query);



function getShowData($showObject,$type){
	global $harvest;
      global $logger;
	global $mtv;
	global $mds;
	//===================Episode Reference Object===================== 
	$epObject   = new stdclass;
	$epObject	= $showObject;
	
    $epObject->cast	 		= '';
    $epObject->description 	= '';
    $epObject->genre     	= '';
    $epObject->keywords		= '';
    $epObject->network		= "MTV";
    $epObject->rating		= 'TV-14';
    $epObject->synopsis		= '';
    $epObject->userrating	= 0;
    $epObject->year			= '';    
    $epObject->type			= "1";
    $epObject->url			= $showObject->link;
	$epObject->title		= $showObject->title;
    $epObject->myubi        = $harvest->genID();
    $epObject->fileloc		= $showObject->fileloc;
    
	  //===================Added for Episode Content Object===================== 
    $epObject->actors		= '';
 	$epObject->country 		= "US";
 	$epObject->duration		= '';
 	$epObject->episode		= '';
 	$epObject->episodeTitle = '';
 	$epObject->epnum		= '';
	$epObject->pubDate   	= '';
	$epObject->publisher 	= 'Viacom';
	$epObject->season		= '';
	$epObject->ubipoints 	= 0;
	$epObject->urlid		= '';
	
    //===================Added for Episode Stream Object===================== 
    $epObject->aspect	= 9;
    $epObject->caption 	= 0;
    $epObject->cid		= "0";
	$epObject->expire  	= $harvest->addDate(date('Y-m-d'),3);
	$epObject->fee		= "0";
	$epObject->language	= 'en';
	$epObject->pid	 	= 13;
    $epObject->provider	= "Viacom";
    $epObject->quality 	= "SD";
    $epObject->segment	= '';
    $epObject->url_hi	= '';
    $epObject->url_lo	= '';

	//===================Images for All===================== 
	$epObject->thumb		= '';
	$epObject->showthumb	= '';
	$epObject->keyart		= '';
	$epObject->poster		= '';
	$epObject->img_title  = $harvest->concatTitle($epObject->title);
	$epObject->img_string = $showObject->img_string;
		
		
	//===================Retrieving Episode Data===================== 
	$logger->info("mtv_scrape_prod.php:getShowData - Link: " . $epObject->url);	
	$tags     = get_meta_tags($epObject->url);
	
	$fulleps  = $tags['mtvn_fullep_lnk'];
	$fulleps  = $mtv.$fulleps;
	$c        = file_get_contents($fulleps);		

	$entryplaced = false;
	$num_urls = null;
	$season   =	null;
	$seasons  = null;
	$series   = null;
	

	
	$seasons  = array();
	// Check for Episodes
	
	if(stripos($fulleps,"fulleps")===false) {
		$logger->info("mtv_scrape_prod.php:getShowData - No Shows: " . $epObject->url . " skipped!");
		return "no shows";
	}
	
	preg_match('/<div class="group-b">(.*)<\/div>/siU',$c,$eplist);
	$logger->info("mtv_scrape_prod.php:getShowData - Printing eplist " . $eplist);
	//print_r($eplist);

	// **********SEASONS AVAILABLE**********
	if(stripos($fulleps,"season") > 0){ 
		print 'search from link ' . $fulleps;
		$scrape = new Scrape();
		$scrape->fetch($fulleps);
		$data = $scrape->removeNewlines($scrape->result);
		$start = '<option value=\"\/global(.*)\">';
		$end = "<\/option>";
		$_seasons = $scrape->fetchAllBetween($start,$end,$data,true);
		
		$logger->debug("mtv_scrape_prod.php:getShowData - Seasonal show. ");
		//print_r($_seasons);
		
		$i=0;
		foreach($_seasons as $num){
	
			$num = trim($num);
			$numpos = stripos($num,"Season ");
			$num = strip_tags(substr($num,$numpos+7, strlen($num)));
			$logger->debug("mtv_scrape_prod.php:getShowData - Season: " .$num . " available.");

			array_push($seasons,(int)$num); 
			$seasons[$i] = $num;
			$i++;
		}
		
		
		$epObject->seasons = count($_seasons); 
		
		$num_urls = count($_seasons);
		if($num_urls == 0){
			print "[***************************************---first season of show---*************************************";
			$urldata    = explode('/',$fulleps);
			$seasondata = explode('_',$urldata[5]);
			$season     = (int)trim($seasondata[1]);
			//print_r($urldata);
			//print_r($seasondata);
			if(is_numeric($season) && $season!=0)
				$epObject->season = $season;
			else
				$epObject->season = 1;
				
			$series = false;
			$epObject->seasons = 1;
			$num_urls = 1;
		}else
			$series = true;

		// **********NOT SEASONAL SHOW**********
	}else if(stripos($eplist[0],"Full Episodes")>0){
		$logger->debug("mtv_scrape_prod.php:getShowData - Not seasonal show.  Grabbing single season");
		$season = "1";
		$epObject->season = $season;
		$num_urls   = 1;
		$series     = false;

	}else{
		$num_urls = 0;
		$logger->debug("mtv_scrape_prod.php:getShowData - No Shows: "  . $epObject->url . " skipped!");
		return "no shows";

	}

	//===================Hulu Check===================== 

	$isHulu = false;
	$huluurl = "http://www.hulu.com/".str_replace("_","-",strtolower($epObject->img_title));

	// WE HAVE TO RESOLVE THIS DADDYS GIRL BS - $sObject->img_title != "daddys_girls"
	if(file_get_contents($huluurl)){
		$isHulu = true;
		$logger->debug("mtv_scrape_prod.php:getShowData - is HULU! ");
	}
	
	$epObject->genre       = getGenre($epObject->title,$huluurl,$isHulu);
	$epObject->description = getDescriptions($epObject->url,$huluurl, $epObject->title, $isHulu);
	$epObject->synopsis    = (strlen($epObject->description) > 200 ) ? substr($epObject->description,0,200) . "..." : $epObject->description; 
	$epObject->keywords     = str_replace(" ",", ",$epObject->title);
	

	$logger->info("mtv_scrape_prod.php:getShowData - Going to getIMDBid: " . $object->title);
	$imdbID = $harvest->getIMDBid($epObject->title, 1);
	//=============1. GET IMAGES WITH IMDB ===========================
	if ($imdbID  != "error") {
		$logger->info("mtv_scrape_prod.php:getShowData - getIMDBid found! ");
		$imdbLink = "http://www.imdb.com/title/" .$imdbID ;
		$epObject  = $mds->scrapeIMDBmain($imdbLink,$epObject,true);
	//	$epObject->synopsis = $seriesObj->synopsis;
//		$epObject->pubDate = $seriesObj->pubDate;
//		$epObject->duration = $seriesObj->runtime;
//		$epObject->cast = $seriesObj->actors;
//		$epObject->description = $seriesObj->description;
//		$epObject->rating = $seriesObj->rating;
//		$epObject->genre = $seriesObj->genre;
//		$epObject->keywords = $seriesObj->keywords;
//		$epObject->year = $seriesObj->year;
//		$epObject->poster = $seriesObj->poster;
		
	
	//=============IF NOT IMDB, TRY TO GET IMAGES WITH HULU===========================
	} elseif ($isHulu){
		
		$art = file_get_contents("http://assets.huluim.com/shows/show_thumbnail_".$epObject->img_title.".jpg");
		if($art)
			$epObject->showthumb = "http://assets.huluim.com/shows/show_thumbnail_".$epObject->img_title.".jpg";
		
		$art = file_get_contents("http://assets.huluim.com/shows/key_art_".$epObject->img_title.".jpg");
		if($art)
			$epObject->keyart = "http://assets.huluim.com/shows/key_art_".$epObject->img_title.".jpg";
		
	}
	
	//MAKE SURE WE HAVE SOME SORT OF IMAGES SET
	
	$imageset             = getShowImages(trim(str_replace("MTV's","",$epObject->title)));
	
	if($epObject->showthumb == "")
		$epObject->showthumb  = $harvest->saveImageNew($epObject->img_title,"",$imageset->showthumb,"show_thumbnail");
	else
		$epObject->showthumb  = $harvest->saveImageNew($epObject->img_title,"",$epObject->showthumb,"show_thumbnail");
	
		
	if($epObject->keyart == "")
		$epObject->keyart     = $harvest->saveImageNew($epObject->img_title,"",$imageset->keyart,"key_art");
	else
		$epObject->keyart     = $harvest->saveImageNew($epObject->img_title,"",$epObject->keyart,"key_art");
		
	
	if ($epObject->poster == '')
		$epObject->poster     = $harvest->saveImageNew($epObject->img_title,"",$imageset->poster,"poster");
	else
		$epObject->poster     = $harvest->saveImageNew($epObject->img_title,"",$epObject->poster,"poster");
	
	
/*	print " num urls is " . $num_urls;
	if($num_urls == 0){
		print " found no episodes for some reason";
		exit('stop');
		return;
	}*/
	$checkPage = new Scrape();
	
	
		for($k=0; $k<$num_urls ;$k++){
										
			if($series){
				$spos1    = stripos($fulleps,"season");
				$spos2    = stripos($fulleps,"video.");
				$currentSeason = substr($fulleps,$spos1,$spos2-$spos1-1);
				$newSeason = "season_". $seasons[$k];
				$season  = $seasons[$k];
				$epObject->season = $season;
				$seasonpg = trim(str_replace($currentSeason,$newSeason,$fulleps));	
			}else
				$seasonpg = $fulleps;
			
print 'page to scrape' . $seasonpg;
			$checkPage->fetch($seasonpg);
						
			$data  = $checkPage->removeNewlines($checkPage->result);
			
			$start = '<li id=\"vidlist_(.*)\" (.*)>';
			$end   = "<\/li>";
			$episodes = $checkPage->fetchAllBetween($start,$end,$data,true);
		
			//UPTO THIS POINT epObject is really seriesObject
			$seriesObj  = new stdclass;
			$seriesObj  = $epObject;
			
			print_r('.................retrieve episodes for season ' . $epObject->season);
		   
		
			$show_year = 9999;
			foreach($episodes as $entry){
				//print "[episode]   ";
				$obj = GetEpisodeData($entry,$epObject);
				
				if ((int)$obj->year < (int) $show_year){
					
					$show_year = (int) $obj->year;
					$logger->debug("mtv_scrape_prod.php:getShowData - Updated show year: " . $seriesObj->year);
					
				}
				
			}//end entry loop
			
			$seriesObj->year  = $show_year;
			$seriesObj->myubi = $harvest->genID();
			$ep_ref_exists    = $harvest->dupReferenceCheck($seriesObj->fileloc,"episode");
	
	
			if($ep_ref_exists == false){
				$logger->info("mtv_scrape_prod.php:getShowData - No Reference Present - Inserting : " . $seriesObj->fileloc);
				saveEpisodeRef($seriesObj);
			}else
				print ".....mtv_scrape_prod.php:getShowData - Show ref is already present....";
			
			
			
		}//end season loop
	
	return;
				
}

function GetEpisodeData($entry, $epObject) {
	
	global $harvest;
	global $logger;
	global $mtv;
	
	$logger->debug("mtv_scrape_prod.php:GetEpisodeData - Episode traverse: " . $entry);
					
	//EPISODE TITLE
	preg_match('/maintitle=\"(.+)\"maincontent/i',$entry,$titlepart);
	$eptitle = trim($titlepart[1]);
	$eptitle = explode("|", $eptitle);
	$eptitle = $eptitle[sizeof($eptitle)-1];
	
	if($eptitle == "--")
		$epObject->episodeTitle = "special";
	else
		$epObject->episodeTitle = $eptitle;
	$logger->debug("mtv_scrape_prod.php:GetEpisodeData - Episode title: " . $epObject->episodeTitle);

	$entryplaced = true;
		
	$epObject->myubi        = $harvest->genID();
	
	//MTV URI for XML		
	$uripos = stripos($entry,"mainuri",40);
	$urlpos = stripos($entry,"mainurl",$uripos);
	$numpos = stripos($entry,'list-ep">',30);
	$epnum  = substr ($entry, $numpos+9,strlen($entry)-($numpos+9)-5);
	$uri    = substr($entry,$uripos+9,$urlpos-($uripos+9)-1);
	
	//EPISODE URL
	$urlpos 	= stripos($entry,"mainurl",0)+9;
	$url 		= trim(substr($entry,$urlpos));
	$urlpos2	= stripos($url,"mainviews");
	$url		= $mtv . substr($entry, $urlpos, $urlpos2-1);
	$epObject->url = $url;
			
	//DESCRIPTION
	$descpos	= stripos($entry,"maincontent",0)+13;
	$desc 		= trim(substr($entry,$descpos));
	$descpos2 	= stripos($desc,"mainposted");
	$desc 		= substr($entry, $descpos, $descpos2-1);
	
	$epObject->description = $desc;
	$epObject->synopsis    = $desc;
	$logger->debug("mtv_scrape_prod.php:GetEpisodeData - Description: " . $epObject->description);
	
			
	//EPNUM & EPISODE
	$ep_info 		= explode("|",strip_tags($entry));
	$numpos			= stripos($entry,'list-ep">',30);
	$epnum  		= substr ($entry, $numpos+9,strlen($entry)-($numpos+9)-5);
	$epArray		= getEpisodeNum($ep_info, $epnum, $epObject->img_title);
	
	$epObject->epnum   = $epArray['epnum'];
	
	$logger->debug("mtv_scrape_prod.php:GetEpisodeData - Episode: " . $epObject->episode);
	$logger->debug("mtv_scrape_prod.php:GetEpisodeData - Epnum: " . $epObject->epnum);
	
	
	//PUBDATE
	$pdatepos		= stripos($entry,"mainposted",0)+12;
	$pubdate 		= trim(substr($entry,$pdatepos));
	$pdatepos2 		= stripos($pubdate,"mainuri");
	$pubdate 		= substr($entry, $pdatepos, $pdatepos2-1);
	$logger->debug("mtv_scrape_prod.php:GetEpisodeData - pubdate: " . $pubdate);
	 
	$pubdate_bits  = explode("/", $pubdate);

	if (strlen($pubdate_bits[0]) == 1) 
		$pubdate_bits[0] = '0'.$pubdate_bits[0];
	
	if (strlen($pubdate_bits[1]) == 1) 
		$pubdate_bits[1] = '0'.$pubdate_bits[1];
	
	if (strlen($pubdate_bits[2]) == 2)
		$pubdate_bits[2] = '20'.$pubdate_bits[2];
	
	$epObject->pubDate = trim($pubdate_bits[2]).'-'.trim($pubdate_bits[0]).'-'.trim($pubdate_bits[1]);
	$epObject->year    = $pubdate_bits[2];
	$logger->debug("mtv_scrape_prod.php:GetEpisodeData - pubdate dateFormatted: " . $epObject->pubDate);
		
			
	//OTHER EPISODE DATA FROM THE ACTUAL PAGE
	$eptags 			 = get_meta_tags($url);
	$epObject->keywords  = str_replace("Video, Free, MTV","",$eptags['keywords']);
	$eptitle 			 = $eptags['mtvn_title'];
	$epObject->thumb  	 = $eptags['thumbnail'];
	$epuri 			     = $eptags['mtvn_uri'];
	$logger->debug("mtv_scrape_prod.php:GetEpisodeData - keywords: " . $epObject->keywords);
	$logger->debug("mtv_scrape_prod.php:GetEpisodeData - thumb: " . $epObject->thumb);
		
	
	$html		 = file_get_contents_curl($url); 
	$doc		 = new DOMDocument(); 
	@$doc->loadHTML($html); 
	
	if($epObject->thumb ==""){
		$metas 	     = $doc->getElementsByTagName('meta'); 
		
		print_r($meta);
		
		for ($i = 0; $i < $metas->length; $i++){ 
			$meta = $metas->item($i); 
			
			if($meta->getAttribute('property') == 'og:image') 
				$epObject->thumb  = $meta->getAttribute('content'); 
			
		} 
	}	
	
	
	//EMBED
	@$doc->loadHTML($html); 
	$metas 	     = $doc->getElementsByTagName('meta'); 
	
	for ($i = 0; $i < $metas->length; $i++){ 
	    $meta = $metas->item($i); 
		
	    if($meta->getAttribute('property') == 'og:video') {
			$embed 			  = $meta->getAttribute('content'); 
			$epObject->url_hi = "mtv,".$embed;
			$epObject->url_lo = "mtv,". $embed;
		}
	} 
	
	
	if($epObject->url_hi ==""){
	
		
		$metas 	     = $doc->getElementsByTagName('link'); 
		
		for ($i = 0; $i < $metas->length; $i++){ 
			$meta = $metas->item($i); 
			
			if($meta->getAttribute('rel') == 'video_src') {
				$embed 			  = $meta->getAttribute('href'); 
				$epObject->url_hi = "mtv,".$embed;
				$epObject->url_lo = "mtv,". $embed;
			}
		} 
	}
	$logger->debug("mtv_scrape_prod.php:GetEpisodeData - embed: " . $epObject->url_hi);
			
	//DURATION	
	$xml = $harvest->getRSS("http://media.mtvnservices.com/player/config.jhtml?uri=".$uri);
	foreach($xml->player->feed->rss->channel->item as $showinfo){

		$rt = (int) $showinfo->children('media', true)->group->content->attributes()->duration;
		//$logger->debug("mtv_scrape_prod.php:GetEpisodeData - duration: " . $rt);
		$runtime += $rt;	
				
	}
	
	$epObject->duration = $runtime;


	$epObject->urlid = $harvest->buildURLIDepisode($epObject->season,$epObject->epnum , $epObject->fileloc);
	$entry_exists    = $harvest->dupContentCheck($epObject->urlid,"episode");
			
	if($entry_exists->result == false){
		$epObject->thumb 	 = $harvest->saveImageNew($epObject->img_title,"",$epObject->thumb,"thumbnail",$epObject->myubi);
		
		echo "..[INSERTING this episode of " . $epObject->fileloc . " [Episode]: " . $epObject->episode . " [Season]: " . $epObject->season ." ].. ";
		insertEpisode($epObject);
		
	} else {
		
		echo "..[ ". $epObject->title . " has already been saved lets check for stream. ]..";
		$dupcheck = $harvest->dupStreamCheck($entry_exists->myubi,$epObject->provider,"episode");
							
		if($dupcheck == false){
			$epObject->myubi = $entry_exists->myubi;
			echo "..[ Episode stream doesn't exist. INSERTING!!!! ]...";
			insertEpisodeStream($epObject);
			
		}else
			echo("[\/mtv_scrape_prod.php:GetEpisodeData - We already have this episode stream too./\]");	
	}
	
	return $epObject;
}


function findTitle($t) {
	
	 global $logger;
 	$title = $t;
 
	if(stripos($t,"/>") > 0){
		$_pos = stripos($t,"/>");
		$title = substr($t,$_pos+2,strlen($t)-1);
	}
						
	//ensure that the season number isn't tacked onto the title					
	if(strpos($title,"(") > 0){			
		$paren = strpos($title,"(");	
		$title=substr($title,0,$paren-1);
		$title=trim($title);
	}
	
	return $title;
	
}

function findImageString($s, $link) {
	
	global $logger;
	global $mtv;
	$logger->debug("mtv_scrape_prod.php:findImageString - Parsing: " . $s);
	
	$img_string;
	
	//Getting the bigger image
	if(preg_match_all("/src=\"(.*)\"/siU", $s, $srcmatch)) {
		$respos = explode("/",substr($srcmatch[0][0],5,strlen($srcmatch[0][0])-2));
							
		$img_string = substr($srcmatch[0][0],5,strlen($srcmatch[0][0])-2);
		$img_string = str_replace($respos[count($respos)-1],"281x211.jpg",$img_string);
		
	}
						
	if(file_get_contents($img_string)){				//incase our conversion to a larger image above doesnt work, we can pull a default thumbnail from metadata
	}else{
		$tags = get_meta_tags($link);
	
		$img_string  = $tags['thumbnail'];
		$img_string   = $mtv.$img_string;
							
	}
	
	$logger->debug("mtv_scrape_prod.php:findImageString - Result: " . $img_string);
	return $img_string;
}


function getEpisodeNum($ep_info, $epnum, $_img_title) {
	
	$epArray = array();
	$epArray['epnum'] = $epnum;
	$epArray['episode'] = '';
	$episode = '';
	
	if(stripos($ep_info[1],"Ep. ",0) && $epArray['epnum'] == "--"){
		$epArray['epnum']= str_replace("Ep. ","",$ep_info[1]);
		if($ep_info[2]){
			$episode = trim($ep_info[2]);
		}else{
			$episode = trim($ep_info[0]);
		}
	}elseif($epArray['epnum'] == "--"){		
		$epArray['epnum'] ="x";
		if($ep_info[2]){
			$episode = trim($ep_info[2]);
		}elseif($ep_info[1]){
			$episode = trim($ep_info[1]);
		}else{
			$episode = trim($ep_info[0]);
		}					
	}else{						
		if($ep_info[2]){
			$episode = trim($ep_info[2]);
		}elseif($ep_info[1]){
			$episode = trim($ep_info[1]);
		}else{
			$episode = trim($ep_info[0]);
		}
							
	}
										
	if((string) strtolower(substr($episode,0,strlen(str_replace("_"," ",$_img_title)))) ==  (string) strtolower(str_replace("_"," ",$_img_title))){
		$episode = trim(substr($episode,strlen(str_replace("_"," ",$_img_title))+1));
	}
	
	$epArray['episode'] = $episode;
	
	return $epArray;
}

function getGenre($title,$huluurl,$isHulu){

	$genre;
	if(!$isHulu) {
		$genre = "Reality";
		if(stripos($title,"race") || stripos($title,"dub") || stripos($title,"ride")){
			$genre = "Automotive";
		}else if (stripos($title,"death") || stripos($title,"crank") || stripos($title,"toon")){
			$genre = "Animation";
		}
		
	}
	else {
		$genre = getHuluGenre($huluurl);
	}
	return $genre;

}

function getDescriptions($_url, $_huluurl, $_title, $isHulu) {
	global $logger;
	if (!$isHulu) {
		return getDescription($_url);
	}else {
		$logger->debug("mtv_scrape_prod.php - Getting Hulu Description! ");
		return getHuluDescription($_huluurl);
	}
}

function getDescription($_url){
	global $logger;	
	$logger->debug("mtv_scrape_prod.php:getDescription - Getting description with url: " . $_url);
	$scrape = new Scrape();
	$scrape->fetch($_url);

	$data = $scrape->removeNewlines($scrape->result);
	$logger->debug("mtv_scrape_prod.php:getDescription - data: " . $data);
	
	/*
	 * OLD CODE
	$start = '<ol class="lst photo-alt "><li class=" last last"><div class="title2"/><p>';
	$end = "<\/p>";
	*/

	$start = '<meta property="og:description"content="';
	$end = '"/>';
	
	$rows = $scrape->fetchAllBetween($start,$end,$data,true);
	
	$pos_s1 = stripos($rows[0],'content="');
	$pos_s2 = $pos_s1+9;
	$desc = trim(substr($rows[0], $pos_s2));
	$pos_end = stripos($desc,$end);
	$desc = substr($rows[0], $pos_s2, $pos_end);
	
	/*
	 * OLD CODE
	$desc = strip_tags(html_entity_decode($rows[1]));
	if(strlen($desc) > 430){
		$desc = substr($desc,0,428) . "...";
	}
	*/
		
	$logger->debug("mtv_scrape_prod.php:getDescription - description: " . $desc);
	return $desc;
}

function getHuluDescription($data){

	    $page = file_get_contents($data);
		preg_match('/<meta property="og:description"(.*)?\/>/i',substr($page,0,5000),$meta);

		return trim(substr(str_replace('content="',"",$meta[1]),0,strlen(str_replace('content="',"",$meta[1]))-1));

}
	
function getHuluGenre($data){
	global $logger;	
	$logger->debug("mtv_scrape_prod.php - Getting Hulu genre! ");
		$url = $data;
		$scrape = new Scrape();
		$scrape->fetch($url);
		
		$data = $scrape->removeNewlines($scrape->result);
		
		$rows = $scrape->fetchAllBetween('<div class="info">','</div>',$data,true);
		
		$i=0;
		while($entry = $rows[$i]){
			 
			 if(strripos($entry,"http://www.hulu.com/genres/",0)){
				 $regexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>"; 
			
						if(preg_match_all("/$regexp/siU", $entry, $matches)) {
							
							$_genre = $matches[3][0];
							return $_genre;
						}
						
				  break;
			 }else{
				 //print "no match <br>";
				 $i++;
			 }
				
		}
		
	
}

function getHuluKeywords($_url){
	
	global $logger;	
	$logger->debug("mtv_scrape_prod.php:getHuluKeywords() ");
	$scrape = new Scrape();
	
	$scrape->fetch($_url);
	
	$data = $scrape->removeNewlines($scrape->result);
	
	$rows = $scrape->fetchAllBetween("<div class='tags-content-cell' tid= '(.*?)' cid='(.*?)'>",'</div>',$data,true);

	$i=0;
	$k=0;
	$tags = "";
	$specChar = array(":",",","'","&","%","$","#","!","*","@","?","~","+",".","/","-","[","]","(",")");
	while($entry = $rows[$i]){
		 // print $entry."<br>";
		  
		  $regexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>"; 
			
			if(preg_match_all("/$regexp/siU",  $entry, $matches)) {
				//print_r($matches);
				while($entry = $matches[3][$k]){
					//print $entry . "<br>";
					$match = null;
					for($i=0;$i<count($specChar);$i++){
						if(stripos($entry,$specChar[$i])){$match = 1;}
					}
					
					if($match == 1){
						return $tags;
						break;
					}else{
							if(strlen($tags) == 0){
								$tags .= $entry;
							}else{
								$tags .= "," . $entry;
							}
					}
					
					$k++;
					if($k == 12){break;}
				}
				//print_r($matches);
				return $tags;
		
			}
		$i++;
	}
	
}

function saveEpisodeRef($e){
	global $t_suffix;
	global $logger;
	$query = "insert into episode_reference". $t_suffix . " (myubi_id,title, description, synopsis, network, url, keywords, genre, keyart, poster, showthumb, userrating, cast, rating, fileloc,type,year) VALUES ('".$e->myubi."','".mysql_escape_string(htmlentities($e->title,ENT_QUOTES, 'UTF-8'))."','".mysql_escape_string(htmlentities($e->description,ENT_QUOTES, 'UTF-8'))."','".mysql_escape_string(htmlentities($e->synopsis,ENT_QUOTES, 'UTF-8'))."','".$e->network."','".$e->url."','".mysql_escape_string(htmlentities($e->keywords))."','".mysql_escape_string($e->genre)."','".$e->keyart."','".$e->poster."','".$e->showthumb."','".$e->userrating."','".$e->actors."','".$e->rating."','".$e->fileloc."','".$e->type."','".$e->year."')";
		 $logger->debug("mtv_scrape_prod.php:saveEpisodeRef -  episode_reference insert query: " . $query);
		if (mysql_query($query)) {
			
			//print "<p>============".$e->title." SHOW REF INSERTED=================<p>";
			$logger->debug("mtv_scrape_prod.php:saveEpisodeRef -  episode_reference INSERTED: ");
		
		} else {
			$logger->error("mtv_scrape_prod.php:saveEpisodeRef -  episode_reference insert: " . mysql_error());
		
		}
			
}

function insertEpisode($e){
global $t_suffix;
global $harvest;

 $query = "insert into episode_content". $t_suffix . "(MYUBI_ID,TITLE,EPISODE,SEASON,TYPE,DESCRIPTION,SYNOPSIS,NETWORK,KEYWORDS,COUNTRY,YEAR,URL,SHOWTHUMB,THUMB,POSTER,KEYART,DURATION,GENRE,EPISODETITLE,URLID,PUBDATE,RATING,USERRATING,FILELOC) VALUES ('".$e->myubi."','".mysql_escape_string(htmlentities($e->title,ENT_QUOTES, 'UTF-8'))."','".$e->epnum."','".$e->season."',".$e->type.",'".mysql_escape_string(htmlentities($e->description,ENT_QUOTES, 'UTF-8'))."','".mysql_escape_string(htmlentities($e->synopsis))."','".$e->network."','".mysql_escape_string(htmlentities($e->keywords))."','US','".$e->year."','".$e->url."','".$e->showthumb."','".$e->thumb."','".$e->poster."','".$e->keyart."','".$e->duration."','".mysql_escape_string($e->genre)."','".mysql_escape_string(htmlentities($e->episodeTitle,ENT_QUOTES, 'UTF-8'))."','".$e->urlid."','".$e->pubDate."','".$e->rating."','".$e->userrating."','".$e->fileloc."')";
			
	
				if (mysql_query($query)) {
					print_r($e);
					print "<p>==========Content for ". $e->title." INSERTED=================<p>";
					insertEpisodeStream($e);
				} else
					echo "<strong>Content insert error : ".mysql_error()."</strong>";
	

}

function insertEpisodeStream($e){
		global $t_suffix;
		global $logger;
	
		 $query = "INSERT INTO episode_streams". $t_suffix . " (myubi_id, url_hi, url_lo, provider, aspect, quality, expire, pid, cid, segment, fee,captions,language) VALUES ('".$e->myubi."', '".$e->url_hi."', '".$e->url_lo."', '".$e->provider."', 9, '".$e->quality."', '".$e->expire."', ".$e->pid.", ".$e->cid.", '0', '0','".$e->caption."','".$e->language."')";
		 
		if (mysql_query($query)) {
		 print "<p>============EPISODE STREAM" . $e->epnum ." INSERTED=================<p>";
			   //$logger->error("mtv_scrape_prod.php Mysql Query Error episode_streams" . mysql_error());
			
		} else 
			echo "saveepisodeStream:InsertError: -> Something went wrong: ".mysql_error();
			//$logger->error("mtv_scrape_prod.php Mysql Query Error episode_streams" . mysql_error());
}


function checkEpFiles($_epnum, $_season, $_title){
	global $logger;	
		$query = "select * from episode_content where TITLE='".$_title."' AND EPISODE='".$_epnum."' AND SEASON='".$_season."'";
		$res = mysql_query($query);
				
        	if($res->error) {
                	$logger->error("mtv_scrape_prod.php Message Mysql SQL Query Error" . mysql_error());
        	}


			if(mysql_num_rows($res) > 0){
				$data = mysql_fetch_array($res);
				print "<br><i>duplicate found " . $data['TITLE'] . "</i> <br>";
				$logger->warn("mtv_scrape_prod.php duplicate found " .  $data['TITLE'] );
				print "<br><i>duplicate found " . $data['TITLE'] . "</i> <br>";
				return (bool) false;
			}else{
				print "<br><i>save it now</i> <br>";
				$logger->info("mtv_scrape_prod.php save it now "  );
				return (bool) true;
			}
}



function getShowImages($title){
	global $harvest;
	$img = new stdclass;
	print "...getting images for " . $title ." ...";
	$showthumb 		= $harvest->theTVdb($title,"showthumb");
	$poster			= $harvest->theTVdb($title,'poster');
	$keyart         = $harvest->theTVdb($title,"keyart");
	
	$imgassets = googleImageCheck($title);
	
	
	if($keyart == "error")
		$img->keyart  = $imgassets[0];
	else
		$img->keyart  = $keyart;
	
	if($showthumb == "error")
		$img->showthumb  = $imgassets[1];
	else
		$img->showthumb  = $showthumb;
		
	
	if($poster == "error")
		$img->poster     = $imgassets[3];
	else
		$img->poster     = $poster;
	
	return $img;
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
	
//	print_r($results);
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

?>
