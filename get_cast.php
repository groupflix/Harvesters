<?php

ini_set("memory_limit","164M");
error_reporting(E_ERROR);
include 'Scrape.php';
include '../db/DBconfig.php'; 
include '../utils/DateConverter.php';
include '../IO/FileType.php';
include 'com/HarvestMethods.php';
include 'com/MetaDataSources.php';

$dbconfig = new DBconfig();
$suffix   = $dbconfig->getDBsuffix();
$scrape   = new Scrape();			
$harvest  = new HarvestMethods();
$mds      = new MetaDataSources();
$notes    = "";
//SET DEBUG VARIABLES=======================================

$tmp = false;
$t_suffix;

if($tmp){
	$harvest->setTableSuffix('_tmp');
	$t_suffix = "_tmp";
}else
	$t_suffix ="";
	$suffix = $t_suffix;
/*require_once('/opt/myubi/Log4php/Logger.php');
Logger::configure('/opt/myubi/Log4php/resources/appender_jstream.properties');
$logger = Logger::getRootLogger();
$logger->info("PHP Harvester :jstream.php Starting" );
global $logger;*/

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




$showList  = array();

//$query = "select distinct title,type,myubi_id from episode_reference".$suffix." where myubi_id not in (select myubi_id from cast_reference".$suffix.")";
$query = "select distinct title,type,myubi_id from episode_reference".$suffix." where title='The Walking Dead' or title='30 Rock' or title='Boardwalk Empire' or title='Modern Family'";
$res   = mysql_query($query);
if (!$res) die("Could not connect to the database, mysql error: ".mysql_error());

$i = 0;


