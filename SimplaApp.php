<?php
include_once("config.php");
include_once("Lang.php");

class App
{
	private static
		$db = NULL,
		$log = [];
	
	public static function db($query = null)
	{
		global $config;
		if(static::$db == NULL)
		{
			static::$db = mysqli_connect($config["db"]["host"], $config["db"]["username"], $config["db"]["password"], $config["db"]["database"]);
			static::$db->query("SET NAMES utf8");
			static::$db->query("SET time_zone = '+07:00'");
		}
		if($query === null) return static::$db;
		$ret = static::$db->query($query);
		if(static::$db->error)
		{
			$back = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
			App::log("Database error (in file $back[file] on line $back[line]) in query:\n«{$query}»\n<b>" . static::$db->error . "</b>");
		}
		return $ret;
	}
	
	public static function log($message)
	{
		static::$log[] = $message;
	}
	
	public static function logout()
	{
		return implode("\n", static::$log);
	}
}
?>