<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * 勤怠更新API（PUT / PATCH /api/v1/attendance-records/{attendanceRecord}）のリクエストボディの検証。
 *
 * 部分更新に対応する。送られた項目だけを検証・更新し、未送信の項目は既存値を保持する。
 * エラーメッセージは StoreAttendanceRecordRequest（FN060）と同じ。
 */
class UpdateAttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var AttendanceRecord $attendanceRecord */
        $attendanceRecord = $this->route('attendanceRecord');

        return [
            'date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
                // 重複判定に使うのは「更新対象の勤怠の持ち主」の user_id。
                // 管理者が他人の勤怠を更新するときも、持ち主の日付と重複しないかを見る。
                Rule::unique('attendance_records', 'date')
                    ->where('user_id', $attendanceRecord->user_id)
                    ->ignore($attendanceRecord->id),
            ],
            'clock_in' => ['sometimes', 'required', 'date_format:H:i:s'],
            'clock_out' => ['nullable', 'date_format:H:i:s'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * clock_out と clock_in の前後関係を検証する。
     * 片方だけ送られたときは、もう片方に既存の値を使って比べる。
     * （after:clock_in のルールだと、clock_in が未送信のときに比較できないため、ここで検証する）
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // 形式エラーがあるときは、前後関係の検証を重ねない
            if ($validator->errors()->hasAny(['clock_in', 'clock_out'])) {
                return;
            }

            /** @var AttendanceRecord $attendanceRecord */
            $attendanceRecord = $this->route('attendanceRecord');

            $clockIn = $this->has('clock_in') ? $this->input('clock_in') : $attendanceRecord->clock_in;
            $clockOut = $this->has('clock_out') ? $this->input('clock_out') : $attendanceRecord->clock_out;

            if ($clockIn !== null && $clockOut !== null && $clockOut <= $clockIn) {
                $validator->errors()->add('clock_out', '退勤時刻は出勤時刻より後の時刻を指定してください。');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => '勤怠日は必須です。',
            'date.date_format' => '勤怠日は YYYY-MM-DD 形式で指定してください。',
            'date.unique' => 'この日付の勤怠は既に登録されています。',
            'clock_in.required' => '出勤時刻は必須です。',
            'clock_in.date_format' => '出勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out.date_format' => '退勤時刻は HH:MM:SS 形式で指定してください。',
            'comment.max' => '備考は 255 文字以内で入力してください。',
            'comment.string' => '備考は文字列で指定してください。',
        ];
    }
}
