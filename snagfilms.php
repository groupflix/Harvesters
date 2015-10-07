<?php

ini_set("memory_limit","64M");

error_reporting(E_ERROR);
include 'Scrape.php';
include 'com/HarvestMethods.php';
include '../db/DBconfig.php'; 
include '../utils/DateConverter.php';
include '../IO/FileType.php';
include 'com/MetaDataSources.php';


//require_once('Logger.php');
//require_once('/opt/myubi/Log4php/Logger.php');
//Logger::configure('C:\log4php_config.properties');
//Logger::configure('/opt/myubi/Log4php/resources/appender_snagfilms.properties');

//$logger = Logger::getRootLogger();
//global $logger;

print "PHP Harvester : snagfilms.php Starting ";

//===================Database Connection=====================
$dbconfig = new DBconfig();
$host = $dbconfig->getHOST();
$username = $dbconfig->getUSERNAME();
$password = $dbconfig->getPASSWORD();
$db = $dbconfig->getDATABASE();

$res = mysql_connect($host, $username, $password);
//if (!$res) die("Could not connect to the server, mysql error: ".mysql_error($res));
	if (!$res) {
		print "PHP Harvester : snagfilms.php Message - Could not connect to the server, mysql error: ".mysql_error($res);
	}

		$res = mysql_select_db($db);

	if (!$res) {
		print "PHP Harvester : snagfilms.php Message - Could not connect to the server, mysql error: ".mysql_error($res);
	}




//===================Global Parameters=====================
$harvest = new HarvestMethods();
$scrape    = new Scrape();
$mdS       = new MetaDataSources();

$provider  = "SnagFilms";
$type = 'film';


//0 array($_myubi,
	 //1 $_title,
	 //2 $_type,
	 //3 $_keywords,
	 //4 $_desc,
	 //5 $_network,
	 //6 $_showthumb,
	 //7 $_thumbnail,
	 //8 $_keyart,
	 //9 $_provider,
	 //10 $_genre,
	 //11 $_url,
	 //12 $_pubDate,
	 //13 $_year,
	 //14 $_urating,
	 //15 $_rating,
	//16 $_expire,
	//17 $_runtime,
	//18 $_urlid,
	//19 $_embed,
	//20 $_mobile,
	//21 $_fileloc);

		
		$scrape->fetch("http://www.snagfilms.com/films/rss");
		$data  = $scrape->removeNewlines($scrape->result);
		$start = '<a\s[^>]*href="http://feeds.feedburner.com/snagfilms/(\"??)([^\" >]*?)\\1[^>]*>';
		$end   = "<\/a>";

		$rows  = $scrape->fetchAllBetween($start,$end,$data,true);
		
		foreach($rows as $cat){
			
			$regexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>"; 
			$feed   = "";
			
			if(preg_match_all("/$regexp/siU", $cat, $matches)) {			
					$feed = $matches[2][0];
			}
			
			$xml = $harvest->getRSS($feed);
			//print_r($xml);
				foreach($xml->channel->item as $filmInfo){
					
					$dataary = array('','',$type,'','',$provider,'',
						'','',$provider,'','','','','','','','','','','','');
					
					//Title
					$title = htmlentities($filmInfo->title);
					$dataary[1] = $title;
					
					//Published Date
					$pub   = str_replace(",","",$filmInfo->pubDate);
					$_pubdate = explode(" ",$pub);
					$year = $_pubdate[3];
					$dataary[13] = $year;
					
					//create proper date string
					$datecv = &new DateConverter();
					$datecv->setDay($_pubdate[1]);
					$datecv->setMonth($_pubdate[2]);
					$datecv->setYear($_pubdate[3]);
					$pubDate = $datecv->dateFormat($_pubdate[3],$_pubdate[2],$_pubdate[1]);
					$dataary[12] = $_pubDate;
					
					//Description
					$desc = mysql_real_escape_string($filmInfo->description);
					$desc =  htmlentities($desc);
					$dataary[4] = $desc;
					
					//See if IMDB has additional info
					/*if($mdS->retrieveIMDB($harvest->concatTitle($title),$scrape)){
						print "<p>imdb link ".$mdS->getIMDB()." \n\n\n";
						pullIMDB($mdS->getIMDB());
						
					}else{
						print "no imdb link";
					}*/
					$imdb = $harvest->getIMDB($title);
					$imdbReady = false;
			
					if($imdb != "error" && strtolower($imdb->{'Title'}) == strtolower($filmInfo->title)){
						$imdbReady = true;
						
						$extrating   = $imdb->{'Rating'} / 10;
						$rating      = $extrating * 5;
						$dataary[15] = $rating;
						
						$genre     = $imdb->{'Genre'};
						$dataary[10] = $genre;
						$director  = $imdb->{'Director'};
						$showthumb = $imdb->{'Poster'};
						$dataary[6] = $showthumb;
						$keyart = $imdb->{'Poster'};
						$dataary[8] = $keyart; 
						print "PHP Harvester : IMDB keyart is ! " . $keyart;
						
						//could be used, but the api delivers enough info as is
						//pullIMDB("http://www.imdb.com/title/".$imdb->{'ID'});
					}
					
					
					//Get additional information from individual film page
					
					$filmInfo->link;
					print "PHP Harvester : Link is: " . $filmInfo->link;
					
						if (isFilm($filmInfo->link)) {
							print "PHP Harvester : snagfilms.php Video duration OK! ";
						//	print "PHP Harvester : snagfilms.php Fetching URL: " .  $url;
							$newary = getData($filmInfo->link, $dataary);
							
							print "PHP Harvester : Checking duplicate with Title= " .  $newary[1] . ' and Provider=' . $newary[9];
							
							$duplicate = $harvest->duplicateFilmCheck($newary[1],$newary[9]);
							
							print "PHP Harvester : Checking duplicate result= " . $duplicate;
							if($duplicate == false) {
								$newary[0] = $harvest->genID();
								$thumbnail = $harvest->saveImage($harvest->concatTitle(trim($newary[1])),$newary[7],"thumbnail",$newary[0]);
								$keyart = $harvest->saveImage($harvest->concatTitle(trim($newary[1])),$newary[8],"keyart",$newary[0]);
							//	$logger->info("PHP Harvester : Saved thumbnail " . $thumbnail);
								//$logger->info("PHP Harvester : Saved keyart " . $keyart);
								//$logger->info("PHP Harvester : Inserting to database ");
								saveShow($newary[0],$newary[1],$newary[2],$newary[3],$newary[4],$newary[5],$newary[6],$thumbnail,$keyart,$newary[9],$newary[10],$newary[11],$newary[12],$newary[13],$newary[14],$newary[15],$newary[16],$newary[17],$newary[18],$newary[19],$newary[20],$newary[21]);
							}
						}
				}
			
		}

	    
	    
