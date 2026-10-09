<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i', 'after:new_clock_in'],
            'new_break_in' => ['nullable', 'array'],
            'new_break_out' => ['nullable', 'array'],
            'new_break_in.*' => ['nullable', 'date_format:H:i'],
            'new_break_out.*' => ['nullable', 'date_format:H:i'],
            'comment' => ['required', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時間を入力してください',
            'new_clock_in.date_format' => '出勤時間は"H:i"形式で入力してください',
            'new_clock_out.required' => '退勤時間を入力してください',
            'new_clock_out.date_format' => '退勤時間は"H:i"形式で入力してください',
            'new_clock_out.after' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_break_in.*.date_format' => '休憩時間は"H:i"形式で入力してください',
            'new_break_out.*.date_format' => '休憩時間は"H:i"形式で入力してください',
            'comment.required' => '備考を記入してください',
        ];
    }

    /**
     * 休憩の前後関係を検証する。複数フィールドを見比べる必要があるため、ここで検証する。
     * 休憩ごとに次を調べる（形式エラーはルールで報告済みなら重ねない）。
     *  - 追加用の空行（開始・終了ともに未入力）は対象外
     *  - 片方だけの入力は不適切
     *  - 開始が出勤より前、または退勤より後は不適切
     *  - 終了が退勤より後、または開始より前は不適切
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $clockIn = $this->toMinutes($this->input('new_clock_in'));
            $clockOut = $this->toMinutes($this->input('new_clock_out'));
            $breakIns = (array) $this->input('new_break_in', []);
            $breakOuts = (array) $this->input('new_break_out', []);

            collect(array_keys($breakIns))
                ->merge(array_keys($breakOuts))
                ->unique()
                ->each(function (int|string $i) use ($validator, $clockIn, $clockOut, $breakIns, $breakOuts): void {
                    $rawIn = $breakIns[$i] ?? null;
                    $rawOut = $breakOuts[$i] ?? null;
                    if ($rawIn === null && $rawOut === null) {
                        return; // 追加用の空行
                    }

                    $in = $this->toMinutes($rawIn);
                    $out = $this->toMinutes($rawOut);

                    // 片方だけ入力、または形式不正（形式不正は上のルールで報告済みなら重ねない）
                    if ($in === null || $out === null) {
                        if (! $validator->errors()->has("new_break_in.$i") && ! $validator->errors()->has("new_break_out.$i")) {
                            $validator->errors()->add("new_break_out.$i", '休憩時間が不適切な値です');
                        }

                        return;
                    }

                    // 休憩開始が出勤より前、または退勤より後
                    if (($clockIn !== null && $in < $clockIn) || ($clockOut !== null && $in > $clockOut)) {
                        $validator->errors()->add("new_break_in.$i", '休憩時間が不適切な値です');
                    }

                    // 休憩終了が退勤より後
                    if ($clockOut !== null && $out > $clockOut) {
                        $validator->errors()->add("new_break_out.$i", '休憩時間もしくは退勤時間が不適切な値です');
                    } elseif ($out < $in) {
                        // 終了が開始より前なら不正として扱う
                        $validator->errors()->add("new_break_out.$i", '休憩時間が不適切な値です');
                    }
                });
        });
    }

    // "H:i"形式だけを受け付け、それ以外（空文字、配列、"25:00"など）はnullを返す変換関数
    private function toMinutes(mixed $time): ?int
    {
        if (! is_string($time) || ! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
            return null;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }
}
