<?php

/**
 * AI 2012 Unibas Exercises [kingmo,ala]
 *
 Usage: php UniformCostSearch.php options [args...]
 options: */
class CostSearch
{
	//number of knots
	public  $knots;

	//array of distances/costs between 2 knots
	public  $queue = array();

	public  $shortestPath = array();

	public  $fullDistance = 0;

	public  $computeAllPathsLimit = 10;
	
	public  $executionTime;

	public  $options = array("CostSearchUtilities", "UniformCostSearch", "AStarCostSearch");
	public  $handlerLevel = 1;

	function __construct()
	{
		ini_set("memory_limit ", "2048M");
		//ob_start();
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
		$do = @$_SERVER["argv"][$this->handlerLevel];
		switch(@$do) {
			case "createRandomList";
				new CostSearchUtilities();
			break;
			
			case "best";
				new UniformCostSearch();
			break;
			 
			case "astar":
				new AStarCostSearch();
				break;
				 
			default:
				$this->help();
		}
	}

	public  function help()
	{
		$rc = new ReflectionClass(get_class($this));
		echo str_replace("*", " ", str_replace("/", " ", $rc->getDocComment()))	."\n";

		foreach ($this->options as $option)
		{
			$rc = new ReflectionClass($option);
			echo str_replace("*", " ", str_replace("/", " ", $rc->getDocComment()))	."\n";
		}
		return;
	}

	/********** Views ****/

	/******** Controls ***/

	/**
	 * Given a cost for a set of options from a situation we take the "best" (smallest) next step
	 * cost is computed by computeCost	 
	 * 
	 * @Note: please note that the startKnot is given! */
	private function findShortestPath($startKnot)
	{
		$itterations = -1;
		$homeKnot = $startKnot;
		do
		{
			
			$shortestDistance = 0;
			$nextKnot = -1;

			foreach($this->queue[$startKnot] as $childKnot => $distance)
			{
				if($nextKnot == $startKnot || in_array($childKnot, $this->shortestPath))
					continue;
					
				$cost = $this->computeCost($distance);

				if(($shortestDistance == 0 OR $cost < $shortestDistance)
						&& $cost>0)
				{
					$shortestDistance 	= $distance;
					$lowestCost			= $cost;
					$nextKnot 			= $childKnot;
				}
			}

			$this->shortestPath[] 			= $nextKnot;
			$this->shortestPathDistances[]	= $shortestDistance;
			$this->fullDistance  		   += $shortestDistance;
			$this->shortestPathCost[]		= $lowestCost;
			$this->fullCost		  		   += $lowestCost;
				
			$startKnot 						= $nextKnot;
		}
		while (!((count($this->shortestPath) == $this->knots || $nextKnot == -1)));

		//go fucking home
		$this->shortestPathDistances[] = $this->queue[$this->shortestPath[count($this->shortestPath)-1]][$homeKnot];
		$this->fullDistance 		  += $this->queue[$this->shortestPath[count($this->shortestPath)-1]][$homeKnot];
		$this->shortestPath[]		   = $homeKnot; // end knot is same as start knot

		return true;
	}

	#http://www.php.net/manual/en/features.commandline.io-streams.php
	//@someday, would have been fun, but too much work
	private function readStdIn($valid_inputs, $default = '')
	{
		$input = null;
		while(!isset( $input ) || (is_array ( $valid_inputs ) 
		   && ! in_array ( $input, $valid_inputs )) || ($valid_inputs == 'is_file' && ! is_file ( $input )) )
		{
			$input = strtolower ( trim ( fgets ( STDIN ) ) );
			if (empty ( $input ) && ! empty ( $default ))
				$input = $default;
		}
		return $input;
	}
	
	public function printShortestPath() {
		
		echo "==< shortest path >==============================\n";
		
		$shortestPath = implode ( " => ", $this->shortestPath );
		
		echo $shortestPath . "\n";
		echo @implode ( " + ", $this->shortestPathDistances ) . " = " . round ( $this->fullDistance, 2 ) . "\n";
		echo "Length of path: " . round ( $this->fullDistance, 2 ) . "\n";
		
		echo "==< shortest path END >==========================\n";
		$this->printExecutionTime ();
	}
	
