<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Gym Check-in Scanner</title>
    <style>
        body { font-family: sans-serif; display: grid; place-items: center; min-height: 90vh; background: #f4f4f4; }
        #qr-reader { width: 450px; border: 2px solid #eee; }
        #result-container { margin-top: 20px; text-align: center; }
        .status-active { font-size: 2.5em; color: green; font-weight: bold; }
        .status-expired { font-size: 2.5em; color: red; font-weight: bold; }
        .status-not_found { font-size: 2.5em; color: orange; font-weight: bold; }
        .member-name { font-size: 1.5em; color: #333; }
        .message { font-size: 1em; color: #555; }
    </style>
</head>
<body>

    <div>
        <h1>Front Desk Scanner</h1>
        <div id="qr-reader"></div>
        <div id="result-container">
            <p class="message">Scan member's QR code...</p>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <script>
        // 2. This function runs when a QR is successfully scanned
        function onScanSuccess(decodedText, decodedResult) {
            // decodedText will be "mem_aKqXv5Pz"
            console.log(`Scan result: ${decodedText}`);

            // Stop the scanner
            html5QrcodeScanner.pause();

            // 3. Send the scanned ID to our Laravel API
            sendDataToBackend(decodedText);
        }

        // 4. This function sends the data
        function sendDataToBackend(scannedId) {
            const resultContainer = document.getElementById('result-container');
            resultContainer.innerHTML = '<p class="message">Validating...</p>';

            fetch('/api/check-in-validate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    // Get the CSRF token from the meta tag
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    unique_id: scannedId
                })
            })
            .then(response => response.json())
            .then(data => {
                // 5. Display the result from the API
                displayResult(data);
            })
            .catch(error => {
                console.error('Error:', error);
                displayResult({ status: 'error', message: 'Connection error. Check console.' });
            });
        }

        // 6. This function displays the status visually
        function displayResult(data) {
            const resultContainer = document.getElementById('result-container');
            let name = data.name ? `<p class="member-name">${data.name}</p>` : '';

            resultContainer.innerHTML = `
                <h2 class="status-${data.status}">${data.status.toUpperCase()}</h2>
                ${name}
                <p class="message">${data.message}</p>
            `;

            // 7. Clear the message and restart the scanner after 4 seconds
            setTimeout(() => {
                resultContainer.innerHTML = '<p class="message">Scan member\'s QR code...</p>';
                html5QrcodeScanner.resume();
            }, 4000);
        }

        // 8. Initialize the scanner
        var html5QrcodeScanner = new Html5QrcodeScanner(
            "qr-reader", 
            { fps: 10, qrbox: { width: 250, height: 250 } }
        );

        // Start the scanner
        html5QrcodeScanner.render(onScanSuccess);

    </script>
</body>
</html>