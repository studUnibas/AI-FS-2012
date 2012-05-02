<?php

/**
 * AI 2012 Unibas Exercises [kingmo,ala]
 *
 Usage: php CostSearch.php options [args...]
 options: */
class CostSearch
{
	public  $debug = 0;
	
	//number of knots
	public  $knots;

	//array of distances/costs between 2 knots
	public  $queue = array();

	public  $shortestPath = array();

	public  $fullDistance = 0;

	public  $computeAllPathsLimit = 10;
	
	public  $executionTime;

	public  $options = array("CostSearchUtilities", "UniformCostSearch", "AStarCostSearch", "CPLL");
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
				
			case "cpll":
				new CPLL();
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
	public  function findShortestPath($startKnot)
	{
		if ($this->debug>0)
			echo "-- compute shortest path starting from $startKnot\n";
		$this->shortestPath 		 = array($startKnot);
		$this->shortestPathDistances = array();
		$this->fullDistance 		 = 0;
		$itterations 				 = -1;
		$this->homeKnot = $homeKnot  = $startKnot;
		$this->visitedKnots 		 = 0;
		
		do
		{
			$shortestDistance = 0;
			$lowestCost		  = 0;
			$nextKnot 		  = -1;
			$lastKnot		  = $startKnot;

			foreach($this->queue[$startKnot] as $childKnot => $distance)
			{
				if($nextKnot == $startKnot || in_array($childKnot, $this->shortestPath))
					continue;
					
				$cost = $this->computeCost($startKnot, $lastKnot, $childKnot, $distance);

				if(($shortestDistance == 0 OR $cost < $lowestCost)
						&& $cost>0)
				{
					$shortestDistance 	= $distance;
					$lowestCost			= $cost;
					$nextKnot 			= $childKnot;
				}
			}

			$this->visitedKnots++;
			$this->shortestPath[] 			= $nextKnot;
			$this->shortestPathDistances[]	= $shortestDistance;
			$this->fullDistance  		   += $shortestDistance;
			$this->shortestPathCost[]		= $lowestCost;
			$this->fullCost		  		   += $lowestCost;
			$this->treeWeight	  		   -= $shortestDistance;
				
			$lastKnot						= $startKnot;
			$startKnot 						= $nextKnot;
			//unset($this->queue[$nextKnot]);
		}
		while (!((count($this->shortestPath) == $this->knots || $nextKnot == -1)));

		if ($nextKnot>-1)
		{
			//go fucking home
			$this->shortestPathDistances[] = $this->queue[$nextKnot][$homeKnot];
			$this->fullDistance 		  += $this->queue[$nextKnot][$homeKnot];
			$this->shortestPath[]		   = $homeKnot; // end knot is same as start knot
		}
		
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
	
	public function printShortestPath()
	{
		if (true or  $this->printStyle=="CSV")
		{
			echo "{$this->knots}	{$this->fullDistance}	{$this->executionTime}\n";
		}
		else
		{
			echo "==< shortest path >==============================\n";
			
			$shortestPath = implode ( " => ", $this->shortestPath );
			
			echo $shortestPath . "\n";
			echo @implode ( " + ", $this->shortestPathDistances ) . " = " . round ( $this->fullDistance, 2 ) . "\n";
			echo "Length of path: " . round ( $this->fullDistance, 2 ) . "\n";
			
			echo "==< shortest path END >==========================\n";
			$this->printExecutionTime ();
		}
	}
	
	public function printExecutionTime() {
		echo "execution time for find shortest path: " . ( int ) $this->executionTime . " seconds\n";
	}
	
	public function loadFile($filename) {
		if (empty ( $filename )) {
			echo "Error: tried to open empty file!\n";
			exit;
			return false;
		}

		$file = fopen ( $filename, "r" );
		
		$row = - 1;
		$parentKnot = 0;
		
		while ( ! feof ( $file ) ) {
			$value = trim ( fgets ( $file ) );
			
			if ((empty ( $value ) && $value != "0") || ! is_numeric ( $value )) {
				continue;
			}
			
			if ($row === - 1)
			{
				$intValue = ( int ) $value;
				if ($intValue != $value) {
					echo "parser error\n";
					exit;
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
			exit;
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
	
	//makes no sense
	public function printAllPaths() {
		echo "\n--- Print all Paths ---\n";
		
		$queue = clone $this->queue;
		
		if ($this->knots > 8) {
			echo "Sorry, dont do more then 8..\n";
			return;
		}
		
		$iterator = 0;
		//$count = $knots;
		
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
			case "getShortestPathOfExampleFiles":
				$knots	  = isset($_SERVER["argv"][3]) ? $_SERVER["argv"][3] : 100;
			
				for ($i=4; $i<min(100, $knots); $i++)
				{
				$filename = sprintf("tsp/size%02d", $i).".tsp";
				$this->loadFile ( $filename );
				$this->getShortestPath();
				$this->printShortestPath();
				}
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
	
	public  function computeCost($startKnot, $thisKnot, $childKnot, $distance)
	{
		return $distance;
	}
}

/**  
 * astar getShortestPathOfFile [filename]		
 * astar getShortestPathOfRandomList [numberOfKnots] 
 * astar getShortestPathOfExampleFiles //run files from website (sizeXX.tsp) 
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
			case "getShortestPathOfExampleFiles":
				$knots	  = isset($_SERVER["argv"][3]) ? $_SERVER["argv"][3] : 100;
				
				for ($i=4; $i<min(100, $knots); $i++)
				{
					$filename = sprintf("tsp/size%02d", $i).".tsp";
					$this->loadFile ( $filename );
					$this->getShortestPath();
					$this->printShortestPath();
				}
				break;
			default:
				$this->help();
		}
	}
	
	
/********** Views *****/	
	
	public function getShortestPath($startKnot = 0)
	{
		$start = microtime(true);
		
		$this->buildSpanningTree($this->queue, $startKnot);
		$this->findShortestPath($startKnot);
		
		$this->executionTime = microtime(true) - $start;
		
	}
	
	public function getShortestPathTSP($startKnot = 0)
	{
		
	}
	
/********** Controls ****/	
	
	public  function computeCost($startKnot, $thisKnot, $childKnot, $distanceFromThisToChild)
	{
		if ($distanceFromThisToChild==0) return;
		
		if ($this->debug>0)
		{
			echo "start		child\n";
			echo $startKnot . "\t\t" . $childKnot ."\n";
		}
		
		//target state
		if ($startKnot==$childKnot)
			return 0;
		
		//not target state, but all visited
		elseif ($this->knots==$this->visitedKnots)
			return $this->fullDistance;
		
		//array_push($this->shortestPath, $childKnot);
		//$this->buildSpanningTree($this->queue, $childKnot);
		//array_pop($this->shortestPath);
		
		//woa, a priority queue would have been really cool
		$min = 0;
		foreach ($this->queue[$this->homeKnot] as $child => $distance)
		{
			if ($child==$childKnot) continue;
			if (in_array($child, $this->shortestPath)) continue;
			
			if (($min==0 or $min>$distance) && $distance>0)
			{
				$min = $distance;
				$minKnot = $child;
			}
		}
		
		$return = $min;
		$min = 0;
		
		foreach ($this->queue[$childKnot] as $child => $distance)
		{ 
			if ($child==$childKnot) continue;
			if (in_array($child, $this->shortestPath)) continue;
			
			if (($min==0 or $min>$distance) && !in_array($child, $this->shortestPath) && $distance>0)
			{
				$min = $distance;
				$minKnot = $child;
			}
			
		}
		
		$return += $min + $distanceFromThisToChild + $this->treeWeight;
		
		IF ($this->debug>1)
		{
			echo " h = $distanceFromThisToChild + $return + $min + {$this->treeWeight}";// + $distanceFromThisToChild"; 
			// + $this->treeWeight;// + $distanceFromThisToChild; // + $distanceFromThisToChild;
			echo " = $return \n";
		}
		
		return $return;
		 
		return min($this->queue[$startKnot]) + min($this->queue[$thisKnot]) + $this->treeWeight;
		
		return $this->fullDistance + $distance + $this->treeWeight;
	}
	
	private $lastCount = 0;
	
	/**
	 * Build MST with Prim Algorithm
	 * It would be a lot smarter to compute it once and then delete from it to compute subsets	 */
	private function buildSpanningTree($queue, $startKnot = 0)
	{
		$lastCount = $this->knots- count($this->shortestPath);
		if ($this->lastCount==$lastCount)
			return;
		$this->lastCount = $lastCount;
		
		if ($this->debug>0)
			echo "---building MST with " . ($this->knots- count($this->shortestPath)) . " knots \n";
		
		$idx = 0;
		$this->spanningTree = array_fill ( 0, $this->knots, array_fill ( 0, $this->knots, 0 ) );
		$this->treeWeight   = 0;
		$closedList [0] = $startKnot;
		
		for($i = 0; $i<$this->knots - 1; $i ++)
		{
			if (in_array($i, $this->shortestPath))
				continue;
			$shortestEdge = max ($this->queue);
			
			for($col = 0; $col <= $idx; $col ++)
			{
				$startKnot = $closedList [$col];
				
				for($row = 0; $row < $this->knots; $row ++)
				{
					if (($queue [$row] [$startKnot] < $shortestEdge) && ($queue [$row] [$startKnot] > 0))
					{
						$shortestEdge = $queue [$row] [$startKnot];
						$prevNode	  = $startKnot;
						$nextNode 	  = $row;
					}
				}
			}
			
			$closedList [++ $idx] = $startKnot = $nextNode;
			
			$this->spanningTree [$prevNode] [$nextNode] = $this->spanningTree [$nextNode] [$prevNode] = $queue [$prevNode] [$nextNode];
			
			$this->treeWeight += $queue [$prevNode] [$nextNode];
			
			//just to go sure..., but we might test difference to sparse matrix and leave this away
			for($a = 0; $a <= $idx; $a ++)
			{
				for($b = 0; $b <= $idx; $b ++)
				{
					$queue[$closedList [$a]] [$closedList [$b]] = $queue[$closedList [$b]] [$closedList [$a]] = 0;
				}
			}
		}
	}
}

/**
 * cpll 30
 * cpll 50
 * cpll 70
 * */
class CPLL extends CostSearch
{
	public  $options	  = array();
	public  $handlerLevel = 2;
	
	public  $clauses = array(); //klauseln
	public  $values  = array();
	
	function CPLL()
	{
		$this->handler();
	}
	
	public  function handler()
	{
		switch(@$_SERVER["argv"][$this->handlerLevel])
		{
			default:
				$filename = isset($_SERVER["argv"][3]) ? $_SERVER["argv"][3] : "30";
				$this->doCPLL($filename);
				$this->printSolution();
				break;
		}
	}

	public  function doCPLL($filename)
	{
		//ini_set('xdebug.max_nesting_level', 100000);
		$handle = opendir('cpll');
			
		$c = 0;
		
		//read all files and run cpll
		while ($file = readdir($handle))
		{
			if (!strstr($file, "problem$filename"))
				continue;
			
			$file = "idiotTest";
				
			$this->loadFile("cpll/".$file);
			
			$start = microtime(true);
			
			if ($this->solveCPLL($this->clauses))
				$this->solution = array($file, microtime(true) - $start);
			
			$c++;
			
			if ($c>0) return;
		}
	}
	
	public  function solveCPLL($clauses)
	{
		$sat = true;
		
		//do
		//{
			if ($this->unsatisfiable($clauses))
				return false;
				
			if (count($clauses)==0)
				return true;
			
			if ($n = $this->unitPropagation($clauses))
			{
				$clauses = $this->simplify($clauses, $n);
				return $this->solveCPLL($clauses);
				//continue;
			}
			else
			{
				foreach ($clauses as $n => $clause)
				{
					foreach (array(true, false) as $d)
					{
						$clausesSplit = $this->simplify($clauses, null, $d);
						$clausesSplit = $this->solveCPLL($clausesSplit);
						if (!$this->unsatisfiable($clausesSplit))
							return $clausesSplit;
					}
				}
				return false;
			}
		//}
		//	while ($sat);
		
		return false;
		
	}
	
	/**
	 * @desc	return index of cause with only one literal */
	public  function unitPropagation($clauses)
	{
		foreach ($clauses as $n => $clause)
		{
			if (count($clause)==1)
				return $n;
		}
	}
	
	//dont think this is right yet
	public  function simplify($clauses, $n = null, $d = null)
	{		
		if (empty($n))
		{
			foreach ($clauses as $itterator)
			{
				break;
			}
		}
		else
			$itterator = $clauses[$n];

		foreach ($itterator as $literal)
		{

			if (empty($d))
				$d = ($literal>0) ? true : false;
			
			foreach ($clauses as $n => $clause)
			{
				foreach ($clause as $m => $thisLiteral)
				{
					// a clause with exactly this litteral is true thus killed
					if ($thisLiteral==$literal)
					{
						unset($clauses[$m]);
						break;
					}
					
					//all opposit literals are purged from their clauses
					if ($thisLiteral+$literal==0)
					{
						unset($clauses[$n][$m]);
						if (count($clauses[$n])==0)
							unset($clauses[$n]);
					}
				}
			}
		}
		return $clauses;
	}
	
	public  function unsatisfiable($clauses)
	{
		foreach ($clauses as $clause)
			if (count($clause)==0)
				return true;
	}
	
	public  function printSolution()
	{
		var_dump($this->solution);
	}
	
	public  function loadFile($filename)
	{
		if (empty ( $filename )) {
			echo "Error: tried to open empty file!\n";
			exit;
			return false;
		}

		$this->clauses = array();
		
		$file = fopen ( $filename, "r" );
		
		$row = 0;
		
		while ( ! feof ( $file ) )
		{
			$value = trim ( fgets ( $file ) );
				
			$pts = explode(" ", $value);
			
			foreach ($pts as $value)
			{
				if ((empty ( $value ) && $value != "0") || ! is_numeric ( $value )) {
					continue;
				}
					
				$intValue = ( int ) $value;
				if ($intValue != $value) {
					echo "parser error\n";
					exit;
					return false;
				}
				$this->clauses[$row][] = $value;
			}		
				
			$row ++;
		}
		fclose ( $file );
	}

}

$CostSearch = new CostSearch();

?>