function getData($url, $_dataary) 
{ 
	global $logger;
	global $harvest;
	global $scrape;
	$tempary = $_dataary;
	
	    //===================Get <meta name=.../> Stuff=====================
		$metanames = get_meta_tags($url);
		// Title
		$title = $metanames['title'];
		$titleary = explode('|', $title);
		$title = $titleary[0];
		$tempary[1] = $title;
		//$logger->info("PHP Harvester:Title: " . $title);
		
		//FileLoc
		$fileloc = $harvest->concatTitle(trim($title));
		$tempary[21] = 'content/SnagFilms/' . $fileloc . '.xml';
		//$logger->info("PHP Harvester:Fileloc: " . $fileloc);
		
		//Description - converts all &039; and bullshit to normal shit
		$description = $metanames['description'];
		$description = htmlspecialchars_decode($description, ENT_QUOTES);
		$descriptionary = explode('Watch free', $description);
		$description = $descriptionary[0];
		$tempary[4] = $description;
		//$logger->info("PHP Harvester:Description: " . $description);
		
		//Keywords
		$keywords = $metanames['keywords'];
		$keywordsary = explode('watch,', $keywords);
		$keywordsary2 = explode('Indie Wire, ', $keywords);
		$keywords = $keywordsary[0] . $keywordsary2[1];
		$tempary[3] = $keywords;
		//$logger->info("PHP Harvester:Keywords: " . $keywords);
		
		//Pubdate, Expiredate and general Date
		$date = date_create($metanames['date']);
		$date = date_format( $date, 'Y-m-d');
		$pubdate = $date;
		$tempary[12] = $pubdate;
		$expiredate = date('Y-m-d', strtotime(date("Y-m-d", strtotime($date)). "+1 month"));
		$tempary[16] = $expiredate;
		//$logger->info("PHP Harvester:Expire: " . $expiredate);
		//$logger->info("PHP Harvester:Date: " . $date);
		$year = date('Y');
		$tempary[13] = $year;
		//$logger->info("PHP Harvester:Year: " . $year);
		
		//===================Get <meta property=.../> Stuff=====================
		$html = file_get_contents_curl($url); 
 
		$doc = new DOMDocument(); 
		@$doc->loadHTML($html);
		
		$date;
		$duration;
		$thumb;
		 
		$metas = $doc->getElementsByTagName('meta'); 
		for ($i = 0; $i < $metas->length; $i++) 
		{ 
		    $meta = $metas->item($i); 
		    if($meta->getAttribute('property') == 'video:duration') {
		        $duration = $meta->getAttribute('content'); 
			}
		    if($meta->getAttribute('property') == 'og:image') {
		        $thumb = $meta->getAttribute('content'); 
			}
		} 
		//$logger->info("PHP Harvester:Duration: " . $duration);
		$tempary[17] = $duration;
		//$logger->info("PHP Harvester:Thumb: " . $thumb);
		// TODO have to incorporate content/images/....jpg
		$tempary[7] = $thumb;
		
		//===================Get <link rel=.../> Stuff=====================
		
		$snagfilms_url;
		$urlid;
		$embed;
		
		$scrape->fetch($url);
		$data  = $scrape->removeNewlines($scrape->result);
		$start = "<link rel=\"canonical\" ";
		$end   = '/>';
		$rows  = $scrape->fetchAllBetween($start,$end,$data,true);
		
		foreach($rows as $links){
			$snagfilms_url_ary = explode('href=', $links);
			$snagfilms_url = $snagfilms_url_ary[1];
			$snagfilms_url = str_replace("\"", '', $snagfilms_url);
			$snagfilms_url = str_replace(" ", '', $snagfilms_url);
			$snagfilms_url = str_replace("/>", '', $snagfilms_url);
			//$logger->info("PHP Harvester:Link: " .  $snagfilms_url);

		}//end for loop
		$tempary[11] = $snagfilms_url;
		
		$start = "<script class=";
		$end   = '</script>';
		$rows  = $scrape->fetchAllBetween($start,$end,$data,true);
		
		foreach($rows as $urlids){
			$urlids_ary = explode(' ', $urlids);
			$urlid = str_replace("\"", '', $urlids_ary[1]);
			$urlid = str_replace("class=", '', $urlid);
			//$logger->info("PHP Harvester:UrlId: " .  $urlid);

		}//end for loop
		$tempary[18] = $urlid;
		
		$embed = getEmbed($urlid); 
		
		if ($embed != null) {
			//$logger->info("PHP Harvester:Embed results... ");
			//$logger->info("PHP Harvester:Low Res: " . $embed[0]);
			//$logger->info("PHP Harvester:Low Res Stream: " . $embed[1]);
			//$logger->info("PHP Harvester:High Res: " . $embed[2]);
			//$logger->info("PHP Harvester:High Res Stream: " . $embed[3]);
			$tempary[19] = 'Snagfilms,' . $embed[3];
			$tempary[20] = 'Snagfilms,' . $embed[1];
		}

		return $tempary;
}

