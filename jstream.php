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
$critical = "";
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
if (!$res) die("Could not connect to the database, mysql error: ".mysql_error());
/*if($res->error) {
		$logger->error("PHP Harvester : jstream.php Message Mysql Connection Error" . mysql_error());
}*/




//=============PULL SHOWS LINK LIST===========================================================
	$showLinks =array();
	$showTitles=array();
	print "[======PULLING SHOW LIST=============]";
		/*$scrape->fetch("http://www.jstream.info/");

		$data  = $scrape->removeNewlines($scrape->result);
		$start = '<ul><li>';
		$end   = "<\/li><\/ul>";

		$lArr  = $scrape->fetchAllBetween($start,$end,$data,true);
		//if more than one list is returned, we have to check to see which one has shows. Also make sure shows were returned
		if(count($lArr) > 1){
			//create  a method to detect if the terms Watch, Online, and http:// are used more than 20 times, if so that is the list we want
			//toss the other lists and save the master to $lArr
		}else if(count($lArr) == 0){
			//report a critical error in harvestor method
		}
		
		if(preg_match_all("/<li><a\s[^>]*href=(\'??)([^\' >]*?)\\1[^>]*>(.*)<\/a><\/li>/siU", $lArr[0], $showArr)) {
			

			//qualify all the links we want, keep consistant.  Current qualifier is .info/watch
			for($i=0; $i<count($showArr[2]); $i++){
				if(strripos($showArr[2][$i],'.info/watch',0) ===false && strripos($showArr[2][$i],'watchthewalkingdeadonline.info/',0) ===false){
					unset($showArr[2][$i]);
					unset($showArr[3][$i]);
				}
			}

			$showLinks  = $showArr[2];
			$showTitles = $showArr[3];
		}
		*/
		$sc = file_get_contents("http://series-cravings.info/");

		$start = stripos($sc,'<div class="entry">',0);
		$end   = stripos($sc,'<p class="postmetadata">',$start);
		$list  = substr($sc,$start,$end-$start);
		
		preg_match_all('/<li>(.*)<\/li>/siU',$list,$matches);
		
		
		//grab the links
		$sLinks = array();
		$sTitles = array();
		foreach($matches[0] as $show){
			
			preg_match("/<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU", $show, $linkinfo);
		
			array_push($sLinks,$linkinfo[2]);
			array_push($sTitles,strip_tags($linkinfo[3]));
			
		}
		
		//print_r($sTitles);
		//see what has been updated
		$loop = 0;
		$showLinks = array();
		$showTitles = array();
		foreach($matches[0] as $show){
	/*		
			$fileloc = $harvest->getFileloc($harvest->concatTitle($title),'episode');
			$check   = mysql_query("SELECT title FROM episode_content_tmp WHERE fileloc='".$fileloc."'");*/
			if($sTitles[$loop]!="30 Rock" && $sTitles[$loop]!="Last Resort" && $sTitles[$loop]!="Castle" && $sTitles[$loop]!="Revolution (2012)" && $sTitles[$loop]!="Touch"){
				if(strripos($show,"ated!!</em>",0)>0 || strripos($show,"<em>New!!</em>",0)>0 || $sTitles[$loop] == "Boss" || $sTitles[$loop] == "Californication" || $sTitles[$loop] == "Entourage" ){
					array_push($showLinks,$sLinks[$loop]);
					array_push($showTitles,$sTitles[$loop]);
				}
			}
			$loop++;	
		}
		
		//print_r($showLinks);
		//exit('stop');
