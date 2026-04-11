<?php

use Livewire\Component;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Layout;
use App\Services\DatabaseExtractorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

new #[Layout('layouts::app')] class extends Component {

    public string $engine = 'sqlite';

    #[Validate('required|string')]
    public string $filePath = '';

    public function mount()
    {

        $this->filePath = database_path('chinook.db');
    }

    public function extractSchema()
    {
        $this->validate();

        try {
            $extractor = new DatabaseExtractorService();
            $schemaJson = $extractor->extractSqlite($this->filePath);

            $diagramId = Str::uuid()->toString();

            Cache::put('diagram_' . $diagramId, $schemaJson, now()->addHours(2));

            //nagigate: true, não dá refresh
            return $this->redirect('/diagram/' . $diagramId, navigate: true);

        } catch (\Exception $e) {
            $this->addError('Erro', $e->getMessage());
        }
    }
};
?>

<div class="p-6">
    <h2 class="text-2xl font-semibold text-zinc-800 dark:text-zinc-200 mb-6">Extrair informação de Base de dados SQLite</h2>

    @if (session()->has('error'))
        <div class="mb-4 p-4 text-red-700 bg-red-100 rounded-md dark:bg-red-900/30 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit="extractSchema" class="flex flex-col space-y-4 max-w-md">
        <div>
{{--            <label for="filePath" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">--}}
{{--                Caminho do ficheiro--}}
{{--            </label>--}}
        {{--    <input
                type="text"
                id="filePath"
                wire:model="filePath"
                readonly
                class="w-full rounded-md border-zinc-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:border-zinc-600 dark:text-white dark:placeholder-zinc-500"
            >--}}
            <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 rounded-md border border-blue-200 dark:border-blue-800 text-sm">
                <strong>Base de dados:</strong> {{ basename($filePath) }}
            </div>
{{--            @error('filePath')--}}
{{--            <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>--}}
{{--            @enderror--}}
        </div>

        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-md transition duration-150 ease-in-out flex items-center justify-center">
            <span wire:loading.remove wire:target="extractSchema">Extrair e Gerar diagrama</span>
            <span wire:loading wire:target="extractSchema">A processar...</span>
        </button>
    </form>
</div>
