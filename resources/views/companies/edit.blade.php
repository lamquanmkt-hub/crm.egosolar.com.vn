@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-1">Sửa thông tin công ty</h4>
            <div class="text-muted">{{ $company->name }}</div>
        </div>

        <a href="{{ route('companies.index') }}" class="btn btn-light">
            Quay lại
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Có lỗi xảy ra:</div>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('companies.update', $company) }}">
        @csrf
        @method('PUT')

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">
                Thông tin pháp lý
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Tên công ty</label>
                        <input type="text" name="name" class="form-control"
                               value="{{ old('name', $company->name) }}" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Mã công ty</label>
                        <input type="text" name="code" class="form-control"
                               value="{{ old('code', $company->code) }}" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Mã số thuế</label>
                        <input type="text" name="tax_code" class="form-control"
                               value="{{ old('tax_code', $company->tax_code) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="{{ old('email', $company->email) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Số điện thoại</label>
                        <input type="text" name="phone" class="form-control"
                               value="{{ old('phone', $company->phone) }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Địa chỉ</label>
                        <textarea name="address" class="form-control" rows="3">{{ old('address', $company->address) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header bg-white fw-semibold">
                Thông tin thanh toán in trên PDF
            </div>

            <div class="card-body">
                @php
                    $bankRows = old('bank_accounts', $company->bank_accounts ?? []);

                    if (is_string($bankRows)) {
                        $decodedBankRows = json_decode($bankRows, true);
                        $bankRows = is_array($decodedBankRows) ? $decodedBankRows : [];
                    }

                    if (!is_array($bankRows)) {
                        $bankRows = [];
                    }

                    $bankRows = array_values(array_slice($bankRows, 0, 2));

                    if (count($bankRows) === 0) {
                        $bankRows = [
                            [
                                'bank_account' => old('bank_account', $company->bank_account),
                                'bank_name' => old('bank_name', $company->bank_name),
                                'bank_holder' => old('bank_holder', $company->bank_holder),
                                'is_default' => 1,
                            ],
                            [
                                'bank_account' => '',
                                'bank_name' => '',
                                'bank_holder' => old('bank_holder', $company->bank_holder),
                                'is_default' => 0,
                            ],
                        ];
                    }

                    if (count($bankRows) === 1) {
                        $bankRows[] = [
                            'bank_account' => '',
                            'bank_name' => '',
                            'bank_holder' => $bankRows[0]['bank_holder'] ?? old('bank_holder', $company->bank_holder),
                            'is_default' => 0,
                        ];
                    }

                    $defaultIndex = 0;

                    foreach ($bankRows as $i => $row) {
                        if (!empty($row['is_default'])) {
                            $defaultIndex = $i;
                            break;
                        }
                    }

                    $defaultBank = $bankRows[$defaultIndex] ?? $bankRows[0];
                @endphp

                <input type="hidden" name="bank_account" id="bank_account_default" value="{{ $defaultBank['bank_account'] ?? '' }}">
                <input type="hidden" name="bank_name" id="bank_name_default" value="{{ $defaultBank['bank_name'] ?? '' }}">
                <input type="hidden" name="bank_holder" id="bank_holder_default" value="{{ $defaultBank['bank_holder'] ?? '' }}">

                <div class="alert alert-info py-2 small">
                    Nhập <b>2 tài khoản ngân hàng</b>. PDF đơn hàng sẽ hiện cả 2 tài khoản có dữ liệu.
                    Chỉ chọn được <b>1 tài khoản mặc định</b>.
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle table-bordered mb-2">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 26%;">Số tài khoản</th>
                                <th style="width: 32%;">Ngân hàng</th>
                                <th style="width: 30%;">Tên tài khoản</th>
                                <th class="text-center" style="width: 12%;">Mặc định</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bankRows as $index => $bank)
                                <tr class="bank-account-row">
                                    <td>
                                        <input type="text"
                                               name="bank_accounts[{{ $index }}][bank_account]"
                                               class="form-control form-control-sm js-bank-account"
                                               value="{{ $bank['bank_account'] ?? '' }}">
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="bank_accounts[{{ $index }}][bank_name]"
                                               class="form-control form-control-sm js-bank-name"
                                               value="{{ $bank['bank_name'] ?? '' }}">
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="bank_accounts[{{ $index }}][bank_holder]"
                                               class="form-control form-control-sm js-bank-holder"
                                               value="{{ $bank['bank_holder'] ?? '' }}">
                                    </td>
                                    <td class="text-center">
                                        <input type="radio"
                                               name="bank_default_index"
                                               value="{{ $index }}"
                                               class="form-check-input js-bank-default"
                                               {{ $defaultIndex === $index ? 'checked' : '' }}>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="col-12 mt-3">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input"
                               {{ old('is_active', $company->exists ? $company->is_active : true) ? 'checked' : '' }}>
                        <span class="form-check-label">Đang hoạt động</span>
                    </label>
                </div>

                <script>
                    function syncDefaultBank() {
                        const checked = document.querySelector('.js-bank-default:checked');
                        const row = checked ? checked.closest('tr') : document.querySelector('.bank-account-row');

                        if (!row) return;

                        document.getElementById('bank_account_default').value = row.querySelector('.js-bank-account').value || '';
                        document.getElementById('bank_name_default').value = row.querySelector('.js-bank-name').value || '';
                        document.getElementById('bank_holder_default').value = row.querySelector('.js-bank-holder').value || '';
                    }

                    document.querySelectorAll('.js-bank-account,.js-bank-name,.js-bank-holder,.js-bank-default').forEach(function (input) {
                        input.addEventListener('input', syncDefaultBank);
                        input.addEventListener('change', syncDefaultBank);
                    });

                    document.querySelector('form')?.addEventListener('submit', syncDefaultBank);

                    syncDefaultBank();
                </script>
            </div>


        <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <a href="{{ route('companies.index') }}" class="btn btn-light">Hủy</a>
                <button type="submit" class="btn btn-primary">Lưu thông tin</button>
            </div>
        </div>
    </form>
</div>
@endsection