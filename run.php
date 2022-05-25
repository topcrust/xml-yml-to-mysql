<?php

if(!isset($argv[1])) die( "usage: {$argv[0]} <file.xml>" . PHP_EOL );

require './Xmltosql.php';

$settings = [
	'DB_HOST' => 'localhost',
	'DB_NAME' => 'test',
	'DB_USER' => 'test',
	'DB_PASS' => 'test',
	'XML_FILE' => $argv[1],
	'LOG_FILE_NAME' => 'handle_xml.log',
];

$xmlhandler = new Xmltosql( $settings );

if( $xmlhandler->get_xml_date() > $xmlhandler->get_last_success_date() ) {
	$xmlhandler->db_connect();
	$xmlhandler->handle_categories('categories');
	$xmlhandler->handle_offers('offers');
	$xmlhandler->db_close();
	$xmlhandler->write_log();
}

?>