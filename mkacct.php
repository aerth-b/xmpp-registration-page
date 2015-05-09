<?php
// This script implements XEP-0077: In-Band Registration
// See http://xmpp.org/extensions/xep-0077.html

$valid_fields = array('username', 'nick', 'password', 'name', 'first',
	'last', 'email', 'address', 'city', 'state', 'zip', 'phone', 'url',
	'date', 'misc', 'text', 'key');

define('XMPP_HOST', 'earthbot.net');
define('XMPP_CONN', 'localhost');
define('XMPP_PORT', '5222');
// TODO Optional SRV lookup.

try {
	$stream = simplexml_load_string('<?xml version="1.0"?><stream:stream xmlns:stream="http://etherx.jabber.org/streams" xmlns="jabber:client" version="1.0"><iq id="register" type="set"><query xmlns="jabber:iq:register"/></iq></stream:stream>');

	$stream->addAttribute('to', XMPP_HOST);

	// Captchas or other limitations can go here

	if(!isset($_POST['username']) or !isset($_POST['password']))
		throw new Exception('Empty username or password', 400);
	if($_POST['password'] !== $_POST['password_confirm'])
		throw new Exception('Passwords don\'t match', 400);
	unset($_POST['password_confirm']);

	foreach($_POST as $key => $value)
		if(in_array($key, $valid_fields))
			$stream->iq->query->addChild($key, $value);

	$f = fsockopen(XMPP_CONN, XMPP_PORT);
	if(!$f) throw new Exception('Could not connect to the XMPP server', 500);
	fwrite($f, $stream->asXML()); unset($stream);
	$response = stream_get_contents($f);
	if(!$response) throw new Exception('Could not communicate with the XMPP server', 500);
	fclose($f); unset($f);
	$response = simplexml_load_string($response);
	if(!$response) throw new Exception('The XMPP server sent an invalid response', 500);
	if($stream_error = $response->xpath('/stream:stream/stream:error')) {
		list($stream_error) = $stream_error;
		list($cond) = $stream_error->children();
		throw new Exception($stream_error->text ? $stream_error->text : $cond->getName(), 500);
	}
	$iq = $response->iq;
	if($iq->error) {
		list($cond) = $iq->error->children();
		throw new Exception($iq->error->text ? $iq->error->text : $cond->getName(), 400);
	}
	if($iq = $response->iq and $iq->attributes()->type == 'result') {
		header('HTTP/1.1 201 Created');
		header(sprintf('Location: xmpp://%s@%s', $_POST['username'], XMPP_HOST));
		header('Content-Type: text/plain; charset=utf-8');
		printf("Account xmpp:%s@%s created.\n", $_POST['username'], XMPP_HOST);
	} else throw new Exception('Neither error nor sucesss', 500);
} catch(Exception $e) {
	header(sprintf('HTTP/1.1 %d %s', $e->getCode(), $e->getMessage()));
	header('Content-Type: text/plain; charset=utf-8');
	echo $e->getMessage(),"\n";
}

