<style>
    .fi-page-header-main-ctn {
        padding-block: 0 !important;
    }

    header.fi-page-header {
        display: none !important;
    }

    .fi-main {
        padding-top: 0 !important;
    }

    html {
        /* Remove touch delay: */
        touch-action: manipulation;
    }
    .fi-main-ctn {
        padding-top: 0 !important;
    }
    #canvas_id {
        margin-right: auto;
        margin-left: auto;
        display: block;
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;

    }

    #wasm-container {
        position: relative;
        overflow: hidden;
        margin: 0 !important;
        padding: 0 !important;
        height: 90vh;
        width: 100% !important;
        z-index: 10;
        top: -20px;
    }
    @media (max-width: 1023px) {
        .custom-toolbar > div {
            flex-basis: 100% !important;
            max-width: 100% !important;
            margin-bottom: 1rem;
        }
    }
    @keyframes loader-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .fi-sc-section {
        zoom: 0.9;
    }

</style>

<div id="wasm-container" class="bg-gray-50 dark:bg-gray-950" wire:ignore>

    <canvas
        data-schema="{{ $this->schemaJson }}"
        data-readonly="{{ $this->isPublished ? 'true' : 'false' }}"
        id="canvas_id"
        class="block outline-none"
    />

    <div id="loading_text"
         style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 999999; background-color: rgba(17, 24, 39, 0.7); backdrop-filter: blur(4px); align-items: center; justify-content: center; flex-direction: column;">

        <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 2rem; border-radius: 1rem; background-color: rgba(31, 41, 55, 0.95); box-shadow: 0 0 50px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1);">

            <div style="width: 2.5rem; height: 2.5rem; border-radius: 50%; border: 4px solid rgba(255,255,255,0.1); border-top-color: #3b82f6; animation: loader-spin 1s linear infinite;"></div>

            <p style="font-size: 0.875rem; font-weight: 600; color: white; letter-spacing: 0.05em; text-transform: uppercase; margin: 0; font-family: sans-serif;">
                A carregar diagrama...
            </p>

        </div>
    </div>

</div>
@php
    $jsPath = public_path('wasm/rust_wasm_diagram_viewer.js');
    $wasmPath = public_path('wasm/rust_wasm_diagram_viewer_bg.wasm');

    $jsVersion = file_exists($jsPath) ? filemtime($jsPath) : time();
    $wasmVersion = file_exists($wasmPath) ? filemtime($wasmPath) : time();
@endphp
<script>
    window.hasUnsavedChanges = false;
    window.showCanvasLoader = function() {
        console.log("A mostrar o loader...");
        const loader = document.getElementById('loading_text');

        if (loader) {
            if (loader.parentNode !== document.body) {
                document.body.appendChild(loader);
            }

            loader.style.setProperty('display', 'flex', 'important');
            loader.style.setProperty('z-index', '999999', 'important');
        }
    };

    window.hideCanvasLoader = function() {
        console.log("A esconder o loader...");
        const loader = document.getElementById('loading_text');
        if (loader) {
            loader.style.setProperty('display', 'none', 'important');
        }
    };

    window.handleVersionChange = function(event, element, previousValue) {
        console.log("Dropdown alterado!", previousValue, "->", element.value);

        if (window.hasUnsavedChanges) {
            if (!confirm(`Tem alterações não guardadas. Quer mesmo mudar de versão e perder o progresso?`)) {
                event.stopImmediatePropagation();
                event.preventDefault();
                element.value = previousValue;
                return previousValue;
            }
        }

        window.hasUnsavedChanges = false;

        if (typeof window.showCanvasLoader === "function") {
            window.showCanvasLoader();
        }

        return element.value;
    };

