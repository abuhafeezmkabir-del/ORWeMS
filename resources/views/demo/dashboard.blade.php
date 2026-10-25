@extends('demo.layout')

@section('title', 'Dashboard — ORWeMS Demo')

@section('content')
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card card-demo bg-primary text-white h-100">
                <div class="card-body">
                    <h6 class="text-white-50 mb-1">Active Beneficiaries</h6>
                    <h3 class="mb-0">{{ count($beneficiaries) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card card-demo bg-info text-white h-100">
                <div class="card-body">
                    <h6 class="text-white-50 mb-1">Open Health Concerns</h6>
                    <h3 class="mb-0">{{ array_sum(array_column($beneficiaries, 'open_concerns')) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card card-demo bg-success text-white h-100">
                <div class="card-body">
                    <h6 class="text-white-50 mb-1">Follow-up Due</h6>
                    <h3 class="mb-0">2</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card card-demo bg-warning text-dark h-100">
                <div class="card-body">
                    <h6 class="text-dark-50 mb-1">Case Reviews Pending</h6>
                    <h3 class="mb-0">1</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-demo">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-people me-2 text-primary"></i>Beneficiary Overview</h5>
            <span class="simulated-badge">Synthetic data — no database queries</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Open Concerns</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($beneficiaries as $b)
                        <tr>
                            <td><code>{{ $b['ref'] }}</code></td>
                            <td>{{ $b['name'] }}</td>
                            <td>{{ $b['age'] }}</td>
                            <td>{{ $b['location'] }}</td>
                            <td><span class="badge bg-{{ $b['status'] === 'Active' ? 'success' : 'warning' }}">{{ $b['status'] }}</span></td>
                            <td>{{ $b['open_concerns'] }}</td>
                            <td class="text-end">
                                <a href="{{ route('demo.beneficiary', $b['ref']) }}" class="btn btn-sm btn-outline-primary">View Profile</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection