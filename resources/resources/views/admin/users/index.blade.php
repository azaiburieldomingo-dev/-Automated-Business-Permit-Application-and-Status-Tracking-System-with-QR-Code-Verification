@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="page-header">
        <h1>Users</h1>
        <a href="{{ route('admin.users.create') }}" class="btn">New User</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td><span class="badge badge-blue">{{ ucfirst($user->role) }}</span></td>
                            <td>{{ $user->is_active ? 'Yes' : 'No' }}</td>
                            <td><a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-secondary">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-pagination :paginator="$users" />
    </div>
@endsection
