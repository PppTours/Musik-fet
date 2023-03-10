/*

This script is used to reload the browser when a file is changed.
Simply run it with node auto-reloader.js and open the generated
auto-reloader.html file in your browser.
If you want to execute some code when a file is changed, edit the
onFileChange function.

Note that *all* files in the current directory will be watched,
including subdirectories, this may be a problem if executed in
very large directories.

Tested on Windows 10 with Node.js v16.16.0 and Firefox
No dependencies required

@author Albin Calais 2021

*/

const fs = require('fs');
const http = require('http');
const crypto = require('crypto');
const spawn = require('child_process').spawn;

// -------------- USER CODE -----------------

const CREATE_AUTORELOADER_HTML = true; // set to false if you want to use your own html file

async function onFileChange() {
    // <>TO BE EDITED<>
    // This is where you can add your own code to be executed when a file is changed

    await execCommandToFile('php', ['index.php'], 'index.html');
    console.log('wrote index.html');

    // </>TO BE EDITED</>
}

function execCommand(command, args) {
    return new Promise((resolve) => {
        spawn(command, args, { stdio: 'inherit' })
            .on('close', resolve);
    });
}

function execCommandToFile(command, args, file) {
    let stdout = fs.openSync(file, 'w');
    return new Promise((resolve) => {
        spawn(command, args, { stdio: ['ignore', stdout, 'ignore'] })
            .on('close', resolve);
    });
}

// -------------- APP CODE -----------------

const server = http.createServer(null);

var sockets = [];
let lastUpdateTime = 0;
let currentlyProcessing = false;

server.on('upgrade', (req, socket, head) => {
    console.log('client_connection');
    sockets.push(socket);
    socket.write(
    'HTTP/1.1 101 Web Socket Protocol Handshake\r\n' +
    'Sec-WebSocket-Accept: ' + crypto.createHash('sha1').update(req.headers['sec-websocket-key'] + '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', 'binary').digest('base64') + '\r\n' +
    'Upgrade: WebSocket\r\n' +
    'Connection: Upgrade\r\n' +
    '\r\n');
    socket.on('close', () => {
        sockets = sockets.filter((s) => s !== socket);
        console.log('client_deconnection');
    });
    socket.on('error', () => {
        sockets = sockets.filter((s) => s !== socket);
        console.error('client_error');
    });
    socket.on('end', socket.end);
});

// open server on ws://localhost:3000
server.listen(3000, () => {
    console.log('server started on \033[32mfile:///' + fs.realpathSync('.') + '/auto-reloader.html\033[0m');
});

// watch for file changes
fs.watch('.', async (_event, filename) => {
    if(fs.statSync(filename).mtimeMs <= lastUpdateTime || currentlyProcessing) return;
    console.log('\033[94mfile update\033[0m', filename);
    currentlyProcessing = true;
    await onFileChange();
    sockets.forEach((socket) => {
        socket.write(createFrame('reload'));
    });
    lastUpdateTime = Date.now();
    currentlyProcessing = false;
    console.log('\033[94mfinished processing\033[0m');
});

if(CREATE_AUTORELOADER_HTML && !fs.existsSync('auto-reloader.html')) {
    fs.writeFileSync('auto-reloader.html', `
        <iframe src="index.html"></iframe>
        <script>
        const socket = new WebSocket('ws://localhost:3000');
        socket.addEventListener('message', () => location.reload());
        socket.onclose = () => console.log('close');
        socket.onopen  = () => console.log('open' );
        socket.onerror = () => console.log('error');
        </script>
        <style>
        body {margin: 0; overflow: hidden; }
        iframe {border: none; width: 100vw; height: 100vh; }
        </style>`);
}

// https://betterprogramming.pub/implementing-a-websocket-server-from-scratch-in-node-js-a1360e00a95f
function createFrame(data) {
    const payload = data;
    const payloadByteLength = Buffer.byteLength(payload);
    let payloadBytesOffset = 2;
    let payloadLength = payloadByteLength;
    if (payloadByteLength > 65535) { // length value cannot fit in 2 bytes
      payloadBytesOffset += 8;
      payloadLength = 127;
    } else if (payloadByteLength > 125) {
      payloadBytesOffset += 2;
      payloadLength = 126;
    }
    const buffer = Buffer.alloc(payloadBytesOffset + payloadByteLength);
    buffer.writeUInt8(0b10000001, 0);
    buffer[1] = payloadLength;
    if (payloadLength === 126)
        buffer.writeUInt16BE(payloadByteLength, 2);
    else if (payloadByteLength === 127)
        buffer.writeBigUInt64BE(BigInt(payloadByteLength), 2);
    buffer.write(payload, payloadBytesOffset);
    return buffer;
  }