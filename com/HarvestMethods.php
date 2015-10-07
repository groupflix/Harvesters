<?php

class HarvestMethods{
	
	var $host = '';
	var $dbuser = '';
	var $pswd = '';
	var $db = '';
	var $tsuffix = '';
	/**
	 * 
	 * Get Host
	 */

	public  function  setHOST($host){
		$this->host = $host;
	}
	
	/**
	 * 
	 * Get Username
	 */
	public function setDBUSER($dbuser){
		$this->dbuser = $dbuser;
		
	}
	
	/**
	 * 
	 * Get Password.
	 */
	public function setPASSWORD($pswd){
		$this->pswd = $pswd;
	}
	
	public function setDATABASE($db){
		$this->db = $db;
	}
	
	//Set Debug Table Var

	public  function setTableSuffix($s){
		$this->tsuffix = $s;
	}
	
		
	public function ParseFeed($url){
		
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; U; Linux i686; pt-BR; rv:1.9.2.18) Gecko/20110628 Ubuntu/10.04 (lucid) Firefox/3.6.18' ); 
  	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    	curl_setopt($ch, CURLOPT_URL, $url); 
   		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); 
		
		$body = curl_exec($ch);
		curl_close($ch);
		
		return $body;
	}
	
	public function progress($msg){
		print $msg;
	}
	
	public function concatTitle($data){
			$_title = trim($data);
			
			
			
			$specChar = array(":",",","'","&","%","$","#","!","*","@","?","~",";","ó","ñ","+",".","/","-","[","]","(",")");
				for($i=0;$i<count($specChar);$i++){
					
						if(stripos($_title,$specChar[$i])){
							
							if($specChar[$i] == "ó"){
								$_title = str_replace($specChar[$i],"o",$_title);
							}
							
							if($specChar[$i] == "ñ"){
								$_title = str_replace($specChar[$i],"n",$_title);
							}
							
							if($specChar[$i] == "-" || $specChar[$i] == ":" || $specChar[$i] == "/" ){
								$_title = trim(substr($_title,0,(stripos($_title,$specChar[$i])))) . " " .  trim(substr($_title,(stripos($_title,$specChar[$i])+1)));
								
							}elseif($specChar[$i] == "&"){
								$_title = trim(substr($_title,0,(stripos($_title,$specChar[$i])))) . " and " .  trim(substr($_title,(stripos($_title,$specChar[$i])+1)));
	
							}elseif(stripos($_title,$specChar[$i]) == (strlen($_title)-1)){
								$_title = substr($_title,0,(stripos($_title,$specChar[$i])));
								$_title = trim($_title);
								
							}else{
								$_title = substr($_title,0,(stripos($_title,$specChar[$i])))  .  substr($_title,(stripos($_title,$specChar[$i])+1));
								$_title = trim($_title);
							}
							$i--;
						}
				
				}
				$_title = str_replace(" ","_",strtolower($_title));	
				//print $_title ."<br>";
			return $_title;
	}
	
	public function dupCastCheck($id){
			
			$query = "select firstname from cast" . $this->tsuffix ."  where castId='".$id."'";

			$res = mysql_query($query);
			
			if(mysql_num_rows($res) > 0){
				print "[{\X cast member has already been stored/}]";
				return (bool) true;
			}else{
				print "[\ cast member isnt' stored /]";
				return (bool) false;
			}
			
	
	}
	
	public function dupCastRefCheck($id,$myubi){

			$query = "select * from cast_reference" . $this->tsuffix ."  where castId='".$id."' and myubi_id='".$myubi."'";
			$res = mysql_query($query);
			
			if(mysql_num_rows($res) > 0){
				print "[{\X cast ref has already been stored/}]";
				return (bool) true;
			}else{
				print "[\ cast ref isnt' stored /]";
				return (bool) false;
			}
			
	
	}
	
	public function dupReferenceCheck($fileloc,$type){

			$query = "select title from ".$type."_reference" . $this->tsuffix ."  where fileloc='".$fileloc."'";
			$res = mysql_query($query);
			
			if(mysql_num_rows($res) > 0){
				print "[{\X title ref has already been stored/}]";
				return (bool) true;
			}else{
				print "[\ title ref isnt' stored /]";
				return (bool) false;
			}
			
	
	}
	

	
	public function dupEpisodeCheck($urlid){

				//print "creating like statement $reg  <br>";
			$query = "select DISTINCT TITLE from episode_content" . $this->tsuffix ."  where urlid='".$urlid."'";
			$res = mysql_query($query);
				
			if(mysql_num_rows($res) > 0){
				print "[{ X title has already been stored }]";
				return (bool) true;
			}else{
				print "[{ :-) title isnt' stored }]";
				return (bool) false;
			}
			
	}

	public function dupContentCheck($urlid,$table){
		$msg = new stdclass;
				//print "creating like statement $reg  <br>";
		$query = "select DISTINCT TITLE, myubi_id from ".$table."_content" . $this->tsuffix ."  where urlid='".$urlid."'";
		$res = mysql_query($query);
				
			if(mysql_num_rows($res) > 0){
				$data = mysql_fetch_array($res);
				print "[{ X content info has already been stored }]";
				$msg->result = (bool) true;
				$msg->myubi  = $data['myubi_id'];
				return $msg;
			}else{
				print "[{ :-) content info isnt' stored }]";
				$msg->result = (bool) false;
				return $msg;
			}
			
	}

	public function dupStreamCheck($myubi,$provider,$table){

				//print "creating like statement $reg  <br>";
			$query ="SELECT DISTINCT url_hi FROM ".$table."_streams" . $this->tsuffix ." WHERE myubi_id='".$myubi."' AND provider = '".$provider."'";
			$res = mysql_query($query);
				
			if(mysql_num_rows($res) > 0){
				print "[{ X stream has already been stored }]";
				return (bool) true;
			}else{
				print "[{ :-) stream isnt' stored }]";
				return (bool) false;
			}
			
	}
	
	public function dupFilmCheck($fileloc){

			$query = "select title from film_reference" . $this->tsuffix ."  where fileloc='".$fileloc."'";
			$res = mysql_query($query);
			
			if(mysql_num_rows($res) > 0){
				print "[{ X title has already been stored }]";
				return (bool) true;
			}else{
				print "[{ :-) title isnt' stored }]";
				return (bool) false;
			}
			
	
	}
	
	
	public function dupWebStreamCheck($embed){
		
			
				$query = "select * from web_streams" . $this->tsuffix ."  where url_hi='".$embed."' OR url_lo='".$embed."'";
				$res = mysql_query($query);
		
			
			if(mysql_num_rows($res) > 0){
				print "<b>web stream has already been stored</b> <br>";
				return (bool) true;
			}else{
				print "<b>stream isnt' stored</b> <br>";
				return (bool) false;
			}
			
	}
	
	
	
	public function saveImage($name,$thumbnail,$style,$_myubi=0,$default = ""){
		//name  --  img style title of show i.e.   desperate_housewives
		//thumbnail -- source for image  i.ee  http://assets.huluim.com/shows/show_thumbnail_desperate_housewives.jpg
		//style   --   keyart, showthumb (show thumbnail), thumbnail(episode thumbnail)
		$test =null;
		$value = null;
		
		
		$test= file_get_contents($thumbnail);
		
		
		
		if($style == "keyart"){
			
			if($test == ""){
				print "image link is broken \n<br>";
				if($default == ""){
					return "content/images/key_art_default.jpg";
				}else{
					return $default;
				}
			}
			
			$keyart="/opt/myubi/xml/content/images/key_art_".$name.".jpg";
			$test= glob ($keyart);
			
			if($test[0] == ""){
				//print "no file";
				
			}else if(filesize($keyart) > 100){
				$keyart = str_replace("/opt/myubi/xml/","",$test[0]);
				print "duplicate image---><br>";
				$value = $keyart;
				return $value;
			}
		
			
			if(file_exists($keyart) && filesize($keyart) > 100){
				$keyart = str_replace("/opt/myubi/xml/","",$test[0]);
				return $keyart;
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
			
			if($test == ""){
				print "image link is broken \n<br>";
				if($default == ""){
					return "content/images/show_thumbnail_default.jpg";
				}else{
					return $default;
				}
			}
			
			$showthumb = "/opt/myubi/xml/content/images/show_thumbnail_".$name.".jpg";
			
			if(file_exists($showthumb) && filesize($showthumb) > 80){
				$value = str_replace("/opt/myubi/xml/","",$showthumb);
				print "duplicate showthumb<br>";
				return $value;
			}else{
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
				print "showthumb update for episode   $name---><br>";
				
			}
			
		}else if($style == "thumbnail"){
			
			if($test == ""){
				print "image link is broken \n<br>";
				if($default == ""){
					return "content/images/thumbnail_default.jpg";
				}else{
					return $default;
				}
			}
			
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
				print "---[[[thumbnail is  $thumbnail]]]--";
			}
			
		}

	}
	
	public function saveImageNew($name,$provider,$newimage,$style,$_myubi=0,$default = ""){
		//name  --  img style title of show i.e.   desperate_housewives
		//thumbnail -- source for image  i.ee  http://assets.huluim.com/shows/show_thumbnail_desperate_housewives.jpg
		//style   --   keyart, showthumb (show thumbnail), thumbnail(episode thumbnail)
		$src = file_get_contents($newimage);

		if($src == ""){
			print "...image link broken, returning default...";
			if($default == "")
				return "content/images".$this->tsuffix."/d/".$style."_default.jpg";
			else
				return $default;
			
		}
			
		$subfolder	   = substr($style,0,1);
		$img_filename;
		
		print "=================saving image===========================";
		switch($style){
			case("poster"):
				$img_filename = $style."_".$name;
			break;
			case("cast"):
				$img_filename = $style."_".$_myubi."_".$name;	
			break;
			case("key_art"):
				$img_filename = $style."_".$name;
			break;
			case("show_thumbnail"):
				$img_filename = $style."_".$name;
			break;
			case("thumbnail"):	
				$img_filename = "thumbnail_".$_myubi."_".$name;		
			break;
		}
		
		$image = "/opt/myubi/web/www/content/images".$this->tsuffix."/".$subfolder."/".$img_filename.".*";
		$test  = glob($image);
		
		if($test[0] == ""){
			//print "no file";			
			$raw_data     = $src;
			
			$img_url 	  = explode("/",$image);	
			$imgtype;
			if(strripos($img_url[count($img_url)-1],".") >0){
				$ext = explode(".",$img_url[count($img_url)-1]);
				$imgtype = $ext[count($ext)-1];
				
				if(strchr($imgtype,"?")){
					$extension = explode("?",$imgtype);
					$imgtype = $extension[0];
				}
			}else
				$imgtype = "jpg";
	
		
			if($imgtype == "png" || $imgtype == "jpg" || $imgtype == "jpeg" || $imgtype == "gif"){
				//print "img ext present";
			}else
				$imgtype = "jpg";
			
		   $FT = &new FileType();
			
		   $FT->setFileType($img_filename);
		   $FT->setFormatType($imgtype);
		   $FT->setPath("/opt/myubi/xml/content/images".$this->tsuffix."/".$subfolder);
		   $FT->setDATA($raw_data);
		   $FT->output_file();
		 
		   $value =  "content/images".$this->tsuffix."/".$subfolder."/".$img_filename.".".$imgtype;
		   print "[<----- " . $style ." update for $name (prefix ".$this->tsuffix.")--->]";
		   return $value;
		}else if(file_exists($test[0]) && filesize($test[0]) > 800){
			$imagenew = str_replace("/opt/myubi/web/www/","",$test[0]);
			print "[<**** " . $style ." image $name already exists ****>]";
			return $imagenew;
		}


	}
		
	public function genID() {
		$length = 10;
		$characters = '01234abcdefghijklm01234ABCDEFGHIJKLM56789nopqrstuvwxyzNOPQRSTUVWXYZ56789';
		$string = "";    
		$unique = false;
		
		/*$res = mysql_connect($this->host, $this->dbuser, $this->pswd);	
   		$res = mysql_select_db($this->db);*/
		
		while($unique == false){
			for ($p = 0; $p < $length; $p++) {
				$string .= $characters[mt_rand(0, strlen($characters))];
				$_string = str_split($string);
				shuffle($_string);
				$string = implode("", $_string);
				
			}
			
			$_errchk =0;
			
			$_tables = array("episode_reference".$this->tsuffix,"episode_content".$this->tsuffix,"film_content".$this->tsuffix,"web_reference".$this->tsuffix,"web_content".$this->tsuffix,"film_trailers".$this->tsuffix,"film_reference".$this->tsuffix);
			for($i=0; $i<count($_tables);$i++){
				
				 $query = "select * from ".$_tables[$i]." where MYUBI_ID='".substr($string,0,10)."'";
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
	

	
	public function convertStringToSeconds($time){
			
			$milliSecond = 1000;
			$milliMinute = 1000 * 60;
			$milliHour = 1000 * 60 * 60;
			
			if(strlen($time) == 8){
				$hours = (int) substr($time,0,2);
				$minutes = (int)  substr($time,2,2);
				$seconds = (int) substr($time,5);
				
				return (($hours * $milliHour) + ($minutes * $milliMinute) + ($seconds * $milliSecond))/1000;
				
			}
					
			if(strlen($time) == 7){
				$hours = (int) substr($time,0,1);
				$minutes = (int)  substr($time,2,2);
				$seconds = (int) substr($time,5);
				
				return (($hours * $milliHour) + ($minutes * $milliMinute) + ($seconds * $milliSecond))/1000;
				
			}
			else if(strlen($time) == 5){
				
				$shortmin = (int) substr($time,0,2);
				$shortsec = (int) substr($time,3);
			
				return  (($shortmin * $milliMinute) + ($shortsec * $milliSecond))/1000;
		    }else if(strlen($time) == 8){
				$time = substr($time,1);
				$hours = (int) substr($time,0,1);
				$minutes = (int)  substr($time,2,2);
				$seconds = (int) substr($time,5);
				
				return (($hours * $milliHour) + ($minutes * $milliMinute) + ($seconds * $milliSecond))/1000;
			}
			
			return(0);
	
	}
	
	
	public function getMetaDescription($data,$_title){
	
	
	$tags = get_meta_tags($data);

		$desc = str_replace($_title,"",$tags['description']);
		$desc = str_replace(":","",$desc);
		
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
	
	//deprecated API
	public function getIMDB($title){
		$query    = str_replace(" ","%20",$title);
		$received = file_get_contents('http://www.imdbapi.com/?t='.$query);
		
		$imdbjson = json_decode($received);
		
		if($imdbjson->{'Response'} == "Parse Error"){
			$imdbjson = "error";
		}
		
		return $imdbjson;
	}
	
	public function pullIMDB($req,$type){
		//$query    = str_replace(" ","%20",$title);
		if($type == "title"){
			$reg = urlencode($reg);
			$type = 'q';
		}
		
		$received = file_get_contents('http://www.deanclatworthy.com/imdb/?'.$type.'='.$req);
		$received =	trim(str_replace('{"code":2,"error":"Exceeded API usage limit"}',"",$received));
		$imdbjson = json_decode($received);

		if($imdbjson->{'error'} == "Film not found"){
			$imdbjson = "error";
		}
		
		return $imdbjson;
	}
	
	
	public function getIMDBid($title){
		$results  = file_get_contents('http://www.imdb.com/find?q='.urlencode($title).'&s=tt&exact=true&ref_=fn_tt_ex');


		$tags = get_meta_tags('http://www.imdb.com/find?q='.urlencode($title).'&s=tt&exact=true&ref_=fn_tt_ex');
		
		$titleOnly = explode('(',$tags['title']);
		
		//if(strripos(html_entity_decode($tags['title']),$title,0) > -1){
		if(strripos($title,trim(html_entity_decode($titleOnly[0]),ENT_QUOTES, 'UTF-8'),0) > -1){
			
			preg_match_all("/<meta\s[^>]*property=\"og\:url\" content=(\"??)([^\" >]*?)\\1[^>]*>/siU", $results, $meta);  
			$urlSet = explode("/",$meta[2][0]);
			
			if(strlen($urlSet[4]) < 2)
				return 'error';
			else
				return $urlSet[4];
			
/*			<span class="title-extra">
&#x22;American Horror Story&#x22; 
<i>(original title)</i>
</span>
*/
		}else{
		
			
			$start = strripos($results,'Titles</h3>',1000);
			$end   = strpos($results,'</table>',$start);
		
			$table = substr($results,$start,$end-$start);
			if(preg_match_all("/<tr class=\"findResult \w{3,5}\">(.*)<\/tr>/siU", $table, $tr)){
				
		
				
				preg_match_all("/<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU", $tr[0][0], $id);
				
				$cleanSet  = explode("?",$id[2][0]);
				
				$urlSet    = explode("/",$cleanSet[0]);
				
				$imdbtitle = "";
				if($id[3][1] != "")
					$imdbtitle = str_replace('"',"",html_entity_decode($id[3][1],ENT_QUOTES, 'UTF-8'));
				else
					$imdbtitle = str_replace('"',"",html_entity_decode($id[3][0],ENT_QUOTES, 'UTF-8'));

				preg_match("/\(\d{4,}\).*\((.*)\)/siU", $tr[0][0], $type);
				preg_match('/<p class="find-aka">(.*)<\/p>/siU', $tr[0][0], $aka);

				$nonUS = false;
				if(strripos($aka[1],"alternative title",0)>0)
					$nonUS = true;
				
				
				if(strripos(" ".strtolower($type[1]),"tv series",0) > 0 && levenshtein($title,$imdbtitle)< 2 && $nonUS ===false)
					return $urlSet[2];
				else{
					$results  = file_get_contents('http://www.imdb.com/find?q='.urlencode($title).'&s=tt&ref_=fn_tt_pop');
					
					$start = strripos($results,'Titles</h3>',1000);
					$end   = strpos($results,'</table>',$start);
				
					$table = substr($results,$start,$end-$start);
					if(preg_match_all("/<tr class=\"findResult \w{3,5}\">(.*)<\/tr>/siU", $table, $tr)){
							
						$count = 0;
						
						if(count($tr[0]) > 2)
							$count = 2;
						else
							$count = count($tr[0]);
							
						for($k = 0; $k < $count; $k++){
							preg_match_all("/<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU", $tr[0][$k], $id);
							
							$cleanSet  = explode("?",$id[2][0]);
				
							$urlSet    = explode("/",$cleanSet[0]);
							$imdbtitle = "";
						
							if($id[3][1] != "")
								$imdbtitle = str_replace('"',"",html_entity_decode($id[3][1],ENT_QUOTES, 'UTF-8'));
							else
								$imdbtitle = str_replace('"',"",html_entity_decode($id[3][0],ENT_QUOTES, 'UTF-8'));
							
							preg_match("/\(\d{4,}\).*\((.*)\)/siU", $tr[0][0], $type);
							preg_match('/<p class="find-aka">(.*)<\/p>/siU', $tr[0][0], $aka);
							
							
							$nonUS = false;
							if(strripos($aka[1],"alternative title",0)>0)
								$nonUS = true;
							
							if(count($type) != 0 && $nonUS ===false){
						
								if(strripos(" ".strtolower($type[1]),"tv series",0) > 0 && levenshtein($title,$imdbtitle) < 2)
									return (string) $urlSet[2];
							}
				    	}
						return "error";	
					}
				}
			}
		}
		
	}
	
	public function theTVdb($show,$imgtype,$season="",$ep="",$eptitle=""){
		global $scrape;
		$searchpg = file_get_contents("http://thetvdb.com/?string=".urlencode($show)."&searchseriesid=&tab=listseries&language=English&function=Search");
	
		$start = stripos($searchpg,"<h1>TV Shows",0);
		$end   = stripos($searchpg,"</div>",$start+20);
		$res   = substr($searchpg,$start+20, $end - $start+20);
	
		$showID;
		$showLink;
		$errorMark = 0;
		$result_options = array();
		
		if(preg_match_all("/<tr>(.*)<a\s[^>]*href=\"(.*)\">(.*)<\/a>(.*)<\/tr>/siU", $res, $resnames)) {
		
			for($k=0; $k<count($resnames[0]); $k++){
				
				if(stripos($resnames[4][$k],"English",0) > 0){
					$showdata = new stdClass;
					$showdata->showLink = str_replace('&amp;',"&","http://www.thetvdb.com".$resnames[2][$k]);
					$params   = split("&amp;",$resnames[2][$k]);
					$showdata->ID   = trim(str_replace("id=","",$params[1]));
					$showparts= split(" ",strtolower($show));
					preg_match("/\([1-3][0-9][0-9][0-9]\)/i", $resnames[3][$k], $match);
					if($match)
						$resnames[3][$k] = trim(str_replace($match[0],"",$resnames[3][$k]));
					
					if(strtolower($resnames[3][$k]) == strtolower($show) || $showparts[0] == strtolower($resnames[3][$k]))
						array_push($result_options,$showdata);
				}
				
			}
		}
	
	 if(count($result_options) == 0)
	 return "error";
	
	 for($r=0; $r<count($result_options); $r++){
		$showLink = $result_options[$r]->showLink;
		$showID   = $result_options[$r]->ID;
		
		$showpage     = file_get_contents($showLink);
		$suffix   	  = 1;
		$imagepresent = false;
		
		switch($imgtype){
			case('poster'):
			print 'poster';
				//FIND NUMBER OF IMAGES
				if(preg_match_all("/<a\s[^>]*href=\"\/\?tab=seriesposters\&id=".$showID."\">(.*)<\/a>/siU", $showpage, $num)) {
					preg_match('{(\d+)}',$num[1][0], $max);
					//print_r($max);
					$suffix = $max[0];
				}
				
				//leverage number of images to pull the latest one
				$poster = "http://thetvdb.com/banners/_cache/posters/".$showID."-".$suffix.".jpg";
				$test= file_get_contents($poster);
				
				$trys =0;
				$error;
				while(!$imagepresent){
					$suffix--;
					if($test == ""){
						print "no image, lets try another one";
						$poster = "http://thetvdb.com/banners/_cache/posters/".$showID."-".($suffix).".jpg";
						$test= file_get_contents($poster);
						$trys++;
						if($trys >4){
							$imagepresent = true;
							$error = true;
						}
					}else{
						$imagepresent = true;
					}
				}
				
				if($error == "")
				    return $poster;
				else
					return "error";
			break;
			case('showthumb'):

				//FIND NUMBER OF IMAGES
				if(preg_match_all("/<a\s[^>]*href=\"\/\?tab=seriesfanart\&id=".$showID."\">(.*)<\/a>/siU", $showpage, $num)) {
					preg_match('{(\d+)}',$num[1][0], $max);
					
					$suffix = $max[0];
				}
				
				//leverage number of images to pull the latest one
				$showthumb = "http://thetvdb.com/banners/_cache/fanart/original/".$showID."-".$suffix.".jpg";
				$test= file_get_contents($showthumb);
				
				$trys =0;
				$error;
				while(!$imagepresent){
					$suffix--;
					if($test == ""){
						
						$showthumb = "http://thetvdb.com/banners/_cache/fanart/original/".$showID."-".($suffix).".jpg";
						$test= file_get_contents($showthumb);
						$trys++;
						if($trys >4){
							$imagepresent = true;
							$error = true;
						}
					}else{
						$imagepresent = true;
					}
				}
				
				if($error == "")
				 return $showthumb;
				else
					$errorMark++;
					
			break;
			case('thumbnail'):
			//Find available season
			
				$seasonLink;
				if(preg_match_all("/<a\s[^>]*href=\"\/\?tab=season\&seriesid=".$showID."(\'??)([^\' >]*?)\\1[^>]*>(.*)<\/a>/siU", $showpage, $seasons)) {
					
					for($t=0; $t<count($seasons[3]); $t++){
							if($seasons[3][$t] == $season){
								$linksuffix = str_replace('"',"",$seasons[2][$t]);
								$seasonLink = str_replace('&amp;',"&","http://thetvdb.com/?tab=season&seriesid=".$showID.$linksuffix);
								break;
							}
					}
				}
			
				$seasonpage = file_get_contents($seasonLink);
				
				$start = stripos($seasonpage,'<td class="head">Episode Number',0);
				$end   = stripos($seasonpage,"</tbody>",$start+50);
				$res   = substr($seasonpage,$start+50, $end - $start+50);
				
				$epLink;
				if(preg_match_all("/<tr>(.*)<a\s[^>]*href=\"(.*)\">(.*)<\/a>(.*)<\/tr>/siU", $res, $resnames)) {
					
					for($k=0; $k<count($resnames[0]); $k++){
						
						if(stripos($resnames[0][$k],$eptitle,0) > 0){
							preg_match('/<a\s[^>]*href=\"(.*)\">(.*)<\/a>/siU',$resnames[0][$k], $linkdata);
							
							$epLink = str_replace("?","",str_replace('&amp;',"&",$linkdata[1]));
							break;
						}
						
					}
				}
				
				$idData = split('&',$epLink);
				$epID   = str_replace("id=","",$idData[3]);
				
				$thumbnail = "http://thetvdb.com/banners/_cache/episodes/".$showID."/".$epID.".jpg";
				
				$test= file_get_contents($thumbnail);
				
				$error = "";
				
				if($test == ""){
					$error = true;
				}
				
				if($error == "")
					 return $thumbnail;
				else
					$errorMark++;
			break;
			case('keyart'):
			
				//FIND NUMBER OF IMAGES
				if(preg_match_all("/<a\s[^>]*href=\"\/\?tab=seriesfanart\&id=".$showID."\">(.*)<\/a>/siU", $showpage, $num)) {
					preg_match('{(\d+)}',$num[1][0], $max);
					
					$suffix = $max[0];
				}
				
				//leverage number of images to pull the latest one
				$keyart = "http://thetvdb.com/banners/fanart/original/".$showID."-".($suffix-1).".jpg";
				$test= file_get_contents($keyart);
				
				$trys =0;
				
				while(!$imagepresent){
					$suffix--;
					if($test == ""){
						
						$keyart = "http://thetvdb.com/banners/fanart/original/".$showID."-".($suffix).".jpg";
						$test= file_get_contents($keyart);
						$trys++;
						if($trys >4){
							$imagepresent = true;
							$error = true;
						}
					}else{
						$imagepresent = true;
					}
				}
				
				if($error == "")
				 return $keyart;	
				else
					$errorMark++;
			break;
			case('cast'):
			//Find available season
			$thumbnail = "";
				$error = "";
				$castpics = file_get_contents("http://thetvdb.com/?tab=actors&id=".$showID);
			
				if($castpics == "")
					return "error";
				
				preg_match('/<img src="(.*?)" class="banner" border="0" alt="'.$eptitle.'" title="'.$eptitle.'">/',$castpics,$imgs);
				 //print_r($imgs);
				if($imgs[1] != ""){
					$thumbnail = "http://thetvdb.com" . $imgs[1];
				
					$test= file_get_contents($thumbnail);
				
				}else
					$error = true;
			
				
				if($error == "")
					 return $thumbnail;
				else
					return "error";
			break;
		}
		
			if($errorMark == count($result_options) || $errorMark > 2){
				return "error";
				break;
			}
		 
	   }
	}
	
	public function getFilmTrailer($title){
		//use trailer addict
		$test = file_get_contents('http://www.traileraddict.com/tags/'.$title);
		preg_match("/<title>(.+)<\/title>/i",$test,$urltitle);
		
		if(strripos($urltitle[1],"Not Found",0)>0){
			print " no trailer available";	
			return "error";
		}else{
			$test = "";	
			$tpage = $this->ParseFeed('http://www.traileraddict.com/trailer/'.$title.'/trailer');
	
			preg_match_all('/<meta property="og:[a-z]+" content="(.+)" \/>/siU',substr($tpage,0,15000),$meta);

			//$poster = $meta[1][4];
			$embed  = "traileraddict,".$meta[1][5];
			return $embed;
		}
	}
	
	public function buildURLIDepisode($season,$episode,$fileloc){

		//the first 50 characters of the presume file name will be used as a unique id for providers 
		//that dont have a unique id for us to use
		$urlid  = 'e'.base64_encode($episode);
		$urlid .= 's'.base64_encode($season);
		$urlid .=  base64_encode($fileloc);
		return substr($urlid,0,50);
	}
	
	public function buildURLIDfilm($fileloc,$duration){

		//the first 50 characters of the presume file name will be used as a unique id for providers 
		//that dont have a unique id for us to use
		$urlid  =  base64_encode($duration);
		$urlid .=  base64_encode($fileloc);
		return substr($urlid,0,50);
	}
	
	public function buildURLIDweb($eptitle,$fileloc){

		//the first 50 characters of the presume file name will be used as a unique id for providers 
		//that dont have a unique id for us to use
		$urlid  =  base64_encode($eptitle);
		$urlid .=  base64_encode($fileloc);
		return substr($urlid,0,50);
	}
	
	public function addImageRows($obj){
		$table  = $this->defineType($obj->type);
		
		$select = mysql_query("SELECT showthumb,keyart,poster FROM ".$table."_reference" . $this->tsuffix ." WHERE fileloc='".$obj->fileloc."'");
		$refData= mysql_fetch_assoc($select);
		
		$obj->showthumb = $refData['showthumb'];
		$obj->keyart    = $refData['keyart'];
		$obj->poster    = $refData['poster'];
		
		return $obj;
		
	}
	
	public function addBasicRefRows($obj){
		$table  = $this->defineType($obj->type);
		
		$select = mysql_query("SELECT description,publisher,keywords FROM ".$table."_reference" . $this->tsuffix ." WHERE fileloc='".$obj->fileloc."'");
		$refData= mysql_fetch_assoc($select);
		
		$obj->description = $refData['description'];
		$obj->keywords    = $refData['publisher'];
		$obj->publisher   = $refData['keywords'];
		
		return $obj;
		
	}
	
	public function getFileloc($imgtitle,$type){
		$typeName = $this->defineType($type);
		$subfolder= substr(preg_replace('/[^a-z0-9]/i', "",$imgtitle),0,1);
		
		$fileloc  = "content/xml/".$typeName."/".$subfolder."/".$imgtitle.".xml";
		return $fileloc;
	}
	
	public function defineType($typeNum){
		
		switch($typeNum){
			case(1):
				return "episode";
			break;
			case(2):
				return "film";
			break;
			case(3):
				return "web";
			break;
		}
	}
	
	public function getHuluID($title){

		$img_title = $this->concatTitle($title);
		$showpage = file_get_contents("http://www.hulu.com/".str_replace("_","-",strtolower($img_title)));
		preg_match_all('/content=\"http:\/\/[a-z][a-z][0-9].huluim.com\/show\/(.*)\?(.*)\"/siU',$showpage,$res);
		
		//'http://ib4.huluim.com/video/60120267?size=476x268';
		$data = array();
		$data['showid'] = $res[1][0];
		
		return $data['showid'];
	}
	
	
	public function addDate($orgDate,$mth){
	
	  $cd = strtotime($orgDate);
	  $retDAY = date('Y-m-d', mktime(0,0,0,date('m',$cd)+$mth,date('d',$cd),date('Y',$cd)));
	  return $retDAY;
	  
	} 
	
	public function getRSS($feed){
		
		
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; U; Linux i686; pt-BR; rv:1.9.2.18) Gecko/20110628 Ubuntu/10.04 (lucid) Firefox/3.6.18' ); 
			curl_setopt($ch, CURLOPT_URL, $feed);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$body = curl_exec($ch);
			curl_close($ch);
			
			$xml= new SimpleXmlElement($body, LIBXML_NOCDATA);
			
			return $xml;
	}
	
		
	public function googleImageCheck($title,$type){
		$query= $title . " " . $type;
		$query = rawurlencode($query);
			
		//could implement  imgsz=xxlarge restricts results to large images    
	
		// now, process the JSON string
		$results = json_decode($this->search($query,0)); 
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
				$results = json_decode($this->search($query,8));
			}
		}
		
		$imgset = array($keyart_src,$showthumb_src,$thumbnail_src,$poster_src);
	
		return $imgset;
		
	}
	
	public function search($q,$s){
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
	
}

?>