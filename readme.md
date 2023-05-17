# Musik-fet

TODO: readme

## Installation

Have [nodejs](https://nodejs.org/en/) installed, clone the repository and run `npm install` in the repository folder. Also download a [yt-dlp](https://github.com/yt-dlp/yt-dlp) binary and put it in the repository folder.

This project was written with windows in mind, there should not be much that could break on other OSes but it is not tested.

To run, simply execute `npm run start`. By default the server listens on port 4000.

## Client/Server applications

The client application is a single html page, it is generated from `web-client.php` into two different pages, one for the *host* and one all other clients. The host is the computer on which the server is running.

The server application is the JS script that runs the server and handles both the audio player and audio fetcher, see `server.js`.

In case something goes wrong in the server, seek the reason in the log file, there will be a "cannot connect to server" error in the client.

## Client-server communication

The server must host a standard web server, responding to requests on `/` with the client application (html page). If the client querying the server is the same as the one that is hosting the server (same ip) the client application will include host controls.

In the following requests, a "music" is a json object using this format:
```json
{
  "title":    "string",
  "duration": "number", // in seconds
  "id":       "string", // youtube video id
}
```
The client must only send musics that were given by the server.

API entry points
----------------
As a client:
- (get ) on /get-playing-musics - no body - returns `music[]`
- (get ) on /get-music-propositions - `{ query: string }` - returns `music[]`
- (post) on /add-music - `{ music: music }` - no body
As the host:
- (post) on /remove-music - `{ music: music }` - no body
- (post) on /pause-music - no body - no body
- (post) on /play-now - `{ music: music }` - no body
- (post) on /set-volume - `{ volume: number }` - no body
- (post) on /shuffle-queue - no body - no body

### Keep-alive

To close the app automatically, a keep-alive system is implemented. It is refreshed when the host sends a request. If the web page is closed the keep-alive will expire and the server will close itself.

## TODO
- (post) on /load-playlist - `{ playlist: string /* yt playlist url */ }` - no body
- limit accepted musics to 5min ?
- limit the number of simultaneous donwloads ?
