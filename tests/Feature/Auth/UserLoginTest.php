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
}