//=============PULL EPISODE LINK LIST // INSERT SERIES===========================================================		
print "[======BEGIN EPISODE SEARCH AND INSERT=============]";
$harvest->progress(".");
print_r($showLinks);
for($c = 0; $c < count($showLinks); $c++){
	$seriesObj = new stdClass;
	$seriesObj->title    = processTitle($showTitles[$c]);
	$seriesObj->imgtitle = $harvest->concatTitle($seriesObj->title);
	$seriesObj->type     = 1;
	$seriesObj->provider = "jStream";
	$seriesObj->url      = $showLinks[$c];
	$seriesObj->caption  = 0;  //not offered by jstream
	$seriesObj->myubi_id = $harvest->genID();
	
       //RETRIEVE KEY SERIES METADATA
	    $mainpage = file_get_contents($showLinks[$c]);
		$epsource = "";
		
	
		
$harvest->progress(".GETTING ". $seriesObj->title . " ..");	
		//make sure title doesnt put the as suffix
		
		
		preg_match_all("/<p>(.*)<\/p>/siU", $mainpage, $matches);
			
			$meta;
			for($i=0; $i<count($matches[0]);$i++){
				
				if(stripos($matches[0][$i],"imdb.com",0) > 0)
					$meta = $matches[0][$i];
				
			}
			
			$data = split("<br />",$meta);
			
			if(count($data) < 2)
				$data = split("<br/>",$meta);
            $genre="";
			$imdb ="";
			
			for($k=0; $k<count($data); $k++){
			
				if(stripos($data[$k],"genre",0) > 0){
					$genre = trim(str_replace(", ",",",str_replace("genre:","",strip_tags(strtolower($data[$k])))));
				}else if(stripos($data[$k],"imdb",0) > 0){
					preg_match_all("/<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU", $data[$k], $url);
						$imdb  = split('/',$url[2][0]);
					
				}
			}
		
$harvest->progress("..imdcode ".$imdb[4].".");			
			$imdbShort = $harvest->pullIMDB($imdb[4],"id");
			$epsource  = "http://www.imdb.com/title/".$imdb[4]."/episodes";
			$imdbReady = false;
	

			if($imdbShort != "error" && levenshtein($seriesObj->title,$imdbShort->{'title'}) < 2){
				
				$extrating   		   = $imdbShort->{'rating'} / 10;
				$seriesObj->userRating = $extrating * 5;
				$seriesObj->title      = $imdbShort->{'title'};
				
				$imdbgenre             = $imdbShort->{'genres'};
				$seriesObj->genre      = processGenre(strtolower($imdbgenre) . ",". strtolower($genre));
				$runtime               = split(',',$imdbShort->{'runtime'});
			
				$seriesObj->runningtime= trim(str_replace("USA:","",str_replace('min',"",str_replace("(approx.)","",$runtime[0])))) * 60;
				$imdbLink              = $imdbShort->{'imdburl'};
$harvest->progress("..");		
				//could be used, but the api delivers enough info as is
				$seriesObj = $mds->scrapeIMDBmain($imdbLink,$seriesObj,true);
		
			}else{
$harvest->progress(".no api.");	
				$imdb       = $harvest->getIMDBid($seriesObj->title);
				$seriesObj  = $mds->scrapeIMDBmain("http://www.imdb.com/title/".$imdb."/",$seriesObj,true);
				$seriesObj->genre      = htmlentities(processGenre(strtolower($seriesObj->genre) . ",". strtolower($genre)));	
				$seriesObj->userRating = 4;
			}
$harvest->progress($seriesObj->title.".");
				if($seriesObj->description == "")
					$seriesObj->description = $seriesObj->synopsis;
			
				$imgObj 			   = getShowImages($seriesObj->title);
				$seriesObj->keyart     = $imgObj->keyart;
				$seriesObj->poster     = $imgObj->poster;
				$seriesObj->showthumb  = $imgObj->showthumb;
			
				$seriesObj->title      = htmlentities($seriesObj->title);
				$seriesObj->fileloc    = $harvest->getFileloc($seriesObj->imgtitle,$seriesObj->type);
		    
		
$harvest->progress("....");	

//RETRIEVE SEASONS FOR THIS SERIES
$seasonArr = array();
$data  = $scrape->removeNewlines($mainpage);
$start = '<h2><strong>(.*)<\/strong><\/h2>(.*)<ul><li>';
$end   = "<\/li><\/ul>";
$sArr  = $scrape->fetchAllBetween($start,$end,$data,true);
$seriesObj->season =count($sArr);

print_r($seriesObj);
$harvest->progress("..PROCESS SEASONS / EPISODES..");	
		
	if($seriesObj->title != "" && ($seriesObj->description != "" || $seriesObj->genre !="")){
		
		$seriesObj->keyart    = $harvest->saveImageNew($seriesObj->imgtitle,"",$seriesObj->keyart,"key_art");
		$seriesObj->showthumb = $harvest->saveImageNew($seriesObj->imgtitle,"",$seriesObj->showthumb,"show_thumbnail");
		$seriesObj->poster    = $harvest->saveImageNew($seriesObj->imgtitle,"",$seriesObj->poster,"poster");
		
		//check for duplicate show
		$entry_exists = $harvest->dupReferenceCheck($seriesObj->fileloc,"episode");
				
		if($entry_exists == false){

			saveShow($seriesObj);	
			
			$showcount++;
		}
		
		print_r($seriesObj);
		
		
	
		preg_match_all('/<h2><strong>.*<\/strong><\/h2>.*<ul>.*<li>(.*)<\/li>.*<\/ul>/siU',$mainpage,$eps);

		$sArr = array();
		
		for($s = 0; $s<count($eps[0]); $s++){
				array_push($sArr,$eps[0][$s]);
		}
		
		
		if(stripos($eps[0][0],'</strong>',0) !=  strripos($eps[0][0],'</strong>',0)){
			
			$sArr = array();
			$sSet = explode("<h2>",substr($eps[0][0],4));
			
			for($s = 0; $s<count($sSet); $s++){
					array_push($sArr,"<h2>".$sSet[$s]);
			}
		}
		
			$loop = 1;
		/*if($seriesObj->title=="Boss"){
			$loop = count($sArr);
			print "======== Grabbing all ".$loop." Seasons of ".$seriesObj->title."=======";
		}
		*/
		
		for($k = 0; $k < $loop; $k++){ //ADJUST HERE for seasons
		
			$seasonObj = new stdClass;
			
			$seasonObj->num = str_replace("Season ","",substr($sArr[$k],stripos($sArr[$k],'Season',0),stripos($sArr[$k],'</strong>',0)-stripos($sArr[$k],'Season',0)));
			
$harvest->progress("..");				
			//GATHER EPISODES FOR THIS SEASON
			preg_match_all("/<li><a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>(.*)<\/li>/siU", $sArr[$k], $epMatches);
			
				$epArr = array();
				
				for($e = 0; $e < count($epMatches[3]) ; $e++){   //adjust here for episodes
				
				$harvest->progress("._");	
					$epObj  	     = harvestEpisode($epMatches[3][$e],str_replace("'","",$epMatches[2][$e]));
					$epObj->title    = $seriesObj->title;
					$epObj->provider = $seriesObj->provider;
					$epObj->season   = $seasonObj->num;
					$epObj->imgtitle = $seriesObj->imgtitle;

					$epObj   = scrapeIMDBepisode($epsource .'?season='.$seasonObj->num,$epObj);
							
					$epObj->myubi_id    = $harvest->genID();
					$epObj->poster      = $seriesObj->poster;
					$epObj->showthumb   = $seriesObj->showthumb;
					$epObj->keyart      = $seriesObj->keyart;
					
					$epObj->url         = "http://jstream.info";
					$epObj->aspect 		= "9";
					$epObj->language	= "en";
					$epObj->pid   		= "3";
					$epObj->type   		= 1;
					$epObj->caption     = 0;
					$epObj->cid   		= "0";
					$epObj->userRating  = $seriesObj->userRating;
					$epObj->genre 		= $seriesObj->genre;
					$epObj->country		= "US";
					$epObj->url         = $seriesObj->url;
					$epObj->fileloc		= $seriesObj->fileloc;
					$epObj->quality 	= 'SD';
					$epObj->expire  	= $harvest->addDate(date('Y-m-d'),2);
					$epObj->urlid 		= $harvest->buildURLIDepisode($epObj->season,$epObj->epnum,$epObj->fileloc);
					$epObj->year     	= $seriesObj->year;
					
					
					if($epObj->runningtime == 0){$epObj->runningtime = $seriesObj->runningtime;}
					if($epObj->runningtime == 0 || $epObj->runningtime == ""){$epObj->runningtime = 3000;};
					
					if($epObj->network == ""){$epObj->network = $seriesObj->network;};
					
					if($epObj->description == ""){$epObj->description = $epObj->synopsis;};
					if($epObj->description == ""){$epObj->description = $seriesObj->synopsis;}
					else{$epObj->description = "Sorry, there is no description available for this episode";};
					//print "..seasons ". $epObj->season ."...";
					//print " [  ". $epObj->episodetitle . "  ]";
					array_push($epArr,$epObj);
					
				}
				$seasonObj->docs = $epArr;
				//skipped this level of the obj, didnt' serve a purpose for harvesting
			
			
			array_push($seasonArr,$seasonObj->docs);
			
		}
		
		
		$seriesObj->seasonSet = $seasonArr;
	
		for($s=0; $s < count($seriesObj->seasonSet); $s++){ //
			
			for($e=0; $e < count($seriesObj->seasonSet[$s]); $e++){
				
				$epdata = $seriesObj->seasonSet[$s][$e];
				
				$entry_exists = $harvest->dupContentCheck($epdata->urlid,"episode");
					
				if($entry_exists->result == false){
					print "....save this episode " . $epdata->episodetitle . " ....";
					print_r($epdata);
					saveEpisode($epdata);
					$epcount++;
				}else{
					print "......this ep of " . $epdata->episodetitle . " has " . $entry_exists->myubi ." already been saved lets check for stream......";
					
					$dupcheck = $harvest->dupStreamCheck($entry_exists->myubi,$epdata->provider,"episode");
					
					if($dupcheck == false){
						$epdata->myubi_id = $entry_exists->myubi;
						print "....[creating new jSTREAM stream with id " . $epdata->myubi_id." ]...";
						insertEpisodeStream($epdata);
					}else
						print "we already have this stream too<P> ...";
		
				}
				
			}
		}
	}else
		$notes .= "error loading show ". $c ." \\";
	//break;
}
	
	
	$notes = "Jstream inserted ".$showcount." shows and ".$epcount."episodes " .$critical;
	//print $notes;
	
