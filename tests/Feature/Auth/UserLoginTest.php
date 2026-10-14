<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース一覧 No.2：ログイン認証機能（一般ユーザー）
 */
class UserLoginTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'taro@example.com';

    private const PASSWORD = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create([
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ]);
    }

    /** メールアドレスが未入力の場合、バリデーションメッセージが表示される */
    public function test_email_is_required(): void
    {
        $response = $this->post('/login', [
            'email' => '',
            'password' => self::PASSWORD,
        ]);

        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
        $this->assertGuest();
    }

    /** パスワードが未入力の場合、バリデーションメッセージが表示される */
    public function test_password_is_required(): void
    {
        $response = $this->post('/login', [
            'email' => self::EMAIL,
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
        $this->assertGuest();
    }

    /** 登録内容と一致しない場合、バリデーションメッセージが表示される */
    public function test_login_fails_with_unregistered_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'wrong@example.com',
            'password' => self::PASSWORD,
        ]);

        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
        $this->assertGuest();
    }

    /** 補足：一般ユーザーは打刻画面へ遷移する（管理者ログインとの切り替えで変わっていないことの確認） */
    public function test_user_can_login_and_is_redirected_to_attendance(): void
    {
        $response = $this->post('/login', [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect('/attendance');
        $this->assertAuthenticated();
    }

    /** 補足：ヘッダーのボタンからログアウトすると、ログイン画面へ遷移する（FN013） */
    public function test_user_can_logout_and_is_redirected_to_login(): void
    {
        $user = User::where('email', self::EMAIL)->first();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
