@extends('layouts.app')

@section('content')
    <p class="eyebrow">Account help</p>
    <h1>Reset your password</h1>
    <p>Enter the email address linked to your account and we’ll send you a reset link.</p>

    @if (session('status'))
        <div class="legacy-auth-status" role="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="gg-field">
            <label class="gg-label" for="email">Email address</label>
            <input id="email" type="email" class="gg-input" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif>
            @error('email')<p id="email-error" class="gg-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="gg-button gg-button--primary">Send password reset link</button>
    </form>
    <p><a href="{{ route('login') }}">Back to sign in</a></p>
@endsection