$query = "update harvester_index set notes='".$notes."' ,last_update='".date('Y-m-d')."' where provider='jStream'";
$res   = mysql_query($query);















function harvestEpisode($listing,$link){
	global $scrape;
	global $critical;
	
	$epObj = new stdClass;
	
	$epnumTitle   = trim(preg_replace("/&#?[a-z0-9]{2,8};/i","",$listing));
	$strSplit     = split(' ',$epnumTitle);
	$epObj->epnum = $strSplit[1];
	
	$title = trim($strSplit[2]);
	for($t = 3; $t < count($strSplit); $t++){
		$title .= " " . $strSplit[$t];
	}
	$epObj->episodetitle = htmlentities(trim($title),ENT_QUOTES, 'UTF-8');
	
	//lets visit the page to gather information
	$scrape->fetch($link);

	$data  = $scrape->removeNewlines($scrape->result);
	$start = '<iframe\s[^>]*src=(\"??)([^\" >]*?)\\1[^>]*>';
	$end   = "<\/iframe>";

	$eppage= $scrape->fetchAllBetween($start,$end,$data,true);

	
	$eppage = file_get_contents(str_replace(".info",".ch",$link));
	
	if(preg_match_all("/<iframe\s[^>]*src=(\"??)([^\" >]*?)\\1[^>]*>/siU", $eppage, $matches)) {
	    
		if(strripos($matches[2][0],"http://",0) === false){
			$critical = "****Embed missing, maybe cricitcal error";
		}else
			$epObj->embed = "jstream,".preg_replace("/#?[a-z0-9]{2,8};/i","",$matches[2][0]) . "&autoplay=true";
		
		if(strripos($epObj->embed,"rutube",0)>0)
			$epObj->embed = "rutube,".preg_replace("/#?[a-z0-9]{2,8};/i","",$matches[2][0]);
	}
	return $epObj;
	
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
		
		$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
		$epimdbLink;
		$epimddID;
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
		
		if($epimddID != "")
			$epObject  = $mds->scrapeIMDBmain("http://www.imdb.com/title/".$epimddID."/",$epObject,false);
			
			$thumbnail = $harvest->theTVdb($epObject->title,"thumbnail",$epObject->season,$epObject->epnum,$epObject->episodetitle);
			
			if($thumbnail == "error"){
				$imgassets    	   = googleImageCheck($epObject->title . " episode " . $epObject->episodetitle);
				$epObject->thumbnail  = $imgassets[2];
			}else
				$epObject->thumbnail  = $thumbnail;
				
			
			
		return $epObject;
}