</script>
<script type="module">
    window.wasmHandle = null;

    async function initWasm() {
        const loadingText = document.getElementById('loading_text');
        const canvas = document.getElementById('canvas_id');
        const isReadOnly = canvas.dataset.readonly === 'true';

        try {
            window.showCanvasLoader()
            // Estas duas linhas são para forçar a atualização do ficheiro wasm para nao usar o que está na cache quando o wasm é atualizado
            // Força o download do novo ficheiro JS
            const wasm = await import('/wasm/rust_wasm_diagram_viewer.js?v={{ $jsVersion }}');
            // Passa o caminho explícito do WASM com a versão para o inicializador
            await wasm.default({
                module_or_path: '/wasm/rust_wasm_diagram_viewer_bg.wasm?v={{ $wasmVersion }}'
            });
            window.wasmHandle = new wasm.WebHandle();
            window.wasmHandle.load_data(canvas.dataset.schema);

            window.hideCanvasLoader()

            window.wasmHandle.start(canvas, isReadOnly).catch(console.error);
        } catch (error) {
            console.error('Erro a carregar o wasm', error);
            if (loadingText) {
                loadingText.innerHTML = `<div class='bg-red-500/80 p-4 rounded-lg text-white text-sm font-bold shadow-xl backdrop-blur-md'>Erro ao carregar o diagrama.</div>`;
            }
        }
    }
    window.addEventListener('beforeunload', function (e) {
        if (window.hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = true;
        }
    });
    window.addEventListener('trigger-rust-save', () => {
        if (window.wasmHandle) {
            window.wasmHandle.trigger_save();
            // Simula um movimento do rato para acordar o eframe caso esteja parado
            const canvas = document.getElementById('canvas_id');
            if (canvas) canvas.dispatchEvent(new MouseEvent('mousemove'));
        }
    });

    document.addEventListener('livewire:navigated', initWasm, {once: true});

    if (document.readyState === 'complete') {
        initWasm();
    }

    window.saveDiagramState = function (jsonString) {
        Livewire.dispatch('save-diagram', {jsonPayload: jsonString});
    };
    window.addEventListener('reload-wasm-schema', (event) => {
        if (window.wasmHandle) {
            const schema = event.detail.schema;
            const isReadOnly = event.detail.isReadOnly;
            // Carrega o novo JSON do diagrama
            window.wasmHandle.load_data(schema);

            // Atualiza o estado do diagrama diretamente no wasm
            if (typeof window.wasmHandle.set_read_only === 'function') {
                window.wasmHandle.set_read_only(isReadOnly);
            }

            window.hasUnsavedChanges = event.detail.hasUnsavedChanges ?? false;
        }
        if (typeof window.hideCanvasLoader === 'function') {
            window.hideCanvasLoader();
        }
    });

    window.savePixelsAsPng = function (width, height, pixelsArray) {
        if (!pixelsArray || pixelsArray.length === 0) {
            console.error("Erro: O Rust enviou um array de pixels vazio.");
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');

        // Copia segura da memória do WebAssembly
        const clampedArray = new Uint8ClampedArray(pixelsArray.buffer, pixelsArray.byteOffset, pixelsArray.length);
        const imageData = new ImageData(clampedArray, width, height);
        ctx.putImageData(imageData, 0, 0);

        try {
            // Tenta usar Blob (Mais rápido e consome menos memória)
            canvas.toBlob(function (blob) {
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.download = '{{ $this->diagramName }}.png';
                link.href = url;
                document.body.appendChild(link); // Necessário no Firefox
                link.click();
                document.body.removeChild(link);
                setTimeout(() => URL.revokeObjectURL(url), 150);
            }, 'image/png');
        } catch (e) {
            console.warn("Blob bloqueado por falta de HTTPS. A usar DataURL de segurança...");
            // Ignora o bloqueio de segurança
            const link = document.createElement('a');
            link.download = '{{ $this->diagramName }}.png';
            link.href = canvas.toDataURL('image/png');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    };
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('trigger-export-png', () => {
            if (window.wasmHandle) {
                window.wasmHandle.trigger_export();
                const canvas = document.getElementById('canvas_id');
                if (canvas) {
                    canvas.dispatchEvent(new MouseEvent('mousemove'));
                }
            }
        });
    });
    window.addEventListener('trigger-rust-sync', () => {
        if (window.wasmHandle) {
            window.wasmHandle.trigger_sync();
        }
    });

    window.openSyncModal = function (jsonString) {
        Livewire.dispatch('update-sync-json', {jsonString: jsonString});
    };
</script>
