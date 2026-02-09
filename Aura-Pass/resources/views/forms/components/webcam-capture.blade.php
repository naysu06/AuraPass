<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <!-- Load Cropper.js Styles and Scripts locally for this component -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

    <div x-data="{
        stream: null,
        image: $wire.entangle('{{ $getStatePath() }}'), // Final cropped image sent to backend
        rawImage: null, // Temporary raw capture from webcam
        cameraOptions: [],
        selectedCamera: '',
        cropper: null,
        mode: 'camera', // States: 'camera', 'cropping', 'preview'
        
        init() {
            // Check if we already have an image (Edit mode)
            if (this.image) {
                this.mode = 'preview';
            } else {
                this.initCamera();
            }
        },

        initCamera() {
            navigator.mediaDevices.enumerateDevices()
                .then(devices => {
                    this.cameraOptions = devices.filter(device => device.kind === 'videoinput');
                    if (this.cameraOptions.length > 0) {
                        // Attempt to find external camera first, else default
                        this.selectedCamera = this.cameraOptions[this.cameraOptions.length - 1].deviceId;
                        this.startCamera();
                    }
                });
        },

        startCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
            }
            if (!this.selectedCamera) return;

            navigator.mediaDevices.getUserMedia({ 
                video: { 
                    deviceId: { exact: this.selectedCamera },
                    width: { ideal: 1280 }, // Higher res for better cropping
                    height: { ideal: 720 }
                } 
            })
            .then(stream => {
                this.stream = stream;
                this.$refs.video.srcObject = stream;
                this.$refs.video.play();
            })
            .catch(err => console.error('Camera Error:', err));
        },

        capture() {
            const canvas = document.createElement('canvas');
            canvas.width = this.$refs.video.videoWidth;
            canvas.height = this.$refs.video.videoHeight;
            const ctx = canvas.getContext('2d');
            
            // Mirror logic for capture
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(this.$refs.video, 0, 0);
            
            // 1. Save raw image for cropping
            this.rawImage = canvas.toDataURL('image/jpeg', 1.0);
            
            // 2. Switch to cropping mode
            this.mode = 'cropping';
            
            // 3. Initialize Cropper (Wait for DOM to update with rawImage)
            this.$nextTick(() => {
                if (this.cropper) this.cropper.destroy();
                this.cropper = new Cropper(this.$refs.cropImage, {
                    aspectRatio: 1, // Force Square (1:1) for profile pics
                    viewMode: 1,
                    autoCropArea: 0.8,
                });
            });
        },

        saveCrop() {
            // Get cropped result
            this.image = this.cropper.getCroppedCanvas({
                width: 500,  // Resize to reasonable dimensions
                height: 500
            }).toDataURL('image/jpeg', 0.9);

            // Cleanup
            this.cropper.destroy();
            this.cropper = null;
            this.mode = 'preview';
        },

        retake() {
            this.image = null;
            this.rawImage = null;
            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }
            this.mode = 'camera';
            this.startCamera();
        }
    }">
        
        <!-- WRAPPER -->
        <div class="max-w-sm mx-auto space-y-3">
            
            <!-- 1. CAMERA MODE -->
            <div x-show="mode === 'camera'">
                <!-- UPDATED: Added dark mode classes to the select -->
                <select 
                    x-model="selectedCamera" 
                    @change="startCamera()" 
                    class="block w-full mb-2 text-sm border-gray-300 rounded-lg shadow-sm 
                           bg-white text-gray-900 
                           dark:bg-gray-800 dark:border-gray-600 dark:text-white
                           focus:border-primary-500 focus:ring-primary-500"
                >
                    <template x-for="cam in cameraOptions" :key="cam.deviceId">
                        <option :value="cam.deviceId" x-text="cam.label || 'Camera ' + (cameraOptions.indexOf(cam) + 1)"></option>
                    </template>
                </select>

                <div class="relative w-full overflow-hidden bg-black rounded-lg aspect-[4/3] border-2 border-gray-300 shadow-md">
                    <video x-ref="video" class="w-full h-full object-cover" style="transform: scaleX(-1);" autoplay playsinline></video>
                </div>

                <button type="button" @click="capture()" class="w-full mt-3 px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    Capture
                </button>
            </div>

            <!-- 2. CROPPING MODE -->
            <div x-show="mode === 'cropping'">
                <div class="relative w-full bg-black rounded-lg overflow-hidden border-2 border-blue-500">
                    <!-- Image to be cropped -->
                    <img x-ref="cropImage" :src="rawImage" class="block max-w-full">
                </div>
                
                <div class="flex gap-2 mt-3">
                    <button type="button" @click="retake()" class="flex-1 px-3 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="button" @click="saveCrop()" class="flex-1 px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-bold">
                        Confirm Crop
                    </button>
                </div>
            </div>

            <!-- 3. PREVIEW MODE (Final Result) -->
            <div x-show="mode === 'preview'">
                <div class="relative w-full overflow-hidden bg-black rounded-lg aspect-square border-2 border-green-500 shadow-md">
                    <img :src="image" class="w-full h-full object-contain">
                </div>

                <button type="button" @click="retake()" class="w-full mt-3 px-3 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    Retake Photo
                </button>
            </div>

        </div>
    </div>
</x-dynamic-component>