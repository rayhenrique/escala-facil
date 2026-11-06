<div>
    <label for="date" class="field-label">Data</label>
    <input x-ref="firstField" wire:model="date" id="date" type="date" class="field-input" @error('date') aria-invalid="true" aria-describedby="date-error" @enderror>
    @error('date') <p id="date-error" class="field-error">{{ $message }}</p> @enderror
</div>
<div>
    <label for="start-time" class="field-label">Início</label>
    <input wire:model="startTime" id="start-time" type="time" class="field-input" @error('start_time') aria-invalid="true" aria-describedby="start-time-error" @enderror>
    @error('start_time') <p id="start-time-error" class="field-error">{{ $message }}</p> @enderror
</div>
<div>
    <label for="end-time" class="field-label">Horário de término</label>
    <input wire:model="endTime" id="end-time" type="time" class="field-input" @error('end_time') aria-invalid="true" aria-describedby="end-time-error" @enderror>
    @error('end_time') <p id="end-time-error" class="field-error">{{ $message }}</p> @enderror
</div>
<div>
    <label for="end-date" class="field-label">Data de término (opcional)</label>
    <input wire:model="endDate" id="end-date" type="date" class="field-input" aria-describedby="end-date-help{{ $errors->has('end_date') ? ' end-date-error' : '' }}" @error('end_date') aria-invalid="true" @enderror>
    <p id="end-date-help" class="mt-2 text-xs leading-5 text-muted">Para 24h, use o mesmo horário e a data do dia seguinte. Sem data, um horário final menor termina no dia seguinte; um horário maior termina no mesmo dia.</p>
    @error('end_date') <p id="end-date-error" class="field-error">{{ $message }}</p> @enderror
</div>
