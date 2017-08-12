<?php
/**
 * Class allowing to simple parse raw mails
 * @author Maxie
 * @license MIT
 * @version 0.2
 */
class MailParser {
	/**
	 * Raw content
	 * @var string
	 */
	private $raw = '';
	/**
	 * All headers
	 * @var array
	 */
	private $headers = [];
	/**
	 * All bodies
	 * @var array
	 */
	private $bodies = [];
	/**
	 * Whitelist of HTML elements
	 * @var array
	 */
	private $whitelist = [
		'a','abbr','acronym','address','area','b','bdo','big','blockquote','br','button','caption','center','cite','code','col','colgroup','dd','del','dfn','dir','div','dl','dt','em','fieldset','font','form','h1','h2','h3','h4','h5','h6','hr','i','img','input','ins','kbd','label','legend','li','map','menu','ol','optgroup','option','p','pre','q','s','samp','select','small','span','strike','strong','sub','sup','table','tbody','td','textarea','tfoot','th','thead','u','tr','tt','u','ul','var'
	];
	/**
	 * Allowed MIMES of attachments
	 * @var array
	 */
	private $attachmentsMIMEs = [
		'application/zip',
		'image/tiff',
		'application/x-compressed',
		'application/x-tar',
		'application/pdf',
		'video/mpeg',
		'audio/mpeg',
		'image/jpeg',
		'application/msword',
		'image/bmp',
		'image/png',
		'image/gif'
	];
	/**
	 * @param string $raw
	 */
	function __construct($raw='') {
		$this->setRaw($raw);
	}
	/**
	 * Overwrite raw version of mail
	 * @param string $raw
	 */
	function setRaw($raw='') {
		if(is_string($raw))
			$this->raw = $raw;
	}
	/**
	 * Returns raw version of mail
	 * @return string
	 */
	function getRaw() {
		return $this->raw;
	}
	/**
	 * Set-up new HTML whitelist erasing old one 
	 * @param array $list
	 */
	function setWhitelist(array $list) {
		$this->whitelist = [];
		foreach($list as $tag) {
			$this->pushWhitelist($tag);
		}
	}
	/**
	 * Returns array of HTML tags 
	 * @return array
	 */
	function getWhitelist() {
		return $this->whitelist;
	}
	/**
	 * Push one or multiple tags into whitelist
	 * @param array|string ...
	 */
	function pushWhitelist() {
		foreach( func_get_args() as $tag ) {
			if(is_string($tag)
			&& !in_array($tag, $this->whitelist)) {
				$this->whitelist[] = $tag;
				continue;
			}
			if(is_array($tag)) {
				call_user_method_array(__METHOD__, $this, $tag);
			}
		}
	}
	/**
	 * Remove given variables from HTML whitelist
	 * @param array|string ...
	 */
	function removeWhitelist() {
		foreach( func_get_args() as $tag ) {
			if(is_string($tag)
			&& in_array($tag, $this->whitelist)) {
				unset($this->whitelist[array_search($tag, $this->whitelist)]);
				continue;
			}
			if(is_array($tag)) {
				call_user_method_array(__METHOD__, $this, $tag);
			}
		}
	}
	/**
	 * Set-up new new list of allowed MIMES of attachments
	 * @param array $list
	 */
	function setAllowedMIMEs(array $list) {
		$this->attachmentsMIMEs = [];
		foreach($list as $tag) {
			$this->pushWhitelist($tag);
		}
	}
	/**
	 * Returns array of allowed MIMES of attachments
	 * @return array
	 */
	function getAllowedMIMEs() {
		return $this->attachmentsMIMEs;
	}
	/**
	 * Push one or multiple values into allowed MIMES
	 * @param array|string ...
	 */
	function pushAllowedMIMEs() {
		foreach( func_get_args() as $tag ) {
			if(is_string($tag)
			&& !in_array($tag, $this->attachmentsMIMEs)) {
				$this->attachmentsMIMEs[] = $tag;
				continue;
			}
			if(is_array($tag)) {
				call_user_method_array(__METHOD__, $this, $tag);
			}
		}
	}
	/**
	 * Remove given variables from allowed MIMES of attachments
	 * @param array|string ...
	 */
	function removeAllowedMIMEs() {
		foreach( func_get_args() as $tag ) {
			if(is_string($tag)
			&& in_array($tag, $this->attachmentsMIMEs)) {
				unset($this->attachmentsMIMEs[array_search($tag, $this->attachmentsMIMEs)]);
				continue;
			}
			if(is_array($tag)) {
				call_user_method_array(__METHOD__, $this, $tag);
			}
		}
	}
	/**
	 * Set-up array as config
	 * @param string $name
	 * @param string $value
	 */
	function setHeader($name, $value) {
		$this->parseHeader($this->headers, $name, $value);
	}
	/**
	 * Parse value to readable version
	 * @param  array  &$h
	 * @param  string $name
	 * @param  string $value
	 */
	function parseHeader(&$h, $name, $value) {
		$name = strtolower($name);
		switch($name) {
			case 'to':
			case 'from':
				$h[$name] = $this->parseAddresses($value);
				break;
			case 'date':
				$h[$name] = strtotime($value);
				break;
			case 'subject':
				$h[$name] = $this->parseNonASCII($value);
				break;
			case 'content-type':
				if(preg_match('/boundary="([^"]+)"/i', $value, $m)) {
					$h['boundary'] = $m[1]; 
				} else {
					$h['boundary'] = false;
				}
				if(preg_match('/([-\w]+\/[-\w]+)/i', $value, $m)) {
					$h['content-type'] = $m[1];
				}
				if(preg_match('/charset="?([^"\s]+)"?/i', $value, $m)) {
					$h['charset'] = $m[1];
				}
				if(preg_match('/name="?([^"\s]+)"?/i', $value, $m)) {
					$h['filename'] = $m[1];
				}
				break;
			case 'content-disposition':
				if(preg_match('/attachment/i', $value, $m)) {
					$h['is-attachment'] = true;
				}
				if(preg_match('/name="?([^"\s]+)"?/i', $value, $m)) {
					$h['filename'] = $m[1];
				}
				$h['content-disposition'] = $value;
				break;
			default:
				if(isset($h[$name])) {
					if(!is_array($h[$name])) {
						$h[$name] = [$h[$name]];
					}
					$h[$name][] = trim($value);
				} else {
					$h[$name] = trim($value);
				}
		}
	}
	/**
	 * Parse adresses from header From or To
	 * @param  srting $value
	 * @return array
	 */
	function parseAddresses($value) {
		$temp = str_split($value);
		$quoted = false;
		$result = []; 
		$title = true;
		$i = 0; // array position
		foreach($temp as $char) {
			if(!isset($result[$i]))
				$result[$i] = [
					'title' => '',
					'mail' => ''
				];
			switch($char){
				// Toggle collecting name
				case '"': 
					$quoted = !$quoted;
					break;
				// Start collecting mail
				case '<':
					if(!$quoted){
						$title = false;
					} else {
						$result[$i][$title?'title':'mail'] .= $char;
					}
					break;
				// Stop collecting mail
				case '>':
					if(!$quoted){
						$title = true;
					} else {
						$result[$i][$title?'title':'mail'] .= $char;
					}
					break;
				// New element if not quoted
				case ',':
					if(!$quoted){
						$title = true;
						$i++;
						break;
					} else
						$result[$i][$title?'title':'mail'] .= $char;
					break;
				// Collect chars
				default:
					$result[$i][$title?'title':'mail'] .= $char;
					break;
			}
		}
		$newResult = [];
		foreach($result as $ar) {
			extract($ar);
			$title = trim($this->parseNonASCII(trim($ar['title'])));
			$mail  = trim($ar['mail']);
			if(strlen($mail) === 0 && strlen($title) === 0)
				continue;
			if(strlen($mail) === 0) {
				$mail = $title;
				$title = '';
			}

			$newResult[] = [
				'mail' => trim($mail),
				'name' => trim($title)
			];
		}
		return $newResult;
	}
	/**
	 * Parse non-ASCII strings
	 * Following RFC 1342 standard
	 * @param  string $value
	 * @return string
	 */
	function parseNonASCII($value) {
		$result = '';
		foreach(preg_split('/\s+/', $value) as $string){
			if(preg_match('/^=\?(.+)\?(q|b)\?(.*?)\?=$/i', $string, $m)) {
				$string = $m[3];
				switch(strtolower(trim($m[2]))) {
					case 'q':
						$string = quoted_printable_decode($string);
						break;
					case 'b':
						$string = base64_decode($string);
						break;
				}
				if(strtolower(trim($m[1])) != 'utf-8')
					$string = iconv(strtoupper(trim($m[1])), 'UTF-8//TRANSLIT', $string);
				$result .= ' '.str_replace('_',' ',trim($string));
			} else 
				$result .= $value;
		}
		return trim($result);
	}
	/**
	 * Read headers and save them as array
	 * @param string $content
	 * @return array
	 */
	function parseHeaders(&$content) {
		list($headers, $content) = preg_split('/^\s*$/m', $content, 2);
		$data = [];
		$collected = '';
		$name = '';
		foreach(preg_split('/\r\n|\n|\r/', $headers) as $line) {
			if(preg_match('/^[^\s]/', $line)) {
				if($collected && $name) {
					// Push previous collected header
					$name = trim(strtolower($name));
					$collected = trim($collected);
					$this->parseHeader($data, $name, $collected);
				}
				// Start to collect new header
				list($name, $collected) = preg_split('/:/i', $line, 2);
			} else {
				// Collect
				$collected .= ' '. trim($line);
			}
		}
		if($collected && $name) {
			// Push previous collected header
			$name = trim(strtolower($name));
			$collected = trim($collected);
			$this->parseHeader($data, $name, $collected);
		}
		return $data;
	}
	/**
	 * Decode 7bit coding
	 * @param  string $text
	 * @return string
	 */
	private function _decode7Bit($text) {
  	$characters = array(
  		'=20' => ' ',
  		'=E2=80=99' => "'",
  		'=0A' => "\r\n",
  		'=A0' => ' ',
  		'=C2=A0' => ' ',
  		"=\r\n" => '',
  		'=E2=80=A6' => '…',
  		'=E2=80=A2' => '•',
  	);
  	foreach ($characters as $key => $value) {
  		$text = str_replace($key, $value, $text);
  	}
  	return $text;
	}
	/**
	 * Discover and separate all of contents
	 * @return array
	 */
	function parseBody($headers, $content) {
		$data = [];
		if(isset($headers['boundary']) && $headers['boundary']) {
			$boundary = $headers['boundary'];
			$content = preg_replace('/(^\s*--'.$boundary.')|(--'.$boundary.'--\s*$)/', '', $content);
			foreach(preg_split('/^\s*--'.$boundary.'\s*$/m', $content) as $subContent) {
				$subContent = trim($subContent);
				$subHeaders = $this->parseHeaders($subContent);
				foreach($this->parseBody($subHeaders, $subContent) as $oneSubContent) {
					$data[] = $oneSubContent;
				}
			}
		} else {
			$content = trim($content);
			if(isset($headers['content-transfer-encoding'])) {
				switch(strtolower($headers['content-transfer-encoding'])) {
					case 'base64':
						$content = base64_decode($content);
						break;
					case 'quoted-printable':
						$content = quoted_printable_decode($content);
						break;
					case '7bit':
						$content = $this->_decode7Bit($content);
						break;
					case '8bit':
						$content = mb_convert_encoding($content, 'UTF-8');
						break;
					default:
						return [];
				}
			}
			if(isset($headers['charset'])) {
				if($headers['charset'] != 'utf-8') {
					$content = iconv(strtoupper(trim($headers['charset'])), 'UTF-8//TRANSLIT', $content);
				}
			}
			if(!isset($headers['content-type']))
				$headers['content-type'] = '';
			switch($headers['content-type']) {
				case 'text/html':
					$content = strip_tags($content, '<'.implode('><',$this->whitelist).'>');
					break;
				case 'text/plain':
					$content = htmlspecialchars($content, ENT_DISALLOWED|ENT_QUOTES|ENT_HTML5);
					break;
				default:
					if(isset($headers['is-attachment'])) {
						if($headers['is-attachment']) {
							break;
						}
					}
					return [];
			}
			$data[] = [
				'headers' => $headers,
				'body' => $content
			];
		}
		return $data;
	}
	/**
	 * Parse given message
	 * @param boolean $onlyText 
	 * @return string
	 */
	function parse() {
		$this->headers = $this->parseHeaders($this->raw);
		if(!isset($this->headers['content-type']))
			$this->headers['content-type'] = '';
		if(!isset($this->headers['boundary']))
			$this->headers['boundary'] = false;
		$this->bodies = $this->parseBody(
			$this->headers,
			$this->raw
		);
		$this->raw = null;
	}
	/**
	 * Return contents possible to print
	 * @return array
	 */
	function getBodies($first=false) {
		$result = [];
		foreach( $this->bodies as $body ) {
			if(isset($body['headers']['content-type'])) {
				$result[] = $body;
			}
		}
		return $result;
	}
	/**
	 * Returns first found body with given mime
	 * @param  string $mime
	 * @return array
	 */
	function getBody($mime, $dummy=false) {
		$result = [];
		foreach( $this->bodies as $body ) {
			if(isset($body['headers']['content-type'])
			&& $body['headers']['content-type'] === $mime) {
				return $body;
			}
		}
		if($dummy){
			return [
				'headers' => [
					'content-type' => $mime
				],
				'body' => ''
			];
		}
		return false;
	}
	/**
	 * Return contents possible to save as file
	 * @return array
	 */
	function getAttachments($allowed=true) {
		$result = [];
		foreach( $this->bodies as $body ) {
			if(isset($body['headers']['content-type'])
			&& isset($body['headers']['is-attachment'])
			&& $body['headers']['is-attachment'] === true){
				if( $allowed
				&& !in_array(
						$body['headers']['content-type'],
						$this->attachmentsMIMEs)
				)	continue;
				$result[] = $body;
			}
		}
		return $result;
	}
	/**
	 * Get headers of mail
	 * @return array
	 */
	function getHeaders() {
		return $this->headers;
	}
	/**
	 * Return 'from' as string
	 * @return string
	 */
	function getFrom() {
		$r = [];
		if(!isset($this->headers['from']))
			return '';
		foreach( $this->headers['from'] as $one ) {
			$oneR = [];
			if($one['name'])
				$oneR[] = $one['name'];
			if($one['mail'])
				$oneR[] = '<'.$one['mail'].'>';
			$r[] = implode(' ',$oneR);
		}
		return implode(', ', $r);
	}
	/**
	 * Return 'to' as string
	 * @return string
	 */
	function getTo() {
		$r = [];
		if(!isset($this->headers['to']))
			return '';
		foreach( $this->headers['to'] as $one ) {
			$oneR = [];
			if($one['name'])
				$oneR[] = $one['name'];
			if($one['mail'])
				$oneR[] = '<'.$one['mail'].'>';
			$r[] = implode(' ',$oneR);
		}
		return implode(', ', $r);
	}
	/**
	 * Returns 'subject'
	 * @return string
	 */
	function getSubject($alt='') {
		if(isset($this->headers['subject']))
			return $this->headers['subject'];
		return $alt;
	}
}