<x-filament-panels::page>

    <div style="width: 100%; max-width: 500px;">
        {{-- 1. Camera selection dropdown --}}
        <div class="mb-4">
            <label for="camera-select" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Select Camera:
            </label>
            <select id="camera-select"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:border-primary-500 dark:focus:ring-primary-500">
                {{-- Options will be added by JavaScript --}}
            </select>
        </div>

        {{-- 2. The Video Viewfinder --}}
        <video id="qr-video" style="width: 100%;"></video>
    </div>

    {{-- 3. Include the qr-scanner library (it's a module) --}}
    <script type="module">
        // Import the scanner
        import QrScanner from 'https://unpkg.com/qr-scanner@1.4.2/qr-scanner.min.js';

        const videoElem = document.getElementById('qr-video');
        const cameraSelect = document.getElementById('camera-select');
        let qrScanner;

        // This function is called on a successful scan
        const onScanSuccess = (result) => {
            if (qrScanner) {
                qrScanner.destroy(); // Destroy the current scanner
                qrScanner = null;
            }
            
            @this.call('processScan', result.data); // Send data to Filament

            // After a delay, re-create the scanner
            setTimeout(() => {
                const selectedCameraId = cameraSelect.value;
                startScanner(selectedCameraId);
            }, 2000); // 2-second delay
        };

        // This function populates the camera dropdown
        function setupCameraDropdown() {
            QrScanner.listCameras(true) // true = with labels
                .then(cameras => {
                    // Clear any old options
                    cameraSelect.innerHTML = ''; 
                    
                    cameras.forEach(camera => {
                        const option = document.createElement('option');
                        option.value = camera.id;
                        option.innerHTML = camera.label;
                        cameraSelect.appendChild(option);
                    });

                    // Set the dropdown to the currently active camera
                    if (qrScanner) {
                        cameraSelect.value = qrScanner.getCamera();
                    }
                })
                .catch(err => console.error(err));
        }

        // This function starts the scanner
        function startScanner(deviceId) {
            if (qrScanner) {
                qrScanner.destroy();
                qrScanner = null;
            }
            
            qrScanner = new QrScanner(
                videoElem,
                onScanSuccess,
                {
                    preferredCamera: deviceId || 'environment',
                    highlightScanRegion: true,
                    highlightCodeOutline: true,
                }
            );

            // --- THIS IS THE KEY CHANGE ---
            // Start the scanner first...
            qrScanner.start()
                .then(() => {
                    // ...THEN populate the camera list
                    // (now that we have permission)
                    setupCameraDropdown();
                })
                .catch(err => console.error(err));
            // --- END CHANGE ---
        }

        // --- Main execution ---
        document.addEventListener('livewire:navigated', () => {
            startScanner(); // Start with the default camera

            // Add the 'change' listener just once
            cameraSelect.addEventListener('change', () => {
                startScanner(cameraSelect.value);
            });
        });

        // Make sure to stop everything when the user navigates away
        document.addEventListener('livewire:navigating', () => {
            if (qrScanner) {
                qrScanner.destroy();
            }
        }, { once: true });

    </script>
</x-filament-panels::page>