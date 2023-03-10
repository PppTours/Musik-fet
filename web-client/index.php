<?php

const IS_ADMIN = true;

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
    </header>
    <div id="central-column">
        <?php if(IS_ADMIN): ?>
            <div id="yt-player"></div>
            <script src="https://www.youtube.com/iframe_api"></script>
        <?php endif; ?>
        <div id="content">
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
    </div>
    
    <template id="music-template">
        <div>
            <span class="t-playing-icon">
                <div class="music-bar-anim"></div>
                <div class="music-bar-anim"></div>
                <div class="music-bar-anim"></div>
            </span>
            <!-- <svg class="t-playing-icon" xmlns="http://www.w3.org/2000/svg" height="20" width="20" viewBox="0 96 960 960"><path d="M160 896V576h140v320H160Zm250 0V256h140v640H410Zm250 0V456h140v440H660Z" fill="white"/></svg> -->
            <span class="t-delay">+3min</span>
            <a class="t-title" href="youtube.com" target="_blank">Title</a>
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
    for(let music of nextMusics) {
        const musicElement = document.importNode(musicTemplate.content, true).firstElementChild;
        if(delay != 0) {
            musicElement.querySelector(".t-delay").textContent = `+${Math.ceil(delay/60)}min`;
            musicElement.querySelector(".t-playing-icon").remove();
        } else {
            musicElement.querySelector(".t-delay").remove();
        }
        musicElement.querySelector(".t-title").textContent = music.title;
        musicElement.querySelector(".t-title").href = music.url;
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

function reloadPlayingMusics() {
    let oldMusisc = currentMusics;
    currentMusics = fetchPlayingMusics();
    if(oldMusisc.length != currentMusics.length) {
        rebuildMusicsList(currentMusics);
    } else for(let i = 0; i < oldMusisc.length; i++) {
        if(oldMusisc[i].title != currentMusics[i].title) {
            rebuildMusicsList(currentMusics);
            return;
        }
    }
}

function fetchPlayingMusics() {
    // mockup
    // this is supposed to be replaced by a query to the server
    return [
        { title: 'foo', duration: 45, url: 'http://youtube.com' },
        { title: 'foo', duration: 45+Math.floor(Math.random()*100), url: 'http://youtube.com' },
        { title: 'foo', duration: 45, url: 'http://youtube.com' },
        { title: 'foo', duration: 45, url: 'http://youtube.com' },
        { title: 'foo' + Math.random(), duration: 45, url: 'http://youtube.com' },
    ];
}

function fetchMusicPropositions(query) {
    // mockup
    // this is supposed to be replaced by a query to the server
    return [
        { title: 'foo', duration: 45, url: 'http://youtube.com' },
        { title: 'foo', duration: 45+Math.floor(Math.random()*100), url: 'http://youtube.com' },
        { title: 'foo', duration: 45, url: 'http://youtube.com' },
        { title: 'foo', duration: 45, url: 'http://youtube.com' },
        { title: 'foo' + Math.random(), duration: 45, url: 'http://youtube.com' },
    ];
}

function sendAddMusic(music) {
    // do not send a request if one is already pending
    // (prevent double click)
    if(isRequestPending) return;
    isRequestPending = true;
    setTimeout(() => isRequestPending = false, 1000);

    // send the actual request
    let success = true;

    if(success) {
        // reload playing musics after the modification
        reloadPlayingMusics();
        rebuildMusicPropositions([]);
        addMusicInput.value = '';
    }
}

window.addEventListener('load', () => {
    // active fetching, websockets are a pain to setup
    reloadPlayingMusics();
    setInterval(() => reloadPlayingMusics(), 5000);

    // wrap the fetch in a timeout to prevent spamming the server
    let timeout = null;
    addMusicInput.addEventListener('input', () => {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            rebuildMusicPropositions(fetchMusicPropositions(addMusicInput.value));
        }, 500);
    });
});

</script>
<?php if(IS_ADMIN): ?>
<script>

function onYouTubeIframeAPIReady() {

    function onPlayerReady(event) {
        event.target.playVideo();
    }

    function onPlayerStateChange(event) {
        if(event.data == YT.PlayerState.ENDED) {
            // reloadPlayingMusics();
        }
    }
    const player = new YT.Player('yt-player', {
        height: '390',
        width: '640',
        videoId: 'M7lc1UVf-VE',
        events: {
            'onReady': onPlayerReady,
            'onStateChange': onPlayerStateChange
        },
    });
}

</script>
<?php endif; ?>
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

#content {
    max-width: 500px;
    margin: auto;
    padding: 1rem;
}

#musics-list {
    transition: height 0.5s;
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
    grid-template-columns: 1fr 3fr;
    row-gap: 10px;
}

#musics-list > :nth-child(2n+1) {
    color: #dadada;
    font-weight: bolder;
    width: 50px;
}

#musics-list > :nth-child(2n),
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