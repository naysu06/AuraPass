<x-filament-panels::page>
    {{-- 1. The HTML element --}}
<div id="qr-reader" style="width: 100%; max-width: 500px;"></div>

{{-- 2. The library --}}
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
    function onScanSuccess(decodedText, decodedResult) {
        @this.call('processScan', decodedText);
        console.log(`Scan result: ${decodedText}`);
    }

    function onScanFailure(error) {
        // You can ignore this
    }

    // This ensures the script runs when the page loads
    document.addEventListener('livewire:navigated', () => {
        
        let html5QrcodeScanner = new Html5QrcodeScanner(
            "qr-reader",
            {
                fps: 10,
                
                // Let's make the scanning box a bit bigger
                qrbox: { width: 300, height: 300 },

                // --- THIS IS THE CRITICAL FIX ---
                // Use the browser's native, faster BarcodeDetector if available.
                // This makes scanning screens MUCH more reliable.
                experimentalFeatures: {
                    useBarCodeDetector: true
                },
                // --- END FIX ---
            },
            false // verbose=false
        );

        // Start the scanner
        html5QrcodeScanner.render(onScanSuccess, onScanFailure);
    });
</script>
</x-filament-panels::page>