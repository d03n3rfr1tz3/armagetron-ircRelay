#!/usr/bin/php
<?php

$config = array();
$config["debug"] = 1;
$config["ircAddress"] = "";
$config["ircPort"] = 6667;
$config["ircChannel"] = "";
$config["ircNick"] = "";
$config["ircPass"] = "";
$config["ircAuthType"] = "";
$config["ircAuthPass"] = "";
$config["ircIgnore"] = array();

$sock = false;
$pingCount = 0;
$retryCount = 0;

$playerColors = array();
$rgbColors = array(
	"gray" => array(5, 5, 5),
	"blue" => array(1, 1, 15),
	"green" => array(1, 15, 1),
	"red" => array(15, 1, 1),
	"brown" => array(12, 8, 0),
	"purple" => array(8, 0, 12),
	"orange" => array(15, 10, 0),
	"yellow" => array(15, 15, 0),
	"lime" => array(0, 15, 10),
	"cyan" => array(0, 15, 15),
	"pink" => array(15, 0, 15)
);
$ircColors = array(
	"gray" => "\003" . "14",
	"blue" => "\003" . "02",
	"green" => "\003" . "03",
	"red" => "\003" . "04",
	"brown" => "\003" . "05",
	"purple" => "\003" . "06",
	"orange" => "\003" . "07",
	"yellow" => "\003" . "08",
	"lime" => "\003" . "09",
	"cyan" => "\003" . "11",
	"pink" => "\003" . "13"
);

function Start()
{
	global $sock, $config;
	if ($sock != false) return;
	if (empty($config["ircAddress"])) return;
	if (empty($config["ircChannel"])) return;
	if (empty($config["ircNick"])) return;

	// prepare connection
	$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($sock == false) {
		Debug("Connection to irc server " . $config["ircAddress"] . " failed, because creating the socket failed", 1);
		return;
	}

	// connect to server
	$address = $config["ircAddress"];
	if (!preg_match("/[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}/", $address)) $address = gethostbyname($address);
	if (!socket_connect($sock, $address, $config["ircPort"])) {
		Debug("Connection to irc server " . $config["ircAddress"] . " (" . $address . ") failed", 1);
		$sock = false;
		return;
	}
	Debug("Connecting to irc server " . $config["ircAddress"] . " (" . $address . ")", 1);
	socket_set_nonblock($sock);
	usleep(10000);

	// receive something before sending
	Receive("");

	// send pass
	if (!empty($config["ircPass"])) {
		Send("PASS " . $config["ircPass"], 3);
	}

	// send nick
	Send("NICK " . $config["ircNick"], 3);

	// send user
	Send("USER ircrelay 0 * :Armagetron ircRelay", 3);

	// receive until PING
	Receive("PING");

	// give the server an additional second
	sleep(1);

	// send auth
	if ($config["ircAuthType"] == "AuthServ") {
		Send("PRIVMSG AuthServ :AUTH " . $config["ircNick"] . " " . $config["ircAuthPass"], 3);
		Receive("");
	}
	if ($config["ircAuthType"] == "NickServ") {
		Send("PRIVMSG NickServ :IDENTIFY " . $config["ircAuthPass"], 3);
		Receive("");
	}
	if ($config["ircAuthType"] == "Q") {
		Send("PRIVMSG Q@CServe.quakenet.org :AUTH " . $config["ircNick"] . " " . $config["ircAuthPass"], 3);
		Receive("");
	}

	// send join
	Send("JOIN #" . $config["ircChannel"], 3);
}

function Stop()
{
	global $sock;

	socket_close($sock);
	$sock = false;
}

