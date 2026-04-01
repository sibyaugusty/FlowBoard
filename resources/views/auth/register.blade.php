@extends('layouts.auth')

@section('title', 'Register')
@section('subtitle', 'Create your account to get started.')

@section('content')
<form method="POST" action="{{ url('/register') }}" class="fb-auth-form" id="registerForm">
    @csrf

    {{-- Top-level alert for general errors --}}
    @if ($errors->has('email') && $errors->first('email') === 'The email has already been taken.')
        <div class="alert alert-danger fb-alert">
            <i class="bi bi-exclamation-circle me-1"></i>This email is already registered. Please <a href="{{ route('login') }}" class="fb-link">sign in</a> instead.
        </div>
    @endif

    {{-- Full Name --}}
    <div class="mb-3">
        <label for="name" class="form-label">Full Name</label>
        <div class="fb-input-group">
            <i class="bi bi-person"></i>
            <input type="text" class="form-control fb-input @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required autofocus placeholder="John Doe">
        </div>
        @error('name')
            <div class="fb-field-error"><i class="bi bi-exclamation-circle-fill"></i> {{ $message }}</div>
        @enderror
    </div>

    {{-- Email --}}
    <div class="mb-3">
        <label for="email" class="form-label">Email Address</label>
        <div class="fb-input-group">
            <i class="bi bi-envelope"></i>
            <input type="email" class="form-control fb-input @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com">
        </div>
        @error('email')
            <div class="fb-field-error"><i class="bi bi-exclamation-circle-fill"></i> {{ $message }}</div>
        @enderror
    </div>

    {{-- Password --}}
    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <div class="fb-input-group">
            <i class="bi bi-lock"></i>
            <input type="password" class="form-control fb-input @error('password') is-invalid @enderror" id="password" name="password" required placeholder="Min 8 characters">
        </div>
        @error('password')
            <div class="fb-field-error"><i class="bi bi-exclamation-circle-fill"></i> {{ $message }}</div>
        @enderror
    </div>

    {{-- Confirm Password --}}
    <div class="mb-4">
        <label for="password_confirmation" class="form-label">Confirm Password</label>
        <div class="fb-input-group">
            <i class="bi bi-lock-fill"></i>
            <input type="password" class="form-control fb-input" id="password_confirmation" name="password_confirmation" required placeholder="••••••••">
        </div>
    </div>

    <button type="submit" class="btn fb-btn-primary w-100 mb-3">
        <i class="bi bi-person-plus me-2"></i>Create Account
    </button>

    <p class="text-center text-muted mb-0">
        Already have an account? <a href="{{ route('login') }}" class="fb-link">Sign in</a>
    </p>
</form>
@endsection
