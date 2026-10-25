@extends('demo.layout')

@section('title', "Health Records — {$beneficiary['name']} — ORWeMS Demo")

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4><i class="bi bi-heart-pulse me-2 text-primary"></i>Restricted Health Records</h4>
            <p class="text-muted small mb-0">{{ $beneficiary['name'] }} — <code>{{ $beneficiary['ref'] }}</code></p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('demo.beneficiary', $beneficiary['ref']) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Profile</a>
            <a href="{{ route('demo.health.create', $beneficiary['ref']) }}" class="btn btn-outline-primary"><i class="bi bi-plus me-1"></i>Add Record (Simulated)</a>
        </div>
    </div>

    <div class="card card-demo">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Health Record History</h5>
            <span class="simulated-badge">Fictional records — restricted demo access</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Date</th><th>Concern</th><th>Casework Summary</th><th>Status</th><th>Follow-up</th></tr>
                    </thead>
                    <tbody>
                    @foreach($records as $record)
                        <tr>
                            <td>{{ $record['date'] }}</td>
                            <td><span class="badge bg-info text-dark">{{ $record['concern'] }}</span></td>
                            <td class="text-muted small">{{ $record['summary'] }}</td>
                            <td><span class="badge bg-{{ $record['status'] === 'Open' ? 'warning' : 'success' }}">{{ $record['status'] }}</span></td>
                            <td>{{ $record['follow_up'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-warning mt-4">
        <i class="bi bi-shield-lock me-2"></i>
        <strong>Privacy Notice:</strong> Health records are restricted in the production system. This demonstration shows
        fictional records only. No real health data is displayed, and no health data can be saved in this environment.
    </div>
@endsection