function ArrayToString($arr){
	
	$str = $arr[0];
	
	for($i=1; $i<count($arr);$i++){
		
		$str .= ", " . $arr[$i];
		
	}
	
	return $str;
}

function processTitle($str){
	$title = trim(str_replace("Online","",str_replace("Watch","",$str)));
	
	if(stripos($title,', The',0)>0){
		$newtitle = str_replace(', The',"",$title);
		$title    = "The " . trim($newtitle);
	}
	
	return $title;
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
	$keyart_src;
	$showthumb_src;
	$thumbnail_src;
	$keepsearching = true;
	
	while($keepsearching === true){
		foreach($results->responseData->results as $img){
	
			$ratio  = $img->{'height'} / $img->{'width'};  //official key art ratio is .39
			
			if($ratio > .37 && $ratio < .505 && $keyart_src =="")
				$keyart_src = $img->{'url'};
				
			if($ratio > .44 && $ratio < .61 && $showthumb_src =="")
				$showthumb_src = $img->{'url'};
			
			if($ratio > .44 && $ratio < .61 && $thumbnail_src =="")
				$thumbnail_src = $img->{'url'};
		}

		if(($showthumb_src != "" && $keyart_src !="" && $thumbnail_src!="") || $search == 1){
			$keepsearching = false;
		}else{
			$search++;
			$results = json_decode(search($query,8));
		}
	}
	
	$imgset = array($keyart_src,$showthumb_src,$thumbnail_src);

	
	return $imgset;
	
}

