<?php

include '../db/DBconfig.php'; 
include '../IO/FileType.php';
include 'Scrape.php';

$dbconfig = new DBconfig();
			

$host = $dbconfig->getHOST();
$username = $dbconfig->getUSERNAME();
$password = $dbconfig->getPASSWORD();
$db = $dbconfig->getDATABASE();

$res = mysql_connect($host, $username, $password);
if (!$res) die("Could not connect to the server, mysql error: ".mysql_error($res));
$res = mysql_select_db($db);
if (!$res) die("Could not connect to the database, mysql error: ".mysql_error($res));

session_start();


for($k=5;$k<20;$k++){

$url = 'content/video_sitemap.'.$k.'.xml';
$xml_file = file_get_contents($url);
$xml = simplexml_load_string($xml_file);  	
$res = null;
$provider  = "Hulu";

foreach($xml->url as $showinfo){
$fee  = $showinfo->video->requires_subscription;	
$type  = $showinfo->video->video_type;	
$network  = $showinfo->video->company;
$userRating = $showinfo->video->rating;

		if($fee=="no" && $type=="feature_film" && $network != "crackle-movies" && $userRating > 2){
			$title  = $showinfo->video->title;
			$season = "x";
			$epnum  = "x";
			print "checking ". htmlentities($title) ."<P>";
			
			if(stripos($title,"'")){
				$reg = trim(substr(htmlentities($title),0,(stripos($title,"'")))) ."%";
				print "creating like statement $reg  <br>";
				$query = "select TITLE from film_content where TITLE LIKE '".$reg."' AND NETWORK='".$network."' AND PROVIDER='".$provider."'";
				$res = mysql_query($query);
				
				if(mysql_num_rows($res) > 0){
						
					if(mysql_num_rows($res) == 1 && stripos($title,"'") > (strlen($title)-3)){
					
					}else{
						$duplicate = false;
						while($_list = mysql_fetch_array($res)){
							print "comparing ". trim(substr($_list[0],(stripos($_list[0],"'")+1))) . " to " . trim(substr($title,(stripos($title,"'")+1))) ."<br>";
								
								if(trim(substr($_list[0],(stripos($_list[0],"'")+1))) ==  trim(substr($title,(stripos($title,"'")+1)))){
									print "duplicate found <br>";
									$duplicate = true;
								}
									
						}
						
						if($duplicate == false){ $res="";}
					}
						
				}else{
					$res="";
				}
				
			}else{
				$query = "select * from film_content where TITLE='".htmlentities($title)."' AND NETWORK='".$network."' AND PROVIDER='".$provider."'";
				$res = mysql_query($query);
			}
			
			
			
			
					if(mysql_num_rows($res) > 0){
						print "<b>title has already been stored</b> <br>";
					}else{
							$url 	= $showinfo->loc;
							$pubfull= $showinfo->video->tvshow->premier_date;
							$expire = $showinfo->video->expiration_date;
							$rating = $showinfo->video->family_friendly;
							$userRating = $showinfo->video->rating;
							$thumb  = $showinfo->video->thumbnail_loc;
							$keyword= getKeywords($url);
							$temp  = explode("/",$url);
							$urlid = $temp[4];
							$totalsg= 1;
							$runtime  = $showinfo->video->duration;
							$myubi = genID();
							$keyword = mysql_real_escape_string($keyword);

							$surl      = getURL($title,$urlid);
							$genre     = getGenre($surl);	
							$desc      = getDescription($surl,$title);
							$desc      = htmlentities($desc);
							$_title = $title;
									$specChar = array(":",",","'","&","%","$","#","!","*","@","?","~","+",".","/","-","[","]","(",")");
										for($i=0;$i<count($specChar);$i++){
											
												if(stripos($_title,$specChar[$i])){
													
													if($specChar[$i] == "-" || $specChar[$i] == ":" || $specChar[$i] == "/" ){
														$_title = trim(substr($_title,0,(stripos($_title,$specChar[$i])))) . " " .  trim(substr($_title,(stripos($_title,$specChar[$i])+1)));
														$i--;
													}elseif($specChar[$i] == "&"){
														$_title = trim(substr($_title,0,(stripos($_title,$specChar[$i])))) . " and " .  trim(substr($_title,(stripos($_title,$specChar[$i])+1)));
							
													}elseif(stripos($_title,$specChar[$i]) == (strlen($_title)-1)){
														$_title = substr($_title,0,(stripos($_title,$specChar[$i])));
														$_title = trim($_title);
														
													}else{
														$_title = substr($_title,0,(stripos($_title,$specChar[$i])))  .  substr($_title,(stripos($_title,$specChar[$i])+1));
														$_title = trim($_title);
													}
													
												}
										
										}
							$img_title = str_replace(" ","_",strtolower($_title));	
							print "img title $img_title <br>";
							if($rating == "Yes"){
								$rating = "TV-PG";
							}else{
								$rating = "TV-MA";
							}
							
							if($expire != ""){
								$temp      = explode("T",$expire);
								$expire    = $temp[0];
							}else{
								$expire    = "2011-12-01";
							}
							
							$temp      = explode("T",$pubfull);
							$pubDate   = $temp[0];
							
							$temp      = explode("-",$pubDate);
							$year      = $temp[0];
							
							$myubi	   = genID();
							//check movie thumbnail format before including show thumb
							$showthumb = saveImage($img_title,"http://assets.huluim.com/shows/show_thumbnail_".$img_title.".jpg","showthumb");
							$keyart    = saveImage($img_title,"http://assets.huluim.com/shows/key_art_".$img_title.".jpg","keyart");
							$thumbnail = saveImage($img_title,$thumb,"thumbnail",$myubi);
							$embed     = getEmbed($url);
							if($embed == ""){
								$embed = $url;
							}
							$type      = "film";
							$title     = mysql_real_escape_string($title);
							$title 	   = htmlentities($title);
							//print "ID : $myubi  --- $title has no fee and it is in the $genre genre  with key art located at $showthumb <p>";
							print "---preparing to save <br>";
							saveShow($myubi,$title,$type,$keyword,$desc,$network,$showthumb,$thumbnail,$keyart,$provider,$genre,$url,$pubDate,$year,$userRating,$rating,$expire,$runtime,$urlid,$embed);
						
					}
		}else{
			print "---not an episode or it requires subscription<br>";
		}
	
}

}







