<?php
class Lang{
	private static $curLang = null, $defLang = null;
	public $id, $name, $code,
		$texts = null;
	
	private function __construct($data){
		$this->id = $data["id"];
		$this->code = $data["code"];
		$this->name = $data["name"];
	}
	
	public function text($id)
	{
		if($this->texts !== null && isset($this->texts[$id])) return $this->texts[$id];
		return
		(
			($text = App::db()->query("SELECT `text` FROM `lang` WHERE `id` = \"" . App::db()->real_escape_string($id) . "\" AND `lang` = {$this->id}")->fetch_assoc()) ?
			$text["text"] :
			null
		);
	}
	
	public function allTexts()
	{
		if($this->texts !== null) return $this->texts;
		$this->texts = [];
		$texts = App::db()->query("SELECT `id`, `text` FROM `lang` WHERE `lang` = {$this->id} ORDER BY `id` ASC");
		while($next = $texts->fetch_assoc()) $this->texts[$next["id"]] = $next["text"];
		return $this->texts;
	}
	
	public static function get($id){
		return 
		(
			($lang = App::db()->query("SELECT * FROM `langs` WHERE `id` = " . ($id * 1))->fetch_assoc()) ?
			new static($lang) :
			null
		);
	}

	public static function find($code)
	{
		return
		(
			($lang = App::db()->query("SELECT * FROM `langs` WHERE `code` = \"" . App::db()->real_escape_string($code) . "\"")->fetch_assoc()) ?
			new static($lang) :
			null
		);
	}
	
	public static function getAll(){
		$ret = [];
		$langs = App::db()->query("SELECT * FROM `langs` ORDER BY `id` ASC");
		while($next = $langs->fetch_assoc())
			$ret[] = new static($next);
		return $ret;
	}
	
	public static function editTexts($texts)
	{
		if(!is_array($texts)) return;
		foreach($texts as $id => $langs)
		{
			foreach($langs as $lang => $text)
			{
				App::db()->query("DELETE FROM `lang` WHERE `id` = '".App::db()->real_escape_string($id)."' AND `lang` = $lang");
				if($text !== "")
					App::db()->query("INSERT INTO `lang` (`id`, `lang`, `text`) VALUES ('".App::db()->real_escape_string($id)."', $lang, '".App::db()->real_escape_string($text)."')");
					
			}
		}
	}
	
	public static function addTexts($texts)
	{
		if(!is_array($texts)) return;
		foreach($texts as $next)
		{
			if(!$next["id"]) continue;
			foreach($next["langs"] as $lang => $text)
			{
				if($text === "") continue;
				App::db()->query("INSERT INTO `lang` (`id`, `lang`, `text`) VALUES ('".App::db()->real_escape_string($next['id'])."', $lang, '".App::db()->real_escape_string($text)."')");
			}
		}
	}
	
	public static function getDefaultLanguage()
	{
		if(static::$defLang !== null) return $defLang;
		return (static::$defLang = static::get(1));
	}
	
	public static function setCurLang($lang)
	{
		if($lang instanceof Lang) return (static::$curLang = $lang);
		if(!($lang = static::get($lang))) return null;
		if(!isset($_SESSION["lang"])) $_SESSION["lang"] = [];
		$_SESSION["lang"]["curId"] = $lang->id;
		return (static::$curLang = $lang);
	}
	
	public static function getCurLang()
	{
		if(static::$curLang !== null) return static::$curLang;
		if(!isset($_SESSION["lang"])) $_SESSION["lang"] = [];
		if(isset($_SESSION["lang"]["curId"]) && ($lang = static::get($_SESSION["lang"]["curId"]))) return (static::$curLang = $lang);
		return null;
	}
	
	public static function getPreferedLangs()
	{
		static $langs = null;
		if($langs !== null) return $langs;
		
		$langs = [];
		$found = [];
		
		//Если пользователь уже выбрал язык, то он первый в приоритете
		if($cur = static::getCurLang())
		{
			$found[$cur->code] = 1;
			$langs[] = $cur;
		}
		
		//Далее идут языки из настроек его браузера
		preg_replace_callback("/([a-z]{2,3})(-?[a-z0-9-;.=]*)?,/", function($match) use(&$langs, &$found)
		{
			if(isset($found[$match[1]])) return;
			$found[$match[1]] = 1;
			if($add = static::find($match[1])) $langs[] = $add;
		}, strtolower($_SERVER["HTTP_ACCEPT_LANGUAGE"] . ","));
		
		//И последним идёт язык по умолчанию для сайта
		$def = static::getDefaultLanguage();
		if(!isset($found[$def->code])) $langs[] = $def;
		
		return $langs;
	}
	
	public static function write($text, $ln = "html")
	{
		foreach(static::getPreferedLangs() as $lang)
		{
			if(($ret = $lang->text($text)) !== null)
			{
				if(gettype($ln) == 'object' && get_class($ln) == 'Closure') return $ln($ret);
				switch($ln)
				{
				case "html":
					return str_replace("\n", "<br />", $ret);
					break;
				case "array":
					return explode("\n", $ret);
					break;
				}
				return $ret;
			}
		}
		App::log("Lang: '$text' wasn't found in any language!");
		return "";
	}
}
?>