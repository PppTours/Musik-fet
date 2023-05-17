/*
Tiny script that watches for file changes and regenerates the two client pages.
Always run this after having modified index.php, DO NOT modify index.html or
index_host.html directly.
*/

const fs = require('fs');
const spawn = require('child_process').spawn;

// -------------- USER CODE -----------------

async function onFileChange() {
    // <>TO BE EDITED<>
    // This is where you can add your own code to be executed when a file is changed

    await execCommandToFile('php', ['index.php'], '../public/index.html');
    await execCommandToFile('php', ['index.php'], '../public/index_host.html', { IS_HOST:'true' });
    console.log('wrote index.html');

    // </>TO BE EDITED</>
}

function execCommandToFile(command, args, file, environment = { }) {
    let stdout = fs.openSync(file, 'w');
    return new Promise((resolve) => {
        spawn(command, args, { stdio: ['ignore', stdout, 'ignore'], env: environment })
            .on('close', resolve);
    });
}

// -------------- APP CODE -----------------

let lastUpdateTime = 0;
let currentlyProcessing = false;

// watch for file changes
fs.watch('.', async (_event, filename) => {
    if(fs.statSync(filename).mtimeMs <= lastUpdateTime || currentlyProcessing) return;
    console.log('file update', filename);
    currentlyProcessing = true;
    await onFileChange();
    lastUpdateTime = Date.now();
    currentlyProcessing = false;
    console.log('finished processing');
});

onFileChange(); // kickstart
