<?php

ini_set("memory_limit","164M");
error_reporting(E_ERROR);
include 'Scrape.php';
include '../db/DBconfig.php'; 
include '../utils/DateConverter.php';
include '../IO/FileType.php';
include 'com/HarvestMethods.php';
include 'com/MetaDataSources.php';
include 'com/simple_html_dom.php';

$dbconfig = new DBconfig();
$scrape   = new Scrape();			
$harvest  = new HarvestMethods();
$mds      = new MetaDataSources();
$notes    = "";
//SET DEBUG VARIABLES=======================================

$tmp = true;
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

$epcount   = 0;
$showcount = 0;


$catPage = file_get_contents("http://fullepisode.info/");

//=============PULL SHOW LIST===========================================================

preg_match_all('/<option class="level-\d{1}" value=\"(\'??)([^\' >]*?)\\1[^>]*\">(.*)<\/option>/siU', $catPage, $mArr);
$mData=array();
	
//CANT FIGURE OUT HOW TO CLEAN THE STRING OF WIERD CHARACTERS
//Have to figure out how to do this, or figure out how to skip titles with odd characters in title


/*for($i = 0; $i < count($mArr[3]); $i++){  //count($mArr[3])
	$tempObj = new stdclass;
	
	$t_str = str_replace("&#8217;", "'", $mArr[3][$i]);
	$t_str = str_replace("&amp", "&", $t_str);
	$t_str = iconv('ASCII', 'UTF-8//IGNORE',html_entity_decode(str_replace("â","'",$t_str),ENT_QUOTES, 'UTF-8'));
	//print  $t_str . ' -----------   ';
	
	if (stripos($t_str, "&#") === FALSE) {		

		$data  = explode("Season",$t_str);
		$piece = explode(" ",trim($data[0]));
	
		$adden = $piece[count($piece)-1];
	
		$entry = str_replace(')',"", str_replace('(',"",$adden));
		
		$tempObj->title  = trim(str_replace(')',"", str_replace('(',"",$data[0])));
		$tempObj->season = trim($data[1]);
		
		if (is_numeric($entry)){
			$tempObj->year    = $entry;
			$tempObj->country = "us";
			$tempObj->title  =  trim(str_replace($tempObj->year,"",$tempObj->title));
		}
		else if(stripos($adden,"(",0)){
			$tempObj->year    = 2011;
			$tempObj->country = substr($entry, 0, 2);
			$tempObj->title  =  trim(str_replace($tempObj->country,"",$tempObj->title));
		}else{
			$tempObj->country = "us";
			$tempObj->year    = 2011;
		}
		
		$entry = str_replace(')',"",str_replace('(',"",$t_str));
	
		$tempObj->myubi   = $harvest->genID();
		$tempObj->urating = 0;
		$tempObj->type    = 1;
		$urladd = strtolower(trim(str_replace(" ","-",ereg_replace("[^A-Za-z0-9 ]", "", $entry))));
		
		$tempObj->showurl  = 'http://fullepisode.info/category/'.$urladd;
		
		$check = file_get_contents($tempObj->showurl);
	
		//stripos($check,'Sorry, the page your requested could not be found, or no longer exists.',0) > 0
		if($check){
			print_r($tempObj);
			array_push($mData,$tempObj);
		
		}else
			print 'show page invalid';
		
	}
	

}*/

/*$path = "/opt/myubi/web/www/harvesters/content/";
//save show list for later;
$FT = &new FileType();
$FT->setFileType("fullepinfo_showlist");
$FT->setFormatType("txt");
$FT->setPath($path);	
$FT->setDATA(json_encode($mData));	
$FT->output_file();*/
print "*=======================  Created Reference List ==================================*";
$showList = file_get_contents('content/fullepinfo_showlist.txt');
$mData    = json_decode($showList);
//=============PULL EPISODE LINK LIST // COMPILE INFO SERIES===========================================================	
	foreach($mData as $seriesObj){
		$seriesObj->title = trim($seriesObj->title);
		
		$epimdbsource ="";
		$showpage = file_get_contents($seriesObj->showurl);
		preg_match_all('/<div class=\"home-post-wrap\">(.*)<div style=\"clear: both\;\"><\/div>/siU', $showpage, $list);
		
	
		//utilize show title to reference imdb and fill in information
		//then use episode link to traverse to page for video embded grab.
		$imdbID = $harvest->getIMDBid(html_entity_decode($seriesObj->title,ENT_QUOTES, 'UTF-8'));
		if($imdbID  != "error"){
			$imdbLink   = "http://www.imdb.com/title/" .$imdbID ;
	
			$seriesObj  = $mds->scrapeIMDBmain($imdbLink,$seriesObj,true);
			
			$seriesObj->epsynopsis ="";
			if($seriesObj->description == ""){
				if($seriesObj->synopsis == "" ||strlen($seriesObj->synopsis) < 150){
					$seriesObj->epsynopsis  = $seriesObj->synopsis;
					$seriesObj->description = "Sorry there is no description available for this show";
					$seriesObj->synopsis    = "Sorry there is no description available for this show";
					
				}else
					$seriesObj->description = str_replace('&#x27;',"'",html_entity_decode($seriesObj->synopsis,ENT_QUOTES, 'UTF-8'));
					
			}else if(strlen($seriesObj->description) < 150){
				$seriesObj->epsynopsis  = $seriesObj->description;
				$seriesObj->description = "Sorry there is no description available for this show";
				$seriesObj->synopsis    = "Sorry there is no description available for this show";
			}
			
			if($seriesObj->keywords == "")
				$seriesObj->keywords = str_replace(" ",", ",$seriesObj->title);
			
		    
			
			//get images 
			$seriesObj->img_title  = $harvest->concatTitle($seriesObj->title);
			$imgObj 			   = getShowImages($seriesObj->title);
		/*	$seriesObj->keyart     = $imgObj->keyart;
			$seriesObj->poster     = $imgObj->poster;
			$seriesObj->showthumb  = $imgObj->showthumb;*/
			$seriesObj->showthumb  = $harvest->saveImageNew($seriesObj->img_title,"",$imgObj->showthumb,"show_thumbnail");
			$seriesObj->keyart	   = $harvest->saveImageNew($seriesObj->img_title,"",$imgObj->keyart,"key_art");
			$seriesObj->poster	   = $harvest->saveImageNew($seriesObj->img_title,"",$imgObj->poster,"poster");
			
		
			
		
			$seriesObj->fileloc    = $harvest->getFileloc($seriesObj->img_title,1);
			
			print_r($seriesObj);
			
			$entry_exists = $harvest->dupReferenceCheck($seriesObj->fileloc,"episode");
			
			if($entry_exists == false)
				saveShowRef($seriesObj);	
			
		
			foreach($list[1] as $episode){
				 $epObj = new stdclass;
				 
				 
				 preg_match('/<div class="date">(.*)<\/div>/siU', $episode, $date);
				 preg_match('/<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU', $episode, $eplink);
				 $eppage = file_get_contents($eplink[2]);
				 preg_match('/<h2>(.*)<\/h2>\n<p>(.*)<\/p>/siU', $eppage, $desc);
				 $showinfo = getSeasonEpisode($eplink[2]);
				 $dateinfo = processPubDate($date[1]);
				 $tempdesc = getDescription($desc);
				 
				 $epObj->embed   = getEmbed($eppage);
				 $epObj->year    = $dateinfo->year;
				 $epObj->pubdate = $dateinfo->pubdate;
				
				 $epObj->url     = $eplink[2];
				 $epObj->title   = $seriesObj->title;
				 $epObj->img_title   = $seriesObj->img_title;
				 
				 $epObj->season  = $showinfo[1];
				 $epObj->epnum   = $showinfo[3];
				 $epObj->episode = $showinfo['episode'];
				 $epObj->description = $tempdesc;
				 
				 $epsource  = $imdbLink."/episodes";
				 $epObj     = scrapeIMDBepisode($epsource,$epObj);
				 
				 
				 $thumbnail = $harvest->theTVdb($epObj->title,"thumbnail",$epObj->season,$epObj->epnum,$epObject->episode);
				if($thumbnail == "error"){
					$imgasset     = googleImageCheck($epObj->title . " episode " . $epObj->episode);
					$epObj->thumbnail = $imgasset[2];
				}else
					$epObj->thumbnail  = $thumbnail;
				 
				 
				 if($epObj->embed){
					
					if($epObj->description == ""){
						if($epObj->synopsis == "" && $seriesObj->epsynopsis == ""){
							print "no synopsis or epsynopsis";
							$epObj->description = "Sorry there is no description available for this video";
							$epObj->synopsis    = "Sorry there is no description available for this video";
						}else if($seriesObj->epsynopsis != ""){
							$epObj->description = str_replace('&#x27;',"'",html_entity_decode($seriesObj->epsynopsis,ENT_QUOTES, 'UTF-8'));
							$epObj->synopsis    = str_replace('&#x27;',"'",html_entity_decode($seriesObj->epsynopsis,ENT_QUOTES, 'UTF-8'));
						}else
							$epObj->description = str_replace('&#x27;',"'",html_entity_decode($epObj->synopsis,ENT_QUOTES, 'UTF-8'));
						
					}
		
					$epObj->myubi       = $harvest->genID();
					$epObj->type        = 1;
					$epObj->country     = $seriesObj->country;
					$epObj->fileloc     = $seriesObj->fileloc;
					$epObj->rating      = $seriesObj->rating;
					$epObj->provider    = 'FullEpInfo';
					$epObj->pid         = 6;
					$epObj->keyart      = $seriesObj->keyart;
					$epObj->showthumb   = $seriesObj->showthumb;
					
					$epObj->caption     = 0;
					$epObj->quality     = "SD";
					$epObj->cid         = 0;
					$epObj->language    = "en";
					$epObj->urating     = $seriesObj->urating;
					$eObj->expire       = $harvest->addDate(date('Y-m-d'),6);
					
					if($epObj->pubdate == ""){
						$epObj->pubdate     = $seriesObj->pubdate;
						$epObj->year        = $seriesObj->year;
					}
						
					if($epObj->duration == "")
						$epObj->duration = 1800;
						
					if($epObj->description == "")
						$epObj->description = $epObj->synposis = html_entity_decode($seriesObj->description,ENT_QUOTES, 'UTF-8');
				
					
				 					
				  if(  $epObj->epnum != "" && $epObj->season != "" && $epObj->episode != ""){
				 
						$epObj->urlid = $harvest->buildURLIDepisode($epObj->season,$epObj->epnum,$epObj->fileloc);
						$entry_exists = $harvest->dupContentCheck($epObj->urlid,"episode");
						
						
						if($entry_exists->result == false){
							print "save this episode of " . $epObj->episode . " <P>";
							print_r($epObj);
							saveEpisode($epObj);
							$epcount++;
						}else{
							print "this ep of " . $epObj->episode . "has " . $entry_exists->myubi ." already been saved lets check for stream <P>";
							
							$dupcheck = $harvest->dupStreamCheck($entry_exists->myubi,$epObj->provider,"episode");
							
							if($dupcheck == false){
								print "....[Fullep stream doesn't exist]...";
								$epObj->myubi = $entry_exists->myubi;
								insertEpisodeStream($epObj);
							}else
								print "we already have this stream too<P> ...";
				
						}
				  }else{
					  print "...[ X  this episode of " . $epObj->episode . " was missing required data /]...";
				  }
				}//if ...can't have episode without embed
				 	
				
			}
		
		
	
		
		}
		

	//	exit("------------end here------------");
	
}
	