$query = "update harvester_index set last_update='".date('Y-m-d')."' where provider='SnagFilms'";
$res   = mysql_query($query);
	if($res->error) {
		print "PHP Harvester : snagfilms.php Message Mysql Query Error" . mysql_error();
	}
	


// Howie Norman
function pullIMDB($link){
			$showObject  = array();
	
			$scrape    = new Scrape();
			$scrape->fetch($link);
			$data   = $scrape->removeNewlines($scrape->result);
			$start  = '<p itemprop=\"description\">';
			$end    = '<\/p>';
	
			$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
			
			$desc     = mysql_escape_string(strip_tags($imdbdata[0]));
			
			$showObject["synopsis"] = htmlentities($desc);
			
			$data   = $scrape->removeNewlines($scrape->result);
			$start  = '<div class=\"txt-block\">';
			$end    = '<\/div>';
	
			$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
			print_r($imdbdata);
			$creator  = "";
			$starlist = array();
			for($t = 0; $t < count($imdbdata); $t++){
				//print strip_tags($imdbdata[$t]) . "\n\n";
				$subhead = explode(":", strip_tags($imdbdata[$t]));
				//print $subhead[0] . "\n";
				switch(strtolower($subhead[0])){
					case("creator"):
						$personTitle = explode(", ", $subhead[1]);
						$creator     = $personTitle[0];
						print "creator is $creator \n";
						
						$showObject['creator'] = $creator;
					break;
					case("runtime"):
						
						$runtime = trim(str_replace(" min", "",trim($subhead[count($subhead)-1])));
						$runtime = (int) $runtime;
						$runtime = $runtime * 60;
						//print "\n runtime is $runtime \n";
						
						$showObject['runtime'] = $runtime;
					break;
					case("stars"):
					
						$starsNames = str_replace(", ","+", $subhead[1]);
						$starsNames = str_replace(" and ","+", $starsNames);
						$stars      = explode('+',$starsNames);
					
						for($a = 0; $a < count($stars); $a ++){
							if($stars[$a] != ""){
								array_push($starlist,$stars[$a]);
							}
						}
						
						$showObject['actors'] = ArrayToString($starlist);
						
						print "stars are " . $starlist[0] . "\n";
					break;
				}
			}
			print "\n";

			$data   = $scrape->removeNewlines($scrape->result);
			$start  = '<h2>Storyline<\/h2>';
			$end    = '<em class=\"nobr\">';
	
			$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
			
			if(strripos($imdbdata[0],"Storyline")){
				$desclong = mysql_real_escape_string(substr(strip_tags($imdbdata[0]),9));
			}else{
				$desclong = mysql_real_escape_string(strip_tags($imdbdata[0]));
			}
			
			$showObject["description"] = htmlentities($desclong);


			//SEARCH FOR RATING
			$data   = $scrape->removeNewlines($scrape->result);
			$start  = '<div class=\"infobar\">';
			$end    = '<\/div>';
	
			$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
			
			$start = stripos($imdbdata[0],'" alt="',10);
			$end   = stripos($imdbdata[0],'" src="http',10);
			
			$rating= substr($imdbdata[0], $start+7, ($end - ($start+7)));
			$rating= str_replace("_","-",$rating);
			print "rating is " . $rating . "\n";
			if(strripos($imdbdata[0],'class="absmiddle"',0)){
				$showObject["rating"] = $rating;
			}else{
				$showObject["rating"] = "NA";
			}
			
			//SEARCH FOR TRAILER
			$data   = $scrape->removeNewlines($scrape->result);
			$start  = '<td id=\"overview-bottom\">';
			$end    = '<\/span><\/a>';
	
			$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
			$turl     = "";
			
			if(strripos($imdbdata[0],"Watch Trailer")){
				$start = stripos($imdbdata[0],'href="',10);
				$end   = stripos($imdbdata[0],'" itemprop="trailer"',10);
				
				$turl  = substr($imdbdata[0], $start+6, ($end - ($start+6)));
			}
		
			$trailer = "http://www.imdb.com" . $turl . "player?uff=3 \n";
			$showObject["trailer"] = $trailer;
			
			
			//SEARCH FOR GENRE
			$data   = $scrape->removeNewlines($scrape->result);
			$start  = '<h4 class=\"inline\">Genres:<\/h4>';
			$end    = '<\/div>';
	
			$imdbdata  = $scrape->fetchAllBetween($start,$end,$data,true);

			$genredata = str_replace('<h4 class="inline">Genres:</h4>',"",$imdbdata[0]);
			$genrelist = array();
			
				$regexp = "<aonclick=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>"; 
				
				if(preg_match_all('/'.$regexp.'/siU', $genredata, $genrematch)) {
						$genrelist = $genrematch[3];
				}
				
			$showObject["genre"] = ArrayToString($genrelist);
			
			return $showObject;
}


