@extends('demo.layout')

@section('title', "Close Workflow — {$beneficiary['name']} — ORWeMS Demo")

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4><i class="bi bi-check-circle me-2 text-primary"></i>Close Workflow (Simulated)</h4>
            <p class="text-muted small mb-0">{{ $beneficiary['name'] }} — <code>{{ $beneficiary['ref'] }}</code></p>
        </div>
        <a href="{{ route('demo.health.index', $beneficiary['ref']) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Health Records</a>
    </div>

    <div class="card card-demo">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Open Records Eligible for Closure</h5>
            <span class="simulated-badge">Simulation only — no deletion occurs</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Date</th><th>Concern</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    @foreach($records as $record)
                        @if($record['status'] === 'Open')
                            <tr>
                                <td>{{ $record['date'] }}</td>
                                <td>{{ $record['concern'] }}</td>
                                <td><span class="badge bg-warning">{{ $record['status'] }}</span></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#closeModal{{ $record['id'] }}">
                                        Close Record
                                    </button>
                                </td>
                            </tr>

                            <div class="modal fade" id="closeModal{{ $record['id'] }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Close Health Record</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="{{ route('demo.close.confirm', $beneficiary['ref']) }}">
                                            @csrf
                                            <input type="hidden" name="record_id" value="{{ $record['id'] }}">
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Closure Reason</label>
                                                    <textarea name="closure_reason" class="form-control" rows="3" placeholder="Reason for closing this record..." required></label>
                                                    <div class="form-text">Simulated: no record is deleted. History is preserved.</div>
                                                </div>
                                                <div class="alert alert-warning mt-3">
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    This action is simulated. No record will be deleted or modified in any database.
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-outline-primary">Simulate Close</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                    @if(collect($records)->where('status', 'Open')->count() === 0)
                        <tr><td colspan="4" class="text-muted text-center py-4">No open records available for closure.</td></tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection