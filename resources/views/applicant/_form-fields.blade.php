@php($app = $application ?? null)

<fieldset>
    <legend>Applicant Information</legend>
    <div class="form-row">
        <div class="form-group">
            <label for="applicant_name">Applicant Name</label>
            <input type="text" id="applicant_name" name="applicant_name" value="{{ old('applicant_name', $app?->applicant_name) }}" required>
            @error('applicant_name') <div class="field-error">{{ $message }}</div> @enderror
        </div>
        <div class="form-group">
            <label for="contact_number">Contact Number</label>
            <input type="text" id="contact_number" name="contact_number" value="{{ old('contact_number', $app?->contact_number) }}" placeholder="09171234567" required>
            @error('contact_number') <div class="field-error">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email', $app?->email) }}" required>
        @error('email') <div class="field-error">{{ $message }}</div> @enderror
    </div>
</fieldset>

<fieldset>
    <legend>Business Information</legend>
    <div class="form-group">
        <label for="business_name">Business Name</label>
        <input type="text" id="business_name" name="business_name" value="{{ old('business_name', $app?->business_name) }}" required>
        @error('business_name') <div class="field-error">{{ $message }}</div> @enderror
    </div>
    <div class="form-group">
        <label for="business_address">Business Address</label>
        <input type="text" id="business_address" name="business_address" value="{{ old('business_address', $app?->business_address) }}" required>
        @error('business_address') <div class="field-error">{{ $message }}</div> @enderror
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="barangay">Barangay</label>
            <input type="text" id="barangay" name="barangay" value="{{ old('barangay', $app?->barangay) }}" required>
            @error('barangay') <div class="field-error">{{ $message }}</div> @enderror
        </div>
        <div class="form-group">
            <label for="business_nature">Nature of Business</label>
            <input type="text" id="business_nature" name="business_nature" value="{{ old('business_nature', $app?->business_nature) }}" required>
            @error('business_nature') <div class="field-error">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="ownership_type">Ownership Type</label>
            <select id="ownership_type" name="ownership_type" required>
                @php($ot = old('ownership_type', $app?->ownership_type))
                <option value="">— Select —</option>
                @foreach (['Sole Proprietorship', 'Partnership', 'Corporation', 'Cooperative'] as $opt)
                    <option value="{{ $opt }}" @selected($ot === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
            @error('ownership_type') <div class="field-error">{{ $message }}</div> @enderror
        </div>
        <div class="form-group">
            <label for="number_of_employees">Number of Employees</label>
            <input type="number" id="number_of_employees" name="number_of_employees" min="0" value="{{ old('number_of_employees', $app?->number_of_employees ?? 0) }}" required>
            @error('number_of_employees') <div class="field-error">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="dti_sec_type">DTI / SEC Registration Type</label>
            <select id="dti_sec_type" name="dti_sec_type">
                @php($dt = old('dti_sec_type', $app?->dti_sec_type))
                <option value="">— None —</option>
                <option value="DTI" @selected($dt === 'DTI')>DTI</option>
                <option value="SEC" @selected($dt === 'SEC')>SEC</option>
            </select>
            @error('dti_sec_type') <div class="field-error">{{ $message }}</div> @enderror
        </div>
        <div class="form-group">
            <label for="dti_sec_number">DTI / SEC Number</label>
            <input type="text" id="dti_sec_number" name="dti_sec_number" value="{{ old('dti_sec_number', $app?->dti_sec_number) }}">
            @error('dti_sec_number') <div class="field-error">{{ $message }}</div> @enderror
        </div>
    </div>
</fieldset>
