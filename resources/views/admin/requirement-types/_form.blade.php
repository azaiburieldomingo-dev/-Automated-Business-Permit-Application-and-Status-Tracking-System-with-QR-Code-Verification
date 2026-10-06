@php($t = $type ?? null)
<div class="form-group">
    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="{{ old('name', $t?->name) }}" required>
    @error('name') <div class="field-error">{{ $message }}</div> @enderror
</div>
<div class="form-group">
    <label for="code">Code (slug)</label>
    <input type="text" id="code" name="code" value="{{ old('code', $t?->code) }}" required>
    @error('code') <div class="field-error">{{ $message }}</div> @enderror
</div>
<div class="form-row">
    <div class="form-group">
        <label for="is_required">Required</label>
        <select id="is_required" name="is_required">
            <option value="1" @selected(old('is_required', $t?->is_required ? '1' : '0') === '1')>Yes</option>
            <option value="0" @selected(old('is_required', $t?->is_required ? '1' : '0') === '0')>No</option>
        </select>
    </div>
    <div class="form-group">
        <label for="is_active">Active</label>
        <select id="is_active" name="is_active">
            <option value="1" @selected(old('is_active', $t ? ($t->is_active ? '1' : '0') : '1') === '1')>Yes</option>
            <option value="0" @selected(old('is_active', $t ? ($t->is_active ? '1' : '0') : '1') === '0')>No</option>
        </select>
    </div>
    <div class="form-group">
        <label for="sort_order">Sort Order</label>
        <input type="number" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', $t?->sort_order ?? 0) }}">
    </div>
</div>
