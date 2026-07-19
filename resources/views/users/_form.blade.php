<form action="{{ $action }}" method="POST" class="mt-3">
    @csrf
    @if($method === 'PUT')
        @method('PUT')
    @endif

    <div class="mb-3">
        <label>Name</label>
        <input class="form-control"
               type="text"
               name="name"
               value="{{ old('name', $user->name ?? '') }}"
               required>
    </div>

    <div class="mb-3">
        <label>Email</label>
        <input class="form-control"
               type="email"
               name="email"
               value="{{ old('email', $user->email ?? '') }}"
               required>
    </div>

    <div class="mb-3">
        <label>Password</label>
        <input class="form-control"
               type="password"
               name="password"
                {{ $user ? '' : 'required' }}>
        @if($user)
            <small class="text-muted">(Để trống nếu không đổi mật khẩu)</small>
        @endif
    </div>

    <div class="mb-3">
        <label>Confirm Password</label>
        <input class="form-control"
               type="password"
               name="password_confirmation"
                {{ $user ? '' : 'required' }}>
    </div>

    @role('admin')
    <div class="mb-3">
        <label>Roles</label>
        <select name="roles[]" class="form-control" multiple>
            @foreach(\Spatie\Permission\Models\Role::all() as $role)
                <option value="{{ $role->name }}"
                        @if($user && $user->hasRole($role->name)) selected @endif>
                    {{ $role->name }}
                </option>
            @endforeach
        </select>
    </div>
    @endrole

    <button class="btn btn-success">Save</button>
</form>
