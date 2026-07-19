@extends('layouts.app')

@section('content')
<div class="container py-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Upload Lead (CSV)</h4>
        <a href="{{ route('marketing.leads.index') }}" class="btn btn-sm btn-outline-secondary">
            Quay lại danh sách
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Có lỗi:</div>
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('marketing.leads.import') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label class="form-label">File CSV</label>
                    <input type="file" name="file" class="form-control" accept=".csv,text/csv" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nguồn (source)</label>
                        <input type="text" name="source" class="form-control" placeholder="VD: Facebook Ads">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Ngày import</label>
                        <input type="date" name="import_date" class="form-control">
                        <div class="form-text">Nếu để trống: lấy ngày hôm nay.</div>
                    </div>
                </div>

                <button class="btn btn-primary">Import</button>
            </form>
        </div>
    </div>
</div>
@endsection
