@extends('layouts.auth')

@section('title', 'Login')
@section('subtitle', 'Welcome back! Sign in to continue.')

@section('content')
<form method="POST" action="{{ url('/login') }}" class="fb-auth-form" id="loginForm">
    @csrf

    {{-- Top-level alert for credential errors --}}
    @if ($errors->has('email') && !$errors->has('password'))
        <div class="alert alert-danger fb-alert">
            <i class="bi bi-shield-exclamation me-1"></i>{{ $errors->first('email') }}
        </div>
    @endif

    {{-- Email --}}
    <div class="mb-3">
        <label for="email" class="form-label">Email Address</label>
        <div class="fb-input-group">
            <i class="bi bi-envelope"></i>
            <input type="email" class="form-control fb-input @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required autofocus placeholder="you@example.com">
        </div>
        @error('email')
            @if ($errors->has('password'))
                <div class="fb-field-error"><i class="bi bi-exclamation-circle-fill"></i> {{ $message }}</div>
            @endif
        @enderror
    </div>

    {{-- Password --}}
    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <div class="fb-input-group">
            <i class="bi bi-lock"></i>
            <input type="password" class="form-control fb-input @error('password') is-invalid @enderror" id="password" name="password" required placeholder="••••••••">
        </div>
        @error('password')
            <div class="fb-field-error"><i class="bi bi-exclamation-circle-fill"></i> {{ $message }}</div>
        @enderror
    </div>

    {{-- Remember Me --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
            <label class="form-check-label small" for="remember">Remember me</label>
        </div>
    </div>

    <button type="submit" class="btn fb-btn-primary w-100 mb-3">
        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
    </button>

    <p class="text-center text-muted mb-0">
        Don't have an account? <a href="{{ route('register') }}" class="fb-link">Create one</a>
    </p>
</form>
@endsection
