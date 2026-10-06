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
        <p class="muted">Allowed file types: {{ implode(', ', (array) config('permit.uploads.mimes')) }}. Max size: {{ config('permit.uploads.max_kb') }} KB.</p>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Required</th>
                        <th>Status</th>
                        <th>Uploaded File</th>
                        <th>Action</th>
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
                                <form method="POST" action="{{ route('applications.requirements.upload', [$application, $type]) }}" enctype="multipart/form-data" style="display:flex;gap:6px;align-items:center">
                                    @csrf
                                    <input type="hidden" name="requirement_type_id" value="{{ $type->id }}">
                                    <input type="file" name="file" required>
                                    <button type="submit" class="btn btn-sm">Upload</button>
                                </form>
                                @if ($req)
                                    <form method="POST" action="{{ route('applications.requirements.remove', [$application, $type]) }}" class="inline-form" style="margin-top:6px">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
