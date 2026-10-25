@extends('demo.layout')

@section('title', "Care / Placement — {$beneficiary['name']} — ORWeMS Demo")

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4><i class="bi bi-house-heart me-2 text-primary"></i>Care / Placement History</h4>
            <p class="text-muted small mb-0">{{ $beneficiary['name'] }} — <code>{{ $beneficiary['ref'] }}</code></p>
        </div>
        <a href="{{ route('demo.beneficiary', $beneficiary['ref']) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Profile</a>
    </div>

    <div class="card card-demo">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Placement Records</h5>
            <span class="simulated-badge">Synthetic data — no database queries</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th><th>Type</th><th>Placement</th><th>Caregiver</th><th>Worker</th><th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($careRecords as $record)
                        <tr>
                            <td>{{ $record['date'] }}</td>
                            <td>{{ $record['type'] }}</td>
                            <td>{{ $record['placement'] }}</td>
                            <td>{{ $record['caregiver'] }}</td>
                            <td>{{ $record['worker'] }}</td>
                            <td class="text-muted small">{{ $record['notes'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card card-demo mt-4">
        <div class="card-body">
            <h6 class="text-muted small text-uppercase mb-3">Existing Relationships</h6>
            <p class="text-muted">View and manage relationships on the <a href="{{ route('demo.relationships.index', $beneficiary['ref']) }}" class="text-primary">Relationships</a> page.</p>
        </div>
    </div>
@endsection