@extends('demo.layout')

@section('title', "Education — {$beneficiary['name']} — ORWeMS Demo")

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4><i class="bi bi-book me-2 text-primary"></i>Education History</h4>
            <p class="text-muted small mb-0">{{ $beneficiary['name'] }} — <code>{{ $beneficiary['ref'] }}</code></p>
        </div>
        <a href="{{ route('demo.beneficiary', $beneficiary['ref']) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Profile</a>
    </div>

    <div class="card card-demo">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Education Enrolments</h5>
            <span class="simulated-badge">Synthetic data — no database queries</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>School</th><th>Level</th><th>Programme</th><th>Enrolment Date</th><th>Status</th><th>Term</th><th>Attendance</th><th>Teacher</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($enrolments as $e)
                        <tr>
                            <td>{{ $e['school'] }}</td>
                            <td>{{ $e['level'] }}</td>
                            <td>{{ $e['programme'] }}</td>
                            <td>{{ $e['enrolment_date'] }}</td>
                            <td><span class="badge bg-{{ $e['status'] === 'Current' ? 'success' : 'secondary' }}">{{ $e['status'] }}</span></td>
                            <td>{{ $e['term'] }}</td>
                            <td>{{ $e['attendance'] }}</td>
                            <td>{{ $e['teacher'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection