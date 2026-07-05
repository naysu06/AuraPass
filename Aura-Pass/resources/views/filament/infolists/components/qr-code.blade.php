<div class="flex justify-center w-full">
    @php
        $logoPath = public_path('images/logo.png');
        
        // Generate the QR Code directly in the view
        $qrCode = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
            ->size(250)
            ->errorCorrection('H')
            ->merge($logoPath, 0.2, true)
            ->margin(1)
            ->generate($getRecord()->unique_id);
            
        $base64 = base64_encode($qrCode);
    @endphp

    <img src="data:image/png;base64,{{ $base64 }}" alt="QR Code" class="mx-auto rounded-lg shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10" style="height: 250px; width: 250px; object-fit: contain;" />
</div>