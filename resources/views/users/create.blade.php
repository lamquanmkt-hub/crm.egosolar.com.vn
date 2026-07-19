@extends('layouts.app')

@section('content')
    <div class="container">
        <h3>Create User</h3>

        @include('users._form', [
            'action' => route('users.store'),
            'method' => 'POST',
            'user' => null
        ])
    </div>
@endsection
