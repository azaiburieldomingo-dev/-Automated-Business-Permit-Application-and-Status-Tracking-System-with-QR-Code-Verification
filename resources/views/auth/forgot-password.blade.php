<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
    <main>
        <h1>Forgot Password</h1>

        @if (session('status'))
            <p class="status">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <ul class="errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ url('/forgot-password') }}">
            @csrf
            <label>Email <input type="email" name="email" value="{{ old('email') }}" required></label>
            <button type="submit">Send Reset Link</button>
        </form>

        <p><a href="{{ url('/login') }}">Back to login</a></p>
    </main>
</body>
</html>