function getEmbed($page){
	preg_match_all('/<iframe\s[^>]*style=(\'??)([^\' >]*?)\\1[^>]*><\/iframe>/siU', $page, $list);
	$embed;
	foreach($list[0]  as $iframe){
		if(strripos($iframe,"novamov",0)){
			preg_match('/src=\'(\'??)([^\' >]*?)\\1[^>]*\'/siU', $iframe, $embedlink);
			//finally we need to grab the actual
			$embedpage = file_get_contents(str_replace("&#038;","&",trim($embedlink[2])));
			$start     = strripos($embedpage,'var flashvars = {}',1000);
			$end       = strripos($embedpage,'var params = {}',$start);
			$varset    = substr($embedpage,$start,$end-$start);
			
			$start     = strripos($embedpage,'swfobject.embedSWF',$end);
			$end       = strripos($embedpage,'e.style.visibility',$start);
			$embedvars = substr($embedpage,$start,$end-$start);
			
			 
			$vars 		= explode('flashvars.',$varset);
			$playervars = explode('",',$embedvars);
			
			$player    = str_replace('"',"",trim(str_replace('swfobject.embedSWF(',"",$playervars[0])));
			
			//print_r($vars);
			//print_r($playervars);
			$flashvars = array();
			foreach($vars as $item){
				$temp = str_replace('"',"",$item);
				$temp = str_replace(';',"",$temp);
				array_push($flashvars,trim(str_replace('"',"",$temp)));
			}
			
			$embed = "fullep,".$player ."?" .$flashvars[3] . "&" .$flashvars[4] . "&" .$flashvars[5] . "fullepisodeinfo&" .$flashvars[6];
			//now extra all the needed information to fill in the following (replace devmyubitv with fullepisodeinfo)
				/*domain=http://www.novamov.com&amp;file=800f9173eefa3&amp;filekey=166.250.34.251-e17132e843cdf9e11c93c5ea54068fb0-devmyubitv&amp;advURL=http://www.novamov.com/video/800f9173eefa3*/
				
			//and retrieve player type i.e /player/novaplayerv5.swf
			//now put them together prefixed with fullep,   to create something like
			
			
			break;
		}
	}
	
	return $embed;
}
		
