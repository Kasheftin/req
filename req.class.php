<?php

/* 
	Req class by Kasheftin

		v. 0.15 (2011.02.04)
			- Redirect in meta is also supported.
		v. 0.14 (2010.11.26)
			- New method get added for getting opts parameters (cookies is the most important).
		v. 0.13 (2010.11.26)
			- Merge with another version.
		v. 0.12 (2010.11.25)
			- bug in read_chunks fixed.
		v. 0.11 (2010.11.24)
			- Some notices...
			- Some debug info added when DEBUG class doesn't exist.
		v. 0.10 (2010.11.23)
			- This is the first version of my Req class.
*/	

class Req
{
	protected $opts = array();
	protected $results = array();

	protected $defaults = array(
		"protocol" => "GET",
		"accept" => "text/html,application/xhtml+xml,application/xml,image/gif,image/x-xbitmap,image/jpeg,image/pjpeg,application/x-shockwave-flash;q=0.9,*/*;q=0.8",
		"accept-language" => "ru",
		"user-agent" => "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12",
		"accept-charset" => "windows-1251,utf-8;q=0.7,*;q=0.7",
		"cache-control" => "no-cache",
		"pragma" => "no-cache",
		"connection" => "close",
		"content-type" => "application/x-www-form-urlencoded",
		"port" => "80",
		"x-requested-with" => false,
		"debug" => false,
		"auto_updatecookies" => true,
		"auto_redirects" => true,
		"auto_redirects_limit" => 5,
		"auto_encode_to" => false,
		"delay" => 0,
	);

	public function __construct($opts = null)
	{
		$this->opts[] = $this->defaults;
		if (isset($opts)) $this->set($opts);
		return $this;
	}

	public function b() { return $this->begin(); }
	public function e() { return $this->end(); }

	public function set()
	{
		$args = func_get_args();

		if (class_exists("DEBUG") && $this->opts[debug])
		{
			DEBUG::log($args,__METHOD__);
		}

		if (count($args) == 3)
			$args = array($args[0]=>array($args[1]=>$args[2]));
		elseif (count($args) == 2)
			$args = array($args[0]=>$args[1]);
		elseif (count($args) == 1 && is_array($args[0]))
			$args = $args[0];
		else throw new ReqException(__METHOD__ . " - incorrect args");

		$this->opts[] = array_merge(array_pop($this->opts),$args);
		return $this;
	}

	public function get($varname,&$var)
	{
		$opts = end($this->opts);
		$var = $opts[$varname];
		return $this;
	}

	public function clear()
	{
		$opts = array_pop($this->opts);

		$args = func_get_args();
		if (count($args) == 3)
			unset($opts[$args[0]][$args[1]][$args[2]]);
		elseif (count($args) == 2)
			unset($opts[$args[0]][$args[1]]);
		elseif (count($args) == 1)
			unset($opts[$args[0]]);
		else throw new ReqException(__METHOD__ . " - incorrect args");

		$this->opts[] = $opts;
		return $this;
	}

	public function req($vars = array())
	{
		$opts = end($this->opts);

		$opts["cookies_str"] = "";
		if (isset($opts["cookies"]) && is_array($opts["cookies"]))
		{
			foreach($opts["cookies"] as $i => $v)
			{
				$ar = explode(";",$v,2);
				$v = $ar[0];
				$opts["cookies_str"] .= $i . "=" . $v . "; ";
			}
		}

		$content = $this->merge_vars_req($vars);
		if ($opts["protocol"] == "GET" && $content)
		{
			$opts["url"] .= (preg_match("/\?/",$opts["url"])?"&":"?") . $content;
			$content = "";
		}
		$opts["content-length"] = strlen($content);

		$required_opts = array("protocol","host","url");
		foreach($required_opts as $opt)
			if (!$opts[$opt]) throw new ReqException(__METHOD__ . " - " . $opt . " is not set");

		$h_opts = array(
			"accept" => "Accept",
			"accept-language" => "Accept-Language",
			"accept-encoding" => "Accept-Encoding",
			"accept-charset" => "Accept-Charset",
			"cookies_str" => "Cookie",
			"cache-control" => "Cache-Control",
			"pragma" => "Pragma",
			"connection" => "Connection",
			"user-agent" => "User-Agent",
			"content-type" => "Content-Type",
			"content-length" => "Content-Length",
			"x-requested-with" => "X-Requested-With",
		);

		$r = $opts["protocol"] . " " . $opts["url"] . " HTTP/1.1\r\n" . "Host: " . $opts["host"] . "\r\n";
		foreach($h_opts as $i => $v)
			if (isset($opts[$i]) && $opts[$i])
				$r .= $v . ": " . $opts[$i] . "\r\n";
		$r .= "\r\n" . $content;

		if ($opts["debug"])
		{
			if (class_exists("DEBUG"))
			{
				DEBUG::log(trim($r),__METHOD__);
			}
			else
			{
				echo "\n\n" . trim($r) . "\n";
			}
		}

		if ($opts["delay"])
		{
			if (class_exists("DEBUG"))
				DEBUG::log("Sleeping for a " . ($opts["delay"]==1?"second":$opts["delay"] . " seconds") . " before sending request",__METHOD__);
			else
				echo "Sleeping for a " . ($opts["delay"]==1?"second":$opts["delay"] . " seconds") . " before sending request\n";

			sleep($opts["delay"]);
		}

		$s = "";
		if ($fp = @fsockopen($opts["host"],$opts["port"],$errn,$errstr))
		{
			fputs($fp,$r);
			while (!feof($fp))
				$s .= fgets($fp, 128);
		} 
		else
			throw new ReqException(__METHOD__ . " - socket error[$errn]: $errstr");

		$this->results[count($this->opts)-1][] = $s;

		if ($opts["auto_updatecookies"])
		{
			$tmp_cookies = $this->parseCookies($s);
			if ($tmp_cookies)
				$this->set("cookies",$tmp_cookies);
		}

		if ($opts["auto_redirects"])
		{
			if (!isset($opts["auto_redirects_cnt"]) || $opts["auto_redirects_cnt"] < $opts["auto_redirects_limit"])
			{
				if ($new_opts = $this->findRedirect($s))
				{
					$this->set($new_opts);
					$this->set("auto_redirects_cnt",isset($opts["auto_redirects_cnt"])?$opts["auto_redirects_cnt"]+1:1);
					$this->req();
				}
			}
			else
				throw new ReqException(__METHOD__ . " - max auto_redirects_limit exceeded");
		}

		return $this;
	}

