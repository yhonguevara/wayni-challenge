<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BCRA File Upload — Wayni</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { background: white; border-radius: 12px; padding: 2.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 480px; width: 100%; }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; color: #1a1a1a; }
        p.subtitle { color: #666; margin-bottom: 2rem; font-size: 0.875rem; }
        .file-input-wrapper { position: relative; margin-bottom: 1.5rem; }
        .file-input-wrapper input[type="file"] { width: 100%; padding: 1rem; border: 2px dashed #d1d5db; border-radius: 8px; cursor: pointer; transition: border-color 0.2s; }
        .file-input-wrapper input[type="file"]:hover { border-color: #6366f1; }
        .file-name { margin-top: 0.5rem; font-size: 0.875rem; color: #4b5563; }
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
        <p class="subtitle">Select a BCRA padron file (.txt) to upload for processing.</p>

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
            @click="upload()"
        >
            <span x-show="!uploading">Upload File</span>
            <span x-show="uploading">Uploading... <span x-text="progress"></span>%</span>
        </button>

        <div class="progress-bar" x-show="uploading">
            <div class="progress-bar-fill" :style="`width: ${progress}%`"></div>
        </div>

        <div class="status status-success" x-show="status === 'success'">
            File uploaded successfully! Processing has been queued.
        </div>

        <div class="status status-processing" x-show="status === 'processing'">
            File is being processed. You will be notified when complete.
        </div>

        <div class="status status-error" x-show="status === 'error'">
            <strong>Upload failed:</strong> <span x-text="errorMessage"></span>
            <button class="btn btn-retry" @click="upload()">Retry Upload</button>
        </div>
    </div>

    <script>
        function uploadForm() {
            return {
                file: null,
                fileName: '',
                uploading: false,
                progress: 0,
                status: null,
                errorMessage: '',

                handleFileSelect(event) {
                    this.file = event.target.files[0];
                    this.fileName = this.file ? this.file.name : '';
                    this.status = null;
                },

                async upload() {
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

                        const { upload_url, fields } = await presignResponse.json();

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
                                key: `uploads/${this.file.name}`,
                                size: this.file.size,
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
            };
        }
    </script>
</body>
</html>