function Event($line)
{
	global $config, $playerColors;
	$matches = array();
	Debug($line, 4);

	// This event is called for each chat message the server receives.
	if (preg_match("/^CHAT ([^ ]+) (\/me )?(.+)$/", $line, $matches)) {
		#CHAT <chatter> [/me] <chat string>
		$player = count($matches) > 1 ? $matches[1] : null;
		$action = count($matches) > 2 ? $matches[2] == "/me " : false;
		$message = count($matches) > 2 ? $matches[3] : null;
		if ($player == null || $message == null || $player == "Admin") return;

		$color = Color($player);
		if ($action) {
			$subtotal = $color . $player . " " . $message;
			$total = "PRIVMSG #" . $config["ircChannel"] . " :\001" . "ACTION " . $subtotal . "\001";
			Send($total, 3);
		} else {
			$subtotal = $color . $player . ": " . "\017" . $message;
			$total = "PRIVMSG #" . $config["ircChannel"] . " :" . $subtotal;
			Send($total, 3);
		}
	}

	// This event is called periodically to list players currently online
	if (preg_match("/^ONLINE_PLAYER ([^ ]+) ([^ ]+) ([0-9]+) ([0-9]+) ([0-9]+) ([^ ]+) ([^ ]+)( [^ ]+)?( [^ ]+)?$/", $line, $matches)) {
		#ONLINE_PLAYER <name> <id> <r> <g> <b> <access_level> <did_login> [<ping> <team>]
		$player = count($matches) > 1 ? $matches[1] : null;
		$playerID = count($matches) > 2 ? $matches[2] : null;
		$red = count($matches) > 3 ? $matches[3] : null;
		$green = count($matches) > 4 ? $matches[4] : null;
		$blue = count($matches) > 5 ? $matches[5] : null;
		$access = count($matches) > 6 ? $matches[6] : null;
		$didLogin = count($matches) > 7 ? $matches[7] : null;
		$ping = count($matches) > 8 ? trim($matches[8]) : null;
		$team = count($matches) > 9 ? trim($matches[9]) : null;
		if ($player == null || $red === null || $green === null || $blue === null) return;

		$playerColors[$player] = array($red, $green, $blue);
	}

	// This event is called each time a player joins the game
	if (preg_match("/^PLAYER_ENTERED(_GRID|_SPECTATOR) ([^ ]+) ([^ ]+) ([^ ]+)$/", $line, $matches)) {
		#PLAYER_ENTERED_GRID <name> <IP> <screen name>
		#PLAYER_ENTERED_SPECTATOR <name> <IP> <screen name>
		$type = count($matches) > 1 ? $matches[1] : null;
		$player = count($matches) > 2 ? $matches[2] : null;
		$playerIP = count($matches) > 3 ? $matches[3] : null;
		$screenName = count($matches) > 4 ? $matches[4] : null;
		if ($player == null) return;

		$color = Color($player);
		$type = $type == "_GRID" ? "the grid" : "as spectator";

		$subtotal = $color . $player . "\017" . " joined " . $type . " from " . $playerIP;
		$total = "PRIVMSG #" . $config["ircChannel"] . " :" . $subtotal;
		Send($total, 3);
	}

	// This event is called each time a player leaves a game
	if (preg_match("/^PLAYER_LEFT ([^ ]+) ([^ ]+) ([^ ]+)$/", $line, $matches)) {
		#PLAYER_LEFT <name> <IP> <screen name>
		$player = count($matches) > 1 ? $matches[1] : null;
		$playerIP = count($matches) > 2 ? $matches[2] : null;
		$screenName = count($matches) > 3 ? $matches[3] : null;
		if ($player == null) return;

		$color = Color($player);

		$subtotal = $color . $player . "\017" . " left the game";
		$total = "PRIVMSG #" . $config["ircChannel"] . " :" . $subtotal;
		Send($total, 3);
	}

	// This event is called each time a player changes the name
	if (preg_match("/^PLAYER_RENAMED ([^ ]+) ([^ ]+) ([^ ]+) ([^ ]+)$/", $line, $matches)) {
		#PLAYER_RENAMED <old name> <new name> <ip> <screen name>
		$playerOld = count($matches) > 1 ? $matches[1] : null;
		$playerNew = count($matches) > 2 ? $matches[2] : null;
		$playerIP = count($matches) > 3 ? $matches[3] : null;
		$screenName = count($matches) > 4 ? $matches[4] : null;

		$color = Color($playerOld);
		$playerColors[$playerNew] = $playerColors[$playerOld];
		$playerColors[$playerOld] = null;

		$subtotal = $color . $playerOld . "\017" . " renamed to " . $color . $playerNew;
		$total = "PRIVMSG #" . $config["ircChannel"] . " :" . $subtotal;
		Send($total, 3);
	}
}