function getURL($data,$_urlID){
	$_title = $data;
		$specChar = array(":",",","'","&","%","$","#","!","*","@","?","~","+",".","/","-","[","]","(",")");
			for($i=0;$i<count($specChar);$i++){
				
					if(stripos($_title,$specChar[$i])){
						
						if($specChar[$i] == "-" || $specChar[$i] == ":" || $specChar[$i] == "/" ){
							$_title = trim(substr($_title,0,(stripos($_title,$specChar[$i])))) . " " .  trim(substr($_title,(stripos($_title,$specChar[$i])+1)));
							$i--;
						}elseif($specChar[$i] == "&"){
							$_title = trim(substr($_title,0,(stripos($_title,$specChar[$i])))) . " and " .  trim(substr($_title,(stripos($_title,$specChar[$i])+1)));

						}elseif(stripos($_title,$specChar[$i]) == (strlen($_title)-1)){
							$_title = substr($_title,0,(stripos($_title,$specChar[$i])));
							$_title = trim($_title);
							
						}else{
							$_title = substr($_title,0,(stripos($_title,$specChar[$i])))  .  substr($_title,(stripos($_title,$specChar[$i])+1));
							$_title = trim($_title);
						}
						
					}
			
			}
	

	$url_title = str_replace(" ","-",strtolower($_title));	
	$url_title = "http://www.hulu.com/watch/".$_urlID."/".$url_title;
	return $url_title;
}

function getDescription($data,$_title){
	
	
	$tags = get_meta_tags($data);

		$desc = str_replace($_title,"",$tags['description']);
		$desc = str_replace(":","",$desc);
		$desc = str_replace("Video description ","",$desc);
		
		if(stripos($desc,",") < 2){
			$desc = str_replace(",","",$desc);
		}
		if(stripos($desc,'"') < 2){
			$desc = str_replace('"',"",$desc);
		}
	
		$desc = trim($desc);
		$desc = mysql_real_escape_string($desc);
	//	$desc = "<![CDATA[".$desc."]]>";
		
		return $desc;

}


