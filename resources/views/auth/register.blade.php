<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
    <main>
        <h1>Create an Account</h1>

        @if ($errors->any())
            <ul class="errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ url('/register') }}">
            @csrf
            <label>Name <input type="text" name="name" value="{{ old('name') }}" required></label>
            <label>Email <input type="email" name="email" value="{{ old('email') }}" required></label>
            <label>Contact Number <input type="text" name="contact_number" value="{{ old('contact_number') }}"></label>
            <label>Address <input type="text" name="address" value="{{ old('address') }}"></label>
            <label>Password <input type="password" name="password" required></label>
            <label>Confirm Password <input type="password" name="password_confirmation" required></label>
            <button type="submit">Register</button>
        </form>

        <p><a href="{{ url('/login') }}">Already have an account? Login</a></p>
    </main>
</body>
</html>
