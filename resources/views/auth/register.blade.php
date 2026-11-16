@extends('layouts.app')
@section('title', 'Create account | Commonwealth')
@section('content')
<section class="auth-shell"><div class="auth-card"><p class="eyebrow dark-eyebrow"><span></span> COMMONWEALTH ACCOUNT</p><h1>Create your account</h1><p class="auth-subtitle">Review digital assets, public markets, businesses, and real-asset research in one place.</p><div class="register-status-note"><strong>Know the current status:</strong> Commonwealth is not currently offering investments, deposits, withdrawals, or custody. Creating an account does not make an investment available. <a href="{{ route('themes.index') }}">Review the 15 research areas</a>.</div>
@if ($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('register.store') }}">@csrf
<label for="name">Full name</label><input id="name" class="form-control" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="120" required>
<label for="email">Email address</label><input id="email" class="form-control" type="email" name="email" value="{{ old('email') }}" autocomplete="email" maxlength="190" required>
<label for="password">Password</label><input id="password" class="form-control" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="200" required><small class="field-hint">Use at least 12 characters.</small>
<label for="password_confirmation">Confirm password</label><input id="password_confirmation" class="form-control" type="password" name="password_confirmation" autocomplete="new-password" maxlength="200" required>
<label class="terms-check"><input type="checkbox" name="terms" value="1" @checked(old('terms')) required><span>I agree to the <a href="{{ route('terms') }}">terms</a> and <a href="{{ route('risk-disclosure') }}">risk disclosures</a>.</span></label>
<button class="button button-navy w-100" type="submit">Create account <span>→</span></button></form>
<p class="auth-bottom">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
</div></section>
@endsection