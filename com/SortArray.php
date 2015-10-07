<?php

class SortArray{
	
	public function Multi($array, $index, $order, $natsort=FALSE, $case_sensitive=FALSE) {
			if(is_array($array) && count($array)>0) {
				foreach(array_keys($array) as $key) 
				$temp[$key]=$array[$key][$index];
				if(!$natsort) {
					if ($order=='asc')
						asort($temp);
					else    
						arsort($temp);
				}
				else 
				{
					if ($case_sensitive===true)
						natsort($temp);
					else
						natcasesort($temp);
				if($order!='asc') 
					$temp=array_reverse($temp,TRUE);
				}
				foreach(array_keys($temp) as $key) 
					if (is_numeric($key))
						$sorted[]=$array[$key];
					else    
						$sorted[$key]=$array[$key];
				return $sorted;
			}
		return $sorted;
	}
}

?>