while($showData = mysql_fetch_assoc($res)){
	$showCast = new stdclass;
 	$rage     = false;
	print ".....".html_entity_decode($showData['title']).".....";
    
	print "...try tvrage scrape...";
	//TRYING TV RAGE============================================================
	$showurl  = 'http://www.tvrage.com/'.$harvest->concatTitle(preg_replace('/\([^)]+\)/si',"",html_entity_decode($showData['title'])),true);
	$metatags = get_meta_tags($showurl);
	
	if(stripos(" ".$metatags['keywords'],preg_replace('/\([^)]+\)/si',"",html_entity_decode($showData['title']),0))==false){
		print '...fail...';
	}else{
		$rage   = true;
		$tvrage = file_get_contents($showurl);
	
		
		$start  = stripos($tvrage,'<span class="content_title">Cast</span>',0);
		$end    = stripos($tvrage,'</table>',0);
		$part   = substr($tvrage,$start,$end-$start);
		
		preg_match_all('/<td width=50\%>(.*)<\/td>/siU',$part,$items);
		//print_r(substr($tvrage,$start,500));

		foreach($items[0] as $actor){
			$pObj = new stdclass;
			
			preg_match('/<img class=\'shadow\' src=(\'??)([^\' >]*?)\\1[^>]*>/siU',$actor,$img);
			preg_match('/<a href=(\'??)([^\' >]*?)\\1[^>]*>(.*)<\/a>/siU',$actor,$link);
			
			$names = explode(' ',$link[3]);
	
			$pObj->thumb 	 = $img[2];
			$pObj->title 	 = $showData['title'];
			$pObj->afirstname= $names[0];
			$pObj->alastname = $names[count($names)-1];
			$pObj->myubi     = $showData['myubi_id'];
			$pObj->ctype     = $showData['type'];
			$pObj->afullname = $link[3];
			$pObj->description= "None available at this time";
			$pObj->type      = 4;
		
		    //getActorDesc($pObj);  need to get api working correctly
		
			$actpage = file_get_contents($link[2]);
			
			$pObj->showthumb = $harvest->theTVdb(html_entity_decode($showData['title'],ENT_QUOTES, 'UTF-8'),"cast",0,0,$pObj->afullname);
			if($pObj->showthumb  == "error"){
				preg_match_all('/<img src=(\'??)([^\' >]*?)\\1[^>]*>/siU',$actpage,$personimgs);
				
				$detect = explode("/",$img[2]);
				$imgid  = explode('-',str_replace('.jpg','',$detect[count($detect)-1]));
				
				$count = 0;
				foreach($personimgs[0] as $row){
					
						
					if((stripos($row,$pObj->lastname,0) > 0 || stripos($row,$pObj->firstname,0) > 0 || stripos($personimgs[2][$count],$imgid[1],0)>0) && stripos($personimgs[2][$count],"nopic",0) === false){
						
						$pObj->showthumb = $personimgs[2][$count];
						break;
					}else
						$pObj->showthumb = "default";
						
					$count++;
				}
			}
			
			$pObj = pullTVRageDetails($pObj,$actpage);
		
			if($pObj->dob == ""){ 
				$pObj->dob    = "na";
				$pObj->height = "na";
			}
			
			$pObj->castId 	 = substr(base64_encode(str_replace(" ","",$pObj->dob)),0,10) . substr(str_replace(" ","",preg_replace('/[^a-z]/si','',$pObj->fullname)),0,15);
					
			$pObj->castId 	 = substr($pObj->castId,0,20);
			
			saveProcess($pObj);
			
			
		}
	
	}
	//NEED TO PROPERLY USE GOOGLEAPIS 
/*	//TRYING IMDB =====================================================================
	$imdbID   = $harvest->getIMDBid(html_entity_decode($showData['title']),ENT_QUOTES, 'UTF-8');
	$imdbLink = "http://www.imdb.com/title/" .$imdbID ;
	
	if($imdbID != "error" && $rage ===false){
		print "....no tv rage, using imdb...";
		$showCast = $mds->getCast($imdbLink,$showCast);
		print_r($showCast);
	
		$personArr = array($showCast->actors,$showCast->creator);

		for($i=0; $i < 2; $i++){
			
			$actorList = explode(", ",$personArr[$i]);
	
			foreach($actorList as $person){
				$pObj = new stdclass;
				
						  $names = explode(' ',$person);
				$pObj->fullname  = $person;
				$pObj->myubi     = $showData['myubi_id'];
				$pObj->ctype     = $showData['type'];
				$pObj->fullname  = $person;
				$pObj->firstname = $names[0];
				$pObj->lastname  = $names[count($names)-1];
				$pObj->type      = ($i == 0) ? 4 : 5;
			
				$data = $harvest->ParseFeed('https://www.googleapis.com/freebase/v1/search?query='.urlencode($person).'&start=1&limit=1&indent=true&filter=(any%20type:/people/person)');
			//print_r($pInfo);
				$pInfo = json_decode($data);
				$purl  = $pInfo->result[0]->mid;
				
				$data = $harvest->ParseFeed('https://www.googleapis.com/freebase/v1/topic'.$purl.'?filter=/common/topic/article');
			
				$pInfo = json_decode($data);
				
				$pObj->description  = $pInfo->property->{'/common/topic/article'}->values[0]->property->{'/common/document/text'}->values[0]->value;
				$synop = substr($pObj->description,0,300);
				
				if(strripos($synop,"(born",0)){
					$start = strripos($synop,"(born",0);
					$end   = strripos($synop,")",$start);
					$pObj->dob  = trim(substr($synop,$start + 5,$end - $start -5));
					
					if(is_numeric(substr($pObj->dob,0,strlen($pObj->dob)-1))){
						//good to go
					}else{
						preg_match('/\d{4}/',$pObj->dob,$split);
						$newbday   = explode($split[0],$pObj->dob);
						$pObj->dob = $newbday[0] . $split[0];
					}
					preg_match('/\d{4}/',$pObj->dob,$year);
					
					$pObj->age = birthday($year[0]);
					//print $item;
				}else{
					$pObj->dob = "na";
					$pObj->age = 0;
				}
				
				$pObj->castId = substr(base64_encode(str_replace(" ","",$pObj->dob)),0,10) . substr(str_replace(" ","",$pObj->fullname),0,15);
					
				$pObj->castId = substr($pObj->castId,0,10);
				
				$pObj->thumb  = $harvest->theTVdb(html_entity_decode($showData['title'],ENT_QUOTES, 'UTF-8'),"cast",0,0,$person);
				$pObj->height = "na";
				$pObj->height = "na";
				if($pObj->thumb == "error"){
					$imgs = googleImageCheck($person);
					$pObj->thumb = $imgs[1];
					
				}
				$pObj->showthumb = $pObj->thumb;
				
				saveProcess($pObj);
			
			}
		
		}//loop through actor / director
	}//no showcast found*/
		

}


function saveProcess($person){
	global $harvest;
	
	
	if($person->firstname != "" && $person->lastname != ""){
					
		$dupcheck = $harvest->dupCastCheck($person->castId);
		
		if($dupcheck == false){
			print "...adding new member....";
			print_r($person);
			insertCastMember($person);
			
		}else{
			print "..." . $person->fullname ." already a member, check references for ".$person->castId." ...";
			$dupcheck = $harvest->dupCastRefCheck($person->castId,$person->myubi);
			
			if($dupcheck == false){
				print "....adding " . $person->fullname . " to ref for " . $person->title ." ....";
				print_r($person);
				insertCastRef($person);
			}
		}

	}
	
}