function Receive($until = null)
{
	global $config, $pingCount;

	$found = false;
	while (!$found) {
		$output = Read();
		if (!$output && $until === null) return;
		if (!$output && $until !== null) continue;

		$lines = explode("\r\n", $output);
		foreach ($lines as $line) {
			if (empty($line)) continue;
			Debug($line, 4);

			// passing messages from the channel to Armagetron
			if (preg_match("/ PRIVMSG /", $line)) {
				// get username
				$startpos = strpos($line, ":", 0);
				$endpos = strpos($line, "!", 0);
				$username = substr($line, $startpos + 1, $endpos - 1);

				// get message
				$startpos = strpos($line, ":", $endpos);
				$message = substr($line, $startpos + 1);

				// send the IRC message into the BZFlag chat
				if (!in_array($username, $config["ircIgnore"], true)) {
					if (strlen($message) > 8 && substr($message, 0, 7) == "\001" . "ACTION") {
						$total = "0xffffff" . $username . " 0xffff7f" . substr($message, 8);
						echo "CONSOLE_MESSAGE * " . substr($total, 0, strlen($total) - 1) . " * \n";
						Debug($total, 4);
					} else {
						$total = "0xffffff" . $username . ": 0xffff7f" . $message;
						echo "CONSOLE_MESSAGE " . $total . "\n";
						Debug($total, 4);
					}
				} else {
					Debug("Message from " . $username . " got ignored", 4);
				}
			}

			// respond to pings
			if (preg_match("/^PING /", $line)) {
				$pongdata = substr($line, 5);
				$pong = "PONG " . $pongdata;
				Send($pong, 4);
				$pingCount++;
			}

			if ($until == "" || substr($line, 0, strlen($until)) == $until) {
				$found = true;
			}
		}
	}
}

function Read()
{
	global $sock;

	$buffer = '';
	$done = false;
	while (!$done) {
		$chunk = socket_read($sock, 1024);
		if ($chunk === false) {
			$error = socket_last_error($sock);
			if ($error != 11 && $error != 115) {
				Debug(socket_strerror($error), $error, 1);
				Stop();
				$done = true;
			}
			break;
		} elseif ($chunk == '') {
			$done = true;
			break;
		} else {
			$buffer .= $chunk;
		}
	};
	return $buffer;
}

function Send($data, $debug)
{
	global $sock;
	Debug($data, $debug);

	$line = $data . "\r\n";
	$result = socket_write($sock, $line, strlen($line));
	if ($result === false) {
		$error = socket_last_error($sock);
		if ($error != 11 && $error != 115) {
			Debug(socket_strerror($error), $error, 1);
			Stop();
		}
	}
}

function Debug($message, $debug)
{
	global $config;
	if ($config["debug"] < $debug) return;

	$line = "[" . date("Y-m-d H:i:s") . "] " . $message . "\n";
	if (!file_put_contents(dirname(__FILE__) . "/ircRelay.log", $line, FILE_APPEND))
		throw new \ErrorException($message);
}

function Color($player)
{
	global $playerColors, $rgbColors, $ircColors;

	if (!array_key_exists($player, $playerColors)) return "\017";
	$playerColor = $playerColors[$player];

	// determine the nearest color
	$color = "black";
	$minDistance = null;
	foreach ($rgbColors as $colorName => $rgbColor) {
		$colorDistance = ColorDistance($rgbColor, $playerColor);
		if ($minDistance === null || $colorDistance < $minDistance) {
			$minDistance = $colorDistance;
			$color = $colorName;
		}
	}

	if (!array_key_exists($color, $ircColors)) return "\017";
	return $ircColors[$color];
}

function ColorDistance($color1, $color2)
{
	$delta_r = $color1[0] - $color2[0];
	$delta_g = $color1[1] - $color2[1];
	$delta_b = $color1[2] - $color2[2];
	return $delta_r * $delta_r + $delta_g * $delta_g + $delta_b * $delta_b;
}

stream_set_blocking(STDIN, 0);
while (!feof(STDIN)) {
	usleep(10000);

	// start if not already done
	if (!$sock) {
		// wait longer with every attempt
		$sleep = 5;
		if ($pingCount > 5) {
			$pingCount = 0;
			$retryCount = 0;
		}
		for ($i = 0; $i < $retryCount; $i++) {
			$sleep = $sleep * 2;
		}
		sleep($sleep);
		$retryCount++;

		// start now
		Start();
		continue;
	}

	// receive Armagetron events
	$line = rtrim(fgets(STDIN, 1024));
	if (!empty($line)) Event($line);

	// receive IRC messages
	Receive(null);
}
Stop();
?>
