<?php

const IS_HOST = true;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href="index.css" rel="stylesheet">
</head>
<body>
    <header>
        Musikfet
        <?php if(IS_HOST): ?>
        <br>- Host -
        <?php endif; ?>
    </header>

    <div id="content">
        <?php if(IS_HOST): ?>
        <div>
            <button>Charger une playlist</button>
            <button>Mettre en pause</button>
        </div>
        <?php endif; ?>
        <div id="musics-list">
        </div>
        <div id="no-music-playing-info">
            Aucune musique en cours :'(
        </div>
        <div id="add-music-box">
            <span>Rajouter une musique :</span>
            <input id="add-music-input" type="text" autocomplete="off">
        </div>
        <div id="music-propositions-list">
        </div>
    </div>
    
    <template id="music-template">
        <div>
            <span class="t-playing-icon">
                <div class="music-bar-anim"></div>
                <div class="music-bar-anim"></div>
                <div class="music-bar-anim"></div>
            </span>
            <span class="t-delay">+3min</span>
            <a class="t-title" href="youtube.com" target="_blank">Title</a>
            <span>
                <?php if(IS_HOST): ?>
                <span class="t-play-now-btn">&gt;</span>
                &nbsp;
                <span class="t-remove-btn">X</span>
                <?php endif; ?>
            </span>
        </div>
    </template>
    <template id="music-proposition-template">
        <div>
            <span class="t-add-btn">Ajouter</span>
            <span class="t-title">Title</span>
        </div>
    </template>
</body>
<script>

const musicsList = document.getElementById("musics-list");
const musicPropositionsList = document.getElementById("music-propositions-list");
const musicTemplate = document.getElementById("music-template");
const musicPropositionTemplate = document.getElementById("music-proposition-template");
const addMusicInput = document.getElementById("add-music-input");
const noMusicPlayingInfo = document.getElementById("no-music-playing-info");

var isRequestPending = false;

var currentMusics = [0];

function rebuildMusicsList(nextMusics) {
    musicsList.innerHTML = '';
    noMusicPlayingInfo.hidden = nextMusics.length != 0;
    let delay = 0;
    for(let i = 0; i < nextMusics.length; i++) {
        const music = nextMusics[i];
        const musicElement = document.importNode(musicTemplate.content, true).firstElementChild;
        if(delay != 0) {
            musicElement.querySelector(".t-delay").textContent = `+${Math.ceil(delay/60)}min`;
            musicElement.querySelector(".t-playing-icon").remove();
        } else {
            musicElement.querySelector(".t-delay").remove();
        }
        musicElement.querySelector(".t-title").textContent = music.title;
        musicElement.querySelector(".t-title").href = music.url;
        musicElement.querySelector(".t-remove-btn")?.addEventListener("click", () => sendRemoveMusic(music));
        if(i != 0)
            musicElement.querySelector(".t-play-now-btn")?.addEventListener("click", () => sendPlayNowMusic(music));
        else
            musicElement.querySelector(".t-play-now-btn")?.remove();
        musicsList.append(...musicElement.childNodes);
        delay += music.duration;
    }
}

function rebuildMusicPropositions(propositions) {
    musicPropositionsList.innerHTML = '';
    for(let music of propositions) {
        const musicElement = document.importNode(musicPropositionTemplate.content, true).firstElementChild;
        musicElement.querySelector(".t-title").textContent = music.title;
        musicElement.querySelector(".t-add-btn").addEventListener("click", () => sendAddMusic(music));
        musicPropositionsList.append(...musicElement.childNodes);
    }
}

async function reloadPlayingMusics() {
    let oldMusisc = currentMusics;
    currentMusics = await fetchPlayingMusics();
    if(oldMusisc.length != currentMusics.length) {
        rebuildMusicsList(currentMusics);
    } else for(let i = 0; i < oldMusisc.length; i++) {
        if(oldMusisc[i].title != currentMusics[i].title) {
            rebuildMusicsList(currentMusics);
            return;
        }
    }
}

async function fetchPlayingMusics() {
    // mockup
    // this is supposed to be replaced by a query to the server
    return [
        { title: 'foo', duration: 45, url: 'http://youtube.com' },
        { title: 'foo', duration: 45+Math.floor(Math.random()*100), url: 'http://youtube.com' },
        { title: 'foo', duration: 45, url: 'http://youtube.com' },
        { title: 'foo', duration: 45, url: 'http://youtube.com' },
        { title: 'foo' + Math.random(), duration: 45, url: 'http://youtube.com' },
    ];

    return await fetch("/get-playing-musics")
        .then(response => response.json())
        .catch(e => {
            console.error(e);
            return [];
        });
}