function insertCastMember($c){
	global $suffix;
	global $harvest;
	
	$img_title 	 = $harvest->concatTitle($c->fullname);
	$c->thumb  	 = $harvest->saveImageNew($img_title,"tvdb",$c->thumb,"cast",$c->castId);
	$c->showthumb= $harvest->saveImageNew($img_title,"tvdb",$c->showthumb,"cast_large",$c->castId);
	$c->age      = (property_exists($c,'age'))? $c->age : 0;
	
	$insert = "INSERT INTO cast".$suffix." (castId, firstname, description, lastname, thumb, keyart, type, fullname, dob,height,origin,age,alias_firstname,alias_lastname,alias_fullname) VALUES ('".$c->castId."', '".mysql_escape_string(htmlentities($c->firstname,ENT_QUOTES, 'UTF-8'))."', '".mysql_escape_string(htmlentities($c->description,ENT_QUOTES, 'UTF-8'))."', '".mysql_escape_string(htmlentities($c->lastname,ENT_QUOTES, 'UTF-8'))."', '".$c->thumb."', '".$c->showthumb."', ".$c->type.", '".mysql_escape_string(htmlentities($c->fullname,ENT_QUOTES, 'UTF-8'))."', '".$c->dob."', '".mysql_escape_string($c->height)."', '".mysql_escape_string($c->origin)."', ".mysql_escape_string($c->age).",'".mysql_escape_string(htmlentities($c->afirstname,ENT_QUOTES, 'UTF-8'))."','".mysql_escape_string(htmlentities($c->alastname,ENT_QUOTES, 'UTF-8'))."','".mysql_escape_string(htmlentities($c->afullname,ENT_QUOTES, 'UTF-8'))."')";
	
	if(mysql_query($insert)){
		print "[[INSERT Cast member " . $c->firstname . " success]]";
		insertCastRef($c);
	}else{
	 echo "Cast member insert mysql error: ".mysql_error();
	 print $insert;
	}

	
}

	function insertCastRef($c){
		global $suffix;
		$insert = "INSERT INTO cast_reference".$suffix." (castId, myubi_id,cType) VALUES ('".$c->castId."', '".$c->myubi."',".$c->ctype.")";
		if(mysql_query($insert)){
			print "[[INSERT Cast reference " . $c->firstname . " success]]";
		}else
		    echo "Cast reference insert mysql error: ".mysql_error();
	}
	
	
function getActorDesc($person){
	global $harvest;
	
	$data = $harvest->ParseFeed('https://www.googleapis.com/freebase/v1/search?query='.urlencode($person->fullname).'&start=1&limit=1&indent=true&filter=(any%20type:/people/person)');
	
	$pInfo = json_decode($data);
//	print_r($pInfo);
	$purl  = $pInfo->result[0]->mid;
	$data = $harvest->ParseFeed('https://www.googleapis.com/freebase/v1/topic'.$purl.'?filter=/common/topic/article');
	$pInfo = json_decode($data);
	
	//print_r($pInfo->property->{'/common/topic/article'}->values[0]->text);
	$description 		  = $pInfo->property->{'/common/topic/article'}->values[0]->property->{'/common/document/text'}->values[0]->value;
	
	$person->description  = $description;

}

function pullTVRageDetails($obj,$person){
	
	$start  = stripos($person,'<iframe',0);
	$end    = stripos($person,'</tbody>',$start);
	$part   = substr($person,$start,$end-$start);
	
	preg_match_all('/<strong>(.*)<br \/>/siU',$part,$attr);
	
	foreach($attr[1] as $item){
		
		$_item = explode(":",$item);
	
		switch(strtolower($_item[0])){
			case('birth name'):
				$bname  = explode(' ',trim(strip_tags(html_entity_decode($_item[1],ENT_QUOTES, 'UTF-8'))));
				$obj->firstname = $bname[0];
				
				if($bname[count($bname)-1] == "Jr." || $bname[count($bname)-1] == "II" || $bname[count($bname)-1] == "III")
					$obj->lastname  = $bname[count($bname)-2];
				else
					$obj->lastname  = $bname[count($bname)-1];
					
				$obj->fullname  = strip_tags(html_entity_decode($_item[1],ENT_QUOTES, 'UTF-8'));
				print "....fullname " . strip_tags(html_entity_decode($_item[1],ENT_QUOTES, 'UTF-8'))."....";		
			break;
			case('date of birth'):
				$obj->dob = trim(strip_tags(preg_replace('/\([^)]+\)/si',"",$_item[1])));
				
				preg_match('/\(([^)]+)\)/si',$_item[1],$age);
				$obj->age = trim(str_replace("Age ","",$age[1]));
				if($obj->age == "")
					$obj->age = 0;	
			break;
			case('country of birth'):
				$obj->origin = trim(str_replace('country of birth:',"",strtolower(strip_tags($item))));
			break;
			case('height'):
				$obj->height = str_replace('Â½','',html_entity_decode(trim(preg_replace('/\([^)]+\)/si',"",strip_tags($_item[1]))),ENT_QUOTES, 'UTF-8'));
			break;
		}
		
	}
	return $obj;
}

function birthday ($year){
		$year_diff  = date("Y") - $year;

		return $year_diff;
}
	
function googleImageCheck($title){
	$query= $title . " tv";
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
			if($ratio > .9 && $ratio < 1.1 && $thumbnail_src =="")
				$thumbnail_src = $img->{'url'};
				
			if($ratio > .6 && $ratio < .7 && $poster_src =="")
				$poster_src = $img->{'url'};
			
		}

		if(($thumbnail_src!="" && $poster_src!="") || $search == 3){
			$keepsearching = false;
		}else{
			$search++;
			$results = json_decode(search($query,8));
		}
	}
	
	$imgset = array($thumbnail_src,$poster_src);

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
?>