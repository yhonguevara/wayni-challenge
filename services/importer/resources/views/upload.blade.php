<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>BCRA File Upload — Wayni</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { background: white; border-radius: 12px; padding: 2.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 560px; width: 100%; }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; color: #1a1a1a; }
        p.subtitle { color: #666; margin-bottom: 2rem; font-size: 0.875rem; }
        
        .tabs { display: flex; gap: 0.5rem; margin-bottom: 2rem; border-bottom: 2px solid #e5e7eb; }
        .tab { padding: 0.75rem 1.5rem; border: none; background: none; cursor: pointer; font-size: 0.875rem; font-weight: 600; color: #6b7280; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
        .tab:hover { color: #6366f1; }
        .tab.active { color: #6366f1; border-bottom-color: #6366f1; }
        
        .mode-description { background: #f9fafb; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.875rem; color: #4b5563; }
        .mode-description strong { color: #1a1a1a; }
        
        .file-input-wrapper { position: relative; margin-bottom: 1.5rem; }
        .file-input-wrapper input[type="file"] { width: 100%; padding: 1rem; border: 2px dashed #d1d5db; border-radius: 8px; cursor: pointer; transition: border-color 0.2s; }
        .file-input-wrapper input[type="file"]:hover { border-color: #6366f1; }
        .file-name { margin-top: 0.5rem; font-size: 0.875rem; color: #4b5563; }
        
        .path-input-wrapper { margin-bottom: 1.5rem; }
        .path-input-wrapper label { display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; }
        .path-input-wrapper input[type="text"] { width: 100%; padding: 0.75rem; border: 2px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; transition: border-color 0.2s; }
        .path-input-wrapper input[type="text"]:focus { outline: none; border-color: #6366f1; }
        .path-input-wrapper .hint { font-size: 0.75rem; color: #6b7280; margin-top: 0.5rem; }
        
        .btn { width: 100%; padding: 0.875rem; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #6366f1; color: white; }
        .btn-primary:hover:not(:disabled) { background: #4f46e5; }
        .btn-primary:disabled { background: #d1d5db; cursor: not-allowed; }
        .btn-retry { background: #ef4444; color: white; margin-top: 1rem; }
        .btn-retry:hover { background: #dc2626; }
        
        .progress-bar { width: 100%; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; margin: 1rem 0; }
        .progress-bar-fill { height: 100%; background: #6366f1; border-radius: 4px; transition: width 0.3s; }
        
        .status { margin-top: 1rem; padding: 1rem; border-radius: 8px; font-size: 0.875rem; }
        .status-success { background: #d1fae5; color: #065f46; }
        .status-error { background: #fee2e2; color: #991b1b; }
        .status-processing { background: #dbeafe; color: #1e40af; }
    </style>
</head>
<body>
    <div class="container" x-data="uploadForm()">
        <h1>BCRA File Upload</h1>
        <p class="subtitle">Select a method to upload BCRA padron files for processing.</p>

        <div class="tabs">
            <button 
                class="tab" 
                :class="{ 'active': mode === 'presigned' }"
                @click="mode = 'presigned'; reset()"
            >
                Mode A: File Upload
            </button>
            <button 
                class="tab" 
                :class="{ 'active': mode === 'local' }"
                @click="mode = 'local'; reset()"
            >
                Mode B: Local Path
            </button>
        </div>

        <!-- Mode A: Pre-signed URL Upload -->
        <div x-show="mode === 'presigned'">
            <div class="mode-description">
                <strong>Mode A:</strong> Upload a file from your computer. The file is uploaded directly to S3 using a pre-signed URL, then processed by the backend.
            </div>

            <div class="file-input-wrapper">
                <input
                    type="file"
                    accept=".txt"
                    @change="handleFileSelect($event)"
                >
                <div class="file-name" x-show="fileName" x-text="fileName"></div>
            </div>

            <button
                class="btn btn-primary"
                :disabled="!file || uploading"
                @click="uploadPresigned()"
            >
                <span x-show="!uploading">Upload File</span>
                <span x-show="uploading">Uploading... <span x-text="progress"></span>%</span>
            </button>

            <div class="progress-bar" x-show="uploading">
                <div class="progress-bar-fill" :style="`width: ${progress}%`"></div>
            </div>
        </div>

        <!-- Mode B: Local Path -->
        <div x-show="mode === 'local'">
            <div class="mode-description">
                <strong>Mode B:</strong> Process a file already present in the server filesystem. Enter the absolute path to the file inside the Docker container.
            </div>

            <div class="path-input-wrapper">
                <label for="local-path">File Path</label>
                <input
                    type="text"
                    id="local-path"
                    placeholder="/app/storage/app/uploads/deudores_bcra.txt"
                    x-model="localPath"
                    @input="status = null"
                >
                <div class="hint">Copy the file into the container first: <code>docker compose cp deudores_bcra.txt importer:/app/storage/app/uploads/</code></div>
            </div>

            <button
                class="btn btn-primary"
                :disabled="!localPath || uploading"
                @click="uploadLocal()"
            >
                <span x-show="!uploading">Process File</span>
                <span x-show="uploading">Processing...</span>
            </button>
        </div>

        <div class="status status-success" x-show="status === 'success'">
            File uploaded successfully! Processing has been queued.
        </div>

        <div class="status status-processing" x-show="status === 'processing'">
            File is being processed. You will be notified when complete.
        </div>

        <div class="status status-error" x-show="status === 'error'">
            <strong>Upload failed:</strong> <span x-text="errorMessage"></span>
            <button class="btn btn-retry" @click="mode === 'presigned' ? uploadPresigned() : uploadLocal()">Retry Upload</button>
        </div>
    </div>

    <script>
        function uploadForm() {
            return {
                mode: 'presigned',
                file: null,
                fileName: '',
                localPath: '',
                uploading: false,
                progress: 0,
                status: null,
                errorMessage: '',

                reset() {
                    this.file = null;
                    this.fileName = '';
                    this.localPath = '';
                    this.uploading = false;
                    this.progress = 0;
                    this.status = null;
                    this.errorMessage = '';
                },

                handleFileSelect(event) {
                    this.file = event.target.files[0];
                    this.fileName = this.file ? this.file.name : '';
                    this.status = null;
                },

                async uploadPresigned() {
                    if (!this.file) return;

                    this.uploading = true;
                    this.progress = 0;
                    this.status = null;
                    this.errorMessage = '';

                    try {
                        // Step 1: Get pre-signed URL
                        const presignResponse = await fetch('/api/presign', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                            body: JSON.stringify({ filename: this.file.name }),
                        });

                        if (!presignResponse.ok) {
                            throw new Error('Failed to get upload URL');
                        }

                        const { upload_url, fields, key } = await presignResponse.json();

                        // Step 2: Upload file to S3 via pre-signed URL
                        const formData = new FormData();
                        Object.entries(fields).forEach(([key, value]) => {
                            formData.append(key, value);
                        });
                        formData.append('file', this.file);

                        await new Promise((resolve, reject) => {
                            const xhr = new XMLHttpRequest();
                            xhr.open('POST', upload_url);

                            xhr.upload.addEventListener('progress', (e) => {
                                if (e.lengthComputable) {
                                    this.progress = Math.round((e.loaded / e.total) * 100);
                                }
                            });

                            xhr.addEventListener('load', () => {
                                if (xhr.status >= 200 && xhr.status < 300) {
                                    resolve();
                                } else {
                                    reject(new Error(`Upload failed with status ${xhr.status}`));
                                }
                            });

                            xhr.addEventListener('error', () => reject(new Error('Network error during upload')));
                            xhr.addEventListener('abort', () => reject(new Error('Upload aborted')));

                            xhr.send(formData);
                        });

                        // Step 3: Notify backend
                        const notifyResponse = await fetch('/api/notify-upload', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                            body: JSON.stringify({
                                key: key,
                            }),
                        });

                        if (!notifyResponse.ok) {
                            throw new Error('Upload succeeded but notification failed');
                        }

                        this.status = 'success';
                    } catch (error) {
                        this.status = 'error';
                        this.errorMessage = error.message || 'An unexpected error occurred';
                    } finally {
                        this.uploading = false;
                    }
                },

                async uploadLocal() {
                    if (!this.localPath) return;

                    this.uploading = true;
                    this.status = null;
                    this.errorMessage = '';

                    try {
                        const response = await fetch('/api/upload', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                            body: JSON.stringify({ path: this.localPath }),
                        });

                        if (!response.ok) {
                            const errorData = await response.json();
                            throw new Error(errorData.error || 'Failed to process file');
                        }

                        this.status = 'success';
                    } catch (error) {
                        this.status = 'error';
                        this.errorMessage = error.message || 'An unexpected error occurred';
                    } finally {
                        this.uploading = false;
                    }
                },
            };
        }
    </script>
</body>
</html>
