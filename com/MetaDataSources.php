<?php

class MetaDataSources{
	
	public function getCast($link,$showObj){
		global $harvest;
		$showObject  = $showObj;
	    $showObject->actors = "";
		$scrape    = new Scrape();
		$scrape->fetch($link);
		$data   = $scrape->removeNewlines($scrape->result);
		/*while($scrape->result == ""){
			print "download error";
			$scrape->fetch($link);
		}*/
		//PULL CREATORS / STARS
		//$data   = $scrape->removeNewlines($scrape->result);
		$start  = '<div class=\"txt-block\">';
		$end    = '<\/div>';

		$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
	
		$creator  = "";
		$starlist = array();
		
		for($t = 0; $t < count($imdbdata); $t++){
			//print strip_tags($imdbdata[$t]) . "\n\n";
			$subhead = explode(":", strip_tags($imdbdata[$t]));
			//print $subhead[0] . "\n";
			
			switch(strtolower($subhead[0])){
				case("creators"):
					$showObject->creator = trim($subhead[1]);
				break;
				case("creator"):
				
					$showObject->creator = trim($subhead[1]);
				break;
				case("runtime"):
					if($showObject->duration == 0 || $showObject->duration == ""){
						$runtime = str_replace('min',"",trim(str_replace("(approx.)","",trim($subhead[count($subhead)-1]))));
						$runtime = (int) preg_replace("/[^0-9]/", '',$runtime);
						$runtime = $runtime * 60;
						//print "\n runtime is $runtime \n";
						
						$showObject->duration = $runtime;
					}
				break;
				case("stars"):
					
					if(preg_match_all("/<aonclick=(\'??)([^\' >]*?)\\1[^>]*>(.*)<\/a>/siU", $imdbdata[$t], $starnames)) {
						
						if($starnames[3][count($starnames[3])-1] == "See full cast and crew")
							unset($starnames[3][count($starnames[3])-1]);
							
						$showObject->actors = mysql_escape_string($this->ArrayToString($starnames[3]));
					}

				break;
			}
		}
		
		if($showObject->actors =="")
			return "error";
		else
			return $showObject;
	}
	
