# IRC Relay

[![Compile Plugin](https://github.com/d03n3rfr1tz3/armagetron-ircRelay/actions/workflows/build.yml/badge.svg)](https://github.com/d03n3rfr1tz3/armagetron-ircRelay/actions/workflows/build.yml)
![Minimum Armagetron Version](https://img.shields.io/badge/Armagetron-v0.2.9+-blue.svg)
[![License](https://img.shields.io/github/license/d03n3rfr1tz3/armagetron-ircRelay.svg)](LICENSE.md)

This plugin is a clone of the IRC Relay plugin for BZFlag I (partially) made.
It should work with version Armagetron 0.2.9 sty+ct+ap.

## Requirements

You need the sty+ct+ap version of your Armagetron server, which you can find here: \
https://code.launchpad.net/~armagetronad-ap/armagetronad/0.2.9-armagetronad-sty+ct+ap

## Usage

### Loading the plug-in

Drop the `ircRelay.cfg` in your settings folder and load it with `INCLUDE ircRelay.cfg` in your `server_info.cfg`.

Also drop the `ircRelay.php` script into your scripts folder. You might want to create a file named `ircRelay.log`
right next to it and give write permissions, if you want some sort of logging.

### Configuration

The configuration is at the start of the `ircRelay.php` file. Just fill out the variables and you are ready.
The value for `debug` can be between 0 and 4, depending on how much information you want.

| Name | Type | Default | Description |
| ---- | ---- | ------- | ----------- |
| `ircAddress` | string |  | Required. The IP address of your IRC server. |
| `ircChannel` | string |  | Required. The channel your IRC Relay should join. |
| `ircNick` | string |  | Required. The nickname your IRC Relay should use. |
| `ircPass` | string |  | Optional. The password for the IRC server. |
| `ircAuthType` | string |  | Optional. The authentication type of the IRC server. Choose one: `AuthServ`, `NickServ` or `Q`. |
| `ircAuthPass` | string |  | Optional. The authentication password for the IRC server. |
| `ircIgnore` | string |  | Optional. Array of strings of ignored IRC users. Messages from these users will not be passed into the Armagetron chat. |

## License

[LICENSE](LICENSE.md)
