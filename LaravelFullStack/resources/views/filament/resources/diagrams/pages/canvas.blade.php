<style>
    .fi-page-header-main-ctn { padding-block: 0 !important; }
    header.fi-page-header { display: none !important; }
    .fi-main { padding-top: 0 !important; }

    html {
        /* Remove touch delay: */
        touch-action: manipulation;
    }
    #canvas_id {
        /*position: absolute ;*/
        /*top: 0 ;*/
        /*left: 0 ;*/
        /*right: 0 ;*/
        /*bottom: 0 ;*/
        /*width: 100% ;*/
        /*height: 100% ;*/
        /*max-width: 100% ;*/
        /*max-height: 100% ;*/
        /*margin: 0 ;*/
        /*padding: 0 ;*/

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
        width: 100%;
        z-index: 10;
    }


</style>

<div id="wasm-container" class="bg-gray-50 dark:bg-gray-950" wire:ignore>

    <canvas
        data-schema="{{ $this->schemaJson }}"
        id="canvas_id"
        class="block outline-none"
    />

    <div id="loading_text" class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-center pointer-events-none z-20">
        <div class="flex flex-col items-center gap-3 p-6 rounded-2xl bg-gray-900/50 backdrop-blur-md border border-white/10 shadow-2xl">
            <p class="text-sm font-medium text-white tracking-wide">A carregar diagrama...</p>
            <div class="w-8 h-8 rounded-full border-2 border-white/20 border-t-white animate-spin"></div>
        </div>
    </div>

</div>

<script type="module">
    window.wasmHandle = null;

    async function initWasm() {
        const loadingText = document.getElementById('loading_text');
        const canvas = document.getElementById('canvas_id');

        try {
            const wasm = await import('/wasm/rust_wasm_diagram_viewer.js');
            await wasm.default();

            window.wasmHandle = new wasm.WebHandle();
            window.wasmHandle.load_data(canvas.dataset.schema);

            if (loadingText) loadingText.style.display = 'none';

            window.wasmHandle.start(canvas).catch(console.error);
        } catch (error) {
            console.error('Erro a carregar o wasm', error);
            if (loadingText) {
                loadingText.innerHTML = `<div class='bg-red-500/80 p-4 rounded-lg text-white text-sm font-bold shadow-xl backdrop-blur-md'>Erro ao carregar o diagrama.</div>`;
            }
        }
    }
    window.addEventListener('trigger-rust-save', () => {
        if (window.wasmHandle) {
            window.wasmHandle.trigger_save();
            // Simula um movimento do rato para acordar o eframe caso esteja parado
            const canvas = document.getElementById('canvas_id');
            if(canvas) canvas.dispatchEvent(new MouseEvent('mousemove'));
        }
    });
    document.addEventListener('livewire:navigated', initWasm, { once: true });
    if (document.readyState === 'complete') {
        initWasm();
    }

    window.saveDiagramState = function(jsonString) {
        Livewire.dispatch('save-diagram', { jsonPayload: jsonString });
    };
</script>
