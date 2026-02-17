import QrScanner from 'qr-scanner';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Initialize Echo
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: window.kioskConfig.pusherKey,
    cluster: window.kioskConfig.pusherCluster,
    forceTLS: true
});

let qrScanner;
let isOnCooldown = false;

// --- WAKE LOCK ---
let wakeLock = null;
async function requestWakeLock() {
    try {
        if ('wakeLock' in navigator) {
            wakeLock = await navigator.wakeLock.request('screen');
            console.log('Wake Lock is active');
            wakeLock.addEventListener('release', () => console.log('Wake Lock released'));
        }
    } catch (err) {
        console.error(`Wake Lock Error: ${err.name}, ${err.message}`);
    }
}
document.addEventListener('visibilitychange', async () => {
    if (wakeLock !== null && document.visibilityState === 'visible') {
        await requestWakeLock();
    }
});
requestWakeLock();


// --- DOM ELEMENTS & LOGIC ---
document.addEventListener('DOMContentLoaded', () => {
    const statusPanel = document.getElementById('status-panel');
    const messageBox = document.getElementById('message');
    const statusText = document.getElementById('status-text');
    const nameText = document.getElementById('name-text');
    const dateText = document.getElementById('date-text');
    const memberPhoto = document.getElementById('member-photo');
    const videoElem = document.getElementById('qr-video');
    const cameraSelect = document.getElementById('camera-select');

    // --- ECHO LISTENERS ---
    window.Echo.channel('monitor-screen')
        .listen('.member.checked_in', (e) => updateStatusUI('checked_in', e.member))
        .listen('.member.checked_out', (e) => updateStatusUI('checked_out', e.member))
        .listen('.member.scan_failed', (e) => updateStatusUI(e.reason, e.member));

    function updateStatusUI(status, member) {
        if (status === 'ignored') {
            console.log('Debounce: Scan ignored.');
            isOnCooldown = false;
            return;
        }

        // Reset UI
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

        // --- STATUS LOGIC ---
        if (status === 'checked_in' || status === 'active') {
            statusPanel.classList.add('bg-green');
            statusText.textContent = 'WELCOME';
            nameText.textContent = member.name;
            dateText.textContent = 'Valid Until: ' + formatDate(member.membership_expiry_date);
            dateText.classList.add('visible');
            showMemberPhoto(member);
        } else if (status === 'checked_out') {
            statusPanel.classList.add('bg-blue');
            statusText.textContent = 'THANK YOU FOR WORKING OUT';
            if (member) {
                nameText.textContent = member.name;
                dateText.textContent = 'See you next time!';
                showMemberPhoto(member);
            } else {
                nameText.textContent = 'Member';
                dateText.textContent = '';
            }
            dateText.classList.add('visible');
        } else if (status === 'no_photo') {
            statusPanel.classList.add('bg-orange');
            statusText.textContent = 'ACCESS DENIED';
            nameText.textContent = 'Member has no photo. Please update your photo at the front desk.';
            dateText.textContent = '';
        } else if (status === 'expired') {
            statusPanel.classList.add('bg-red');
            statusText.textContent = 'MEMBERSHIP EXPIRED';
            nameText.textContent = member.name;
            dateText.textContent = 'Expired: ' + formatDate(member.membership_expiry_date);
            dateText.classList.add('visible');
            showMemberPhoto(member);
        } else if (status === 'not_found') {
            statusPanel.classList.add('bg-red');
            statusText.textContent = 'INVALID QR';
            nameText.textContent = 'Member not found.';
            dateText.textContent = '';
        }

        // Animation
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
                memberPhoto.classList.remove('visible');
                messageBox.classList.add('show');
                isOnCooldown = false;
            }, 500);
        }, 3000);
    }

    // --- SCANNER SETUP ---
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

    function startScanner(deviceId) {
        if (qrScanner) {
            qrScanner.stop();
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
                    maxScansPerSecond: 25, // Lowered slightly to save CPU
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

    // Start scanner
    startScanner();
    cameraSelect.addEventListener('change', () => startScanner(cameraSelect.value));
});

window.addEventListener('beforeunload', () => {
    if (qrScanner) qrScanner.destroy();
});