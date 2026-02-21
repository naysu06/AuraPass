<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>Gym Check-in Kiosk</title>
    
    <script>
        window.kioskConfig = {
            pusherKey: '{{ config("broadcasting.connections.pusher.key") }}',
            pusherCluster: '{{ config("broadcasting.connections.pusher.options.cluster") }}'
        };
    </script>

    @vite(['resources/css/app.css', 'resources/js/monitor.js'])
    
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
        
        /* FIX: Ensure video tag fills the container */
        video { width: 100%; height: auto; border-radius: 0.25rem; display: block; }
    </style>
</head>
<body>

    <!-- 1. FETCH SETTINGS FOR MIRRORING -->
    @php
        $settings = \App\Models\GymSetting::first();
        // Default to true (mirrored) if not set
        $mirror = $settings ? $settings->camera_mirror : true;
    @endphp

    <!-- FLOATING CLOCK WIDGET -->
    <div class="fixed top-6 right-8 z-50 text-right pointer-events-none">
        <div id="clock-time" class="text-5xl font-bold text-white font-mono tracking-wider" style="text-shadow: 2px 2px 8px rgba(0,0,0,0.6);">
            --:--
        </div>
        <div id="clock-date" class="text-lg text-gray-200 font-semibold tracking-wide uppercase mt-1" style="text-shadow: 1px 1px 4px rgba(0,0,0,0.6);">
            ---
        </div>
    </div>

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
                
                <!-- 2. APPLY INVERTED DYNAMIC MIRRORING -->
                <!-- Swapped logic: 1 (Normal) when True, -1 (Flipped) when False to correct the opposite behavior -->
                <div class="mt-4" style="transform: scaleX({{ $mirror ? 1 : -1 }}); display: flex; width: 100%; position: relative;">
                    <video id="qr-video"></video>
                </div>

            </div>
        </div>
    </div>
</body>
</html>