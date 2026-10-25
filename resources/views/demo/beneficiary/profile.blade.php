@extends('demo.layout')

@section('title', "Beneficiary {$beneficiary['name']} — ORWeMS Demo")

@section('content')
    <div class="row mb-3">
        <div class="col-md-6">
            <h4><i class="bi bi-person me-2 text-primary"></i>{{ $beneficiary['name'] }}</h4>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('demo.dashboard') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Dashboard</a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card card-demo bg-light">
                <div class="card-body text-center">
                    <span class="simulated-badge">Fictional record</span>
                    <h5 class="card-title">{{ $beneficiary['name'] }}</h5>
                    <p class="text-muted small">{{ $beneficiary['age'] }} years old &bull; {{ $beneficiary['gender'] }}</p>
                    <p class="text-muted small">{{ $beneficiary['location'] }}</p>
                    <span class="badge bg-{{ $beneficiary['status'] === 'Active' ? 'success' : 'warning' }}">{{ $beneficiary['status'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card card-demo">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase">Case Reference</h6>
                    <p class="mb-1"><code>{{ $beneficiary['ref'] }}</code></p>
                    <h6 class="text-muted small text-uppercase">Case Worker</h6>
                    <p class="mb-1">{{ $beneficiary['caseworker'] }}</p>
                    <h6 class="text-muted small text-uppercase">Enrolled</h6>
                    <p class="mb-0">{{ $beneficiary['enrolled_at'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card card-demo">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase">Placement</h6>
                    <p class="mb-1">{{ $beneficiary['placement_type'] }}</p>
                    <h6 class="text-muted small text-uppercase">Caregiver</h6>
                    <p class="mb-0">{{ $beneficiary['caregiver'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-demo">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-heart-pulse me-2 text-primary"></i>Health Concerns</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Type</th><th>Date</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    @foreach($beneficiary['health_concerns'] as $concern)
                        <tr>
                            <td>{{ $concern['type'] }}</td>
                            <td>{{ $concern['date'] }}</td>
                            <td><span class="badge bg-{{ $concern['status'] === 'Up to date' || $concern['status'] === 'Clear' ? 'success' : 'warning' }}">{{ $concern['status'] }}</span></td>
                        </tr>
                    @endforeach
                    @if(empty($beneficiary['health_concerns']))
                        <tr><td colspan="3" class="text-muted text-center py-3">No health concerns on record.</td></tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-4 mb-3">
            <a href="{{ route('demo.care.index', $beneficiary['ref']) }}" class="btn btn-outline-primary w-100"><i class="bi bi-house-heart me-1"></i>Care / Placement History</a>
        </div>
        <div class="col-md-4 mb-3">
            <a href="{{ route('demo.education.index', $beneficiary['ref']) }}" class="btn btn-outline-primary w-100"><i class="bi bi-book me-1"></i>Education History</a>
        </div>
        <div class="col-md-4 mb-3">
            <a href="{{ route('demo.health.index', $beneficiary['ref']) }}" class="btn btn-outline-primary w-100"><i class="bi bi-heart-pulse me-1"></i>Health Records</a>
        </div>
    </div>
@endsection