function ArrayToString($arr){
	
	$str = $arr[0];
	
	for($i=1; $i<count($arr);$i++){
		
		$str .= ", " . $arr[$i];
		
	}
	
	return $str;
}

function getDescription($_url){

	$tags = get_meta_tags($_url);

	$desc = $tags['description'];
	$desc = str_replace("The official site of ","",$desc);
	$desc  = mysql_real_escape_string($desc);
	$desc  = htmlentities($desc);
	return $desc;
}

function getKeywords($_url){
	
	$tags = get_meta_tags($_url);

	$keywords = $tags['keywords'];
	$keywords = str_replace("about the show","",$keywords);
	$keywords  = mysql_real_escape_string($keywords);
	$keywords  = htmlentities($desc);
	return $desc;
}

function getCast($_url){
		$actors = "";
		$scrape = new Scrape();

		$scrape->fetch($_url);
		
		$data  = $scrape->removeNewlines($scrape->result);
		$start = '<div id=\"castCarousel\" class=\"castfloat\">';
		$end   = "<\/div>";

		$rows  = $scrape->fetchAllBetween($start,$end,$data,true);
		//print_r($rows);
		$regexp = "<p>(.*)<\/p><\/li>";
		
		if(preg_match_all("/$regexp/siU", $rows[0], $matches)) {
				$k =0;
				while($name = $matches[1][$k]){
					$actors .= $name .", ";
					$k++;
				}	
		}
		
		$actors = substr($actors,0,strlen($actors)-2);
		return $actors;
}



