<?php

/*
 * Writes Tests/vectors.json: the tickets every verifier is checked against,
 * signed by Ticket\TicketSigner. "sign" holds payloads, their JSON as
 * json_encode() writes it and the ticket expected; "verify" holds tickets,
 * the secret and room they are checked with, and whether they hold (with the
 * payload when they do). Valid tickets expire in 2100 (4102444800), expired
 * ones expired in 2001 (1000000000): the outcome is the same on any clock
 * between the two. Run: php bin/vectors.php
 */

use Omniplay\Ticket\TicketSigner;

require __DIR__.'/../vendor/autoload.php';

const SECRET = 's3cr3t-shared-with-the-servers';
const LATER = 4102444800;
const PAST = 1000000000;

$signed = [
    ['a payload of three claims, with the secret "secret" (the first vector of the format, as written in 2026)', ['uid' => 2, 'room' => 'lobby', 'exp' => 2000000000], 'secret'],
    ['a player\'s seat on a server', ['sub' => 'player-42', 'name' => 'Ada', 'room' => 'eu-1-a3f9', 'exp' => LATER], SECRET],
    ['slashes and characters past ASCII, escaped as json_encode() escapes them', ['sub' => 'p/7', 'name' => 'Zoë 🎮', 'url' => 'https://example.org/a/b', 'room' => 'lobby', 'exp' => LATER], SECRET],
    ['a server\'s heartbeat token: its room is "heartbeat:" and the server\'s id (Heartbeats::room())', ['room' => 'heartbeat:eu-1-a3f9', 'exp' => LATER], SECRET],
    ['nested claims: a list, an object, a boolean, a null', ['sub' => 'p-1', 'roles' => ['host', 'player'], 'team' => ['id' => 3, 'color' => 'red'], 'ok' => true, 'none' => null, 'room' => 'lobby', 'exp' => LATER], SECRET],
];
$sign = [];
foreach ($signed as [$name, $payload, $secret]) {
    $sign[] = ['name' => $name, 'payload' => $payload, 'secret' => $secret, 'json' => json_encode($payload), 'ticket' => TicketSigner::sign($payload, $secret)];
}

$seat = $sign[1]['ticket'];
[$body, $mac] = explode('.', $seat);
$forged = rtrim(strtr(base64_encode((string) json_encode(['sub' => 'player-1', 'name' => 'Ada', 'room' => 'eu-1-a3f9', 'exp' => LATER])), '+/', '-_'), '=');
$flipped = substr($mac, 0, -1).('0' === substr($mac, -1) ? '1' : '0');
$raw = static fn (string $json): string => TicketSigner::signJson($json, SECRET);
$notBase64 = 'not*base64';

$verify = [
    ['a player\'s seat, for its room', $seat, SECRET, 'eu-1-a3f9', $sign[1]['payload']],
    ['slashes and characters past ASCII: the payload read back as it was', $sign[2]['ticket'], SECRET, 'lobby', $sign[2]['payload']],
    ['nested claims read back', $sign[4]['ticket'], SECRET, 'lobby', $sign[4]['payload']],
    ['a server\'s heartbeat token, for its heartbeat room', $sign[3]['ticket'], SECRET, 'heartbeat:eu-1-a3f9', $sign[3]['payload']],
    ['a player\'s ticket presented as the server\'s heartbeat: another room', $seat, SECRET, 'heartbeat:eu-1-a3f9', null],
    ['an expiry with a fraction: its integer part is compared', $raw('{"room":"lobby","exp":4102444800.5}'), SECRET, 'lobby', ['room' => 'lobby', 'exp' => 4102444800.5]],
    ['expired', TicketSigner::sign(['sub' => 'player-42', 'room' => 'lobby', 'exp' => PAST], SECRET), SECRET, 'lobby', null],
    ['another room', $seat, SECRET, 'eu-1-b000', null],
    ['another secret', $seat, 'another-secret', 'eu-1-a3f9', null],
    ['an empty secret verifies nothing', TicketSigner::signJson('{"room":"lobby","exp":4102444800}', 'x'), '', 'lobby', null],
    ['the payload changed, the signature kept', $forged.'.'.$mac, SECRET, 'eu-1-a3f9', null],
    ['the signature\'s last digit changed', $body.'.'.$flipped, SECRET, 'eu-1-a3f9', null],
    ['the signature in capitals: hex is lower case', $body.'.'.strtoupper($mac), SECRET, 'eu-1-a3f9', null],
    ['no signature after the dot', $body.'.', SECRET, 'eu-1-a3f9', null],
    ['no dot', $body, SECRET, 'eu-1-a3f9', null],
    ['an extra dot: the signature is what follows the last one', $seat.'.x', SECRET, 'eu-1-a3f9', null],
    ['no expiry', $raw('{"room":"lobby"}'), SECRET, 'lobby', null],
    ['an expiry that is a boolean', $raw('{"room":"lobby","exp":true}'), SECRET, 'lobby', null],
    ['a room that is a number, asked as a string', $raw('{"room":5,"exp":4102444800}'), SECRET, '5', null],
    ['no room', $raw('{"exp":4102444800}'), SECRET, 'lobby', null],
    ['a payload that is a list', $raw('["lobby",4102444800]'), SECRET, 'lobby', null],
    ['a signed body that is not base64url JSON', $notBase64.'.'.hash_hmac('sha256', $notBase64, SECRET), SECRET, 'lobby', null],
];
foreach ($verify as $i => [$name, $ticket, $secret, $room, $payload]) {
    $verify[$i] = ['name' => $name, 'ticket' => $ticket, 'secret' => $secret, 'room' => $room, 'valid' => null !== $payload, 'payload' => $payload];
}

$vectors = [
    'format' => 'base64url(json payload) "." hex(hmac_sha256(that base64url, secret)); the payload names "room" (a string) and "exp" (Unix seconds)',
    'clock' => 'valid tickets expire at 4102444800 (2100-01-01), expired ones at 1000000000 (2001-09-09): verify against the real clock',
    'sign' => $sign,
    'verify' => $verify,
];
file_put_contents(__DIR__.'/../Tests/vectors.json', json_encode($vectors, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION)."\n");
echo count($sign), ' signed, ', count($verify), " verified\n";
