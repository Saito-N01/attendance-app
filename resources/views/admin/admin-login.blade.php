@extends('layouts.admin-app')

@section('css')
@vite('resources/css/admin/admin-login.css')
@endsection

@section('content')
<div class="login__content">
    <div class="login__heading">
        <h1 class="login__heading--item">管理者ログイン</h1>
    </div>
    <form class="form" action="/admin/login" method="post" novalidate>
        @csrf
        <div class="form__group">
            <label class="form__label" for="email">メールアドレス</label>
            <input class="form__input" id="email" type="email" name="email" value="{{ old('email') }}">
            <div class="form__error">
                @error ('email')
                {{ $message }}
                @enderror
            </div>
        </div>
        <div class="form__group">
            <label class="form__label" for="password">パスワード</label>
            <input class="form__input" id="password" type="password" name="password">
            <div class="form__error">
                @error ('password')
                {{ $message }}
                @enderror
            </div>
        </div>
        <div class="form__button">
            <button class="form__button--submit">管理者ログインする</button>
        </div>
    </form>
</div>
@endsection