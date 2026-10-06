<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
    <main>
        <h1>Reset Password</h1>

        @if ($errors->any())
            <ul class="errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ url('/reset-password') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label>Email <input type="email" name="email" value="{{ $email ?? old('email') }}" required></label>
            <label>Password <input type="password" name="password" required></label>
            <label>Confirm Password <input type="password" name="password_confirmation" required></label>
            <button type="submit">Reset Password</button>
        </form>
    </main>
</body>
</html>
