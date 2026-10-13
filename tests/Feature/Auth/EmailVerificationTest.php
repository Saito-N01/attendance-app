<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * テストケース一覧 No.16：★ メール認証機能
 *
 * 認証メールの「送信」は Notification::fake() で、本物のメールサーバーを使わずに検証する。
 * 認証リンクは本番と同じ「署名付きURL」を URL::temporarySignedRoute で生成して開く。
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** 会員登録後、認証メールが送信される */
    public function test_verification_mail_is_sent_after_registration(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'テスト太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // 登録直後は未認証のユーザーとして作られる
        $user = User::where('email', 'taro@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);

        // 登録後は勤怠画面へ送られるが、未認証なので verified ミドルウェアが認証誘導画面へ案内する
        $response->assertRedirect('/attendance');
        $this->get('/attendance')->assertRedirect('/email/verify');

        // 登録したメールアドレス宛に認証メールが1通だけ送られている
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    /** メール認証誘導画面で「認証はこちらから」ボタンを押下するとメール認証サイトに遷移する */
    public function test_verify_button_links_to_the_mail_site(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/email/verify');

        $response->assertOk();
        $response->assertSee('認証はこちらから');
        // ボタンの遷移先は開発用メールサイト（Mailpit）。別タブで開く
        $response->assertSee(
            '<a class="verify__link" href="http://localhost:8025" target="_blank" rel="noopener noreferrer">認証はこちらから</a>',
            false
        );
    }

    /** メール認証サイトのメール認証を完了すると、勤怠登録画面に遷移する */
    public function test_completing_verification_redirects_to_attendance_page(): void
    {
        $user = User::factory()->unverified()->create();

        // メールに記載される認証リンクと同じ署名付きURLを開く
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertRedirect('/attendance?verified=1');
        $this->assertNotNull($user->fresh()->email_verified_at);

        // 遷移先の勤怠登録画面が表示できる
        $this->get('/attendance')->assertOk();
    }

    /** 補足：未認証のユーザーは勤怠画面に入れず、認証誘導画面へ戻される */
    public function test_unverified_user_is_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/attendance')->assertRedirect('/email/verify');
    }

    /** 補足：不正な署名のリンクでは認証されない */
    public function test_invalid_signature_does_not_verify(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/email/verify/'.$user->id.'/'.sha1($user->email))
            ->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    /** 補足：「認証メールを再送する」で認証メールが再送される */
    public function test_verification_mail_can_be_resent(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->from('/email/verify')->post('/email/verification-notification');

        $response->assertRedirect('/email/verify');
        $response->assertSessionHas('message', '認証メールを再送しました。');
        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