function getDescription($info,$episode){
	$res;
	if(strripos($info[1],"Short Description",0) > 0){
		 $epdesc   = $info[2];
		 //$end      = explode('Episode '.$episode, $desc[1]);
		 //$eptitle  = trim(str_replace("Short Description:","",$end[1]));

		 $res      = htmlentities(trim($epdesc),ENT_QUOTES, 'UTF-8');
		 
		 /*if(strtolower($epObj->episode) != strtolower($title))
			 $epObj->episode = $eptitle;*/

	 }
	 
	 return $res;
}
function getSeasonEpisode($url){
 $urldata  = explode("/",$url);
 $lastnode = explode("season",$urldata[count($urldata)-2]);
 $showinfo = explode("-",$lastnode[1]);
 
 $temp= "";
 $i=0;
 foreach($showinfo as $str){
	 if($i > 3)
		$temp .= ucfirst($str). " ";
		
	 $i++;
 }
 
 $showinfo['episode'] = trim($temp);
 return $showinfo;
}
function processPubDate($d){
	$pdate = new stdclass;
	
	$temp = trim(preg_replace("/[^0-9 ]/", '',$d));
	
	$dateparts = explode(" ",$temp);
	
	$pdate->pubdate = $dateparts[2] ."-".  $dateparts[0]."-".  $dateparts[1];
	$pdate->year    = $dateparts[2];
	
	return $pdate;
}
function scrapeIMDBepisode($link,$epData){
		global $harvest;
		global $mds;
		$epObject  = $epData;
		
		$scrape  = new Scrape();
		$scrape->fetch($link);
		
		//PULL EPISODE BLOCK
		$data   = $scrape->removeNewlines($scrape->result);
		$start  = '<div class=\"list_item(\"??)([^\" >]*?)\\1[^>]*>(.*)';
		$end    = '<\/div>';
		
		$imdbdata  = $scrape->fetchAllBetween($start,$end,$data,true);
		$epimdbLink= "";
		$epimddID  = "";
		for($t = 0; $t < count($imdbdata); $t++){
			$start = strripos($imdbdata[$t],", Ep".$epObject->epnum,0);
			$end   = strripos($imdbdata[$t],"</div>",$start+4);
			$str   = substr($imdbdata[$t],$start+4,$end-$start-4);
			
			if((int)$str == (int)$epObject->epnum){
			
				if(preg_match_all("/<div\s[^>]*data-const=(\"??)([^\" >]*?)\\1[^>]*>/siU", $imdbdata[$t], $matches)) {
					$epimdbLink = "http://www.imdb.com/title/". $matches[2][0];
					$epimddID   = $matches[2][0];
				}
				break;
			}
			
		}
		
		if($epimdbID != ""){
			$imdbLink   = "http://www.imdb.com/title/" .$epimdbID ;
		
			$epObject = $mds->scrapeIMDBmain($imdbLink,$epObject,false);
		}

		return $epObject;
}


