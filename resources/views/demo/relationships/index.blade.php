@extends('demo.layout')

@section('title', "Relationships — {$beneficiary['name']} — ORWeMS Demo")

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4><i class="bi bi-people me-2 text-primary"></i>Relationship Management</h4>
            <p class="text-muted small mb-0">{{ $beneficiary['name'] }} — <code>{{ $beneficiary['ref'] }}</code></p>
        </div>
        <a href="{{ route('demo.care.index', $beneficiary['ref']) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Care History</a>
    </div>

    <div class="card card-demo">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Existing Relationships</h5>
            <span class="simulated-badge">Synthetic data — no database queries</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Name</th><th>Relation</th><th>Contact</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    @foreach($relationships as $rel)
                        <tr>
                            <td>{{ $rel['name'] }}</td>
                            <td>{{ $rel['relation'] }}</td>
                            <td>{{ $rel['contact'] }}</td>
                            <td><span class="badge bg-success">{{ $rel['status'] }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card card-demo mt-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Add Relationship (Simulated)</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('demo.relationships.store', $beneficiary['ref']) }}" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Enter name" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Relationship</label>
                    <input type="text" name="relation" class="form-control" placeholder="e.g. Foster Caregiver" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Contact</label>
                    <input type="tel" name="contact" class="form-control" placeholder="Phone number" required>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-plus me-1"></i>Simulate Add
                    </button>
                    <span class="ms-3 simulated-badge">Not persisted</span>
                </div>
            </form>
        </div>
    </div>
@endsection