// Evan Ngan 
// @version 1.0
function getEmbed($urlid) 
{ 
	global $logger;
	$getembedurl_header = "http://api.snagfilms.com/assets.jsp?id=";
	$getembedurl = $getembedurl_header . $urlid;
	//$logger->info("PHP Harvester:Embed url: " . $getembedurl);
	$url_data = file_get_contents($getembedurl);
	$obj = json_decode($url_data);
	
	$host = $obj->{'result'}->{'host'};
	//$logger->info("PHP Harvester:host: " . $host);
	$video = $obj->{'result'}->{'video'};


	$lowres = 0;
	$highres = 0;
	$lowresStream;
	$highresStream;
	
	$count = 0;
	foreach($video as $iter) {
		if ($count == 0)
		{
			$lowres = $iter->{'bitrate'};
			$highres = $iter->{'bitrate'};;
			$lowresStream = $iter->{'streamName'};
			$highresStream = $iter->{'streamName'};
		}
		
		if ($iter->{'bitrate'} > $highres) {
			$highres = $iter->{'bitrate'};
			$highresStream = $iter->{'streamName'};
		}
		
		if($iter->{'bitrate'} < $lowres) {
			$lowres = $iter->{'bitrate'};
			$lowresStream = $iter->{'streamName'};
		}
		$count++;

	}
	
	$lowresStream = $host . str_replace("mp4:", '', $lowresStream);
	$highresStream = $host . str_replace("mp4:", '', $highresStream);

	return array($lowres, $lowresStream, $highres, $highresStream);
} 

function isFilm($url) 
{ 
	$html = file_get_contents_curl($url); 
 
	$doc = new DOMDocument(); 
	@$doc->loadHTML($html);
		 
	$metas = $doc->getElementsByTagName('meta'); 
	for ($i = 0; $i < $metas->length; $i++) 
	{ 
		$meta = $metas->item($i); 

		if($meta->getAttribute('property') == 'video:duration') {
		       $duration = $meta->getAttribute('content'); 
		       if($duration >= 1500) {
		       	return true;
		       }
		}
	}
	return false;

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

//0 array($_myubi,
	 //1 $_title,
	 //2 $_type,
	 //3 $_keywords,
	 //4 $_desc,
	 //5 $_network,
	 //6 $_showthumb,
	 //7 $_thumbnail,
	 //8 $_keyart,
	 //9 $_provider,
	 //10 $_genre,
	 //11 $_url,
	 //12 $_pubDate,
	 //13 $_year,
	 //14 $_urating,
	 //15 $_rating,
	//16 $_expire,
	//17 $_runtime,
	//18 $_urlid,
	//19 $_embed,
	//20 $_mobile,
	//21 $_fileloc);
function saveShow($_myubi,$_title,$_type,$_keywords,$_desc,$_network,$_showthumb,$_thumbnail,$_keyart,$_provider,$_genre,$_url,$_pubDate,$_year,$_urating,$_rating,$_expire,$_runtime,$_urlid,$_embed,$_mobile,$_fileloc){

	global $logger;
 	$query = "insert into film_content (MYUBI_ID,TITLE,QUALITY,TYPE,DESCRIPTION,NETWORK,KEYWORDS,USERRATING,RATING,COUNTRY,YEAR,URL,THUMB,RUNNINGTIME,PROVIDER,GENRE,URLID,KEYART,PUBDATE,FEE,EXPIRE,ASPECT,EMBED,MOBILE,FILELOC) VALUES ('$_myubi','$_title','SD','$_type','$_desc','$_network','$_keywords','".$_urating."','".$_rating."','US','$_year','".$_url."','".$_thumbnail."','$_runtime','$_provider','$_genre','$_urlid','".$_keyart."','$_pubDate','0','".$_expire."','9','".$_embed."','".$_mobile."','".$_fileloc."')";
			 
				if (mysql_query($query)) {
					
					$query = "select * from film_content where TITLE='".$_title."' AND NETWORK='".$_network."'";
					$res = mysql_query($query);
					if($res->error) {
                		print "PHP Harvester : snagfilms.php Message Mysql Query Error" . mysql_error();
        			}
					$res =  mysql_fetch_array($res);
					if($res->error) {
                		print "PHP Harvester : snagfilms.php Message Mysql Query Error" . mysql_error();
        			} 
								
				} else {
					print "PHP Harvester : snagfilms.php Message Mysql Query Error" . mysql_error();
					$repeat = true;
				}

}







?>