async function fetchMusicPropositions(query) {
    // mockup
    // this is supposed to be replaced by a query to the server
    return [
        { title: 'foo', duration: 45, url: 'http://youtube.com' },
        { title: 'foo', duration: 45+Math.floor(Math.random()*100), url: 'http://youtube.com' },
        { title: 'foo', duration: 45, url: 'http://youtube.com' },
        { title: 'foo', duration: 45, url: 'http://youtube.com' },
        { title: 'foo' + Math.random(), duration: 45, url: 'http://youtube.com' },
    ];

    return await fetch(`/get-music-propositions?query=${query}`)
        .then(response => response.json())
        .catch(e => {
            console.error(e);
            return [];
        });
}

async function sendRequest(content) {
    // do not send a request if one is already pending
    // (prevent double click)
    if(isRequestPending) return;
    isRequestPending = true;
    let timeout = setTimeout(() => isRequestPending = false, 1000);

    // send the actual request
    let success = true;

    let response = await fetch('/music-action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(content),
    }).catch(() => success = false);

    isResquestPending = false;
    clearTimeout(timeout);

    if(success) {
        // reload playing musics after the modification
        reloadPlayingMusics();
    }

    return { success, response };
}

async function sendAddMusic(music) {
    let { success, response } = await sendRequest({
        action: 'add',
        music: music,
    });

    if(success) {
        rebuildMusicPropositions([]);
        addMusicInput.value = '';
    }
}

async function sendRemoveMusic(music) {
    let { success, response } = await sendRequest({
        action: 'remove',
        music: music,
    });
}

async function sendPlayNowMusic(music) {
    let { success, response } = await sendRequest({
        action: 'play-now',
        music: music,
    });
}

window.addEventListener('load', () => {
    // active fetching, websockets are a pain to setup
    reloadPlayingMusics();
    setInterval(() => reloadPlayingMusics(), 5000);

    // wrap the fetch in a timeout to prevent spamming the server
    let timeout = null;
    addMusicInput.addEventListener('input', () => {
        clearTimeout(timeout);
        timeout = setTimeout(async () => {
            rebuildMusicPropositions(await fetchMusicPropositions(addMusicInput.value));
        }, 500);
    });
});

</script>
<style>

body {
    margin: 0;
    background-color: #232327;
    font-family: sans-serif;
}

header {
    background-color: #3e6bcc;
    font-weight: bolder;
    color: #dadada;
    text-align: center;
    padding: 2rem;
    box-shadow: 0 10px 20px #000000;
}

#add-music-box {
    margin-top: 20px;
    margin-bottom: 10px;
    border-radius: 5px;
    width: calc(100% - 20px);
    padding: 5px 10px 5px 10px;
    display: flex;
    flex-wrap: wrap;
    background-color: #3e6bcc;
    color: white;
    box-shadow: 0 0 10px #00000031;
}

input[type="text"] {
    border: none;
    border-bottom: 1px solid #dadada;
    background-color: rgba(255, 255, 255, 0.123);
    color: white;
    width: 100%;
    margin-top: 5px;
}

input[type="text"]::selection {
    background-color: #dadada;
    color: #232327;
}

input[type="text"]:focus {
    outline: none;
}

#yt-player-container {
    width: 100%;
    display: flex;
    justify-content: center;
    margin-top: 20px;
}

#content {
    max-width: 500px;
    margin: auto;
    padding: 1rem;
}

#musics-list {
    transition: height 0.5s;
    user-select: none;
}

#no-music-playing-info {
    color: #dadada;
    text-align: center;
    padding-top: 20%;
    padding-bottom: 20%;
}

#musics-list,
#add-music-box {
    margin-top: 20%;
}

#musics-list,
#music-propositions-list {
    display: grid;
    grid-template-columns: 1fr 3fr 1fr;
    row-gap: 10px;
}

#musics-list > :nth-child(3n+1) {
    color: #dadada;
    font-weight: bolder;
    width: 50px;
}

#musics-list > :nth-child(3n) {
    font-weight: bolder;
    text-align: center;
}

#musics-list > :nth-child(3n) > :first-child {
    color: #4df171;
    cursor: pointer;
}

#musics-list > :nth-child(3n) > :last-child {
    color: red;
    cursor: pointer;
}

#musics-list > :nth-child(3n+2),
#music-propositions-list > :nth-child(2n)  {
    text-decoration: none;
    color: #dadada;
    font-weight: bolder;
    border-bottom: 1px solid white;
    padding-left: 7px;
}

#music-propositions-list > :nth-child(2n+1) {
    color: #4df171;
    font-weight: bolder;
    text-align: center;
    cursor: pointer;
}

.music-bar-anim {
    height: 4px;
    margin-bottom: 1px;
    background-color: white;
    animation: bad-animation 1s infinite alternate ease-in-out;
}

.music-bar-anim:nth-child(2) {
    animation-delay: -0.8s;
}
.music-bar-anim:nth-child(3) {
    animation-delay: -0.4s;
}

@keyframes bad-animation {
    0%   { width: 5px; }
    100% { width: 30px; }
}

</style>
</html>