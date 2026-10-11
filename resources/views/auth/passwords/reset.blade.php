@extends('layouts.app')

@section('content')
    <p class="eyebrow">Account help</p>
    <h1>Choose a new password</h1>
    <p>Set a new password for your Goodgoods account.</p>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="gg-field">
            <label class="gg-label" for="email">Email address</label>
            <input id="email" type="email" class="gg-input" name="email" value="{{ $email ?? old('email') }}" required autocomplete="email" autofocus @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-error" @endif>
            @error('email')<p id="email-error" class="gg-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div class="gg-field">
            <label class="gg-label" for="password">New password</label>
            <input id="password" type="password" class="gg-input" name="password" required autocomplete="new-password" @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
            @error('password')<p id="password-error" class="gg-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div class="gg-field">
            <label class="gg-label" for="password-confirm">Confirm new password</label>
            <input id="password-confirm" type="password" class="gg-input" name="password_confirmation" required autocomplete="new-password">
        </div>
        <button type="submit" class="gg-button gg-button--primary">Reset password</button>
    </form>
@endsection
