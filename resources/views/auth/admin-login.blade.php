@extends('layouts.app')
@section('title', 'Administrator sign in | Commonwealth')
@section('content')
<section class="auth-shell"><div class="auth-card"><p class="eyebrow dark-eyebrow"><span></span> ADMINISTRATION</p><h1>Authorized access</h1><p class="auth-subtitle">Sign in with an administrator account. Administrative sessions end when the browser session ends.</p>
@if ($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('admin.login.store') }}">@csrf
<label for="email">Administrator email</label><input id="email" class="form-control" type="email" name="email" value="{{ old('email') }}" autocomplete="username" maxlength="190" required autofocus>
<label for="password">Password</label><input id="password" class="form-control" type="password" name="password" autocomplete="current-password" required>
<div class="form-row"><span>Restricted administrative access</span><a href="{{ route('password.request') }}">Forgot password?</a></div>
<button class="button button-navy w-100" type="submit">Sign in to administration <span>→</span></button></form>
<p class="auth-bottom"><a href="{{ route('login') }}">Investor sign in</a></p>
</div></section>
@endsection
