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
                <h1 id="status-text">WELCOME TO FURUKAWA GYM</h1>
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
        // Import the scanner
        import QrScanner from 'https://unpkg.com/qr-scanner@1.4.2/qr-scanner.min.js';

        // --- PART A: ELEMENT SELECTORS ---
        const statusPanel = document.getElementById('status-panel');
        const messageBox = document.getElementById('message');
        const statusText = document.getElementById('status-text');
        const nameText = document.getElementById('name-text');
        const dateText = document.getElementById('date-text');
        nameText.style.whiteSpace = 'pre-line'; // CSS style to make the newline character visible
        
        const videoElem = document.getElementById('qr-video');
        const cameraSelect = document.getElementById('camera-select');
        let qrScanner;

        // --- Helper function to format date ---
        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }

        // --- PART B: ECHO (LISTENER) SETUP ---
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: '{{ env("PUSHER_APP_KEY") }}',
            cluster: '{{ env("PUSHER_APP_CLUSTER") }}',
            forceTLS: true
        });

        // This function will be called when a broadcast is received
        window.Echo.channel('monitor-screen')
            .listen('.member.scanned', (e) => {
                
                // 1. Update text and show the message
                statusPanel.className = ''; // Clear old colors
                messageBox.classList.remove('show');
                dateText.classList.remove('visible'); // Hide date initially
                
                if (e.status === 'active') {
                    statusPanel.classList.add('bg-green');
                    statusText.textContent = 'WELCOME!';
                    nameText.textContent = e.member.name;
                    
                    // SHOW VALID UNTIL DATE
                    dateText.textContent = 'Membership Valid Until: ' + formatDate(e.member.membership_expiry_date);
                    dateText.classList.add('visible');
                } 
                else if (e.status === 'expired') {
                    statusPanel.classList.add('bg-red');
                    statusText.textContent = 'YOUR MEMBERSHIP HAS EXPIRED,\nPLEASE RENEW YOUR MEMBERSHIP AT THE FRONT DESK OR AVAIL OUR WALK IN';
                    nameText.textContent = e.member.name;

                    // SHOW EXPIRED DATE
                    dateText.textContent = 'Expired on: ' + formatDate(e.member.membership_expiry_date);
                    dateText.classList.add('visible');
                } 
                else if (e.status === 'not_found') {
                    statusPanel.classList.add('bg-red');
                    statusText.textContent = 'INVALID CODE';
                    nameText.textContent = 'MEMBER NOT FOUND.\nIF YOU THINK THIS IS A MISTAKE PLEASE HEAD TO THE FRONT DESK';
                    dateText.textContent = ''; // No date for invalid users
                }

                // Force browser to repaint, then fade in
                void messageBox.offsetWidth; 
                messageBox.classList.add('show');
                
                // 2. After 5 seconds, reset the screen
                setTimeout(() => {
                    // Fade out
                    messageBox.classList.remove('show');
                    
                    // After fade out, reset text and color
                    setTimeout(() => { 
                        statusPanel.className = 'bg-default';
                        statusText.textContent = 'WAITING FOR SCAN';
                        nameText.textContent = 'Please scan your QR code.';
                        dateText.textContent = '';
                        dateText.classList.remove('visible');
                        messageBox.classList.add('show');
                    }, 500); // 0.5s for fade-out
                    
                    // 3. Re-start the scanner
                    startScanner(cameraSelect.value); 
                }, 5000); // 5-second display
            });
        
        // --- PART C: QR SCANNER SETUP ---

        // This function is called when a QR code is successfully scanned
        const onScanSuccess = (result) => {
            if (qrScanner) {
                qrScanner.destroy(); // Stop scanning
                qrScanner = null;
            }
            
            // Send the data to our API
            fetch('/api/scan', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ qrData: result.data })
            })
            .catch(async (error) => {
                console.error('Error submitting scan:', error);
                // If fetch fails, restart the scanner so it's not stuck
                await startScanner(cameraSelect.value);
            });
        };

        // This function populates the camera dropdown
        function setupCameraDropdown(activeDeviceId) { 
            QrScanner.listCameras(true)
                .then(cameras => {
                    cameraSelect.innerHTML = ''; 
                    
                    cameras.forEach(camera => {
                        const option = document.createElement('option');
                        option.value = camera.id;
                        option.innerHTML = camera.label;
                        cameraSelect.appendChild(option);
                    });

                    if (activeDeviceId) {
                        cameraSelect.value = activeDeviceId;
                    } 
                    else if (qrScanner) {
                        cameraSelect.value = qrScanner.getCamera();
                    }
                })
                .catch(err => console.error(err));
        }

        // This function starts the scanner
        function startScanner(deviceId) {
            
            // 1. Destroy any existing scanner
            if (qrScanner) {
                qrScanner.destroy();
                qrScanner = null;
            }
            
            // 2. Wait for the browser to "breathe"
            setTimeout(() => {
                
                // 3. Tell the browser to run this code just before its next paint.
                requestAnimationFrame(() => {
                    
                    // 4. Create and start the new scanner
                    qrScanner = new QrScanner(
                        videoElem,
                        onScanSuccess,
                        {
                            preferredCamera: deviceId || 'environment',
                            highlightScanRegion: true,
                            highlightCodeOutline: true,
                        }
                    );

                    qrScanner.start()
                        .then(() => {
                            // 5. Populate the dropdown *after* the camera starts
                            setupCameraDropdown(deviceId);
                        })
                        .catch(err => console.error(err));
                });
            }, 50); 
        }

        // --- Main execution ---
        document.addEventListener('DOMContentLoaded', () => {
            startScanner(); // Start with the default camera

            cameraSelect.addEventListener('change', () => {
                startScanner(cameraSelect.value);
            });
        });

        // Make sure to stop everything when the user leaves the page
        window.addEventListener('beforeunload', () => {
            if (qrScanner) {
                qrScanner.destroy();
            }
        });
    </script>
</body>
</html>