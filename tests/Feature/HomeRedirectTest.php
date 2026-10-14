<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ヘッダーのロゴ（リンク先 /）を押しても 404 にならず、状態に応じた画面へ遷移する
 */
class HomeRedirectTest extends TestCase
{
    use RefreshDatabase;

    /** 未ログインならログイン画面へ */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    /** ログイン済みの一般ユーザーは打刻画面へ */
    public function test_user_is_redirected_to_attendance_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/')->assertRedirect('/attendance');
    }

    /** ログイン済みの管理者は管理者の勤怠一覧へ */
    public function test_admin_is_redirected_to_admin_attendance_list(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/')->assertRedirect('/admin/attendance/list');
    }

    /** メール未認証のユーザーは、最終的に認証誘導画面へ着く */
    public function test_unverified_user_ends_up_at_verification_notice(): void
    {
        $this->actingAs(User::factory()->unverified()->create());

        $this->followingRedirects()->get('/')->assertOk()->assertSee('認証はこちらから');
    }

    /** どのレイアウトのロゴも「/」を指している（リンク切れの再発防止） */
    public function test_logo_links_point_to_root(): void
    {
        $this->get('/login')->assertSee('<a class="header__logo" href="/">', false);
        $this->get('/admin/login')->assertSee('<a class="header__logo" href="/">', false);
    }
}
