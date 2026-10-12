@extends('layouts.app')

@section('title', 'Edit Application')

@section('content')
    <div class="page-header">
        <h1>Edit Application</h1>
        <div>
            <a href="{{ route('applications.show', $application) }}" class="btn btn-secondary">View Status</a>
            <a href="{{ route('applications.summary', $application) }}" class="btn">Review &amp; Submit</a>
        </div>
    </div>

    @if ($application->status === \App\Models\Application::STATUS_FOR_CORRECTION)
        <div class="alert alert-warning">
            This application was returned for correction. Please review the BPLO remarks on the
            <a href="{{ route('applications.show', $application) }}">status page</a>, update the flagged items, and resubmit.
        </div>
    @endif

    <div class="card">
        <h2>Business Details</h2>
        <form method="POST" action="{{ route('applications.update', $application) }}">
            @csrf
            @method('PUT')
            @include('applicant._form-fields', ['application' => $application])
            <button type="submit" class="btn">Save Changes</button>
        </form>
    </div>

    <div class="card">
        <h2>Requirements</h2>
        <p class="muted">
            Choose a file for each document, then click <strong>Upload All Requirements</strong> once.
            Allowed: {{ implode(', ', (array) config('permit.uploads.mimes')) }}. Max {{ round(config('permit.uploads.max_kb') / 1024, 1) }} MB each.
        </p>

        {{-- One form for every document: pick files, upload them all together. --}}
        <form method="POST" action="{{ route('applications.requirements.uploadMany', $application) }}" enctype="multipart/form-data">
            @csrf
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Required</th>
                            <th>Status</th>
                            <th>Uploaded File</th>
                            <th>Choose File</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requirementTypes as $type)
                            @php($req = $application->requirements->firstWhere('requirement_type_id', $type->id))
                            <tr>
                                <td>
                                    {{ $type->name }}
                                    @if ($req && $req->correction_note)
                                        <div class="field-error">Note: {{ $req->correction_note }}</div>
                                    @endif
                                </td>
                                <td>{{ $type->is_required ? 'Yes' : 'Optional' }}</td>
                                <td>
                                    @if ($req)
                                        <span class="badge {{ $req->checklist_status === 'COMPLETE' ? 'badge-green' : ($req->checklist_status === 'FOR_CORRECTION' ? 'badge-yellow' : 'badge-gray') }}">
                                            {{ $req->checklist_status }}
                                        </span>
                                    @else
                                        <span class="badge badge-gray">MISSING</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($req && $req->stored_path)
                                        <a href="{{ route('requirements.file', $req) }}">{{ $req->original_filename }}</a>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <input type="file" name="files[{{ $type->id }}]"
                                           accept="{{ collect((array) config('permit.uploads.mimes'))->map(fn ($m) => '.'.$m)->implode(',') }}">
                                    @error('files.'.$type->id) <div class="field-error">{{ $message }}</div> @enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @error('files') <div class="field-error" style="margin-top:10px">{{ $message }}</div> @enderror

            <div style="margin-top:16px">
                <button type="submit" class="btn btn-success">Upload All Requirements</button>
                <span class="muted" style="margin-left:8px">You can upload now and add or replace files later.</span>
            </div>
        </form>

        {{-- Remove buttons are separate (a form can't nest inside the upload form). --}}
        @php($uploaded = $application->requirements->filter(fn ($r) => $r->stored_path))
        @if ($uploaded->isNotEmpty())
            <div style="margin-top:18px">
                <h3>Remove an uploaded file</h3>
                <div style="display:flex;flex-wrap:wrap;gap:8px">
                    @foreach ($requirementTypes as $type)
                        @php($req = $application->requirements->firstWhere('requirement_type_id', $type->id))
                        @if ($req && $req->stored_path)
                            <form method="POST" action="{{ route('applications.requirements.remove', [$application, $type]) }}"
                                  onsubmit="return confirm('Remove the uploaded {{ $type->name }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Remove {{ $type->name }}</button>
                            </form>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
