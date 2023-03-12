The server must host a standard web server, responding to requests on `/` with the client application (html page). If the client querying the server is the same as the one that is hosting the server (same ip) the client application will include host controls.

Client requests
---------------
In the following requests, a "music" is a json object using this format:
```json
{
    "title": "string",
    "duration": "number", // in seconds
    "url": "string", // youtube watch link (https://www.youtube.com/watch?v=...)
}
```

On `/get-playing-musics` (GET):\
Returns the list of musics currently playing on the server.

On `/get-music-propositions?query={query}` (GET):\
Returns a list of musics supplied by youtube's search API, filtered by the query.
The query is a simple string, it might be a music title or a youtube watch link for example.

On `/music-action` (POST):\
Takes a JSON object, its `action` field dictates the action to perform:
- `add`: Takes a music. Adds the music to the queue. No response.
- `pause` (host only): Takes nothing. Pauses the currently playing music, or resumes it if it was paused. No response.
- `remove` (host only): Takes a music. Removes the music from the queue. No response.
- `play-now` (host only): Takes a music. Brings the music to the top of the queue and starts playing it immediately, the currently playing music is discarded if there was one. No response.
