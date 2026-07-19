@extends('layouts.app')

@section('content')
    <div class="container">
        <h3>Edit User</h3>
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @include('users._form', [
            'action' => route('users.update', $user),
            'method' => 'PUT',
            'user' => $user
        ])
    </div>
@endsection
