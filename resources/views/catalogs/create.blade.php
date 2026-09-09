@extends('layouts.app')

@section('title', 'Subir Catálogo | Ferrevital')

@section('extra_css')
    <style>
        .top-bar {
            margin-bottom: 2rem;
        }

        .top-bar h1 {
            font-size: 1.8rem;
            margin: 0;
            background: linear-gradient(to right, var(--primary), #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .upload-card {
            background-color: var(--card-bg);
            padding: 2.5rem;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            max-width: 600px;
            margin: 0 auto;
        }

        .drop-zone {
            border: 2px dashed var(--border-color);
            border-radius: 0.75rem;
            padding: 3rem 2rem;
            text-align: center;
            background-color: rgba(0,0,0,0.01);
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 1.5rem;
        }

        .drop-zone:hover, .drop-zone.dragover {
            border-color: var(--primary);
            background-color: rgba(99, 102, 241, 0.05);
        }

        .drop-zone svg {
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .drop-zone p {
            margin: 0;
            color: var(--text-muted);
            font-weight: 500;
        }

        .drop-zone span {
            color: var(--primary);
            font-weight: 600;
        }

        input[type="file"] {
            display: none;
        }

        .file-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: var(--bg-color);
            padding: 1rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
            margin-top: 1rem;
        }

        .file-name {
            font-weight: 500;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .remove-file {
            background: none;
            border: none;
            color: var(--error);
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 0.25rem;
            display: flex;
            align-items: center;
        }
        .remove-file:hover { background-color: var(--error-bg); }

        .btn-submit {
            width: 100%;
            justify-content: center;
            padding: 1rem;
            font-size: 1rem;
            margin-top: 1.5rem;
        }

        /* Loading Overlay */
        #loadingOverlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            color: white;
            backdrop-filter: blur(4px);
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s ease-in-out infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
@endsection

@section('content')
    <div class="top-bar">
        <h1 style="text-align: center;">Subir Nuevo Catálogo</h1>
    </div>

    @if(session('success'))
        <div style="max-width: 600px; margin: 0 auto 1.5rem; padding: 1rem; border-radius: 0.5rem; background: var(--success-bg); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2);">
            {{ session('success') }}
        </div>
    @endif
    
    @if($errors->any())
        <div style="max-width: 600px; margin: 0 auto 1.5rem; padding: 1rem; border-radius: 0.5rem; background: var(--error-bg); color: var(--error); border: 1px solid rgba(239, 68, 68, 0.2);">
            <ul style="margin: 0; padding-left: 1.5rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="upload-card">
        <form action="{{ route('catalogs.store') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
            @csrf
            
            <div class="drop-zone" id="dropZone" onclick="document.getElementById('pdf').click()">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <p>Arrastra tu archivo PDF aquí o <span>haz clic para explorar</span></p>
            </div>
            <input type="file" name="pdf" id="pdf" accept="application/pdf" required>

            <div class="file-info" id="fileInfo" style="display: none;">
                <div class="file-name">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span id="fileNameText">documento.pdf</span>
                </div>
                <button type="button" class="remove-file" id="removeFile" title="Quitar archivo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <button type="submit" class="btn btn-primary btn-submit" id="submitBtn" disabled>
                Procesar Catálogo
            </button>
        </form>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay">
        <div class="spinner"></div>
        <h2 style="margin: 0; font-weight: 600;">Subiendo archivo...</h2>
        <p style="margin-top: 0.5rem; opacity: 0.8;">Por favor no cierres esta ventana.</p>
    </div>
@endsection

@section('extra_scripts')
    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('pdf');
        const fileInfo = document.getElementById('fileInfo');
        const fileNameText = document.getElementById('fileNameText');
        const removeFileBtn = document.getElementById('removeFile');
        const submitBtn = document.getElementById('submitBtn');
        const uploadForm = document.getElementById('uploadForm');
        const loadingOverlay = document.getElementById('loadingOverlay');

        // Drag & Drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
        });

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length) {
                fileInput.files = files;
                updateFileInfo();
            }
        }

        // File Input Change
        fileInput.addEventListener('change', updateFileInfo);

        function updateFileInfo() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                if (file.type === 'application/pdf') {
                    fileNameText.textContent = file.name;
                    fileInfo.style.display = 'flex';
                    dropZone.style.display = 'none';
                    submitBtn.disabled = false;
                } else {
                    alert('Por favor, selecciona un archivo PDF válido.');
                    resetFile();
                }
            }
        }

        // Remove File
        removeFileBtn.addEventListener('click', (e) => {
            e.stopPropagation(); // Evitar click en dropzone
            resetFile();
        });

        function resetFile() {
            fileInput.value = '';
            fileInfo.style.display = 'none';
            dropZone.style.display = 'block';
            submitBtn.disabled = true;
        }

        // Form Submit
        uploadForm.addEventListener('submit', () => {
            loadingOverlay.style.display = 'flex';
            submitBtn.disabled = true;
        });
    </script>
@endsection