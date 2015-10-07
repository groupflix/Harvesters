<?php


include '../db/DBconfig.php'; 
include '../IO/FileType.php';
include 'com/HarvestMethods.php';
include '../utils/SimpleXMLExtended.php';

date_default_timezone_set('EST');
error_reporting(E_ERROR);
$dbconfig = new DBconfig();
$harvest  = new HarvestMethods();
			
require_once('/opt/myubi/Log4php/Logger.php');
Logger::configure('/opt/myubi/Log4php/resources/appender_articles.properties');
$logger = Logger::getRootLogger();

$logger->info("PHP Harvester : create_articles.php Starting ");
global $logger;

//SET DEBUG VARIABLES=======================================
$suffix = $dbconfig->getDBsuffix();
$tmp = false;
$t_suffix;

if($tmp){
	$harvest->setTableSuffix('_tmp');
	$t_suffix = "_tmp";
}else
	$t_suffix ="";
//===========================================================

$host = $dbconfig->getHOST();
$username = $dbconfig->getUSERNAME();
$password = $dbconfig->getPASSWORD();
$db = $dbconfig->getDATABASE();

$res = mysql_connect($host, $username, $password);
        if(!$res) {
                $logger->error("PHP Harvester : create_articles.php Message Mysql Query Error" . mysql_error());
        }
if (!$res) die("Could not connect to the server, mysql error: ".mysql_error($res));
$res = mysql_select_db($db);
        if(!$res) {
                $logger->error("PHP Harvester : create_articles.php Message Mysql Query Error" . mysql_error());
        }
if (!$res) die("Could not connect to the database, mysql error: ".mysql_error($res));




$typeList = array("episode","film","web");
$num_new_files = 0;
$notes ="";

$date   = mysql_query("select last_update from harvester_methods where type='Create Articles'");
$_date  = mysql_fetch_array($date);
print $_date['last_update'];

foreach($typeList as $selectype){
	
	$query = "SELECT distinct ref.title,ref.* from ".$selectype."_reference".$suffix." as ref, ".$selectype."_content".$suffix." as c WHERE c.fileloc = ref.fileloc and c.age > 2012-10-01";//.$_date['last_update'];
	$res = mysql_query($query);
    
		
		$notes  .= "[>>> There are ". mysql_num_rows($res) . " titles to update for type ".$selectype ." <<<]";
		print $notes;
		while($ref = mysql_fetch_assoc($res)){
			$num_new_files++;
			$item;
			
			switch($ref['type']){
				case(1):
				$query = 'SELECT * FROM '.$selectype.'_content'.$suffix.' WHERE fileloc="'.$ref['fileloc'].'" ORDER BY SEASON DESC, EPISODE DESC';
				$item  = mysql_query($query);
				case(3):
				$query = 'SELECT * FROM '.$selectype.'_content'.$suffix.' WHERE fileloc="'.$ref['fileloc'].'" ORDER BY SEASON DESC, EPISODE DESC';
				$item  = mysql_query($query);
				break;
				default:
				$query = 'SELECT * FROM '.$selectype.'_content'.$suffix.' WHERE fileloc="'.$ref['fileloc'].'"';
				$item  = mysql_query($query);
				break;
			}
				
			
				$Articles = &new SimpleXMLExtended("<articles></articles>");
			
				$title = "";
				 
					while($_info = mysql_fetch_assoc($item)){
						  $title = html_entity_decode($_info['title'],ENT_QUOTES, 'UTF-8');
						  
						  $article = $Articles->addChild('article');
						  $article->addAttribute('genre', $_info['genre']);
						  $article->addAttribute('type', $harvest->defineType($_info['type']));
						  $article->addAttribute('title',$_info['title']);
						  $article->addAttribute('age',$_info['age']);
						  
							  $article->addChild('myubi_id',$_info['myubi_id']);
							  $article->addChild('showthumb',$_info['showthumb']);
							  $article->addChild('poster',$_info['poster']);
							  $article->addChild('country',$_info['country']);


								   $show = $article->addChild('show');
								   $show->addAttribute("duration",NewTime($_info['duration']));
								   $show->addAttribute("realduration",$_info['duration']);
								   $show->addAttribute("pubdate",$_info['pubdate']);
								   $show->addAttribute("thumb",$_info['thumb']);
								   
									  $info = $show->addChild('info');
									  $info->addAttribute("rating",$_info['rating']);
									  $info->addAttribute("userRating",$_info['userrating']);
									  
									  if($_info['type'] == 1 || $_info['type'] == 3){
									  $info->addAttribute("episode",html_entity_decode(str_replace('acirc;',"'",$_info['episodetitle']),ENT_QUOTES, 'UTF-8'));		
									   $info->addAttribute("season",$_info['season']);
									   $info->addAttribute("epnum",$_info['episode']);							 									  									 									 }else
									   $info->addAttribute("cast",html_entity_decode($_info['cast'],ENT_QUOTES, 'UTF-8'));	
								
									  
										 $description= $info->addChild('description');
										 $description->addCData(html_entity_decode($_info['description'],ENT_QUOTES, 'UTF-8'));
										 
										 $synopsis   = $info->addChild('synopsis');
										 $synopsis->addCData(html_entity_decode($_info['synopsis'],ENT_QUOTES, 'UTF-8'));
									
										 $stream = $article->addChild('streams'); 
										
								//pull related streams to this episode
								$squery  = "SELECT s.* FROM ".$selectype."_streams".$suffix." as s,provider_priority as pp ";
								$squery .= " WHERE s.myubi_id='".$_info['myubi_id']."' and pp.pid = s.pid  ORDER BY pp.pc ASC ";
								$sres    = mysql_query($squery);
								
								while($video = mysql_fetch_assoc($sres)){
									$src = $stream->addChild('src');
									$src->addAttribute("pid",$video['pid']);
									$src->addAttribute("expire",$video['expire']);
									$src->addAttribute("quality",$video['quality']);
									$src->addAttribute("fee",$video['fee']);
									$src->addAttribute("language",$video['language']);
									$src->addAttribute("captions",$video['captions']);
									$src->addAttribute("provider",$video['provider']);
									
									$p = $video['pid'];
									if( $p == 3 || $p == 5 || $p == 6 || $p == 16)
										$src->addAttribute("mobile",1);
									else
										$src->addAttribute("mobile",0);
										
										$urlhi = $src->addChild('url_hi');
										$urlhi->addCData($video['url_hi']);
										  
										$urllo = $src->addChild('url_lo');
										$urllo->addCData($video['url_lo']);

								}
								
								
								if($_info['type'] == 2){
									
									$trailers = $article->addChild('trailers'); 
																			
									$tquery = 'SELECT * FROM '.$selectype.'_streams'.$suffix.' as st, '.$selectype.'_trailers'.$suffix.' as fc WHERE fc.fileloc="'.$_info['fileloc'].'" and st.myubi_id = fc.myubi_id';
									$tres   = mysql_query($tquery);
									
									if(mysql_num_rows($tres) > 0){
										
										while($video = mysql_fetch_assoc($tres)){
											$src = $trailers->addChild('src');
											$src->addAttribute("pid",$video['pid']);
											$src->addAttribute("quality",$video['quality']);
											$src->addAttribute("fee",$video['fee']);
											$src->addAttribute("available","true");
												
												$urlhi = $src->addChild('url_hi');
												$urlhi->addCData($video['url_hi']);
										}
									}else{
										$src = $trailers->addChild('src');
										$src->addAttribute("available","false");
									}
								}
									 
								$article->addChild('directlink',$_info['url']);
									
					}
						
					//PROPERLY FORMATS XML OUTPUT=========================
					$dom = dom_import_simplexml($Articles)->ownerDocument;
					$dom->formatOutput = true;
				  //  print_r($dom->saveXML());
					//====================================================
							
					$link_title = $harvest->concatTitle($title);
					print "...articles created for ". $link_title ."....";
					$logger->warn("PHP Harvester : create_articles.php articles created for"  . $link_title);	
					saveFile($dom->saveXML(),$link_title,$harvest->defineType($ref['type']));
		
				
				
		}
}