	public function printExecutionTime() {
		echo "execution time for find shortest path: " . ( int ) $this->executionTime . " seconds\n";
	}
	
	public function loadFile($filename) {
		if (empty ( $filename )) {
			echo "Error: tried to open empty file!\n";
			return false;
		}
		// number of cities
		
		$file = fopen ( $filename, "r" );
		
		$row = - 1;
		$parentKnot = 0;
		
		while ( ! feof ( $file ) ) {
			$value = trim ( fgets ( $file ) );
			
			if ((empty ( $value ) && $value != "0") || ! is_numeric ( $value )) {
				continue;
			}
			
			if ($row === - 1) {
				$intValue = ( int ) $value;
				if ($intValue != $value) {
					echo "parser error\n";
					return false;
				}
				$this->knots = $value;
				$row ++;
				continue;
			}
			
			$childKnot = $row % $this->knots;
			if ($childKnot == 0) {
				$parentKnot = $row / $this->knots;
			}
			$this->queue [$parentKnot] [$childKnot] = $value;
			
			$row ++;
		}
		fclose ( $file );
		
		if ($row > 0 && $row % $this->knots != 0) {
			echo "parser error\n";
			return false;
		}
	
	}
	
	public function writeQueueFile($filename = "randomList.tsp") {
		$file = fopen ( $filename, "w" );
		
		fwrite ( $file, $this->knots . "\n" );
		
		foreach ( $this->queue as $distances ) {
			foreach ( $distances as $distance ) {
				fwrite ( $file, $distance . "\n" );
			}
		}
		
		fclose ( $file );
	}
	
	public function printQueue() {
		
		echo "==< Queue >==============================\n";
		
		foreach ( $this->queue as $knot => $distances ) {
			foreach ( $distances as $knotNumber => $distance ) {
				echo $knot . " => " . $knotNumber . " := " . $distance . "\n";
			}
		}
		
		echo "==< Queue END >==========================\n";
	}
	
	public function printAllPaths() {
		echo "\n--- Print all Paths ---\n";
		
		$queue = clone $this->queue;
		
		if ($this->knots > 8) {
			echo "Sorry, dont do more then 8..\n";
			return;
		}
		
		$iterator = 0;
		$count = $knots;
		
		foreach ( $queue as $i => $knots ) {
			$this->allPaths [$cnt] ['knots'] [] = $i;
			foreach ( $knots as $j => $innerKnots ) {
				$this->allPaths [$i] ['distance'];
			}
		}
	}

}

/** 
 * createRandomList [numberOfKnots] [filename]  
 * */
class CostSearchUtilities extends CostSearch
{
	public  function handler()
	{
		echo "\n--- creating random list---\n";
		$knots	  = isset($_SERVER["argv"][2]) ? $_SERVER["argv"][2] : 100;
		$filename = isset($_SERVER["argv"][3]) ? $_SERVER["argv"][3] : "randomList.tsp";
		if (!is_numeric($knots))
		{
			echo "\nNeed Number as Second Parameter!!\n\n";
			$this->help();
			return;
		}
		$this->createRandomList($knots, true, $filename);
	}

/****** Views ****/

	
/******** Controls ****/
	public  function createRandomList($numberOfKnots = 1000, $saveAsFile = false, $filename = "randomList.tsp")
	{
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
	
	
	public  function help()
	{
		$rc = new ReflectionClass('CostSearchUtilities');
		echo $rc->getDocComment()."\n";
	}
}

/**
 * best getShortestPathOfFile [filename]           
 * best getShortestPathOfRandomList [numberOfKnots]
 * best getShortestPathOfFileAllStartpoints [filename] 
 **/
class UniformCostSearch extends CostSearch
{
	public  $options	  = array();
	public  $handlerLevel = 2;
	
	
	public  function handler()
	{
		switch(@$_SERVER["argv"][$this->handlerLevel])
		{
			case "getShortestPathOfFileAllStartpoints":
				$filename = isset($_SERVER["argv"][3]) ? $_SERVER["argv"][3] : "randomList.tsp";
				$this->loadFile($filename);
				for ($i = 0; $i<$this->knots; $i++)
				{
					$this->getShortestPath($i);
					$this->printShortestPath();
				}
				break;
			case "getShortestPathOfFile":
				$filename = isset($_SERVER["argv"][3]) ? $_SERVER["argv"][3] : "randomList.tsp";
				$this->loadFile($filename);
				$this->getShortestPath();
				$this->printShortestPath();
				break;
			case "getShortestPathOfRandomList":
				$knots	  = isset($_SERVER["argv"][3]) ? $_SERVER["argv"][3] : 100;
				$this->createRandomList($knots);
				$this->getShortestPath();
				$this->printShortestPath();
				break;
			default:
				$this->help();
		}
	}
	
/********** Views ****/	
	public  function getShortestPath($startKnot = 0)
	{
		if(empty($this->queue)) {
			echo "please load a list\n";
		}
	
		//initialize va
		$this->shortestPath = array($startKnot);
		$this->shortestPathDistances = array();
		$this->fullDistance = 0;
		$start = microtime(true);
	
		$this->findShortestPathBestFirst($startKnot);
	
		$this->executionTime = microtime(true) - $start;
	
		return $this->shortestPath;
	}
	
/********* Controls ****/	
	

