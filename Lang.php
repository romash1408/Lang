<?php
class Lang{
	const
		CURRENT_AT_TOP = 1;
	
	private static
		$curLang = null,
		$defLang = null,
		$RIGHT_ALIGNED = array("ar");
	
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
	
	public static function getAll($flags = 0){
		if($flags & static::CURRENT_AT_TOP) $order = "`id` = ".(static::getCurrentLanguage()->id)." DESC,";
		
		$ret = [];
		$langs = App::db()->query("SELECT * FROM `langs` ORDER BY $order `id` ASC ");
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
	
	public static function getSelectedLang()
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
		if($cur = static::getSelectedLang())
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
		
		// В конце все остальные языки сайта, включая язык по-умолчанию, если его ещё не было
		foreach(static::getAll() as $lang)
		{
			if(!isset($found[$lang->code]))
			{
				$found[$lang->code] = 1;
				$langs[] = $lang;
			}
		}
		
		return $langs;
	}
	
	public static function getCurrentLanguage()
	{
		return static::getPreferedLangs()[0];
	}
	
	public static function write($text, $ln = "html", &$outLang = null)
	{
		foreach(static::getPreferedLangs() as $lang)
		{
			if(($ret = $lang->text($text)) !== null)
			{
				$outLang = $lang;
				
				if(gettype($ln) == 'object' && get_class($ln) == 'Closure') return $ln($ret);
				switch($ln)
				{
				case "html":
					return static::html($ret);
					break;
				case "attr":
					return str_replace("\n", '\n', $ret);
					break;
				case "array":
					return explode("\n", $ret);
					break;
				default:
					return str_replace("\n", $ln, $ret);
				}
				return $ret;
			}
		}
		App::log("Lang: '$text' wasn't found in any language!");
		return "";
	}
	
	public static function file($name, &$outLang = null)
	{
		foreach(static::getPreferedLangs() as $lang)
		{
			if(is_file($ret = str_replace("%lang", $lang->code, $name)))
			{
				$outLang = $lang;
				return $ret;
			}
		}
		App::log("Lang: file '$name' wasn't found in any language!");
		return $name;
	}
	
	public static function isRightAligned(Lang $lang)
	{
		return in_array($lang->code, static::$RIGHT_ALIGNED);
	}

	public static function dir($lang)
	{
		if(!($lang instanceof Lang)) $lang = Lang::getCurrentLanguage();
		return (static::isRightAligned($lang) ? "rtl" : "ltr");
	}
	
	public static function html($text)
	{
		// теги
		$text = preg_replace_callback('/\[(\/?)([A-z _\-]*)\]/', function($m){
			switch($m[2])
			{
			default:
				if($m[1] == '/') return "</span>";
				return "<span class='$m[2]'>";
			}
		}, $text);
		
		// Перенос строки
		$text = str_replace("\n", '<br />', $text);
		
		return $text;
	}
}
?>