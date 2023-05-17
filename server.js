import express from 'express';
import path from 'path';
import spawn from 'child_process';
import fs from 'fs';
import Audic from 'audic';
import { exec } from 'child_process';

const SERVER_PORT = process.env.PORT ?? 4000;
const MUSIC_FOLDER = path.join('musics');
const MUSIC_DL_FOLDER = path.join('musics-tmp');
const MUSIC_TITLES_FILE = path.join(MUSIC_FOLDER, 'titles.txt');
const LOG_FILE = path.join('log.txt');
const KEEP_ALIVE_TIMEOUT = 1000 * 6; // 6s grace period before stopping the server, the client's host sends requests every 5s at most

// #region prepare_server
const app = express();

function isRequestFromHost(req) {
    return req.ip == '::1' || req.ip == '::ffff:' || req.ip == '::ffff:127.0.0.1';
}

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.get('/get-playing-musics',     responseWithJson(entry_getPlayingMusics));
app.get('/get-music-propositions', responseWithJson(entry_getMusicPropositions));
app.post('/add-music',             responseWithJson(entry_addMusicToQueue));
app.post('/remove-music',          responseWithJson(entry_removeMusicFromQueue, true));
app.post('/pause-music',           responseWithJson(entry_pauseMusic, true));
app.post('/play-now',              responseWithJson(entry_playNowMusic, true));
app.post('/set-volume',            responseWithJson(entry_setVolume, true));
app.post('/shuffle-queue',         responseWithJson(entry_shuffleQueue, true));
app.get('/', (req, res) => {
    res.sendFile(path.resolve(path.join('public', isRequestFromHost(req) ? 'index_host.html' : 'index.html')));
});
app.use((_req, res) => {
    res.status(404).sendFile(path.resolve(path.join('public', '404.html')));
});
// #endregion

// #region prepare_files
fs.mkdirSync(MUSIC_FOLDER, { recursive: true });
fs.rmSync(MUSIC_DL_FOLDER, { recursive: true });
fs.mkdirSync(MUSIC_DL_FOLDER, { recursive: true });
// #endregion

// #region music_logic

function responseWithJson(responder, needsAuth=false) {
    return async (req, res) => {
        let fromHost = isRequestFromHost(req);
        if(fromHost) {
            keepAliveLatestTimestamp = Date.now();
        }
        if(needsAuth && !fromHost)
            return res.status(401).json({ error: 'Unauthorized' });
        try {
            let body = { ...req.body, ...req.params, ...req.query };
            let response = await responder(body, req) ?? {};
            res.json(response);
        } catch (e) {
            res.status(400).json({ error: e.message });
        }
    };
}

function logInfo(...args) {
    let time = new Date().toLocaleTimeString();
    console.log(`[${time}] INFO_ `, ...args);
    fs.appendFileSync(LOG_FILE, `[${time}] INFO_ ${args.join(' ')}\n`);
}

function logError(...args) {
    let time = new Date().toLocaleTimeString();
    console.error(`[${time}] ERROR `, ...args);
    fs.appendFileSync(LOG_FILE, `[${time}] ERROR ${args.join(' ')}\n`);
}

const MUSICS_QUEUE = [ ];
const ACTIVE_FETCHES = [];
const audio = new Audic();
let currentlyPlayingId = undefined;
let paused = false;
let keepAliveLatestTimestamp = Date.now();

audio.addEventListener('ended', () => {
    logInfo('music ended');
    currentlyPlayingId = undefined;
    MUSICS_QUEUE.shift();
    playNextAvailableMusic(); // TODO find out why 'ended' is never called
});
// auto play on music loaded (when changing audio.src there is a delay before audio.play() does anything)
audio.addEventListener('canplay', () => audio.play());

function sanitizeMusicInput(music) {
    if(typeof music !== 'object' ||
        typeof music.title !== 'string' ||
        typeof music.duration !== 'number' ||
        typeof music.id !== 'string')
        throw new Error('Invalid music input');
}

function entry_getPlayingMusics() {
    return MUSICS_QUEUE;
}

async function entry_getMusicPropositions({ query }, req) {
    let cmdResults = await execYtDlp([ `ytsearch10:${query}`, '--print', '{"id":%(id)j,"title":%(title)j,"duration":%(duration)j}', '--flat-playlist' ], req);
    let searchResults = cmdResults
        .split('\n')
        .filter(line => line.length > 0)
        .map(JSON.parse);
    return searchResults;
}

function entry_addMusicToQueue({ music }, req) {
    sanitizeMusicInput(music);
    logInfo(req.ip, 'added', music.title);
    downloadMusicFile(music);
    music.loaded = doesMusicFileExist(music.id);
    MUSICS_QUEUE.push(music);
    if(currentlyPlayingId === undefined && !paused)
        playNextAvailableMusic();
}

function entry_removeMusicFromQueue({ music }) {
    sanitizeMusicInput(music);
    let index = MUSICS_QUEUE.findIndex(m => m.title === music.title);
    if(index === -1) throw new Error('Music not found');
    logInfo('host removed', music.title);
    MUSICS_QUEUE.splice(index, 1);
    if(currentlyPlayingId == music.id) {
        audio.pause();
        currentlyPlayingId = undefined;
        playNextAvailableMusic();
    }
}

function entry_pauseMusic() {
    if(paused) {
        logInfo('host resumed');
        audio.play();
    } else {
        logInfo('host paused');
        audio.pause();
    }
    paused = !paused;
}

