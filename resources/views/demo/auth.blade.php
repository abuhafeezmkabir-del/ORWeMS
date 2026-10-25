@extends('demo.layout')

@section('title', 'Sign In — ORWeMS Demo')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card card-demo shadow-sm">
                <div class="card-body">
                    <h3 class="card-title text-center mb-3">DEMONSTRATION MODE</h3>
                    <small class="text-muted d-block mb-3">All records are fictional. Actions are simulated; no records are saved.</small>

                    <h4 class="mb-3">Sign in to ORWeMS Demo</h4>

                    @if(session('status') === 'demo-login-failed')
                        <div class="alert alert-danger">
                            <strong>Invalid credentials.</strong> Please try again.
                        </div>
                    @endif

                    @if(session('status') === 'demo-not-configured')
                        <div class="alert alert-warning">
                            <strong>Demo not configured.</strong> Demo mode is not currently configured for this deployment.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('demo.login.post') }}" class="mt-4">
                        @csrf
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" name="email" class="form-control" id="email" required value="{{ old('email') }}">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" id="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Sign in</button>
                    </form>

                    <p class="text-center mt-3 small text-muted">
                        Demo credentials: <code>demo@wicov.org / demo123</code>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection