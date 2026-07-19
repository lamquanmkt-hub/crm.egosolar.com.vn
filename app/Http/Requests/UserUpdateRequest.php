<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class UserUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
	    return auth()->check() && auth()->user()->can('update', $this->route('user'));
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
	    $userId = $this->route('user')->id;
	    $rules = [
		    'name' => 'required|string|max:255',
		    'email' => 'required|email|unique:users,email,' . $userId,
		    'password' => 'nullable|string|min:6|confirmed',
	    ];
	    if (auth()->user()->hasRole('admin')) {
		    $rules['roles'] = 'nullable|array';
		    $rules['roles.*'] = 'exists:roles,name';
	    }
	    return $rules;
    }
}