function ArrayToString($arr){
	
	$str = $arr[0];
	
	for($i=1; $i<count($arr);$i++){
		
		$str .= ", " . $arr[$i];
		
	}
	
	return $str;
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





function saveShowRef($s){
global $t_suffix;
global $notes;
//global $logger;


$query = "insert into episode_reference".$t_suffix." (MYUBI_ID,TITLE,TYPE,DESCRIPTION,NETWORK,URL,SHOWTHUMB,KEYART,POSTER,GENRE,USERRATING,RATING,FILELOC,KEYWORDS,CAST,YEAR,SYNOPSIS) VALUES ('".$s->myubi."','".mysql_escape_string($s->title)."','".$s->type."','".mysql_escape_string($s->description)."','".$s->network."','".$s->showurl."','".$s->showthumb."','".$s->keyart."','".$s->poster."','".$s->genre."',".$s->urating.",'".$s->rating."','".$s->fileloc."','".mysql_escape_string(htmlentities($e->keywords,ENT_QUOTES, 'UTF-8'))."','".mysql_escape_string($s->actors)."',".$s->year .",'".mysql_escape_string($s->synopsis)."')";
		 
	
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

 $query = "insert into episode_content".$t_suffix." (MYUBI_ID,TITLE,EPISODE,SEASON,TYPE,DESCRIPTION,NETWORK,KEYWORDS,USERRATING,RATING,COUNTRY,YEAR,URL,SHOWTHUMB,THUMB,DURATION,GENRE,EPISODETITLE,URLID,KEYART,PUBDATE,FILELOC,SYNOPSIS) VALUES ('".$e->myubi."','".mysql_escape_string($e->title)."',".$e->epnum.",".$e->season.",".$e->type.",'".mysql_escape_string($e->description)."','".$e->network."','".mysql_escape_string(htmlentities($e->keywords,ENT_QUOTES, 'UTF-8'))."',".$e->urating.",'".$e->rating."','".$e->country."',".$e->year.",'".$e->url."','".$e->showthumb."','".$e->thumbnail."',".$e->duration.",'".htmlentities($e->genre,ENT_QUOTES, 'UTF-8')."','".htmlentities($e->episode,ENT_QUOTES, 'UTF-8')."','".$e->urlid."','".$e->keyart."','".$e->pubdate."','".$e->fileloc."','".mysql_escape_string($e->synopsis)."')";

 
			     if (mysql_query($query)) {
					print "=================INSERT EPISODE CONTENT ".$e->epnum ." SUCCESS ".$e->title ."=================\n ";
						insertEpisodeStream($e);
				} else {
					echo "[\/*************** Something went wrong with content: ".mysql_error()."*************\/]";
					$notes .= $e->title . " show insert failed";
					/*$logger->error("PHP Harvester : cwtv.php Message Mysql Query Error" . mysql_error());
					$repeat = true;*/
				}

}


	function insertEpisodeStream($e){
		global $t_suffix;
		global $logger;
	
		 $query = "INSERT INTO episode_streams".$t_suffix." (myubi_id, url_hi, url_lo, provider, aspect, quality, expire, pid, cid, segment, fee,language,captions) VALUES ('".$e->myubi."', '".$e->embed."', '".$e->embed."', '".$e->provider."', 9, '".$e->quality."', '".$e->expire."', ".$e->pid.", '".$e->cid."', '0', '0', '".$e->language."', '".$e->caption."')";
		 
		if (mysql_query($query)) {
		 print "<p>============EPISODE STREAM" . $e->title ." INSERTED=================<p>";
			   
		} else 
			print_r("PHP Harvester : episode_streams error" . mysql_error());
	}

?>