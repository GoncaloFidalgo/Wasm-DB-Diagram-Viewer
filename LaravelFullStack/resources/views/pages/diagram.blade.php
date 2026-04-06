<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Cache;

new #[Layout('layouts::app')]
class extends Component {

    public string $diagramId;
    public string $schemaJson = '';

    public function mount($id)
    {
        $this->diagramId = $id;

        $schema = Cache::get('diagram_' . $id);

        if (!$schema) {
            session()->flash('error', 'Diagrama não encontrado ou expirou.');
            return $this->redirect('/extract');
        }

        $this->schemaJson = $schema;
    }
};
?>

<div class="flex flex-col h-[calc(100vh-2rem)] w-full p-4">
    <!-- Wasm Canvas -->
    <div
        id="wasm-canvas"
        data-schema="{{ $schemaJson }}"
        class="relative flex-1 w-full bg-[#909090] dark:bg-[#404040] rounded-lg overflow-hidden shadow-inner touch-manipulation border border-zinc-300 dark:border-zinc-700"
    >
        <canvas id="canvas_id" class="block absolute top-0 left-0 w-full h-full outline-none"/>

        <div id="loading_text"
             class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-center text-gray-100 font-sans pointer-events-none">
            <p class="text-base mb-3 drop-shadow-md">A carregar o diagrama...</p>
            <div
                class="inline-block w-8 h-8 rounded-full border-4 border-t-transparent border-white animate-spin drop-shadow-md"></div>
        </div>
    </div>

    <script type="module">
        async function initWasm() {
            const container = document.getElementById('wasm-canvas');
            const loadingText = document.getElementById('loading_text');
            const canvas = document.getElementById('canvas_id');

            if (!container || !canvas) return;

            try {
                console.log('A carregar Wasm...');

                // Importar e inicializar wasm
                const wasm = await import('/wasm/rust_wasm_diagram_viewer.js');
                await wasm.default();

                // Obter o webhandle para executar as funções da app wasm
                const handle = new wasm.WebHandle();

                // Enviar os dados para a app wasm
                handle.load_data(container.dataset.schema);

                if (loadingText) loadingText.style.display = 'none';

                // Iniciar app
                handle.start(canvas).catch(console.error);

            } catch (error) {
                console.error('Erro a carregar o wasm', error);
                if (loadingText) {
                    loadingText.innerHTML = `<span class='text-red-400 text-sm drop-shadow-md font-bold'>Erro ao carregar o diagrama.</span>`;
                }
            }
        }

        initWasm();
    </script>
</div>