	public function save(&$var)
	{
		$opts = end($this->opts);
		$s = $this->read_chunks(end($this->results[count($this->opts)-1]));
		if ($opts["auto_encode_to"])
		{
			$page_encoding = $this->findEncoding($s);
			if ($page_encoding && $page_encoding != $opts["auto_encode_to"])
				$s = iconv($page_encoding,$opts["auto_encode_to"],$s);
		}
		$var = $s;
		return $this;
	}

	public function saveContent(&$var)
	{
		$this->save($s);
		list($header,$data) = explode("\r\n\r\n",$s,2);
		$var = $data;
		return $this;
	}

	public function saveHeader(&$var)
	{
		$this->save($s);
		list($header,$data) = explode("\r\n\r\n",$s,2);
		$var = $header;
		return $this;
	}

	public function begin()
	{
		$opts = end($this->opts);
		$this->opts[] = $opts;
		return $this;
	}

	public function end()
	{
		unset ($this->results[count($this->opts)-1]);
		$opts = array_pop($this->opts);
		return $this;
	}

	protected function merge_vars_req($vars,$before="",$after="")
	{
		$str = "";
		if (is_array($vars))
		{
			foreach($vars as $i => $v)
			{
				if (is_array($v))
				{
					$str .= ($str?"&":"") . $this->merge_vars_req($v,$before . $i . $after . "[","]");
				}
				else
				{
					$str .= ($str?"&":"") . urlencode($before . $i . $after) . "=" . urlencode($v); 
				}
			}
		}
		return $str;
	}

	protected function read_chunks($str)
	{
		list($header,$data) = explode("\r\n\r\n",$str,2);
		if (preg_match("/Transfer-Encoding:\s*chunked/i",$header))
		{
			$len = strlen($data);
			$s = $data;
			$pos = 0;
			$out = "";
			while (($pos < $len) && (strlen($s) > 0))
			{
				$a = explode("\r\n",$s,2);
				$out .= substr($a[1],0,hexdec($a[0]));
				$s = substr($a[1],hexdec($a[0])+2);
			}
			$out = $header . "\r\n\r\n" . $out;
		}
		else
			$out = $header . "\r\n\r\n" . $data;
		return $out;
	}

	protected function parseCookies($s)
	{
		$cookies = array();
		list($header,$data) = explode("\r\n\r\n",$s,2);
		preg_match_all("/Set-Cookie:(.*?);/i",$header,$m);
		foreach($m[1] as $v)
		{
			$ar = explode("=",trim($v));
			$cookies[$ar[0]] = $ar[1];
		}
		return $cookies;
	}

	protected function findEncoding($s)
	{
		list($header,$data) = explode("\r\n\r\n",$s,2);
		if (preg_match("/charset\s*=(\S+)/",$header,$m))
			return $m[1];
		if (preg_match("/<meta[^<>]*charset\s*=\s*[\"']?([^<>\"']+)[\"']?[^<>]*>/i",$data,$m))
			return $m[1];
		return null;
	}

	protected function findRedirect($s)
	{
		$opts = array();
		list($header,$data) = explode("\r\n\r\n",$s,2);
		if (preg_match("/^HTTP\/\d+\.\d+\s+(301|302)/i",$header) && preg_match("/Location:\s*([^\n]+)/",$header,$m))
		{
			$m[1] = trim($m[1]);
			if (preg_match("/^http(s)?:\/\//",$m[1]))
			{
				list($host,$url) = explode("/",preg_replace("/^http(s)?:\/\//","",$m[1]),2);
				$url = "/" . $url;
				
				$opts["host"] = $host;
				$opts["url"] = $url;
			}
			else
			{
				$opts["url"] = $m[1];
			}
			$opts["protocol"] = "GET";
		}
		elseif (preg_match("/<meta[^<>]*http-equiv=[\"']?Refresh[\"']?[^<>]*>/",$data,$m))
		{
			if (preg_match("/url=([^<>\"']*)/",$m[0],$mm))
			{
				if (preg_match("/^http(s)?:\/\//",$mm[1]))
				{
					list($host,$url) = explode("/",preg_replace("/^http(s)?:\/\//","",$mm[1]),2);
					$url = "/" . $url;
					$opts["host"] = $host;
					$opts["url"] = $url;
				}
				else
				{
					$opts["url"] = $mm[1];
				}
			}
		}
		return $opts;
	}
}