//}

$query = "update harvester_methods set last_update='".date('Y-m-d')."',notes='".$notes."',num_files='".$num_new_files."' where type='Create Articles'";
$res   = mysql_query($query);
        if(!$res) {
                $logger->error("PHP Harvester : create_articles.php Message Mysql Query Error" . mysql_error());
        }

function saveFile($xml,$_title,$_type){
	global $logger;
	global $harvest;
	
	$subfolder= substr(preg_replace('/[^a-z0-9]/i', "",$_title),0,1);
    $savePath = "/opt/myubi/xml/content/xml/".$_type."/".$subfolder;
	
	if(file_exists($savePath) && is_dir($savePath)){
		//print "folder exists, continue";
	}else{
		print "no such dir, making it";
		mkdir($savePath);
	}

	 //print "Saving $_title to system <p>";
	$logger->info("PHP Harvester : create_articles.php Saving" .  $_title . " to system");
	  $FT = &new FileType();
	  $FT->setFileType($_title);
	  $FT->setFormatType("xml");
	  $FT->setPath("/opt/myubi/xml/content/xml/".$_type."/".$subfolder);
	  $FT->setDATA($xml);
	  $FT->output_file();
}

function NewTime($data){
	
    $remainder = null;
	$hrs = $data/(3600);
	$remainder = $hrs - floor($hrs);
	$hrs = floor($hrs);
	
	$min = $remainder * 60;
	$remainder = $min - floor($min);
	$min = floor($min);
	
	$sec = $remainder * 60;
	$remainder = $sec - floor($sec);
	$sec = floor($sec);

	$hString=$hrs;
	//if($hrs < 10){ $hString = "0". $hrs; }else{  $hString=$hrs;}	
	if($min < 10){ $mString = "0". $min; }else{  $mString=$min;}
	if($sec < 10){ $sString = "0". $sec; }else{  $sString=$sec;}

	
	if ($data < 0 || $data==null){
		$value = "00:00";
	    return $value;
	}
	if ( $hrs > 0 ){			
		$value = $hString . ":" .$mString . ":" . $sString;
		return $value;
	}
	else{
		$value = $mString . ":" . $sString;
		return $value;
	}
	
}

function formatTitle($name){
	$_title = $name;

		$specChar = array(":",",","'","&","%","$","#","!","*","@","?","~","+",".","/","-","[","]","(",")");
			for($i=0;$i<count($specChar);$i++){
				
					if(stripos($_title,$specChar[$i])){
						
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
	return $_title;
}

?>