function getGenre($data){
	
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

function genID() {
    $length = 10;
    $characters = '01234abcdefghijklm01234ABCDEFGHIJKLM56789nopqrstuvwxyzNOPQRSTUVWXYZ56789';
    $string = "";    
	$unique = false;
	
	while($unique == false){
		for ($p = 0; $p < $length; $p++) {
			$string .= $characters[mt_rand(0, strlen($characters))];
			$_string = str_split($string);
			shuffle($_string);
			$string = implode("", $_string);
			
		}
		
		$_errchk =0;
		
		$_tables = array("episode_content","film_content","news_content","web_content","trailer_content","music_content","show_reference");
		for($i=0; $i<count($_tables);$i++){
			
	  		 $query = "select * from ".$_tables[$i]." where MYUBI_ID='".$string."'";
			 $res = mysql_query($query);
	   
		   if(mysql_num_rows($res)>0) {
				break;
		   }else{
			 //  print "<br>not found in ".$_tables[$i] ."<p>";
			   $_errchk++;
		   }
			   
		}
		
		if($_errchk++ == count($_tables)){
			$unique = true;
			return $string;
		}else{
			$string=null;
		}
    	
	}
	
	
}

function saveImage($name,$thumbnail,$style,$_myubi=0){
		//name  --  img style title of show i.e.   desperate_housewives
		//thumbnail -- source for image  i.ee  http://assets.huluim.com/shows/show_thumbnail_desperate_housewives.jpg
		//style   --   keyart, showthumb (show thumbnail), thumbnail(episode thumbnail)
		$test =null;
		$value = null;
		
		if($style == "keyart"){
			$test= glob ("/opt/myubi/xml/content/images/key_art_".$name.".*");
			
			if($test[0] == ""){
				//print "no file";
				$keyart="content/images/key_art_".$img_title.".".jpg;
			}else{
				$keyart = str_replace("/opt/myubi/xml/","",$test[0]);
				print "duplicate image---><br>";
				$value = $keyart;
				return $value;
			}
		
			
			if(file_exists($keyart)){
				return $value;
			}else{
				print "<br>no file";
				
				$img_filename = "key_art_".$name;
				$raw_data = file_get_contents($thumbnail);
				
				$img_url = explode("/",$thumbnail);	
				$ext = explode(".",$img_url[count($img_url)-1]);
				$imgtype = $ext[1];
				
				if(strchr($imgtype,"?")){
					$extension = explode("?",$imgtype);
					$imgtype = $extension[0];
				}else{
					
				}
				  $FT = &new FileType();
					
				  $FT->setFileType($img_filename);
				  $FT->setFormatType($imgtype);
				  $FT->setPath("/opt/myubi/xml/content/images");
				  $FT->setDATA($raw_data);
				  $FT->output_file();
				 
				$value =  "content/images/key_art_".$name.".".$imgtype;
				return $value;
				print "thumbnail update for episode   $name---><br>";
				
			}
			
		}else if($style == "showthumb"){
			$test= glob ("content/images/show_thumbnail_".$name.".*");
			
			if($test[0] == ""){
				print "<br>no file";
				
				$img_filename = "show_thumbnail_".$name;
				$raw_data = file_get_contents($thumbnail);
				
				$img_url = explode("/",$thumbnail);	
				$ext = explode(".",$img_url[count($img_url)-1]);
				$imgtype = $ext[1];
				
				if(strchr($imgtype,"?")){
					$extension = explode("?",$imgtype);
					$imgtype = $extension[0];
				}else{
					
				}
				  $FT = &new FileType();
					
				  $FT->setFileType($img_filename);
				  $FT->setFormatType($imgtype);
				  $FT->setPath("/opt/myubi/xml/content/images");
				  $FT->setDATA($raw_data);
				  $FT->output_file();
				 
				$value =  "content/images/show_thumbnail_".$name.".".$imgtype;
				return $value;
				print "thumbnail update for episode   $name---><br>";
				
			}else{
				$value = str_replace("/opt/myubi/xml/","",$test[0]);
				return $value;
				//print "---[[[thumbnail is  $thumbnail]]]--";
			}
			
		}else if($style == "thumbnail"){
			$test= glob ("/opt/myubi/xml/content/images/thumbnail_".$_myubi."_".$name.".*");
			
			if($test[0] == ""){
				print "<br>no file";
				
				$img_filename = "thumbnail_".$_myubi."_".$name;
				$raw_data = file_get_contents($thumbnail);
				
				$img_url = explode("/",$thumbnail);	
				$ext = explode(".",$img_url[count($img_url)-1]);
				$imgtype = $ext[1];
				
				if(strchr($imgtype,"?")){
					$extension = explode("?",$imgtype);
					$imgtype = $extension[0];
				}else{
					
				}
				  $FT = &new FileType();
					
				  $FT->setFileType($img_filename);
				  $FT->setFormatType($imgtype);
				  $FT->setPath("/opt/myubi/xml/content/images");
				  $FT->setDATA($raw_data);
				  $FT->output_file();
				 
				$value =  "content/images/thumbnail_".$_myubi."_".$name.".".$imgtype;
				return $value;
				print "thumbnail update for episode   $name---><br>";
				
			}else{
				$value = str_replace("/opt/myubi/xml/","",$test[0]);
				return $value;
				//print "---[[[thumbnail is  $thumbnail]]]--";
			}
			
		}
		
}

function getKeywords($_url){
	
	$scrape = new Scrape();
	
	$scrape->fetch($_url);
	
	$data = $scrape->removeNewlines($scrape->result);
	
	$rows = $scrape->fetchAllBetween("<div class='tags-content-cell' tid= '(.*?)' cid='(.*?)'>",'</div>',$data,true);
	//print_r($rows);
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

function getEmbed($_url){
	
	
	$scrape = new Scrape();
	
	$scrape->fetch($_url);
	
	$data = $scrape->removeNewlines($scrape->result);
	
	$rows = $scrape->fetchAllBetween('<div id=\"share-copy-code-div\" class=\"gr share_panel_embed\" style=\"(.*?)\" onclick=\"(.*?)\" >','</div>',$data,true);
	//print_r($rows);
	//print "----------------------------------------<p>";
	$i=0;
	while($entry = $rows[$i]){
		// print $entry."<br>";
		 
		 $start = stripos($entry,'<param name="movie" value="') + 27;
         $body  = substr($entry,$start);
		 $end   = stripos($body,'</param>')-2;
		 $value = substr($entry,$start,$end);
		 $value = "Hulu," . $value;
		 // print stripos($entry,'<param name="movie" value="');
		 return $value;
		$i++;
	}
}

function saveShow($_myubi,$_title,$_type,$_keywords,$_desc,$_network,$_showthumb,$_thumbnail,$_keyart,$_provider,$_genre,$_url,$_pubDate,$_year,$_urating,$_rating,$_expire,$_runtime,$_urlid,$_embed){

 $query = "insert into film_content (MYUBI_ID,TITLE,QUALITY,TYPE,DESCRIPTION,NETWORK,KEYWORDS,USERRATING,RATING,COUNTRY,YEAR,URL,THUMB,RUNNINGTIME,PROVIDER,GENRE,URLID,KEYART,PUBDATE,FEE,EXPIRE,ASPECT,EMBED) VALUES ('$_myubi','$_title','HD','$_type','$_desc','$_network','$_keywords','".$_urating."','".$_rating."','US','$_year','".$_url."','".$_thumbnail."','$_runtime','$_provider','$_genre','$_urlid','".$_keyart."','$_pubDate','0','".$_expire."','9','".$_embed."')";
			 
				if (mysql_query($query)) {
					
					$query = "select * from film_content where TITLE='".$_title."' AND NETWORK='".$_network."'";
					$res = mysql_query($query);
					$res =  mysql_fetch_array($res); 
					print "<p>============TITLE $_title INSERTED=================<p>";
					print "thumb  -->  ".$res['THUMB']. "<br>";
					print "keyart  -->  ".$res['KEYART']. "<br>";
					print "url  -->  ".$res['URL']. "<br>";
					print "desc  -->  ".html_entity_decode($res['DESCRIPTION']). "<br>";
					print "urlID   -->  ".$res['URLID']. "<br>";
					print "keywords  -->  ".$res['KEYWORDS']. "<br>";
					print "ubi ID  -->  ".$res['MYUBI_ID']. "<br>";
					print "EXPIRE  -->  ".$res['EXPIRE']. "<br>";
					print "EMBED   -->  ".$res['EMBED']. "<p>";
								
				} else {
					echo "<strong>Something went wrong: ".mysql_error()."</strong>";
					$repeat = true;
				}

}


?>