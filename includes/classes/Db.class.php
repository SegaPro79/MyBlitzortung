<?php

/*
 * Database Class for MyBlitzortung
 */


/*
 * Select connection method
 * Todo: User-configurable
 */

if (PHP_VERSION_ID >= 70000 || class_exists('mysqli')) {
        require_once 'Db/Mysqli.class.php';
} else {
        require_once 'Db/Mysql.class.php';
}



/* Main Database class */
class BoDb extends BoDbMain
{
	static $bulk_query = array();
	static $bulk_names = array();

	
	/*
	 * Connect, send query, return result or id or rows according to
	 * query. Automatic error handling.
	 */
	public static function query($query = '', $die_on_errors = true, $bulk_update = true)
	{
		if (!$query)
		{
			return self::$dbh;
		}
			
		$qtype = strtolower(substr(trim($query), 0, 6));
		$cache_dir = BO_DIR.'/'.BO_CACHE_DIR;
		
		//query cache optimization
		if (DB_LOCK_SAME_QUERIES === true)
		{
			$lockfile = false;
			$ms = 0;
			if ($qtype == 'select')
			{
				if (is_writable($cache_dir))
				{
					$lockfile = $cache_dir.'/.lock_'.md5($query);
					
					clearstatcache();
					$wait = 20;
					while (file_exists($lockfile) && time() - @filemtime($lockfile) < $wait && $ms < ($wait*1000))
					{
						usleep(100000);
						$ms += 100;
						clearstatcache();
					}

					if ($ms == 0)
					{
						@touch($lockfile);
					}
					else
					{
						//file_put_contents($cache_dir.'/lock.log', "\n".date('Ymd His')." $ms ".md5($query), FILE_APPEND);
						//$query = "-- \n".$query;
					}
					
				}
			}
		}

		$start = microtime(true);
		
		$result = self::do_query($query);

		switch ($qtype)
		{
			case 'insert':
				$ret = self::insert_id();
				break;
				
			case 'replace':
			case 'delete':
			case 'update':
				$rows = self::affected_rows();
				$ret = $rows == -1 ? false : $rows;
				break;
				
			default:
				$ret = $result;
				break;
		}
		
		if (DB_LOCK_SAME_QUERIES === true)
		{
			//if ($ms > 0)
			//	file_put_contents($cache_dir.'/lock.log', " ".round((microtime(true) - $start)*1000)." ".strtr($query, array("\n" => " ")), FILE_APPEND);
		
			if ($lockfile)
				@unlink($lockfile);
		}
		
		$dtime = (microtime(true) - $start) * 1e3;
		
		if ( (is_numeric(BO_DB_DEBUG_LOG) && $dtime > BO_DB_DEBUG_LOG) 
			|| BO_DB_DEBUG_LOG === true || BO_DB_DEBUG_LOG === 'explain' || (BO_DB_DEBUG_LOG !== false && $result === false) )
		{
			$text = date("Y-m-d H:i:s | ")
				.sprintf("%5dms | %5d | ", $dtime, $qtype != 'insert' ? self::affected_rows() : 0)
				.strtr($query, array("\n"=>" ", "\t" => " ", "  " => " ", "   " => " "))
				.($result === false ? "\n  --> ".self::error() : "")
				.' | '.$_SERVER['REQUEST_URI']."\n";
			
			if ((is_numeric(BO_DB_DEBUG_LOG) || BO_DB_DEBUG_LOG === 'explain') && $qtype == 'select')
			{
				$r = self::do_query("explain ".$query);
				$text .= "                                          -> ";
				foreach($r->fetch_assoc() as $k => $d)
				{
					$text .= "$k: ".print_r($d, 1).", ";
					
					if ($k == 'table')
						$table = $d;
					
					if ($k == 'key')
					{
						$file = $cache_dir.'/.dbkey.'.$table.'.'.$d;
						$x = @file_get_contents($file);
						file_put_contents($file, ++$x);
						
						if (!$d)
							file_put_contents($file.'queries', $dtime.'ms | '.strtr($query, array("\n"=>""))."\n", FILE_APPEND);
					}
				}
				$text .= "\n";
				
				
			}

			file_put_contents($cache_dir.'/db.log', $text, FILE_APPEND);
		}
		
		if ($result === false)
		{
			if ($die_on_errors !== false)
				echo("<p>Database Query Error:</p><pre>" . htmlspecialchars(self::error()) .
					"</pre> <p>for query</p> <pre>" . htmlspecialchars($query) . "</pre>");

			if ($die_on_errors === true)
				die();
		}

		return $ret;
	}

	public static function bulk_insert($table, $data = array())
	{
		$ok = true;

		if (strlen(self::$bulk_query[$table]) > BO_DB_MAX_QUERY_LEN || empty($data))
		{
			if (self::$bulk_query[$table])
				$ok = self::query(self::$bulk_query[$table], true, false);

			unset(self::$bulk_query[$table]);
			
			if (!$table || empty($data))
				return $ok;
		}
		
		//first call
		if (!self::$bulk_query[$table])
		{
			self::$bulk_names[$table] = array();
			foreach($data as $name => $value)
			{
				self::$bulk_query[$table] .= self::$bulk_query[$table] ? ',' : '';
				self::$bulk_query[$table] .= $name;
				self::$bulk_names[$table][] = $name;
			}
			
			self::$bulk_query[$table] = "REPLACE INTO ".BO_DB_PREF.$table." (".self::$bulk_query[$table].") VALUES ";
		}
		else
			self::$bulk_query[$table] .= ',';
		
		//values
		self::$bulk_query[$table] .= '(';
		foreach (self::$bulk_names[$table] as $i => $name)
		{
			self::$bulk_query[$table] .= $i ? ',' : '';
			self::$bulk_query[$table] .= self::value2sql($data[$name]);
		}
		self::$bulk_query[$table] .= ')';
		
		
		return $ok;
	}
	
	public static function update_data($table, $data = array(), $where = '')
	{
		$sql = '';
		
		foreach($data as $name => $value)
		{
			$sql .= $sql ? ',' : '';
			$sql .= " $name=".self::value2sql($value);
		}
		
		if ($where)
			$sql .= " WHERE $where";
		
		$low_prio = BO_DB_UPDATE_LOW_PRIORITY ? "LOW_PRIORITY" : "";
		
		return self::query("UPDATE $low_prio ".BO_DB_PREF.$table." SET $sql"); 
	}

	
	public static function value2sql($value)
	{
		if (is_array($value))
		{
			switch($value[1])
			{
				case 'hex':	return "x'".$value[0]."'";
				default: return $value[0];
			}
		}
		else if ($value === null)
		{
			return 'NULL';
		}
		else if (is_numeric($value))
		{
			return $value;
		}
		else
		{
			return "'".self::esc($value)."'";
		}
	
	}
}




?>