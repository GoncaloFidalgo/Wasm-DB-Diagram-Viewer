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

if (!window.diagramListenersBound) {
        window.addEventListener('trigger-rust-save', () => {
        if (window.wasmHandle) {
            window.wasmHandle.trigger_save();
        }
    });

    window.addEventListener('trigger-export-png', () => {
        if (window.wasmHandle) {
            window.wasmHandle.trigger_export();
        }
    });

    window.addEventListener('trigger-export-txt', (event) => {
    if (event.detail && event.detail.name) {
            window.diagramExportName = event.detail.name;
        }
            if (window.wasmHandle) {
                window.wasmHandle.trigger_txt_export();
            }
    });

    window.diagramListenersBound = true;
}


    window.saveDiagramState = function (jsonString) {
        //console.timeEnd("LivewireSave");
        Livewire.dispatch('save-diagram', {jsonPayload: jsonString});
        new FilamentNotification()
            .title('Sucesso!')
            .body('Diagrama guardado com sucesso.')
            .success()
            .send();
    };
    document.addEventListener('livewire:navigated', initWasm, {once: true});

    if (document.readyState === 'complete') {
        initWasm();
    }


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

        // Descobrir os limites reais do diagrama (Bounding Box)
        let minX = width, minY = height, maxX = 0, maxY = 0;
        let hasContent = false;

        // Percorre todos os pixeis. Se o Canal Alpha (índice 3) for maior que 0, não é transparente
        for (let y = 0; y < height; y++) {
            for (let x = 0; x < width; x++) {
                const alpha = pixelsArray[(y * width + x) * 4 + 3];
                if (alpha > 0) {
                    if (x < minX) minX = x;
                    if (x > maxX) maxX = x;
                    if (y < minY) minY = y;
                    if (y > maxY) maxY = y;
                    hasContent = true;
                }
            }
        }

        if (!hasContent) {
            console.warn("O diagrama está completamente vazio!");
            return;
        }

        // Adiciona uma margem de segurança de 20px ao redor do diagrama
        const padding = 20;
        minX = Math.max(0, minX - padding);
        minY = Math.max(0, minY - padding);
        maxX = Math.min(width, maxX + padding);
        maxY = Math.min(height, maxY + padding);

        const cropWidth = maxX - minX;
        const cropHeight = maxY - minY;

        // Colocar os pixeis originais num canvas temporário
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = width;
        tempCanvas.height = height;
        const tempCtx = tempCanvas.getContext('2d');

        const clampedArray = new Uint8ClampedArray(pixelsArray.buffer, pixelsArray.byteOffset, pixelsArray.length);
        const imageData = new ImageData(clampedArray, width, height);
        tempCtx.putImageData(imageData, 0, 0);

        // Desenhar APENAS a parte recortada no canvas Final
        const finalCanvas = document.createElement('canvas');
        finalCanvas.width = cropWidth;
        finalCanvas.height = cropHeight;
        const finalCtx = finalCanvas.getContext('2d');

        finalCtx.drawImage(tempCanvas, minX, minY, cropWidth, cropHeight, 0, 0, cropWidth, cropHeight);

        // Exportar o Canvas Final Limpo
        try {
            finalCanvas.toBlob(function (blob) {
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.download = '{{ $this->diagramName }}.png';
                link.href = url;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                setTimeout(() => URL.revokeObjectURL(url), 150);
            }, 'image/png');
        } catch (e) {
            const link = document.createElement('a');
            link.download = '{{ $this->diagramName }}.png';
            link.href = finalCanvas.toDataURL('image/png');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    };



    window.diagramExportName = 'diagrama';
    window.downloadTextFile = function(content) {
        const blob = new Blob([content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.download = `${window.diagramExportName}.txt`;
        link.href = url;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };
    window.addEventListener('trigger-rust-sync', () => {
        if (window.wasmHandle) {
            window.wasmHandle.trigger_sync();
        }
    });

    window.openSyncModal = function (jsonString) {
        Livewire.dispatch('update-sync-json', {jsonString: jsonString});
    };
</script>
