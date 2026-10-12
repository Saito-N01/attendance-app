<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * テストケース一覧 No.1：認証機能（一般ユーザー）
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 正しい入力値一式。各テストで「1項目だけ」上書き・削除して使う。
     *
     * @return array<string, string>
     */
    private function validInput(array $override = []): array
    {
        return array_merge([
            'name' => 'テスト太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $override);
    }

    /** 名前が未入力の場合、バリデーションメッセージが表示される */
    public function test_name_is_required(): void
    {
        $response = $this->post('/register', $this->validInput(['name' => '']));

        $response->assertSessionHasErrors(['name' => 'お名前を入力してください']);
        $this->assertDatabaseCount('users', 0);
    }

    /** メールアドレスが未入力の場合、バリデーションメッセージが表示される */
    public function test_email_is_required(): void
    {
        $response = $this->post('/register', $this->validInput(['email' => '']));

        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
        $this->assertDatabaseCount('users', 0);
    }

    /** パスワードが8文字未満の場合、バリデーションメッセージが表示される */
    public function test_password_must_be_at_least_8_characters(): void
    {
        // 確認用も同じ値にして「文字数」だけが原因のエラーにする
        $response = $this->post('/register', $this->validInput([
            'password' => 'pass123',
            'password_confirmation' => 'pass123',
        ]));

        $response->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください']);
        $this->assertDatabaseCount('users', 0);
    }

    /** パスワードが一致しない場合、バリデーションメッセージが表示される */
    public function test_password_confirmation_must_match(): void
    {
        $response = $this->post('/register', $this->validInput([
            'password_confirmation' => 'different123',
        ]));

        $response->assertSessionHasErrors(['password_confirmation' => 'パスワードと一致しません']);
        $this->assertDatabaseCount('users', 0);
    }

    /** パスワードが未入力の場合、バリデーションメッセージが表示される */
    public function test_password_is_required(): void
    {
        $response = $this->post('/register', $this->validInput([
            'password' => '',
            'password_confirmation' => '',
        ]));

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
        $this->assertDatabaseCount('users', 0);
    }

    /** フォームに内容が入力されていた場合、データが正常に保存される */
    public function test_user_is_saved_when_input_is_valid(): void
    {
        $response = $this->post('/register', $this->validInput());

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'name' => 'テスト太郎',
            'email' => 'taro@example.com',
            'admin_status' => false, // 会員登録では一般ユーザーになる
        ]);

        // パスワードは平文ではなくハッシュ化されて保存される
        $user = User::where('email', 'taro@example.com')->firstOrFail();
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }
}
