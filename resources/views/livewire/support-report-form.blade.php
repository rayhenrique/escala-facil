<div>
    <x-flash-notifications />

    @if ($protocol)
        <div class="rounded-xl border border-primary/30 bg-primary-soft/50 p-5" role="status" aria-live="polite">
            <p class="flex items-center gap-2 text-sm font-semibold text-ink">
                <x-nav-icon name="check-circle" class="size-5 text-primary" />
                Relato enviado com sucesso.
            </p>
            <p class="mt-2 text-sm text-muted">Número de protocolo:</p>
            <p class="mt-1 font-mono text-lg font-semibold text-primary">{{ $protocol }}</p>
            <p class="mt-2 text-xs text-muted">Anote este protocolo e informe à gerência caso precise acompanhar o atendimento.</p>
            <button type="button" wire:click="reportAgain" class="button-secondary mt-4">Enviar outro relato</button>
        </div>
    @else
        <form wire:submit="submit" x-data="{ uploading: false }" x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-cancel="uploading = false" class="mt-4 space-y-5">
            <div>
                <label for="report-module" class="field-label">Módulo onde o problema ocorreu</label>
                <select wire:model="module" id="report-module" class="field-input" @error('module') aria-invalid="true" aria-describedby="report-module-error" @enderror>
                    <option value="">Selecione o módulo</option>
                    @foreach ($modules as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('module') <p id="report-module-error" class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="report-description" class="field-label">Descrição do problema</label>
                <textarea wire:model="description" id="report-description" rows="5" maxlength="2000" class="field-input min-h-32 resize-none" placeholder="Ex.: ao aprovar a troca TROCA-000123, a tela ficou em branco e precisei recarregar." @error('description') aria-invalid="true" aria-describedby="report-description-error" @enderror></textarea>
                @error('description') <p id="report-description-error" class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="report-expected" class="field-label">Comportamento esperado</label>
                <textarea wire:model="expectedBehavior" id="report-expected" rows="4" maxlength="2000" class="field-input min-h-28 resize-none" placeholder="Ex.: esperava ver a mensagem de confirmação e o comprovante da troca aprovada." @error('expectedBehavior') aria-invalid="true" aria-describedby="report-expected-error" @enderror></textarea>
                @error('expectedBehavior') <p id="report-expected-error" class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="report-attachment" class="field-label">Anexo <span class="font-normal text-muted">(opcional)</span></label>
                <input wire:model="attachment" id="report-attachment" type="file" accept=".pdf,.jpg,.jpeg,.png" class="file-input" @error('attachment') aria-invalid="true" aria-describedby="report-attachment-error" @enderror>
                <p class="mt-2 text-xs text-muted">PDF, JPG ou PNG de até 5 MB. Não envie senhas ou documentos com dados sensíveis além do necessário.</p>
                @error('attachment') <p id="report-attachment-error" class="field-error">{{ $message }}</p> @enderror
            </div>

            <button type="submit" :disabled="uploading" class="button-primary w-full sm:w-auto">Enviar relato</button>
        </form>
    @endif
</div>
