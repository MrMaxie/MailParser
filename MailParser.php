<?php
/**
 * Class allowing to simple parse piped mails
 * @author Maxie
 * @license Free
 */
class MailParser {
	/**
	 * All of headers
	 * @var array
	 */
	private $headers = [];
	/**
	 * Raw content
	 * @var string
	 */
	private $content = '';
	/**
	 * All contents
	 * @var array
	 */
	private $contents = [];
	/**
	 * All attachements
	 * @var array
	 */
	private $attachments = [];
	private $HTMLWhiteList = [
		'a','abbr','acronym','address','area','b','bdo','big','blockquote','br','button','caption','center','cite','code','col','colgroup','dd','del','dfn','dir','div','dl','dt','em','fieldset','font','form','h1','h2','h3','h4','h5','h6','hr','i','img','input','ins','kbd','label','legend','li','map','menu','ol','optgroup','option','p','pre','q','s','samp','select','small','span','strike','strong','sub','sup','table','tbody','td','textarea','tfoot','th','thead','u','tr','tt','u','ul','var'
	];
	/**
	 * @param string $content Content of e-mail
	 */
	function __construct($content = '') {
		$this->setContent($content);
	}
	/**
	 * Set new text
	 * @param string $text Content of e-mail
	 */
	function setContent($content = '') {
		if(is_string($content))
			$this->content = $content;
	}
	/**
	 * Set-up array as config
	 * @param string $name
	 * @param string $value
	 */
	private function setHeader($name, $value) {
		$this->parseHeader($this->headers, $name, $value);
	}
	/**
	 * Parse value to readable version
	 * @param  array  &$h
	 * @param  string $name
	 * @param  string $value
	 */
	private function parseHeader(&$h, $name, $value) {
		$name = strtolower($name);
		switch($name) {
			case 'to':
			case 'from':
				$h[$name] = $this->parseAdresses($value);
				if($name === 'to' && count($h[$name])) {
					$h[$name] = $h[$name][0];
				}
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
	private function parseAdresses($value) {
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
	private function parseNonASCII($value) {
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
	private function parseHeaders(&$content) {
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
	private function parseContent($headers, $content) {
		$data = [];
		if(isset($headers['boundary']) && $headers['boundary']) {
			$boundary = $headers['boundary'];
			$content = preg_replace('/(^\s*--'.$boundary.')|(--'.$boundary.'--\s*$)/', '', $content);
			foreach(preg_split('/^\s*--'.$boundary.'\s*$/m', $content) as $subContent) {
				$subContent = trim($subContent);
				$subHeaders = $this->parseHeaders($subContent);
				foreach($this->parseContent($subHeaders, $subContent) as $oneSubContent) {
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
					$content = strip_tags($content, '<'.implode('><',$this->HTMLWhiteList).'>');
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
				'content' => $content
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
		$this->headers = $this->parseHeaders($this->content);
		if(!isset($this->headers['content-type']))
			$this->headers['content-type'] = '';
		if(!isset($this->headers['boundary']))
			$this->headers['boundary'] = false;
		$this->contents = $this->parseContent(
			$this->headers,
			$this->content
		);
		$this->content = null;
	}
	/**
	 * Return contents possible to print
	 * @return array
	 */
	function getViews() {
		$result = [];
		foreach( $this->contents as $view ) {
			if(isset($view['headers']['content-type'])
			&& in_array($view['headers']['content-type'], ['text/plain', 'text/html'])) {
				$result[] = $view;
			}
		}
		return $result;
	}
	/**
	 * Return contents possible to save as file
	 * @return array
	 */
	function getAttachments() {
		$mimes = [
			'application/zip' => 'zip',
			'image/tiff' => 'tiff',
			'application/x-compressed' => 'tgz',
			'application/x-tar' => 'tar',
			'application/pdf' => 'pdf',
			'video/mpeg' => 'mpeg',
			'audio/mpeg' => 'mp3',
			'image/jpeg' => 'jpeg',
			'application/msword' => 'doc',
			'image/bmp' => 'bmp',
			'image/png' => 'png',
			'image/gif' => 'gif'
		];
		$result = [];
		foreach( $this->contents as $view ) {
			if(isset($view['headers']['content-type'])
			&& array_key_exists($view['headers']['content-type'], $mimes)) {
				$view['headers']['ext'] = $mimes[$view['headers']['content-type']];
				$result[] = $view;
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
}