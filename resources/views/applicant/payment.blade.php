@extends('layouts.app')

@section('title', 'Pay Fees')

@section('content')
    <div class="page-header">
        <h1>Pay Permit Fees</h1>
        <a href="{{ route('applications.show', $application) }}" class="btn btn-secondary">Back</a>
    </div>

    <div class="card">
        <h2>Fee Breakdown</h2>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Fee</th><th class="text-right">Amount</th></tr>
                </thead>
                <tbody>
                    @foreach ($breakdown->lines as $line)
                        <tr>
                            <td>{{ $line['name'] }}</td>
                            <td class="text-right">&#8369;{{ number_format($line['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <th>TOTAL</th>
                        <th class="text-right">&#8369;{{ number_format($breakdown->total, 2) }}</th>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Choose Payment Method</h2>
        @if (empty($methods))
            <p class="muted">No payment methods are currently enabled.</p>
        @else
            <form method="POST" action="{{ route('applications.pay.start', $application) }}">
                @csrf
                <div class="form-group">
                    <label for="method">Payment Method</label>
                    <select id="method" name="method" required>
                        @foreach ($methods as $method)
                            <option value="{{ $method }}">{{ strtoupper($method) }}</option>
                        @endforeach
                    </select>
                    @error('method') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <button type="submit" class="btn btn-success">Pay &#8369;{{ number_format($breakdown->total, 2) }}</button>
            </form>
            <p class="muted" style="margin-top:12px">This is a sandbox payment flow. Confirmation is simulated by the gateway webhook or by BPLO staff.</p>
        @endif
    </div>
@endsection
