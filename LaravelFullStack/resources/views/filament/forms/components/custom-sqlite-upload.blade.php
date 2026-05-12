<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            isDragging: false,
            isUploading: false,
            progress: 0,
            fileName: null,

            handleDrop(event) {
                this.isDragging = false;
                this.processFile(event.dataTransfer.files[0]);
            },

            processFile(file) {
                if (!file) return;

                let ext = file.name.split('.').pop().toLowerCase();
                if (ext !== 'sqlite' && ext !== 'db') {
                    new FilamentNotification()
                        .title('Upload Bloqueado')
                        .body('Apenas ficheiros com extensão .sqlite ou .db são permitidos.')
                        .danger()
                        .send();
                    return;
                }

                this.isUploading = true;
                this.fileName = file.name;

                $wire.upload(
                    '{{ $getStatePath() }}',
                    file,
                    (uploadedFilename) => { this.isUploading = false; },
                    () => {
                        this.isUploading = false;
                        this.fileName = null;
                        new FilamentNotification().title('Erro no servidor').danger().send();
                    },
                    (event) => { this.progress = event.detail.progress; }
                );
            },

            removeFile() {
                this.fileName = null;
                $wire.set('{{ $getStatePath() }}', null);
            }
        }"
        style="width: 100%;"
    >
        <div
            x-show="!fileName"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop($event)"
            @click="$refs.fileInput.click()"
            :style="isDragging ? 'border-color: #3b82f6; border-width: 2px; border-style: dashed; border-radius: 0.75rem; background-color: rgba(59, 130, 246, 0.05);' : 'border-color: #000000; border-width: 2px; border-style: dashed; border-radius: 0.75rem; background-color: transparent;'"
            style="position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2.5rem 2rem; cursor: pointer; transition: all 0.2s ease-in-out;"
        >
            <input
                type="file"
                x-ref="fileInput"
                style="display: none;"
                accept=".sqlite,.db"
                @change="processFile($event.target.files[0])"
            >

            <div style="pointer-events: none; display: flex; flex-direction: column; align-items: center; text-align: center;">
                <x-heroicon-o-arrow-up-tray style="width: 36px; height: 36px; color: #9ca3af; margin-bottom: 0.75rem;" />

                <p style="font-size: 0.875rem; color: #4b5563; margin: 0;">
                    <span style="font-weight: 600; color: #3b82f6;">Clica para carregar</span> ou arrasta para aqui
                </p>
                <p style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem;">Ficheiros .sqlite ou .db</p>
            </div>
        </div>

        <div x-show="fileName" x-transition style="display: none;">

            <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; border: 1px solid #e5e7eb; border-radius: 0.75rem; background-color: #ffffff; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);">

                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="padding: 0.5rem; background-color: rgba(59, 130, 246, 0.1); border-radius: 0.5rem; display: flex; align-items: center; justify-content: center;">
                        <x-heroicon-s-circle-stack style="width: 24px; height: 24px; color: #3b82f6;" />
                    </div>

                    <div>
                        <p style="font-size: 0.875rem; font-weight: 600; color: #111827; margin: 0;" x-text="fileName"></p>

                        <div x-show="isUploading" style="width: 12rem; height: 6px; background-color: #f3f4f6; border-radius: 999px; margin-top: 0.5rem; overflow: hidden;">
                            <div style="height: 100%; background-color: #3b82f6; border-radius: 999px; transition: width 0.3s;" :style="`width: ${progress}%`"></div>
                        </div>

                        <p x-show="!isUploading" style="font-size: 0.75rem; font-weight: 600; color: #16a34a; margin: 0; margin-top: 0.25rem;">Pronto para extrair!</p>
                    </div>
                </div>

                <button
                    type="button"
                    @click="removeFile()"
                    style="display: flex; align-items: center; justify-content: center; padding: 0.5rem; color: #9ca3af; border: none; background: transparent; cursor: pointer; border-radius: 0.5rem; transition: all 0.2s;"
                    onmouseover="this.style.color='#ef4444'; this.style.backgroundColor='#fef2f2';"
                    onmouseout="this.style.color='#9ca3af'; this.style.backgroundColor='transparent';"
                >
                    <x-heroicon-m-trash style="width: 20px; height: 20px;" />
                </button>
            </div>
        </div>
    </div>
</x-dynamic-component>
