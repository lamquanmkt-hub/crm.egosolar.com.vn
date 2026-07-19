@extends('layouts.app')

@section('content')
    <div class="container">
        <h3 class="mb-3">Cập nhật kho</h3>

        @include('warehouses._form', [
            'action' => route('warehouses.update', $warehouse),
            'method' => 'PUT',
            'warehouse' => $warehouse,
            'companies' => $companies ?? [],
        ])
    </div>
@endsection