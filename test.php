<?php
date_default_timezone_set('europe/warsaw');
/**
 * Pipe here text with raw mail
 * Will be generated: output.txt file with outputs
 * and all attachments will be saved in /attachemnts directory
 */
chdir(dirname(__FILE__));
require('MailParser.php');
$mail = file_get_contents('php://stdin') or die;

$parser = new MailParser($mail);
$parser->parse();

file_put_contents('output.txt',
	'Subject: '. $parser->getSubject('<empty>').PHP_EOL.
	'From: '. $parser->getFrom().PHP_EOL.
	'To: '. $parser->getTo().PHP_EOL.
	'----'.PHP_EOL.
	$parser->getBody('text/plain', true)['body'].PHP_EOL.
	'----'.PHP_EOL.
	'count of attachments: '.count($parser->getAttachments()).PHP_EOL.PHP_EOL.PHP_EOL
	.print_r($parser, true)
);

if (!file_exists('attachments'))
    mkdir('attachments', 0777);

foreach($parser->getAttachments() as $att) {
	$filename = $att['headers']['filename']
		? 'attachments/'.$att['headers']['filename']
		: 'attachments/'.time();
	file_put_contents($filename, $att['body']);
}