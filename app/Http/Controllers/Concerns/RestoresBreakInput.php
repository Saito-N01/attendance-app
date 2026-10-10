<?php

namespace App\Http\Controllers\Concerns;

/**
 * 勤怠の修正フォームで、バリデーションエラーで戻ってきたときに、入力した休憩を復元する。
 *
 * ユーザー側・管理者側の勤怠詳細（show）で共通に使う。
 * Blade 側は old() を使わず、コントローラーが渡す休憩の配列をそのまま描画するため、
 * 入力内容の復元はコントローラー側で行う。
 */
trait RestoresBreakInput
{
    /**
     * 直前の入力（old input）から休憩の配列を組み立てる。
     * 末尾の空行は、Blade が追加入力用に自分で出すため取り除く。
     * old input が無い（初回表示など）ときは null を返す。呼び出し側は、その場合に元の休憩を使う。
     *
     * @return array<int, array{break_in: string, break_out: string}>|null
     */
    protected function restoreBreaksFromOldInput(): ?array
    {
        if (old('new_break_in') === null) {
            return null;
        }

        return collect(old('new_break_in'))
            ->map(fn (mixed $in, int|string $i) => ['break_in' => $in ?? '', 'break_out' => old("new_break_out.$i") ?? ''])
            // 後ろから空行を読み飛ばし、元の順序に戻す
            ->reverse()
            ->skipWhile(fn (array $break) => $break === ['break_in' => '', 'break_out' => ''])
            ->reverse()
            ->values()
            ->all();
    }
}
