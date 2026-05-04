<x-filament-panels::page>
    <div class="space-y-6">

        {{ $this->connectionForm }}

        @if(!empty($this->extractedTables))
            <div wire:key="details-form">
                {{ $this->detailsForm }}
            </div>
        @endif

    </div>
</x-filament-panels::page>
