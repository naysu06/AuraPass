<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-g-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gym Check-in Status</title>
    <style>
        /* Basic styles */
        body, html {
            height: 100%;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: white;
            overflow: hidden;
        }
        .container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
            text-align: center;
            transition: background-color 0.5s ease;
        }
        #message {
            transform: scale(0.9);
            opacity: 0;
            transition: all 0.3s ease;
        }
        #message.show {
            transform: scale(1);
            opacity: 1;
        }
        h1 {
            font-size: 5rem;
            font-weight: 700;
            margin: 0;
        }
        p {
            font-size: 3rem;
            font-weight: 300;
            margin: 0;
        }

        /* Status colors */
        .bg-default { background-color: #374151; } /* Gray */
        .bg-green { background-color: #10B981; } /* Green */
        .bg-red { background-color: #EF4444; } /* Red */
    </style>
</head>
<body class="bg-default">

    <div class="container" id="container">
        <div id="message">
            <h1 id="status-text">WAITING FOR SCAN</h1>
            <p id="name-text">Please scan your QR code at the desk.</p>
        </div>
    </div>

    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.11.2/dist/echo.iife.js"></script>

    <script>
        // 2. Initialize Echo
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: '{{ env("PUSHER_APP_KEY") }}', // Use your public key
            cluster: '{{ env("PUSHER_APP_CLUSTER") }}',
            forceTLS: true
        });

        const container = document.getElementById('container');
        const message = document.getElementById('message');
        const statusText = document.getElementById('status-text');
        const nameText = document.getElementById('name-text');

        // 3. Listen for the event
        window.Echo.channel('monitor-screen')
            .listen('.member.scanned', (e) => {
                // e.status and e.member hold our data

                // Reset all classes
                container.className = 'container';
                message.className = '';

                if (e.status === 'active') {
                    container.classList.add('bg-green');
                    statusText.textContent = 'WELCOME!';
                    nameText.textContent = e.member.name;
                } 
                else if (e.status === 'expired') {
                    container.classList.add('bg-red');
                    statusText.textContent = 'MEMBERSHIP EXPIRED';
                    nameText.textContent = e.member.name;
                } 
                else if (e.status === 'not_found') {
                    container.classList.add('bg-red');
                    statusText.textContent = 'INVALID CODE';
                    nameText.textContent = 'Member not found.';
                }

                // Show the message
                message.classList.add('show');

                // Reset the screen after 5 seconds
                setTimeout(() => {
                    message.classList.remove('show');
                    // Optional: Reset to default after fade out
                    setTimeout(() => {
                        container.className = 'container bg-default';
                        statusText.textContent = 'WAITING FOR SCAN';
                        nameText.textContent = 'Please scan your QR code at the desk.';
                    }, 500);
                }, 5000);
            });
    </script>
</body>
</html>