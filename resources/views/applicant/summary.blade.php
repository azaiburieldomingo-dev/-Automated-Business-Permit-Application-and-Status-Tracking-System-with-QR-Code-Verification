@extends('layouts.app')

@section('title', 'Review & Submit')

@section('content')
    <div class="page-header">
        <h1>Review &amp; Submit</h1>
        <a href="{{ route('applications.edit', $application) }}" class="btn btn-secondary">Back to Edit</a>
    </div>

    <div class="card">
        <h2>Applicant &amp; Business Details</h2>
        <dl class="kv">
            <dt>Applicant Name</dt><dd>{{ $application->applicant_name }}</dd>
            <dt>Contact Number</dt><dd>{{ $application->contact_number }}</dd>
            <dt>Email</dt><dd>{{ $application->email }}</dd>
            <dt>Business Name</dt><dd>{{ $application->business_name }}</dd>
            <dt>Business Address</dt><dd>{{ $application->business_address }}</dd>
            <dt>Barangay</dt><dd>{{ $application->barangay }}</dd>
            <dt>Nature of Business</dt><dd>{{ $application->business_nature }}</dd>
            <dt>Ownership Type</dt><dd>{{ $application->ownership_type }}</dd>
            <dt>Number of Employees</dt><dd>{{ $application->number_of_employees }}</dd>
            <dt>DTI / SEC</dt><dd>{{ $application->dti_sec_type ? $application->dti_sec_type.' — '.$application->dti_sec_number : '—' }}</dd>
        </dl>
    </div>

    <div class="card">
        <h2>Requirements Checklist</h2>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Document</th><th>Required</th><th>Status</th><th>File</th></tr>
                </thead>
                <tbody>
                    @foreach ($requirementTypes as $type)
                        @php($req = $application->requirements->firstWhere('requirement_type_id', $type->id))
                        <tr>
                            <td>{{ $type->name }}</td>
                            <td>{{ $type->is_required ? 'Yes' : 'Optional' }}</td>
                            <td>
                                @if ($req && $req->checklist_status === 'COMPLETE')
                                    <span class="badge badge-green">COMPLETE</span>
                                @elseif ($type->is_required)
                                    <span class="badge badge-red">MISSING</span>
                                @else
                                    <span class="badge badge-gray">OPTIONAL</span>
                                @endif
                            </td>
                            <td>{{ $req?->original_filename ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <p>Once you submit, a tracking number is assigned and your application is sent to the BPLO for review.</p>
        <form method="POST" action="{{ route('applications.submit', $application) }}">
            @csrf
            <button type="submit" class="btn btn-success">Submit Application</button>
        </form>
    </div>
@endsection