function search($q,$s){
		$google = "https://ajax.googleapis.com/ajax/services/search/images?v=1.0&q=".$q."&rsz=7&start=".$s."&key=ABQIAAAAt4pgIc58Uhow9LYHI2PQnxTNf4uIZ55bLVUnRqbVOhPznVTqGBTBPkMo6PRiTZoM_ME1gslO8EendA&userip=".$ip;

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



function saveShow($s){
//global $harvest;
global $notes;
global $t_suffix;
global $harvest;
//save images
/*$s->keyart    = $harvest->saveImageNew($s->imgtitle,"",$s->keyart,"key_art");
$s->showthumb = $harvest->saveImageNew($s->imgtitle,"",$s->showthumb,"show_thumbnail");
$s->poster    = $harvest->saveImageNew($s->imgtitle,"",$s->poster,"poster");*/



$query = "insert into episode_reference".$t_suffix." (MYUBI_ID,TITLE,TYPE,DESCRIPTION,NETWORK,URL,SHOWTHUMB,KEYART,POSTER,GENRE,USERRATING,RATING,FILELOC,KEYWORDS,CAST,YEAR,SYNOPSIS) VALUES ('".$s->myubi_id."','".mysql_escape_string(htmlentities($s->title,ENT_QUOTES, 'UTF-8'))."',1,'".mysql_escape_string(htmlentities($s->description,ENT_QUOTES, 'UTF-8'))."','".mysql_escape_string($s->network)."','".$s->url."','".$s->showthumb."','".$s->keyart."','".$s->poster."','".mysql_escape_string($s->genre)."',".$s->userRating.",'".$s->rating."','".$s->fileloc."','".mysql_escape_string($s->keywords)."','".$s->actors."',".$s->year .",'".mysql_escape_string($s->synopsis)."')";
		 
	
				if (mysql_query($query)) {
					print "=================INSERT REFERENCe SUCCESS ".$s->title ."=================\n ";
						
				} else {
					echo "<strong>Something went wrong with reference: ".mysql_error()."</strong>";
					$notes .= $s->title . " show insert failed";
					/*$logger->error("PHP Harvester : cwtv.php Message Mysql Query Error" . mysql_error());
					$repeat = true;*/
				}

}

function saveEpisode($e){
	global $notes;
	global $t_suffix;
	global $harvest;

	//global $logger;
	$e->thumbnail = $harvest->saveImageNew($e->imgtitle,"",$e->thumbnail,"thumbnail",$e->myubi_id);

 $query = "insert into episode_content".$t_suffix." (MYUBI_ID,TITLE,EPISODE,SEASON,TYPE,DESCRIPTION,NETWORK,KEYWORDS,USERRATING,RATING,COUNTRY,YEAR,URL,SHOWTHUMB,THUMB,DURATION,GENRE,EPISODETITLE,URLID,KEYART,PUBDATE,FILELOC,SYNOPSIS) VALUES ('".$e->myubi_id."','".mysql_escape_string(htmlentities($e->title,ENT_QUOTES, 'UTF-8'))."',".$e->epnum.",".$e->season.",1,'".mysql_escape_string(htmlentities($e->description,ENT_QUOTES, 'UTF-8'))."','".$e->network."','".mysql_escape_string($e->keywords)."',".$e->userRating.",'".$e->rating."','US',".$e->year.",'".$e->url."','".$e->showthumb."','".$e->thumbnail."',".$e->runningtime.",'".mysql_escape_string($e->genre)."','".mysql_escape_string(htmlentities($e->episodetitle,ENT_QUOTES, 'UTF-8'))."','".$e->urlid."','".$e->keyart."','".$e->pubDate."','".$e->fileloc."','".mysql_escape_string(htmlentities($e->synopsis,ENT_QUOTES, 'UTF-8'))."')";

 
			     if (mysql_query($query)) {
					print "=================INSERT EPISODE Content ".$e->epnum ." SUCCESS ".$e->title ."=================\n ";
						insertEpisodeStream($e);
				} else {
					echo "<strong>Something went wrong with episode: ".mysql_error()."</strong>";
					$notes .= $e->title . " show insert failed";
					/*$logger->error("PHP Harvester : cwtv.php Message Mysql Query Error" . mysql_error());
					$repeat = true;*/
				}

}

	
	function insertEpisodeStream($e){
		global $t_suffix;
		global $logger;
	
		 $query = "INSERT INTO episode_streams".$t_suffix." (myubi_id, url_hi, url_lo, provider, aspect, quality, expire, pid, cid, segment, fee,language,captions) VALUES ('".$e->myubi_id."', '".$e->embed."', '".$e->embed."', '".$e->provider."', 9, '".$e->quality."', '".$e->expire."', ".$e->pid.", '".$e->cid."', '0', '0', '".$e->language."', '".$e->caption."')";
		 
		if (mysql_query($query)) {
		 print "<p>============EPISODE STREAM" . $e->title ." INSERTED=================<p>";
			   
		} else 
			print_r("PHP Harvester : episode_streams error" . mysql_error());
	}

?>