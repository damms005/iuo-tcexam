var app = require('express')();
var http = require('http').Server(app);
var io = require('socket.io')(http);
const formidable = require('formidable')

app.get('/', function (req, res) {
	res.sendFile(__dirname + '/index.html');
});
app.get('/favicon.ico', function (req, res) {
	res.sendFile(__dirname + '/favicon.ico');
});

app.get('/socket.io.js', function (req, res) {
	res.sendFile(__dirname + '/socket.io.js');
});

io.on('connection', function (socket) {
	socket.emit('server-connection-acknowledged', `Me server connection-acknowledged`)
	socket.on('client-thanking-server', function (data) {
		console.log(`Don't mention, clicnt: ${data}`)
	});
});

http.listen(3000, function () {
	command();
	console.log('listening on *:3000');
});

const {
	exec
} = require('child_process');


function command() {
	exec("/usr/bin/zbarimg --raw -Sdisable -Sqrcode.enable -q '/home/tfx/Desktop/tcexam/quiz1/sample-tce-OMR-Functionality-Test-qr-page.png'", (err, stdout, stderr) => {
		if (err) {
			// node couldn't execute the command
			return;
		}

		// the *entire* stdout and stderr (buffered)
		console.log(`stdout: ${stdout}`);
		console.log(`stderr: ${stderr}`);
	});
}

function onConnect(socket) {
	// sending to the client
	socket.emit('hello', 'can you hear me?', 1, 2, 'abc');

	// sending to all clients except sender
	socket.broadcast.emit('broadcast', 'hello friends!');

	// sending to all clients in 'game' room except sender
	socket.to('game').emit('nice game', "let's play a game");

	// sending to all clients in 'game1' and/or in 'game2' room, except sender
	socket.to('game1').to('game2').emit('nice game', "let's play a game (too)");

	// sending to all clients in 'game' room, including sender
	io.in('game').emit('big-announcement', 'the game will start soon');

	// sending to all clients in namespace 'myNamespace', including sender
	io.of('myNamespace').emit('bigger-announcement', 'the tournament will start soon');

	// sending to a specific room in a specific namespace, including sender
	io.of('myNamespace').to('room').emit('event', 'message');

	// sending to individual socketid (private message)
	io.to(`${socketId}`).emit('hey', 'I just met you');

	// WARNING: `socket.to(socket.id).emit()` will NOT work, as it will send to everyone in the room
	// named `socket.id` but the sender. Please use the classic `socket.emit()` instead.

	// sending with acknowledgement
	socket.emit('question', 'do you think so?', function (answer) {});

	// sending without compression
	socket.compress(false).emit('uncompressed', "that's rough");

	// sending a message that might be dropped if the client is not ready to receive messages
	socket.volatile.emit('maybe', 'do you really need it?');

	// specifying whether the data to send has binary data
	socket.binary(false).emit('what', 'I have no binaries!');

	// sending to all clients on this node (when using multiple nodes)
	io.local.emit('hi', 'my lovely babies');

	// sending to all connected clients
	io.emit('an event sent to all connected clients');

}