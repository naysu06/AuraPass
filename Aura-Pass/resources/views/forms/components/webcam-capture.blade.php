<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div x-data="{
        stream: null,
        image: $wire.entangle('{{ $getStatePath() }}'),
        cameraOptions: [],
        selectedCamera: '',
        
        init() {
            // List all video devices
            navigator.mediaDevices.enumerateDevices()
                .then(devices => {
                    this.cameraOptions = devices.filter(device => device.kind === 'videoinput');
                    // Default to the last one (usually the external USB cam) if available
                    if (this.cameraOptions.length > 0) {
                        this.selectedCamera = this.cameraOptions[this.cameraOptions.length - 1].deviceId;
                        this.startCamera();
                    }
                });
        },

        startCamera() {
            // Stop previous stream if any
            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
            }
            
            if (!this.selectedCamera) return;

            navigator.mediaDevices.getUserMedia({ 
                video: { 
                    deviceId: { exact: this.selectedCamera },
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                } 
            })
            .then(stream => {
                this.stream = stream;
                this.$refs.video.srcObject = stream;
                this.$refs.video.play();
            })
            .catch(err => {
                console.error('Camera Error:', err);
            });
        },

        capture() {
            const canvas = document.createElement('canvas');
            canvas.width = this.$refs.video.videoWidth;
            canvas.height = this.$refs.video.videoHeight;
            canvas.getContext('2d').drawImage(this.$refs.video, 0, 0);
            
            // Save Base64 string to the Livewire property
            this.image = canvas.toDataURL('image/jpeg', 0.8);
        },

        retake() {
            this.image = null;
            this.startCamera();
        }
    }">
        
        <div class="space-y-3">
            <!-- Camera Selector -->
            <select 
                x-model="selectedCamera" 
                @change="startCamera()"
                class="block w-full border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            >
                <template x-for="cam in cameraOptions" :key="cam.deviceId">
                    <option :value="cam.deviceId" x-text="cam.label || 'Camera ' + (cameraOptions.indexOf(cam) + 1)"></option>
                </template>
            </select>

            <!-- Video Preview Area -->
            <div class="relative w-full overflow-hidden bg-black rounded-lg aspect-video border-2 border-gray-300 dark:border-gray-600">
                <!-- Live Feed -->
                <video x-ref="video" x-show="!image" class="w-full h-full object-cover" autoplay playsinline></video>
                
                <!-- Captured Image -->
                <img x-show="image" :src="image" class="w-full h-full object-cover">
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-center space-x-4">
                <button 
                    x-show="!image"
                    type="button" 
                    @click="capture()"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    Capture Photo
                </button>

                <button 
                    x-show="image"
                    type="button" 
                    @click="retake()"
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 flex items-center gap-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    Retake
                </button>
            </div>
        </div>
    </div>
</x-dynamic-component>