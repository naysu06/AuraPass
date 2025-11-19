<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>Gym Check-in Kiosk</title>
    
    @vite('resources/css/app.css') 
    
    <style>
        /* 1. Main Layout Setup */
        body, html {
            height: 100%;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            overflow: hidden;
            background-color: #111827; /* Dark background */
        }
        
        /* We use Flexbox for the 50/50 split */
        .kiosk-layout {
            display: flex;
            width: 100%;
            height: 100%;
        }

        /* 2. Left Panel (Status) */
        #status-panel {
            flex: 1.5; /* Take up 50% of the space */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 2rem;
            color: white;
            transition: background-color 0.5s ease;
        }

        /* 3. Right Panel (Scanner) */
        #scanner-panel {
            flex: 1; /* Take up 50% of the space */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background-color: #1f2937; /* Dark gray for the scanner side */
            padding: 2rem;
            box-sizing: border-box; /* Ensure padding doesn't break layout */
        }

        /* 4. Status Message Styles */
        #message {
            transform: scale(0.9);
            opacity: 0;
            transition: all 0.3s ease;
        }
        #message.show {
            transform: scale(1);
            opacity: 1;
        }
        h1 { font-size: 4rem; font-weight: 700; margin: 0; }
        p { font-size: 2rem; font-weight: 300; margin: 0; }
        
        /* NEW: Style for the date text */
        #date-text {
            font-size: 1.5rem;
            font-weight: 400;
            margin-top: 1rem;
            opacity: 0.9;
            background: rgba(0,0,0,0.2); /* Subtle background bubble */
            padding: 5px 15px;
            border-radius: 20px;
            display: none; /* Hidden by default */
        }
        #date-text.visible { display: inline-block; }

        /* 5. Status Background Colors */
        .bg-default { background-color: #374151; } /* Gray */
        .bg-green { background-color: #10B981; } /* Green */
        .bg-red { background-color: #EF4444; } /* Red */

        /* 6. Scanner UI Styles */
        #scanner-ui {
            width: 100%;
            max-width: 600px; /* Max width for the scanner box */
            background: #374151;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        #camera-select {
            color: black;
            width: 100%;
            padding: 0.5rem;
            border-radius: 0.25rem;
            margin-bottom: 1rem;
        }
        video {
            width: 100%;
            height: auto;
            border-radius: 0.25rem;
            /* Force the video to be unmirrored. */
            transform: scaleX(-1) !important;
        }
    </style>
</head>
<body>

    <div class="kiosk-layout">
        
        <div id="status-panel" class="bg-default">
            <div id="message" class="show"> 
                <h1 id="status-text">WELCOME TO QUADS-FURUKAWA GYM</h1>
                <p id="name-text">Please scan your provided QR code.</p>
                
                <div style="width:100%; margin-top: 10px;">
                    <p id="date-text"></p>
                </div>
            </div>
        </div>

        <div id="scanner-panel">
            <div id="scanner-ui">
                <label for="camera-select" class="block text-sm font-medium text-white mb-2">
                    Select Camera:
                </label>
                <select id="camera-select"></select>
                <video id="qr-video" class="mt-4"></video>
            </div>
        </div>

    </div>

    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.11.2/dist/echo.iife.js"></script>
    <script type="module">
        import QrScanner from 'https://unpkg.com/qr-scanner@1.4.2/qr-scanner.min.js';

        // --- ELEMENTS ---
        const statusPanel = document.getElementById('status-panel');
        const messageBox = document.getElementById('message');
        const statusText = document.getElementById('status-text');
        const nameText = document.getElementById('name-text');
        const dateText = document.getElementById('date-text');
        
        const videoElem = document.getElementById('qr-video');
        const cameraSelect = document.getElementById('camera-select');
        
        let qrScanner;
        
        // --- THE MAGIC FLAG ---
        // This prevents the scanner from spamming the API while the user
        // is holding their phone in front of the camera.
        let isOnCooldown = false; 

        // --- ECHO LISTENER ---
        window.Echo = new Echo({
            broadcaster: 'pusher',
            // CHANGE THIS: Use config() instead of env()
            key: '{{ config("broadcasting.connections.pusher.key") }}',
            cluster: '{{ config("broadcasting.connections.pusher.options.cluster") }}',
            forceTLS: true
        });

        window.Echo.channel('monitor-screen')
            .listen('.member.scanned', (e) => {
                updateStatusUI(e.status, e.member);
            });

        // --- UI UPDATER ---
        function updateStatusUI(status, member) {
            // Reset UI classes
            statusPanel.className = ''; 
            messageBox.classList.remove('show');
            dateText.classList.remove('visible');

            // Apply colors and text based on status
            if (status === 'active') {
                statusPanel.classList.add('bg-green');
                statusText.textContent = 'WELCOME!';
                nameText.textContent = member.name;
                dateText.textContent = 'Valid Until: ' + formatDate(member.membership_expiry_date);
                dateText.classList.add('visible');
            } 
            else if (status === 'expired') {
                statusPanel.classList.add('bg-red');
                statusText.textContent = 'YOUR MEMBERSHIP HAS EXPIRED';
                nameText.textContent = member.name;
                dateText.textContent = 'Expired: ' + formatDate(member.membership_expiry_date);
                dateText.classList.add('visible');
            } 
            else if (status === 'not_found') {
                statusPanel.classList.add('bg-red');
                statusText.textContent = 'INVALID';
                nameText.textContent = 'Member not found.';
                dateText.textContent = '';
            }

            // Animation trigger
            void messageBox.offsetWidth; 
            messageBox.classList.add('show');

            // --- RESET TIMER ---
            // After 3 seconds, reset the screen to "Ready"
            // AND unlock the scanner (turn off cooldown)
            setTimeout(() => {
                messageBox.classList.remove('show');
                
                setTimeout(() => { 
                    statusPanel.className = 'bg-default';
                    statusText.textContent = 'WELCOME TO QUADS-FURUKAWA GYM';
                    nameText.textContent = 'Please scan your provided QR code.';
                    dateText.textContent = '';
                    dateText.classList.remove('visible');
                    messageBox.classList.add('show');
                    
                    // CRITICAL: Allow scanning again
                    isOnCooldown = false;
                    
                }, 500);
            }, 3000);
        }

        // --- QR SCANNER LOGIC ---

        const onScanSuccess = (result) => {
            // 1. If we are currently showing a result, IGNORE this scan.
            if (isOnCooldown) {
                return;
            }

            // 2. Lock the scanner immediately
            isOnCooldown = true;

            // 3. Send data to API
            fetch('/api/scan', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ qrData: result.data })
            })
            .catch(error => {
                console.error('Error:', error);
                // If error, unlock immediately so they can try again
                isOnCooldown = false; 
            });
        };

        function setupCameraDropdown(activeDeviceId) { 
            QrScanner.listCameras(true).then(cameras => {
                cameraSelect.innerHTML = ''; 
                cameras.forEach(camera => {
                    const option = document.createElement('option');
                    option.value = camera.id;
                    option.innerHTML = camera.label;
                    cameraSelect.appendChild(option);
                });
                if (activeDeviceId) cameraSelect.value = activeDeviceId;
            });
        }

        async function startScanner(deviceId) {
            if (qrScanner) {
                await qrScanner.stop();
                qrScanner.destroy();
                qrScanner = null;
            }

            // Small pause to help browser rendering
            setTimeout(() => {
                qrScanner = new QrScanner(
                    videoElem,
                    onScanSuccess,
                    {
                        preferredCamera: deviceId || 'environment',
                        highlightScanRegion: true,
                        highlightCodeOutline: true,
                        // Scan aggressively (every frame) because we handle the throttle manually
                        maxScansPerSecond: 25, 
                    }
                );

                qrScanner.start().then(() => {
                    setupCameraDropdown(deviceId);
                });
            }, 50);
        }

        // Helper for date formatting
        function formatDate(dateString) {
            if (!dateString) return '';
            return new Date(dateString).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        // --- EXECUTION ---
        document.addEventListener('DOMContentLoaded', () => {
            startScanner(); 
            cameraSelect.addEventListener('change', () => startScanner(cameraSelect.value));
        });

        window.addEventListener('beforeunload', () => {
            if (qrScanner) qrScanner.destroy();
        });
    </script>
</body>
</html>