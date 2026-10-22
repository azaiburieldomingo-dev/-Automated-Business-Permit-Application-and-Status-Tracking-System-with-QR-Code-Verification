@extends('layouts.app')

@section('title', 'Business Profile')

@section('content')
    <div class="page-header">
        <h1>Business Profile</h1>
        <a href="{{ url('/dashboard') }}" class="btn btn-secondary">Back</a>
    </div>

    @unless ($business)
        <div class="alert alert-info">
            You don't have a saved business profile yet. Fill in the details below and save — they'll be
            used to prefill your next business permit application.
        </div>
    @endunless

    <div class="card">
        <h2>Business Details</h2>
        <p class="muted">Keep your business information up to date. This profile prefills new applications.</p>

        <form method="POST" action="{{ route('business-profile.update') }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="name">Business Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $business?->name) }}" required>
                @error('name') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="address">Business Address</label>
                <input type="text" id="address" name="address" value="{{ old('address', $business?->address) }}" required>
                @error('address') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="barangay">Barangay</label>
                    <input type="text" id="barangay" name="barangay" value="{{ old('barangay', $business?->barangay) }}" required>
                    @error('barangay') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="nature">Nature of Business</label>
                    <input type="text" id="nature" name="nature" value="{{ old('nature', $business?->nature) }}" required>
                    @error('nature') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="ownership_type">Ownership Type</label>
                    <select id="ownership_type" name="ownership_type" required>
                        @php($ot = old('ownership_type', $business?->ownership_type))
                        <option value="">— Select —</option>
                        @foreach (['Sole Proprietorship', 'Partnership', 'Corporation', 'Cooperative'] as $opt)
                            <option value="{{ $opt }}" @selected($ot === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                    @error('ownership_type') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="number_of_employees">Number of Employees</label>
                    <input type="number" id="number_of_employees" name="number_of_employees" min="0"
                           value="{{ old('number_of_employees', $business?->number_of_employees ?? 0) }}" required>
                    @error('number_of_employees') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="dti_sec_type">DTI / SEC Registration Type</label>
                    <select id="dti_sec_type" name="dti_sec_type">
                        @php($dt = old('dti_sec_type', $business?->dti_sec_type))
                        <option value="">— None —</option>
                        <option value="DTI" @selected($dt === 'DTI')>DTI</option>
                        <option value="SEC" @selected($dt === 'SEC')>SEC</option>
                    </select>
                    @error('dti_sec_type') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label for="dti_sec_number">DTI / SEC Number</label>
                    <input type="text" id="dti_sec_number" name="dti_sec_number"
                           value="{{ old('dti_sec_number', $business?->dti_sec_number) }}">
                    @error('dti_sec_number') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <button type="submit" class="btn">Save Business Profile</button>
        </form>
    </div>
@endsection