	public function scrapeIMDBmain($link,$showObj,$extra){
		global $harvest;
		$showObject  = $showObj;
	
		$scrape    = new Scrape();
		$scrape->fetch($link);
		/*while($scrape->result == ""){
			print "download error";
			$scrape->fetch($link);
		}*/
		//PULL SYNOPSIS
		$data   = $scrape->removeNewlines($scrape->result);
		$start  = '<p itemprop=\"description\">';
		$end    = '<\/p>';

		$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
		
		if(strripos(strip_tags($imdbdata[0]),"...S",0) === false)
			$showObject->synopsis   = htmlentities(strip_tags($imdbdata[0]),ENT_QUOTES, 'UTF-8');
		else{
			$descParts = split("\.\.\.S",strip_tags($imdbdata[0]));
			$showObject->synopsis   = htmlentities(strip_tags($descParts[0]),ENT_QUOTES, 'UTF-8');
		}
		
		//PULL PUBDATE
		if($showObject->pubDate == ""){
			$rawpage = file_get_contents($link);
			$start = stripos($rawpage,'<h4 class="inline">Release Date:',0);
			$end   = stripos($rawpage,"</time>",$start+25);
			$res   = substr($rawpage,$start+25, $end - $start+25);
			
			if(preg_match_all("/<time itemprop=\"datePublished\" datetime=\"(.*)\">(.*)<\/time>/siU", $res, $date)) {
				
				if(strlen($date[1][0]) < 8)
					$showObject->pubDate = date('Y-m-d');
				else
					$showObject->pubDate = $date[1][0];
			}
		}
		//PULL CREATORS / STARS
		//$data   = $scrape->removeNewlines($scrape->result);
		$start  = '<div class=\"txt-block\">';
		$end    = '<\/div>';

		$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
	
		$creator  = "";
		$starlist = array();
	
		for($t = 0; $t < count($imdbdata); $t++){
			//print strip_tags($imdbdata[$t]) . "\n\n";
			$subhead = explode(":", strip_tags($imdbdata[$t]));
			//print $subhead[0] . "\n";
			switch(strtolower($subhead[0])){
				case("creators"):
					$showObject->creator = mysql_escape_string(trim($subhead[1]));
				break;
				case("creator"):
				
					$showObject->creator = mysql_escape_string(trim($subhead[1]));
				break;
				case("runtime"):
					if($showObject->duration == 0 || $showObject->duration == ""){
						$runtime = str_replace('min',"",trim(str_replace("(approx.)","",trim($subhead[count($subhead)-1]))));
						$runtime = (int) preg_replace("/[^0-9]/", '',$runtime);
						$runtime = $runtime * 60;
						//print "\n runtime is $runtime \n";
						
						$showObject->duration = $runtime;
					}
				break;
				case("stars"):
					
					if(preg_match_all("/<aonclick=(\'??)([^\' >]*?)\\1[^>]*>(.*)<\/a>/siU", $imdbdata[$t], $starnames)) {
						
						if($starnames[3][count($starnames[3])-1] == "See full cast and crew")
							unset($starnames[3][count($starnames[3])-1]);
							
						$showObject->actors = mysql_escape_string($this->ArrayToString($starnames[3]));
					}

				break;
			}
		}
		//print "\n";

		//$data   = $scrape->removeNewlines($scrape->result);
		$start  = '<h2>Storyline<\/h2>';
		$end    = '<em class=\"nobr\">';

		$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
		
		if(strripos($imdbdata[0],"Storyline"))
			$desclong = htmlentities(substr(strip_tags($imdbdata[0]),9),ENT_QUOTES, 'UTF-8');
		else
			$desclong = htmlentities(strip_tags($imdbdata[0]),ENT_QUOTES, 'UTF-8');
		
		if($showObject->description == "")
			$showObject->description= $desclong;

	
		//SEARCH FOR RATING
		//$data   = $scrape->removeNewlines($scrape->result);
		$start  = '<div class=\"infobar\">';
		$end    = '<\/div>';

		$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
		
		$start = stripos($imdbdata[0],'" alt="',10);
		$end   = stripos($imdbdata[0],'" src="http',10);
		
		$rating= substr($imdbdata[0], $start+7, ($end - ($start+7)));
		$rating= str_replace("_","-",$rating);

		if(strripos($imdbdata[0],'class="absmiddle"',0)){
			$showObject->rating= $rating;
		}else{
			$showObject->rating = "NA";
		}
		
		//SEARCH FOR TRAILER
		//$data   = $scrape->removeNewlines($scrape->result);
		$start  = '<td id=\"overview-bottom\">';
		$end    = '<\/span><\/a>';

		$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
		$turl     = "";

		if(strripos($imdbdata[0],"Watch Trailer")){
			$start = stripos($imdbdata[0],'href="',10);
			$end   = stripos($imdbdata[0],'" itemprop="trailer"',10);
			
			$turl  = substr($imdbdata[0], $start+6, ($end - ($start+6)));
		}
		
		if($turl == ""){
		}else{
		  $showObject->trailer = "http://www.imdb.com" . $turl . "player?uff=3";
		}
		
		
		if($showObject->genre == ""){
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
				
			$showObject->genre = str_replace('-TV',"",$this->ArrayToString($genrelist));
		}
		
		//SEARCH FOR POSTER
		//$data   = $scrape->removeNewlines($scrape->result);
		$start  = '<td rowspan=\"2\" id=\"img_primary\">';
		$end    = '<\/td>';

		$imdbdata  = $scrape->fetchAllBetween($start,$end,$data,true);

			$regexp = '<img src=(\"??)([^\" >]*?)\\1[^>]*>';
			if(preg_match_all('/'.$regexp.'/siU', $imdbdata[0], $imgmatch)) {
				$showObject->poster = $imgmatch[2][0];
			}
		
		//SEARCH FOR KEYWORDS
		//$data   = $scrape->removeNewlines($scrape->result);
		$start  = '<h4 class=\"inline\">Plot Keywords:<\/h4>';
		$end    = '<\/div>';

		$imdbdata  = $scrape->fetchAllBetween($start,$end,$data,true);
		
		$keydata   = str_replace('<h4 class="inline">Plot Keywords:</h4>',"",$imdbdata[0]);
		$keylist   = array();
		
			$regexp = "<aonclick=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>"; 
			
			if(preg_match_all('/'.$regexp.'/siU', $keydata, $keymatch)) {
					$keylist = $keymatch[3];
					
			}
			
		$showObject->keywords = $this->ArrayToString($keylist);
		if(stripos($showObject->keywords,", See",0) > 0)
			$showObject->keywords = str_replace(", See more","",$showObject->keywords);
		else
			$showObject->keywords = $showObject->keywords;
			
		//SEARCH FOR YEAR
		//$data   = $scrape->removeNewlines($scrape->result);
		$start  = '<td id=\"overview-top\">';
		$end    = '<\/span><\/h1>';

		$imdbdata   = $scrape->fetchAllBetween($start,$end,$data,true);
		$splitTitle = explode('<span class="nobr">',$imdbdata[0]);

		preg_match("/\d{4}/", strip_tags(trim(html_entity_decode($splitTitle[count($splitTitle)-1],ENT_QUOTES, 'UTF-8'))),$yearset);
	
		if($yearset[0] == date('Y',strtotime($yearset[0])))
			$showObject->year    =  date('Y',strtotime($yearset[0]));
		else if($showObject->pubDate != date('Y-m-d'))
			$showObject->year    =  date('Y',strtotime($showObject->pubDate));
		else
			$showObject->year    = '2005';
		//SEARCH FOR RELATED TITLES  (cant be done, requires ajax for js...oh wait im a fuckin GGGGGGGGG);
		$imdbID = split('/',$link);
		$fields_string;
		$fields = array(
					'count'=>urlencode(5),
					'start'=>urlencode(0),
					'specs'=>urlencode('p13nsims:' . $imdbID[4]),
					'caller_name'=>urlencode('p13nsims-title'),
				);
				
		if($extra){
		$harvest->progress("..get recs..");				
				//url-ify the data for the POST
				foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
				rtrim($fields_string,'&');
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, 'http://www.imdb.com/widget/recommendations/_ajax/get_more_recs');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch,CURLOPT_POST,count($fields));
				curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
				$body = curl_exec($ch);
				$response;
				if(curl_exec($ch) === false)
					print '<p>Curl error: ' . curl_error($ch);
				else
					$response = json_decode($body);
		
