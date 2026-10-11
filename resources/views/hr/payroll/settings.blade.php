@extends('layouts.app')

@section('title', 'Cài đặt lương')

@section('content')
<div id="egoAttendancePromax" class="pr-workspace">
    <div class="at-shell">
        @include('hr.payroll._breadcrumb', ['pageTitle' => 'Cài đặt lương'])
        <header class="pr-page-head">
            <div class="at-heading">
                <div>
                    <h1>Cài đặt lương</h1>
                    <p>Hồ sơ lương nhân viên và quy tắc tính lương.</p>
                </div>
            </div>
        </header>
        @include('hr.payroll._head')
        <nav class="pr-tabs" aria-label="Cài đặt lương">
            <a href="{{ route('hr.payroll.settings', ['tab' => 'profiles'] + request()->only('month')) }}" class="{{ $tab === 'profiles' ? 'active' : '' }}">Hồ sơ nhân viên</a>
            <a href="{{ route('hr.payroll.settings', ['tab' => 'rules']) }}" class="{{ $tab === 'rules' ? 'active' : '' }}">Quy tắc tính lương</a>
        </nav>
        @if($tab === 'profiles')
            @include('hr.payroll._profiles', $profileData)
        @else
            @include('hr.payroll._rules')
        @endif
    </div>
</div>
@endsection
