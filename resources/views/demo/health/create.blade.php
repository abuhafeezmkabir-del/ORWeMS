@extends('demo.layout')

@section('title', "Add Health Record — {$beneficiary['name']} — ORWeMS Demo")

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4><i class="bi bi-heart-pulse me-2 text-primary"></i>Add Health Record (Simulated)</h4>
            <p class="text-muted small mb-0">{{ $beneficiary['name'] }} — <code>{{ $beneficiary['ref'] }}</code></p>
        </div>
        <a href="{{ route('demo.health.index', $beneficiary['ref']) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Health Records</a>
    </div>

    <div class="card card-demo">
        <div class="card-header bg-white">
            <h5 class="mb-0">Health Record Entry Form</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('demo.health.store', $beneficiary['ref']) }}">
                @csrf

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Health Concern</label>
                        <select name="concern" class="form-select" required>
                            <option value="">Select concern</option>
                            @foreach($concerns as $concern)
                                <option value="{{ $concern }}">{{ $concern }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Record Date</label>
                        <input type="date" name="record_date" class="form-control" value="{{ old('record_date', now()->format('Y-m-d')) }}" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Casework Summary</label>
                    <textarea name="summary" class="form-control" rows="4" placeholder="Brief casework summary..." required></textarea>
                    <div class="form-text">This is a simulated form. No data will be saved to any database.</div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Follow-up Date (optional)</label>
                        <input type="date" name="follow_up" class="form-control">
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-save me-1"></i>Simulate Save
                    </button>
                    <span class="align-self-center text-muted small ms-3"><i class="bi bi-info-circle me-1"></i>Action is simulated — nothing is persisted</span>
                </div>
            </form>
        </div>
    </div>
@endsection