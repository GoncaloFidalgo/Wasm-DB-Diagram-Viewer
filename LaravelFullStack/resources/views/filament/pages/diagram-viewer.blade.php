<x-filament-panels::page>
    <div
        id="wasm-canvas"
        data-schema="{{ $schemaJson }}"
        class="bg-[#909090] dark:bg-[#404040]"
        style="position: fixed; top: 4rem; left: 0; width: 100vw; height: calc(100vh - 4rem); z-index: 10;"
    >
        <canvas
            id="canvas_id"
            class="absolute top-0 left-0 w-full h-full block outline-none"
        ></canvas>

        <div id="loading_text" class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-center text-gray-100 font-sans pointer-events-none z-20">
            <p class="text-base mb-3 drop-shadow-md">A carregar o diagrama...</p>
            <div class="inline-block w-8 h-8 rounded-full border-4 border-t-transparent border-white animate-spin drop-shadow-md"></div>
        </div>
    </div>

    <script type="module">
        async function initWasm() {
            const container = document.getElementById('wasm-canvas');
            const loadingText = document.getElementById('loading_text');
            const canvas = document.getElementById('canvas_id');

            if (!container || !canvas) return;

            document.body.appendChild(container);

            // ResizeObserver to keep the internal canvas pixels sharp!
            const resizeObserver = new ResizeObserver(entries => {
                for (let entry of entries) {
                    canvas.width = entry.contentRect.width;
                    canvas.height = entry.contentRect.height;
                }
            });
            resizeObserver.observe(container);

            try {
                const wasm = await import('/wasm/rust_wasm_diagram_viewer.js');
                await wasm.default();

                const handle = new wasm.WebHandle();
                handle.load_data(container.dataset.schema);

                if (loadingText) loadingText.style.display = 'none';

                handle.start(canvas).catch(console.error);
            } catch (error) {
                console.error('Erro a carregar o wasm', error);
                if (loadingText) {
                    loadingText.innerHTML = `<span class='text-red-400 text-sm drop-shadow-md font-bold'>Erro ao carregar o diagrama.</span>`;
                }
            }
        }

        // Boot Wasm when navigating to the page
        document.addEventListener('livewire:navigated', initWasm, { once: true });
        if (document.readyState === 'complete') {
            initWasm();
        }

        // Clean up the canvas when navigating back to "Os meus diagramas"
        document.addEventListener('livewire:navigating', () => {
            const container = document.getElementById('wasm-canvas');
            if (container && container.parentNode === document.body) {
                document.body.removeChild(container);
            }
        }, { once: true });

        window.saveDiagramState = function(jsonString) {

            Livewire.dispatch('save-diagram', { jsonPayload: jsonString });

        };
    </script>
</x-filament-panels::page>
