<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>Gym Check-in Kiosk</title>
    
    @vite('resources/css/app.css') 
    
    <style>
        /* ... (Previous styles remain the same) ... */
        body, html { height: 100%; margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; overflow: hidden; background-color: #111827; }
        .kiosk-layout { display: flex; width: 100%; height: 100%; }
        #status-panel { flex: 1.5; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 2rem; color: white; transition: background-color 0.5s ease; }
        #scanner-panel { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; background-color: #1f2937; padding: 2rem; box-sizing: border-box; }
        #message { transform: scale(0.9); opacity: 0; transition: all 0.3s ease; }
        #message.show { transform: scale(1); opacity: 1; }
        h1 { font-size: 4rem; font-weight: 700; margin: 0; }
        p { font-size: 2rem; font-weight: 300; margin: 0; }
        #date-text { font-size: 1.5rem; font-weight: 400; margin-top: 1rem; opacity: 0.9; background: rgba(0,0,0,0.2); padding: 5px 15px; border-radius: 20px; display: none; }
        #date-text.visible { display: inline-block; }

        /* --- COLORS --- */
        .bg-default { background-color: #374151; } 
        .bg-green { background-color: #10B981; } 
        .bg-red { background-color: #EF4444; } 
        
        /* 1. NEW: Blue background for Check Out */
        .bg-blue { background-color: #3B82F6; } 

        /* ... (Scanner styles remain the same) ... */
        #scanner-ui { width: 100%; max-width: 600px; background: #374151; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        #camera-select { color: black; width: 100%; padding: 0.5rem; border-radius: 0.25rem; margin-bottom: 1rem; }
        video { width: 100%; height: auto; border-radius: 0.25rem; transform: scaleX(-1) !important; }
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
                <label for="camera-select" class="block text-sm font-medium text-white mb-2">Select Camera:</label>
                <select id="camera-select"></select>
                <video id="qr-video" class="mt-4"></video>
            </div>
        </div>
    </div>

    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.11.2/dist/echo.iife.js"></script>
    <script type="module">
        import QrScanner from 'https://unpkg.com/qr-scanner@1.4.2/qr-scanner.min.js';

        const statusPanel = document.getElementById('status-panel');
        const messageBox = document.getElementById('message');
        const statusText = document.getElementById('status-text');
        const nameText = document.getElementById('name-text');
        const dateText = document.getElementById('date-text');
        const videoElem = document.getElementById('qr-video');
        const cameraSelect = document.getElementById('camera-select');
        
        let qrScanner;
        let isOnCooldown = false; 

        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: '{{ config("broadcasting.connections.pusher.key") }}',
            cluster: '{{ config("broadcasting.connections.pusher.options.cluster") }}',
            forceTLS: true
        });

        window.Echo.channel('monitor-screen')
        // 1. Handle Entry
        .listen('.member.checked_in', (e) => {
            updateStatusUI('checked_in', e.member);
        })
        // 2. Handle Exit
        .listen('.member.checked_out', (e) => {
            updateStatusUI('checked_out', e.member);
        })
        // 3. Handle Errors (Expired / Not Found / Ignored)
        .listen('.member.scan_failed', (e) => {
            updateStatusUI(e.reason, e.member);
        });

        // --- 2. UPDATED UI LOGIC ---
        function updateStatusUI(status, member) {
            // <--- FIX IS HERE --->
        if (status === 'ignored') {
            console.log('Debounce: Scan ignored.');
            
            // CRITICAL: Unlock the scanner so they can scan again!
            isOnCooldown = false; 
            
            return; 
        }

        // <--- NOW it is safe to reset the UI --->
        statusPanel.className = ''; 
        messageBox.classList.remove('show');
        dateText.classList.remove('visible');

        // --- SCENARIO: CHECK IN ---
        if (status === 'checked_in' || status === 'active') {
            statusPanel.classList.add('bg-green');
            statusText.textContent = 'WELCOME';
            nameText.textContent = member.name;
            dateText.textContent = 'Valid Until: ' + formatDate(member.membership_expiry_date);
            dateText.classList.add('visible');
        } 
        
        // --- SCENARIO: CHECK OUT ---
        else if (status === 'checked_out') {
            statusPanel.classList.add('bg-blue');
            statusText.textContent = 'THANK YOU FOR WORKING OUT';
            // Added Safety Check from previous step
            if(member) {
                nameText.textContent = member.name;
                dateText.textContent = 'See you next time!';
            } else {
                nameText.textContent = 'Member';
                dateText.textContent = '';
            }
            dateText.classList.add('visible');
        }

        // --- SCENARIO: EXPIRED ---
        else if (status === 'expired') {
            statusPanel.classList.add('bg-red');
            statusText.textContent = 'YOUR MEMBERSHIP EXPIRED';
            nameText.textContent = member.name;
            dateText.textContent = 'Expired: ' + formatDate(member.membership_expiry_date);
            dateText.classList.add('visible');
        } 
        
        // --- SCENARIO: INVALID ---
        else if (status === 'not_found') {
            statusPanel.classList.add('bg-red');
            statusText.textContent = 'INVALID QR';
            nameText.textContent = 'Member not found.';
            dateText.textContent = '';
        }

        // Trigger Animation
        void messageBox.offsetWidth; 
        messageBox.classList.add('show');

        // Reset Timer
        setTimeout(() => {
            messageBox.classList.remove('show');
            setTimeout(() => { 
                statusPanel.className = 'bg-default';
                statusText.textContent = 'WELCOME TO QUADS-FURUKAWA GYM';
                nameText.textContent = 'Please scan your provided QR code.';
                dateText.textContent = '';
                dateText.classList.remove('visible');
                messageBox.classList.add('show');
                
                isOnCooldown = false; 
            }, 500);
        }, 3000);
    }

        const onScanSuccess = (result) => {
            if (isOnCooldown) return;
            isOnCooldown = true;

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
            setTimeout(() => {
                qrScanner = new QrScanner(
                    videoElem,
                    onScanSuccess,
                    {
                        preferredCamera: deviceId || 'environment',
                        highlightScanRegion: true,
                        highlightCodeOutline: true,
                        maxScansPerSecond: 100, 
                    }
                );
                qrScanner.start().then(() => {
                    setupCameraDropdown(deviceId);
                });
            }, 50);
        }

        function formatDate(dateString) {
            if (!dateString) return '';
            return new Date(dateString).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

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