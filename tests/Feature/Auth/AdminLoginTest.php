<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケース一覧 No.3：ログイン認証機能（管理者）
 *
 * 管理者は専用ガードではなく、web ガード＋ users.admin_status で判定する設計。
 */
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'admin@example.com';

    private const PASSWORD = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->admin()->create([
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ]);
    }

    /** メールアドレスが未入力の場合、バリデーションメッセージが表示される */
    public function test_email_is_required(): void
    {
        $response = $this->post('/admin/login', [
            'email' => '',
            'password' => self::PASSWORD,
        ]);

        $response->assertSessionHasErrors(['email' => 'メールアドレスを入力してください']);
        $this->assertGuest();
    }

    /** パスワードが未入力の場合、バリデーションメッセージが表示される */
    public function test_password_is_required(): void
    {
        $response = $this->post('/admin/login', [
            'email' => self::EMAIL,
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['password' => 'パスワードを入力してください']);
        $this->assertGuest();
    }

    /** 登録内容と一致しない場合、バリデーションメッセージが表示される */
    public function test_login_fails_with_unregistered_credentials(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'wrong@example.com',
            'password' => self::PASSWORD,
        ]);

        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
        $this->assertGuest();
    }

    /**
     * 一般ユーザーの認証情報では管理者ログインできない。
     * admin_status による振り分けが崩れていないことの確認。
     */
    public function test_general_user_cannot_login_as_admin(): void
    {
        User::factory()->create([
            'email' => 'general@example.com',
            'password' => self::PASSWORD,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'general@example.com',
            'password' => self::PASSWORD,
        ]);

        $response->assertSessionHasErrors(['email' => 'ログイン情報が登録されていません']);
        $this->assertGuest();
    }
}