	private function findShortestPathBestFirst($startKnot)
	{
		$this->findShortestPath($startKnot);
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
	
	private function computeCost($distance)
	{
		return $distance;
	}
}

/**  
 * astar getShortestPathOfFile [filename]		
 * astar getShortestPathOfRandomList [numberOfKnots]  
 * */
class AStarCostSearch extends CostSearch
{
	public  $options	  = array();
	public  $handlerLevel = 2;
	
	public  $treeWeight = 0;
	
	public  function handler()
	{
		switch(@$_SERVER["argv"][$this->handlerLevel])
		{
			case "getShortestPathOfFile":
				$filename = isset($_SERVER["argv"][3]) ? $_SERVER["argv"][3] : "randomList.tsp";
				$this->loadFile ( $filename );
				$this->getShortestPath();
				$this->printShortestPath();
				break;
			case "getShortestPathOfRandomList":
				$knots	  = isset($_SERVER["argv"][3]) ? $_SERVER["argv"][3] : 100;
				$this->createRandomList($knots);
				$this->getShortestPath();
				$this->printShortestPath();
				break;
			default:
				$this->help();
		}
	}
	
	
/********** Views *****/	
	
	public function getShortestPath($startKnot = 0)
	{
		
		$this->buildSpanningTree ( $startKnot );
		var_dump ( $this->queue );
		var_dump ( $this->spanningTree );
		
		$this->queue = $this->spanningTree;
		//$this->getShortestPath ();
		//$this->printShortestPath ();
	}
	
/********** Controls ****/	
	
	private function computeCost($distance)
	{
		
	}
	
	private function buildSpanningTree($startKnot = 0, $totalWeight = 0)
	{
		echo "-----building MST with {$this->knots} knots \n";
		
		$queue = $this->queue;
		$idx = 0;
		$this->spanningTree = array_fill ( 0, $this->knots, array_fill ( 0, $this->knots, 0 ) );
		$closedList [0] = $startKnot;
		
		for($i = 0; $i<$this->knots - 1; $i ++)
		{
			$shortestEdge = max ($this->queue);
			
			for($col = 0; $col <= $idx; $col ++)
			{
				$startKnot = $closedList [$col];
				
				for($row = 0; $row < $this->knots; $row ++)
				{
					if (($queue [$row] [$startKnot] < $shortestEdge) && ($queue [$row] [$startKnot] > 0))
					{
						$shortestEdge = $queue [$row] [$startKnot];
						$prevNode = $startKnot;
						$nextNode = $row;
					}
				}
			}
			
			$closedList [++ $idx] = $startKnot = $nextNode;
			
			$this->spanningTree [$prevNode] [$nextNode] = $this->spanningTree [$nextNode] [$prevNode] = $this->queue [$prevNode] [$nextNode];
			
			$totalWeight += $queue [$prevNode] [$nextNode];
			
			for($a = 0; $a <= $idx; $a ++)
			{
				for($b = 0; $b <= $idx; $b ++)
				{
					$queue [$closedList [$a]] [$closedList [$b]] = $queue [$closedList [$b]] [$closedList [$a]] = 0;
				}
			}
		}
	}
}

$CostSearch = new CostSearch();

?>