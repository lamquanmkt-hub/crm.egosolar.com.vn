@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Danh sách kho hàng</h3>
        <a href="{{ route('warehouses.create') }}" class="btn btn-primary">+ Thêm kho</a>
    </div>



    <div class="card">
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên kho</th>
                    <th>Địa điểm</th>
                    <th>Công ty</th>
                    <th>Quản lý</th>
                    <th class="text-end">Hành động</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($warehouses as $warehouse)
                    <tr>
                        <td>{{ $warehouse->id }}</td>
                        <td>{{ $warehouse->name }}</td>
                        <td>{{ $warehouse->location }}</td>
                        <td>
                            @php $cs = $warehouse->companies ?? collect(); @endphp
                            @if($cs->count())
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($cs as $c)
                                        <span class="badge bg-light text-dark border">
                                            {{ $c->name }} @if($c->code) ({{ $c->code }}) @endif
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>{{ $warehouse->manager?->name }}</td>
                        <td class="text-end">
                            <a href="{{ route('warehouses.edit', $warehouse->id) }}" class="btn btn-sm btn-warning">Sửa</a>
                            <form action="{{ route('warehouses.destroy', $warehouse->id) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Bạn chắc chắn muốn xóa?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger">Xóa</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="p-3">
                {{ $warehouses->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
