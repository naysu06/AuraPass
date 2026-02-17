<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>Gym Check-in Kiosk</title>
    
    @vite('resources/css/app.css') 
    
    <style>
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

        /* COLORS */
        .bg-default { background-color: #374151; } 
        .bg-green { background-color: #10B981; } 
        .bg-red { background-color: #EF4444; } 
        .bg-blue { background-color: #3B82F6; } 
        /* Orange for Strict Mode Warning */
        .bg-orange { background-color: #F59E0B; }

        /* PHOTO STYLE */
        #member-photo {
            width: 150px; height: 150px; border-radius: 50%; object-fit: cover;
            border: 4px solid white; margin-bottom: 20px; display: none; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        #member-photo.visible { display: block; }

        #scanner-ui { width: 100%; max-width: 600px; background: #374151; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        #camera-select { color: black; width: 100%; padding: 0.5rem; border-radius: 0.25rem; margin-bottom: 1rem; }
        video { width: 100%; height: auto; border-radius: 0.25rem; transform: scaleX(-1) !important; }
    </style>
</head>
<body>

    <div class="kiosk-layout">
        <div id="status-panel" class="bg-default">
            <div id="message" class="show flex flex-col items-center"> 
                <!-- Member Photo -->
                <img id="member-photo" src="" alt="Member Face" />

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
        // Use the latest version to fix the Canvas Performance warning
        import QrScanner from 'https://cdn.jsdelivr.net/npm/qr-scanner@1.4.2/qr-scanner.min.js';

        const statusPanel = document.getElementById('status-panel');
        const messageBox = document.getElementById('message');
        const statusText = document.getElementById('status-text');
        const nameText = document.getElementById('name-text');
        const dateText = document.getElementById('date-text');
        const memberPhoto = document.getElementById('member-photo'); 
        
        const videoElem = document.getElementById('qr-video');
        const cameraSelect = document.getElementById('camera-select');
        
        let qrScanner;
        let isOnCooldown = false; 

        // --- WAKE LOCK (Keeps screen on) ---
        let wakeLock = null;
        async function requestWakeLock() {
            try {
                wakeLock = await navigator.wakeLock.request('screen');
                console.log('Wake Lock is active');
                wakeLock.addEventListener('release', () => {
                    console.log('Wake Lock was released');
                });
            } catch (err) {
                console.error(`${err.name}, ${err.message}`);
            }
        }
        document.addEventListener('visibilitychange', async () => {
            if (wakeLock !== null && document.visibilityState === 'visible') {
                await requestWakeLock();
            }
        });
        requestWakeLock();
        // -----------------------------------

        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: '{{ config("broadcasting.connections.pusher.key") }}',
            cluster: '{{ config("broadcasting.connections.pusher.options.cluster") }}',
            forceTLS: true
        });

        window.Echo.channel('monitor-screen')
        .listen('.member.checked_in', (e) => updateStatusUI('checked_in', e.member))
        .listen('.member.checked_out', (e) => updateStatusUI('checked_out', e.member))
        .listen('.member.scan_failed', (e) => updateStatusUI(e.reason, e.member));

        function updateStatusUI(status, member) {
            // 1. Check Debounce first
            if (status === 'ignored') {
                console.log('Debounce: Scan ignored.');
                isOnCooldown = false; 
                return; 
            }

            // 2. Reset UI
            statusPanel.className = ''; 
            messageBox.classList.remove('show');
            dateText.classList.remove('visible');
            memberPhoto.classList.remove('visible'); 

            const showMemberPhoto = (mem) => {
                if (mem && mem.profile_photo) {
                    memberPhoto.src = '/storage/' + mem.profile_photo;
                    memberPhoto.classList.add('visible');
                } else {
                    memberPhoto.src = ''; 
                }
            };

            // --- CHECK IN ---
            if (status === 'checked_in' || status === 'active') {
                statusPanel.classList.add('bg-green');
                statusText.textContent = 'WELCOME';
                nameText.textContent = member.name;
                dateText.textContent = 'Valid Until: ' + formatDate(member.membership_expiry_date);
                dateText.classList.add('visible');
                showMemberPhoto(member); 
            } 
            
            // --- CHECK OUT ---
            else if (status === 'checked_out') {
                statusPanel.classList.add('bg-blue');
                statusText.textContent = 'THANK YOU FOR WORKING OUT';
                if(member) {
                    nameText.textContent = member.name;
                    dateText.textContent = 'See you next time!';
                    showMemberPhoto(member);
                } else {
                    nameText.textContent = 'Member';
                    dateText.textContent = '';
                }
                dateText.classList.add('visible');
            }

            // --- NEW: STRICT MODE (NO PHOTO) ---
            else if (status === 'no_photo') {
                statusPanel.classList.add('bg-orange');
                statusText.textContent = 'ACCESS DENIED';
                nameText.textContent = 'Member has no photo. Please update your photo at the front desk.';
                dateText.textContent = '';
            }

            // --- EXPIRED ---
            else if (status === 'expired') {
                statusPanel.classList.add('bg-red');
                statusText.textContent = 'MEMBERSHIP EXPIRED';
                nameText.textContent = member.name;
                dateText.textContent = 'Expired: ' + formatDate(member.membership_expiry_date);
                dateText.classList.add('visible');
                showMemberPhoto(member);
            } 
            
            // --- INVALID ---
            else if (status === 'not_found') {
                statusPanel.classList.add('bg-red');
                statusText.textContent = 'INVALID QR';
                nameText.textContent = 'Member not found.';
                dateText.textContent = '';
            }

            // 3. Trigger Animation
            void messageBox.offsetWidth; 
            messageBox.classList.add('show');

            // 4. Auto-Reset Timer (3 Seconds)
            setTimeout(() => {
                messageBox.classList.remove('show');
                setTimeout(() => { 
                    statusPanel.className = 'bg-default';
                    statusText.textContent = 'WELCOME TO QUADS-FURUKAWA GYM';
                    nameText.textContent = 'Please scan your provided QR code.';
                    dateText.textContent = '';
                    dateText.classList.remove('visible');
                    memberPhoto.classList.remove('visible');
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