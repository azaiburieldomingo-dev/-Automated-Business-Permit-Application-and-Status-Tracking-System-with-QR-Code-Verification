<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
    <main>
        <h1>Login</h1>

        @if ($errors->any())
            <ul class="errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ url('/login') }}">
            @csrf
            <label>Email <input type="email" name="email" value="{{ old('email') }}" required></label>
            <label>Password <input type="password" name="password" required></label>
            <label><input type="checkbox" name="remember" value="1"> Remember me</label>
            <button type="submit">Login</button>
        </form>

        <p><a href="{{ url('/forgot-password') }}">Forgot your password?</a></p>
        <p><a href="{{ url('/register') }}">Create an account</a></p>
    </main>
</body>
</html>