function entry_setVolume({ volume }) {
    if(typeof volume !== 'number' || volume < 0 || volume > 1)
        throw new Error('Invalid volume');
    audio.volume = volume;
}

function entry_playNowMusic({ music }) {
    sanitizeMusicInput(music);
    let index = MUSICS_QUEUE.findIndex(m => m.title === music.title);
    music = MUSICS_QUEUE[index];
    if(index === -1) throw new Error('Music not found');
    if(!music.loaded) throw new Error('Music not loaded yet!');
    MUSICS_QUEUE.splice(index, 1);
    MUSICS_QUEUE.unshift(music);
    setPlayingMusic(music);
    paused = false;
    logInfo('host played now', music.title);
}

function entry_shuffleQueue() {
    let newQueue = [];
    newQueue.push(MUSICS_QUEUE.shift()); // keep playing music
    while(MUSICS_QUEUE.length > 0)
        newQueue.concat(MUSICS_QUEUE.splice(Math.floor(Math.random() * MUSICS_QUEUE.length), 1));
    MUSICS_QUEUE.concat(newQueue);
    logInfo('host shuffled queue');
}

async function downloadMusicFile(music) {
    let { id, title } = music;
    let outputFile = `${MUSIC_FOLDER}/${id}.webm`;
    let tempFile = `${MUSIC_DL_FOLDER}/${id}.webm`;
    if(ACTIVE_FETCHES.includes(id)) return;
    if(fs.existsSync(outputFile)) return;

    logInfo('downloading', id);
    ACTIVE_FETCHES.push(id);
    try {
        await execYtDlp([ `https://www.youtube.com/watch?v=${id}`, '-f', 'bestaudio', '-o', tempFile ]);
        fs.renameSync(tempFile, outputFile);
        logInfo('finished downloading', title, 'as', id);
        fs.appendFileSync(MUSIC_TITLES_FILE, `${id} ${title}\n`);
        let pending = MUSICS_QUEUE.find(m => m.id === id);
        if(pending) pending.loaded = true;
        if(currentlyPlayingId === undefined)
            playNextAvailableMusic();
    } catch (e) {
        logError('could not download ', title, 'as', id, ':', e);
        MUSICS_QUEUE.splice(MUSICS_QUEUE.findIndex(m => m.id === id), 1);
        showBalloonTip(`Could not download ${title} as ${id}: ${e}`, 'Error');
    }

    ACTIVE_FETCHES.splice(ACTIVE_FETCHES.indexOf(id), 1);
}

function doesMusicFileExist(id) {
    return fs.existsSync(`${MUSIC_FOLDER}/${id}.webm`);
}

function setPlayingMusic(music) {
    audio.src = `${MUSIC_FOLDER}/${music.id}.webm`;
    audio.play();
    currentlyPlayingId = music.id;
    showBalloonTip(`${music.title}\n${music.id} (${music.duration}s)`);
}

function showBalloonTip(message, icon='Info') {
    exec(`
        [system.Reflection.Assembly]::LoadWithPartialName('System.Windows.Forms') | Out-Null
        $Global:Balloon = New-Object System.Windows.Forms.NotifyIcon
        $Balloon.Icon = [System.Drawing.Icon]::ExtractAssociatedIcon((Get-Process -id $pid | Select-Object -ExpandProperty Path))
        $Balloon.BalloonTipIcon = '${icon}'
        $Balloon.BalloonTipText = '${message}'
        $Balloon.BalloonTipTitle = 'Now Playing' 
        $Balloon.Visible = $true
        $Balloon.ShowBalloonTip(500)
    `, { shell: 'powershell.exe' });
    
}

function playNextAvailableMusic() {
    let firstAvailableMusic = MUSICS_QUEUE.find(m => m.loaded);
    if(!firstAvailableMusic) {
        logInfo('no music available, pausing');
        return;
    }
    logInfo('following with', firstAvailableMusic.title);
    if(MUSICS_QUEUE[0] != firstAvailableMusic) {
        MUSICS_QUEUE.splice(MUSICS_QUEUE.indexOf(firstAvailableMusic), 1);
        MUSICS_QUEUE.unshift(firstAvailableMusic);
    }
    setPlayingMusic(firstAvailableMusic);
}

function execYtDlp(command, associatedRequest=null) {
    logInfo((associatedRequest==null?'executing':associatedRequest.ip+' executed'), command.join(' '));
    return new Promise((resolve, reject) => {
        let stdout = '';
        let stderr = '';
        let ytDlp = spawn.spawn('yt-dlp.exe', command);
        ytDlp.stdout.on('data', chunk => stdout += chunk);
        ytDlp.stderr.on('data', chunk => stderr += chunk);
        ytDlp.on('exit', () => { if(stderr) logError('ytdlp error:', stderr); });
        ytDlp.on('close', () => resolve(stdout));
        ytDlp.on('error', reject);
    });
}

// #endregion

// #region kickstart
fs.appendFileSync(LOG_FILE, `[START] ${new Date().toLocaleString()}\n`);

const server = app.listen(SERVER_PORT, () => {
    logInfo(`Running on http://localhost:${SERVER_PORT} ...`);
});

exec('start http://localhost:4000');

setTimeout(() => {
    let keepAliveInterval = setInterval(() => {
        if(Date.now() - keepAliveLatestTimestamp > KEEP_ALIVE_TIMEOUT) {
            logInfo('ping timeout, exiting');
            audio.destroy();
            server.close();
            clearInterval(keepAliveInterval);
        }
    }, KEEP_ALIVE_TIMEOUT);
}, 5000); // wait for 5 seconds before starting keep alive

// #endregion
