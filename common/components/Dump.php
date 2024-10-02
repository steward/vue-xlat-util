<?php

// Forget who to credit wih this snippet
// Always ran into some damn issue with print_r or var_dump.
// This takes any number of args and spits them out for humans.
// Dump:say ('foo' $someObject, 'fie', $someArray, "FUM',$boolean, etc) ECHO AT CONSOLE
// Dump:log ('foo' $someObject, 'fie', $someArray, "FUM',$boolean, etc) TO BHLogger instance


namespace common\components;

class Dump
{
	private static $_objects;
	private static $_output;
	private static $_depth;


	public $fn;

	/**
	 * Converts a variable into a string representation.
	 * This method achieves the similar functionality as var_dump and print_r
	 * but is more robust when handling complex objects such as PRADO controls.
	 * @param mixed variable to be dumped
	 * @param integer maximum depth that the dumper should go into the variable. Defaults to 10.
	 * @return string the string representation of the variable
	 */
	public static function dump($var,$depth=10,$highlight=false)
	{
		self::$_output='';
		self::$_objects=array();
		self::$_depth=$depth;
		self::dumpInternal($var,0);
		if($highlight)
		{
			$result=highlight_string("<?php\n".self::$_output,true);
			return preg_replace('/&lt;\\?php<br \\/>/','',$result,1);
		}
		else
			return self::$_output;
	}
	private static function dumpInternal($var,$level)
	{
		switch(gettype($var))
		{
			case 'boolean':
				self::$_output.=$var?'true':'false';
				break;
			case 'integer':
				self::$_output.="$var";
				break;
			case 'double':
				self::$_output.="$var";
				break;
			case 'string':
				//self::$_output.="'$var'"; dsm 3 jul 21
				self::$_output.="$var";
				break;
			case 'resource':
				self::$_output.='{resource}';
				break;
			case 'NULL':
				self::$_output.="null";
				break;
			case 'unknown type':
				self::$_output.='{unknown}';
				break;
			case 'array':
				if(self::$_depth<=$level)
					self::$_output.='array(...)';
				else if(empty($var))
					self::$_output.='array()';
				else
				{
					$keys=array_keys($var);
					$spaces=str_repeat(' ',$level*4);
					self::$_output.="array\n".$spaces.'(';
					foreach($keys as $key)
					{
						self::$_output.="\n".$spaces."    [$key] => ";
						self::$_output.=self::dumpInternal($var[$key],$level+1);
					}
					self::$_output.="\n".$spaces.')';
				}
				break;
			case 'object':
				if(($id=array_search($var,self::$_objects,true))!==false)
					self::$_output.=get_class($var).'#'.($id+1).'(...)';
				else if(self::$_depth<=$level)
					self::$_output.=get_class($var).'(...)';
				else
				{
					$id=array_push(self::$_objects,$var);
					$className=get_class($var);
					$members=(array)$var;
					$keys=array_keys($members);
					$spaces=str_repeat(' ',$level*4);
					self::$_output.="$className#$id\n".$spaces.'(';
					foreach($keys as $key)
					{
						$keyDisplay=strtr(trim($key),array("\0"=>':'));
						self::$_output.="\n".$spaces."    [$keyDisplay] => ";
						self::$_output.=self::dumpInternal($members[$key],$level+1);
					}
					self::$_output.="\n".$spaces.')';
				}
				break;
		}
	}

	public static function getAsString($args)
	{
		$s=date("[Y-m-d h:i:s]\n");
		foreach($args AS $arg)
		{
			$s .= self::dump($arg) . "\n";
		}
		return $s;
	}
	// Pass in as many args as needed
	public static function d()
	{
		file_put_contents('/home/me/logs/dump.log', self::getAsString(func_get_args()), FILE_APPEND);
	}
	public static function task()
	{
		file_put_contents('/home/me/sites/www/remote/task/dump.log', self::getAsString(func_get_args()), FILE_APPEND);
	}
	public static function say()
	{
		print self::getAsString(func_get_args()); return;
		$args = func_get_args();
		$s=date("[Y-m-d h:i:s]\n");
		foreach($args AS $arg)
		{
			$s .= self::dump($arg) . "\n";
		}
		print $s;
	}
	public static function sayq(){      // say quietly (skip date and time stamp)
		$s='';
		foreach(func_get_args() AS $arg)
		{
			$s .= self::dump($arg) . "\n";
		}
		print $s;
	}
	public static function log()
	{
		$args = func_get_args();
		$s=date("[Y-m-d h:i:s]\n");
		foreach($args AS $arg)
		{
			$s .= self::dump($arg) . "\n";
		}
		return $s;
	}

}