				$recset = array();
				$reccount = 0;
				foreach($response->{'recommendations'} as $rec){
				
					$regexp = "<div\s[^>]*title=(.*)>(.*)<\/div>"; 
					if(preg_match_all('/'.$regexp.'/siU', $rec->{'content'}, $matchtitle)) {
				
						array_push($recset,trim(str_replace('"',"",$matchtitle[1][0])));
						$reccount++;
					}
					
					if($reccount > 4)
					break;
				}
		$harvest->progress("..get credits..");			
				$showObject->recset = $recset;
				//SEARCH FOR NETWORK
				if($showObject->network == ""){
					$scrape->fetch($link . 'companycredits');
					$data   = $scrape->removeNewlines($scrape->result);
					$start  = '<h2>Distributors</h2><ul>(.*)';
					$end    = '<\/ul>';
					
					$imdbdata   = $scrape->fetchAllBetween($start,$end,$data,true);
				
					$regexp = "<li><a\s[^>]*href=(\'??)([^\' >]*?)\\1[^>]*>(.*)<\/a>(.*)<\/li>"; 
					
						if(preg_match_all('/'.$regexp.'/siU', $imdbdata[0], $match)) {
						
							if($match[3][0]){
									$network = trim(str_replace('Network',"",trim($match[3][0])));
									$network = ($network == "Showtime s") ? "Showtime" : $network;
									$abbr    = split('\(',$network);
								if(stripos($network,"(") ===false)
									$showObject->network = mysql_escape_string($network);
								else
									$showObject->network = mysql_escape_string(trim(str_replace(")","",$abbr[1])));
								
							}
						}
					// SEARCH FOR PRODUCTION COMPANY OR PUBLISHER
					$start  = '<h2>Production Companies</h2><ul>(.*)';
					$end    = '<\/ul>';
					
					$imdbdata   = $scrape->fetchAllBetween($start,$end,$data,true);
					
					$regexp = "<li><a\s[^>]*href=(\'??)([^\' >]*?)\\1[^>]*>(.*)<\/a>(.*)<\/li>"; 
					
						if(preg_match_all('/'.$regexp.'/siU', $imdbdata[0], $match2)) {

							if($match2[3][0]){

								$showObject->publisher = trim($match2[3][0]);

								
							}
						}
				}
			}else{
				$harvest->progress("..skip extras..");	
			}
				$harvest->progress(".end imdb.");		
						
						
						
					//print_r($showObject);
					return $showObject;
		}
		
		function scrapeIMDBmain_arr($link,$showObj,$extra){
				global $harvest;
				$showObject  = $showObj;
		
				$scrape    = new Scrape();
				$scrape->fetch($link);
				/*while($scrape->result == ""){
					print "download error";
					$scrape->fetch($link);
				}*/
				//PULL SYNOPSIS
				$data   = $scrape->removeNewlines($scrape->result);
				$start  = '<p itemprop=\"description\">';
				$end    = '<\/p>';
		
				$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
				
				if(strripos(strip_tags($imdbdata[0]),"...S",0) === false)
					$showObject['synopsis']   = mysql_escape_string(htmlentities(strip_tags($imdbdata[0])),ENT_QUOTES, 'UTF-8');
				else{
					$descParts = split("\.\.\.S",strip_tags($imdbdata[0]));
					$showObject['synopsis']   = mysql_escape_string(htmlentities(strip_tags($descParts[0])),ENT_QUOTES, 'UTF-8');
				}
				
				//PULL PUBDATE
				$rawpage = file_get_contents($link);
				$start = stripos($rawpage,'<h4 class="inline">Release Date:',0);
				$end   = stripos($rawpage,"</time>",$start+25);
				$res   = substr($rawpage,$start+25, $end - $start+25);
				
				if(preg_match_all("/<time itemprop=\"datePublished\" datetime=\"(.*)\">(.*)<\/time>/siU", $res, $date)) {
					
					if(strlen($date[1][0]) < 8)
						$showObject['pubDate'] = date('Y-m-d');
					else
						$showObject['pubDate'] = $date[1][0];
				}
	
				//PULL CREATORS / STARS
				//$data   = $scrape->removeNewlines($scrape->result);
				$start  = '<div class=\"txt-block\">';
				$end    = '<\/div>';
		
				$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
			
				$creator  = "";
				$starlist = array();
			
				for($t = 0; $t < count($imdbdata); $t++){
					//print strip_tags($imdbdata[$t]) . "\n\n";
					$subhead = explode(":", strip_tags($imdbdata[$t]));
					//print $subhead[0] . "\n";
					switch(strtolower($subhead[0])){
						case("creators"):
							$showObject['creator'] = mysql_escape_string(trim($subhead[1]));
						break;
						case("creator"):
						
							$showObject['creator'] = mysql_escape_string(trim($subhead[1]));
						break;
						case("runtime"):
							if($showObject['duration'] == 0 || $showObject['duration'] == ""){
								$runtime = str_replace('min',"",trim(str_replace("(approx.)","",trim($subhead[count($subhead)-1]))));
								$runtime = (int) $runtime;
								$runtime = $runtime * 60;
								//print "\n runtime is $runtime \n";
								
								$showObject['duration'] = $runtime;
							}
						break;
						case("stars"):
							
							if(preg_match_all("/<aonclick=(\'??)([^\' >]*?)\\1[^>]*>(.*)<\/a>/siU", $imdbdata[$t], $starnames)) {
								
								if($starnames[3][count($starnames[3])-1] == "See full cast and crew")
									unset($starnames[3][count($starnames[3])-1]);
									
								$showObject['actors'] = mysql_escape_string($this->ArrayToString($starnames[3]));
							}
	
						break;
					}
				}
				//print "\n";
	
				//$data   = $scrape->removeNewlines($scrape->result);
				$start  = '<h2>Storyline<\/h2>';
				$end    = '<em class=\"nobr\">';
		
				$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
				
				if(strripos($imdbdata[0],"Storyline")){
					$desclong = htmlentities(substr(strip_tags($imdbdata[0]),9),ENT_QUOTES, 'UTF-8');
				}else{
					$desclong = htmlentities(strip_tags($imdbdata[0]),ENT_QUOTES, 'UTF-8');
				}
				
				if($showObject['description'] == "")
					$showObject['description']= mysql_escape_string($desclong);
	
			
				//SEARCH FOR RATING
				//$data   = $scrape->removeNewlines($scrape->result);
				$start  = '<div class=\"infobar\">';
				$end    = '<\/div>';
		
				$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
				
				$start = stripos($imdbdata[0],'" alt="',10);
				$end   = stripos($imdbdata[0],'" src="http',10);
				
				$rating= substr($imdbdata[0], $start+7, ($end - ($start+7)));
				$rating= str_replace("_","-",$rating);
		
				if(strripos($imdbdata[0],'class="absmiddle"',0)){
					$showObject['rating']= $rating;
				}else{
					$showObject['rating'] = "NA";
				}
				
				//SEARCH FOR TRAILER
				//$data   = $scrape->removeNewlines($scrape->result);
				$start  = '<td id=\"overview-bottom\">';
				$end    = '<\/span><\/a>';
		
				$imdbdata = $scrape->fetchAllBetween($start,$end,$data,true);
				$turl     = "";
	
				if(strripos($imdbdata[0],"Watch Trailer")){
					$start = stripos($imdbdata[0],'href="',10);
					$end   = stripos($imdbdata[0],'" itemprop="trailer"',10);
					
					$turl  = substr($imdbdata[0], $start+6, ($end - ($start+6)));
				}
				
				if($turl == ""){
				}else{
				  $showObject['trailer'] = "http://www.imdb.com" . $turl . "player?uff=3";
				}
				
				
				if($showObject['genre'] == ""){
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
						
					$showObject['genre'] = $this->ArrayToString($genrelist);
				}
				
				//SEARCH FOR POSTER
				//$data   = $scrape->removeNewlines($scrape->result);
				$start  = '<td rowspan=\"2\" id=\"img_primary\">';
				$end    = '<\/td>';
		
				$imdbdata  = $scrape->fetchAllBetween($start,$end,$data,true);
		
					$regexp = '<img src=(\"??)([^\" >]*?)\\1[^>]*>';
					if(preg_match_all('/'.$regexp.'/siU', $imdbdata[0], $imgmatch)) {
						$showObject['poster'] = $imgmatch[2][0];
					}
				
				//SEARCH FOR KEYWORDS
				//$data   = $scrape->removeNewlines($scrape->result);
				$start  = '<h4 class=\"inline\">Plot Keywords:<\/h4>';
				$end    = '<\/div>';
		
				$imdbdata  = $scrape->fetchAllBetween($start,$end,$data,true);
				
				$keydata   = str_replace('<h4 class="inline">Plot Keywords:</h4>',"",$imdbdata[0]);
				$keylist   = array();
				
					$regexp = "<aonclick=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>"; 
					
					if(preg_match_all('/'.$regexp.'/siU', $keydata, $keymatch)) {
							$keylist = $keymatch[3];
							
					}
					
				$showObject['keywords'] = $this->ArrayToString($keylist);
				if(stripos($showObject['keywords'],", See",0) > 0)
					$showObject['keywords'] = mysql_escape_string(str_replace(", See more","",$showObject->keywords));
				else
					$showObject['keywords'] = mysql_escape_string($showObject['keywords']);
					
				//SEARCH FOR YEAR
				//$data   = $scrape->removeNewlines($scrape->result);
				if($showObject['year'] == ""){
					$start  = '<td id=\"overview-top\">';
					$end    = '<\/span><\/h1>';
			
					$imdbdata   = $scrape->fetchAllBetween($start,$end,$data,true);
					$splitTitle = split('<span class="nobr">',$imdbdata[0]);
					$showObject['year']  =  preg_replace("/[^0-9]/", '', strip_tags(trim($splitTitle[1])));
					
				}
				
				//SEARCH FOR RELATED TITLES  (cant be done, requires ajax for js...oh wait im a fuckin GGGGGGGGG);
				$imdbID = split('/',$link);
				$fields_string;
				$fields = array(
							'count'=>urlencode(5),
							'start'=>urlencode(0),
							'specs'=>urlencode('p13nsims:' . $imdbID[4]),
							'caller_name'=>urlencode('p13nsims-title'),
						);
							
		if($extra){
		$harvest->progress("..get recs..");				
					//url-ify the data for the POST
					foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
					rtrim($fields_string,'&');
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, 'http://www.imdb.com/widget/recommendations/_ajax/get_more_recs');
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch,CURLOPT_POST,count($fields));
					curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
					$body = curl_exec($ch);
					$response;
					if(curl_exec($ch) === false)
						print '<p>Curl error: ' . curl_error($ch);
					else
						$response = json_decode($body);
			
					$recset = array();
					$reccount = 0;
					foreach($response->{'recommendations'} as $rec){
					
						$regexp = "<div\s[^>]*title=(.*)>(.*)<\/div>"; 
						if(preg_match_all('/'.$regexp.'/siU', $rec->{'content'}, $matchtitle)) {
					
							array_push($recset,trim(str_replace('"',"",$matchtitle[1][0])));
							$reccount++;
						}
						
						if($reccount > 4)
						break;
					}
		$harvest->progress("..get credits..");			
					$showObject['recset'] = $recset;
					//SEARCH FOR NETWORK
					$scrape->fetch($link . 'companycredits');
					$data   = $scrape->removeNewlines($scrape->result);
					$start  = '<h2>Distributors</h2><ul>(.*)';
					$end    = '<\/ul>';
					
					$imdbdata   = $scrape->fetchAllBetween($start,$end,$data,true);
				
					$regexp = "<li><a\s[^>]*href=(\'??)([^\' >]*?)\\1[^>]*>(.*)<\/a>(.*)<\/li>"; 
					
						if(preg_match_all('/'.$regexp.'/siU', $imdbdata[0], $match)) {
						
							if($match[3][0]){
									print_r($match[3][0]);
								if(stripos($match[3][0],"(") ===false){
									$showObject['network'] = mysql_escape_string(trim($match[3][0]));
								}else{
									$abbr    = split('\(',$match[3][0]);
									$showObject['network'] = mysql_escape_string(trim(str_replace(")","",$abbr[1])));
								}
							}
						}
						
		}else{
			$harvest->progress("..skip extras..");	
		}
			$harvest->progress(".end imdb.");		
						
						
						
					//print_r($showObject);
					return $showObject;
		}
		
		public function scrapeIMDBepisode($link,$epData){
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
				
				if($epimddID != ""){
					$epObject  = $this->scrapeIMDBmain("http://www.imdb.com/title/".$epimddID."/",$epObject,false);
					return $epObject;
				}else{
					return "error";
				}
				
		}
		
		
		public function ArrayToString($arr){
	
			$str = $arr[0];
			
			for($i=1; $i<count($arr);$i++){
				
				$str .= ", " . $arr[$i];
				
			}
			
			return $str;
		}

	
}
?>