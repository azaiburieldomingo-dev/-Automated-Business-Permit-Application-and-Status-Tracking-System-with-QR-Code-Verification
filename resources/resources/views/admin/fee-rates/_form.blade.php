@php($r = $feeRate ?? null)
<div class="form-group">
    <label for="fee_type_id">Fee Type</label>
    <select id="fee_type_id" name="fee_type_id" required>
        <option value="">— Select —</option>
        @foreach ($feeTypes as $ft)
            <option value="{{ $ft->id }}" @selected((string) old('fee_type_id', $r?->fee_type_id) === (string) $ft->id)>{{ $ft->name }}</option>
        @endforeach
    </select>
    @error('fee_type_id') <div class="field-error">{{ $message }}</div> @enderror
</div>
<div class="form-group">
    <label for="label">Label</label>
    <input type="text" id="label" name="label" value="{{ old('label', $r?->label) }}" required>
    @error('label') <div class="field-error">{{ $message }}</div> @enderror
</div>
<div class="form-row">
    <div class="form-group">
        <label for="amount">Amount</label>
        <input type="number" step="0.01" min="0" id="amount" name="amount" value="{{ old('amount', $r?->amount) }}" required>
        @error('amount') <div class="field-error">{{ $message }}</div> @enderror
    </div>
    <div class="form-group">
        <label for="calc_type">Calc Type</label>
        <input type="text" id="calc_type" name="calc_type" value="{{ old('calc_type', $r?->calc_type ?? 'fixed') }}">
    </div>
</div>
<div class="form-row">
    <div class="form-group">
        <label for="is_active">Active</label>
        <select id="is_active" name="is_active">
            <option value="1" @selected(old('is_active', $r ? ($r->is_active ? '1' : '0') : '1') === '1')>Yes</option>
            <option value="0" @selected(old('is_active', $r ? ($r->is_active ? '1' : '0') : '1') === '0')>No</option>
        </select>
    </div>
    <div class="form-group">
        <label for="effective_from">Effective From</label>
        <input type="date" id="effective_from" name="effective_from" value="{{ old('effective_from', $r?->effective_from?->format('Y-m-d')) }}">
    </div>
</div>
