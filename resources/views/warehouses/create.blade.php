@extends('layouts.app')

@section('content')
    <div class="container">
        <h3>Thêm kho</h3>

        @include('warehouses._form', [
            'action' => route('warehouses.store'),
            'method' => 'POST',
            'warehouse' => null,
            'companies' => $companies,
        ])
    </div>
@endsection
