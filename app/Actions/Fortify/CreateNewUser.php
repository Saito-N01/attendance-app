<?php

namespace App\Actions\Fortify;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * 入力を検証して一般ユーザーを作成する（検証ルール・メッセージは RegisterRequest と共通）。
     *
     * @param  array<string, string>  $input  name / email / password / password_confirmation
     */
    public function create(array $input): User
    {
        $request = new RegisterRequest;

        Validator::make($input, $request->rules(), $request->messages())->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
