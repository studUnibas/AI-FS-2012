<?php

$uniformCostSearch = new UniformCostSearch();

class UniformCostSearch  {

	private $knots;
	private $queue = array();
	
	private $shortestPath = array();
	
	public  $executionTime;
	
	function __construct()
	{
		ini_set("memory_limit ", "2048M");
		ob_start();
		$this->handler();
	}
	
	function __destruct()
	{
		$output = ob_get_clean();
		if (!empty($_SERVER['HTTP_HOST']))
			echo nl2br($output);
		else
			echo $output;
	}
	
	public  function handler() {
		$do = @$_SERVER["argv"][1];
		switch(@$do) {
		    case "createRandomList":
		    	$knots	  = isset($_SERVER["argv"][2]) ? $_SERVER["argv"][2] : 100;
		    	$filename = isset($_SERVER["argv"][3]) ? $_SERVER["argv"][3] : "randomList.tsp";
		    	if (!is_numeric($knots))
		    	{
		    		echo "\nNeed Number as Second Parameter!!\n\n";
		    		$this->help();
		    		return;
		    	}
		    	$this->createRandomList($knots, true, $filename);
		        break;
		    case "getShortestPathOfFile":
		    	$filename = isset($_SERVER["argv"][2]) ? $_SERVER["argv"][2] : "randomList.tsp";
		    	$this->loadFile($filename);
		        $this->getShortestPath();
		        $this->printShortestPath();
		        break;
		    case "getShortestPathOfRandomList":
		    	$knots	  = isset($_SERVER["argv"][2]) ? $_SERVER["argv"][2] : 100;
		        $this->createRandomList($knots);
		        $this->getShortestPath();
		        $this->printShortestPath();
		        break;
		    default:
		    	$this->help ();

		}
	}
	/**
	 * 
	 */
	public function help()
	{
		echo "Usage: php UniformCostSearch.php options [args...] \n";
		echo " options: \n";
		echo "  createRandomList [numberOfKnots] [filename]\n";
		echo "  getShortestPathOfFile [filename]\n";
		echo "  getShortestPathOfRandomList [numberOfKnots]\n";
	}
 
	
	public  function getShortestPath($startKnot = 0) {
		
		if(empty($this->queue)) {
			echo "please load a list\n";
		}
		
		$this->shortestPath[] = $startKnot;
		
		$start = microtime(true);
		
		$this->findShortestPath($startKnot);
		
		$this->executionTime = microtime(true) - $start;
		
		$this->shortestPath[] = $startKnot; // end knot is same as start knot
		
		return $this->shortestPath;
	}
	
	private function findShortestPath($startKnot)
	{
		do 
		{
			$shortestDistance = 0;
			$nextKnot = -1;
		
			foreach($this->queue[$startKnot] as $childKnot => $distance) {
				if($nextKnot == $startKnot || in_array($childKnot, $this->shortestPath))
					continue;
					
				if($shortestDistance == 0
				 OR $distance < $shortestDistance)
				{
					$shortestDistance = $distance;
					$nextKnot = $childKnot;
				}
			}
		
			$this->shortestPath[] = $nextKnot;
			$this->fullDistance  += $shortestDistance;
			$startKnot = $nextKnot;
		}
		while (!((count($this->shortestPath) == $this->knots || $nextKnot == -1)));

		return true;
	}
	
	private function findShortestPathRecursive($startKnot) {
		
		$shortestDistance = 0;
		$nextKnot = -1;
		
		foreach($this->queue[$startKnot] as $childKnot => $distance) {
			if($nextKnot == $startKnot || in_array($childKnot, $this->shortestPath))
				continue;
			
			if($shortestDistance == 0) {
				$shortestDistance = $distance;
				$nextKnot = $childKnot;
			} elseif($distance < $shortestDistance) {
				$shortestDistance = $distance;
				$nextKnot = $childKnot;
			}
		}
		
		$this->shortestPath[] = $nextKnot;
		
		if(count($this->shortestPath) == $this->knots || $nextKnot == -1)
			return true;
		
		return $this->findShortestPath($nextKnot);
	}
	
	public  function printShortestPath() {
		
		echo "==< shortest path >==============================\n";
		
		$shortestPath = implode(" => ", $this->shortestPath);
		
		echo $shortestPath . "\n";
		echo "Length of path: ". round($this->fullDistance, 2)."\n";
		
		echo "==< shortest path END >==========================\n";
		$this->printExecutionTime();
	}
	
	public  function printExecutionTime() {
		echo "execution time for find shortest path: " . (int)$this->executionTime . " seconds\n";
	}
	
	public  function createRandomList($numberOfKnots = 1000, $saveAsFile = false, $filename = "randomList.tsp") {

		$this->knots = $numberOfKnots;
		
		$min = 1;
		$max = sqrt($numberOfKnots);
		
		for($parentKnot = 0; $parentKnot<$numberOfKnots; $parentKnot++)
		{
			for($childKnot = 0; $childKnot<$numberOfKnots; $childKnot++)
			{
				if($parentKnot == $childKnot) {
					$this->queue[$parentKnot][$childKnot] = 0;
				}
				elseif(empty($this->queue[$parentKnot][$childKnot])) {
					//$value = mt_rand($min, $max);
					$value = ($min+lcg_value()*(abs($max-$min))); // random float numbers
					$this->queue[$parentKnot][$childKnot] = $value;
					$this->queue[$childKnot][$parentKnot] = $value;
				}
			}
		}
		
		if($saveAsFile)
			$this->writeQueueFile($filename);
		
	}
	
	public  function loadFile($filename) {
		if(empty($filename))
			return false;
		
		// number of cities
		
		$file = fopen($filename, "r");
		
		$row = -1;
		$parentKnot = 0;
		
		while (!feof($file)) {
		   $value = trim(fgets($file));
		   
		   if( (empty($value) && $value != "0") || !is_numeric($value)) {
		   	continue;
		   }
		   
		   if($row === -1) {
		   	$intValue = (int) $value;
		   	if($intValue != $value) {
		   		echo "parser error\n";
		   		return false;
		   	}
		   	$this->knots = $value;
		   	$row++;
		   	continue;
		   }
		   
		   $childKnot  = $row%$this->knots;
		   if($childKnot == 0) {
		   	$parentKnot = $row / $this->knots;
		   }
		   $this->queue[$parentKnot][$childKnot] = $value; 
		   
		   $row++;
		}
		fclose($file);

		if($row>0 && $row%$this->knots != 0) {
			echo "parser error\n";
			return false;
		}
		
	}
	
	public  function writeQueueFile($filename = "randomList.tsp") {
		$file = fopen($filename,"w");
		
		fwrite($file, $this->knots . "\n");
		
		foreach($this->queue as $distances )	{
			foreach($distances as $distance) {
				fwrite($file, $distance . "\n");
			}
		}
		
		fclose($file);
	}
	
	public  function printQueue() {
		
		echo "==< Queue >==============================\n";
		
		foreach($this->queue as $knot => $distances )	{
			foreach($distances as $knotNumber => $distance) {
				echo $knot . " => " . $knotNumber . " := " . $distance . "\n";
			}
		}
		
		echo "==< Queue END >==========================\n";
	}
}


?>