import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ command }) => ({
  // CSS 内の url('/images/...') は、開発サーバー（npm run dev）では Vite のサーバー（:5173）に取りに行く。
  // laravel-vite-plugin は既定で public/ を配信しないため、開発時だけ public/ を配信して画像を表示する。
  // （本番ビルドでは public/build に public/ を丸ごとコピーしてしまうため、false のままにする）
  publicDir: command === 'serve' ? 'public' : false,
  plugins: [
    laravel({
      input: [
        'resources/js/app.js',
        'resources/css/sanitize.css',
        'resources/css/common.css',
        'resources/css/auth/verify-email.css',
        'resources/css/user/register.css',
        'resources/css/user/user-login.css',
        'resources/css/user/attendance-register.css',
        'resources/css/user/user-attendance-list.css',
        'resources/css/user/user-detail.css',
        'resources/css/user/user-application-list.css',
        'resources/css/admin/admin-login.css',
        'resources/css/admin/admin-attendance-list.css',
        'resources/css/admin/admin-detail.css',
        'resources/css/admin/admin-application-list.css',
        'resources/css/admin/admin-application-detail.css',
        'resources/css/admin/staff-list.css',
        'resources/css/admin/staff-attendance-list.css',
        'resources/css/reports/index.css', // ※ 応用（マイ勤怠レポート）用。基本のみの場合はこの行を除いてよい。
      ],
      refresh: true,
    }),
  ],
}));
