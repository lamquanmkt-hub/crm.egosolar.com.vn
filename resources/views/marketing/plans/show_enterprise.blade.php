@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">

    <h3 class="fw-bold mb-4">
        {{ $plan->name }}
        <small class="text-muted">
            {{ \Carbon\Carbon::parse($plan->month)->format('m/Y') }}
        </small>
    </h3>

    <div class="row g-4 mb-4">

        <div class="col-lg-3">
            <div class="card shadow-sm rounded-4 p-4">
                <div class="text-muted small">Budget Used</div>
                <div class="fs-4 fw-bold text-success">
                    {{ number_format($actual['spend'],0,',','.') }} đ
                </div>
                <div class="progress mt-2">
                    <div class="progress-bar" style="width: {{ $kpi['progress_budget'] }}%"></div>
                </div>
                <small>{{ $kpi['progress_budget'] }}%</small>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card shadow-sm rounded-4 p-4">
                <div class="text-muted small">Leads</div>
                <div class="fs-4 fw-bold">
                    {{ number_format($actual['leads']) }}
                </div>
                <div class="progress mt-2">
                    <div class="progress-bar bg-success" style="width: {{ $kpi['progress_leads'] }}%"></div>
                </div>
                <small>{{ $kpi['progress_leads'] }}%</small>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card shadow-sm rounded-4 p-4">
                <div class="text-muted small">Revenue</div>
                <div class="fs-4 fw-bold text-primary">
                    {{ number_format($actual['revenue'],0,',','.') }} đ
                </div>
                <div class="progress mt-2">
                    <div class="progress-bar bg-info" style="width: {{ $kpi['progress_rev'] }}%"></div>
                </div>
                <small>{{ $kpi['progress_rev'] }}%</small>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card shadow-sm rounded-4 p-4">
                <div class="text-muted small">ROAS</div>
                <div class="fs-4 fw-bold">{{ $kpi['actual_roas'] }}</div>
                <div class="text-muted small mt-1">
                    CPL {{ number_format($kpi['actual_cpl'],0,',','.') }} đ
                </div>
            </div>
        </div>

    </div>

    <div class="card shadow-sm rounded-4 p-4">
        <h6 class="fw-bold mb-3">Channel Breakdown</h6>
        <table class="table">
            <thead>
                <tr>
                    <th>Channel</th>
                    <th class="text-end">Spend</th>
                </tr>
            </thead>
            <tbody>
                @foreach($channels as $ch)
                    <tr>
                        <td>{{ $ch->channel }}</td>
                        <td class="text-end">
                            {{ number_format($ch->spend,0,',','.') }} đ
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
@endsection