<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceRecordSeeder extends Seeder
{
    private const FIXED_USER_EMAIL = 'user1@example.com';

    private const PATTERNS = [
        'normal' => ['09:00:00', '18:00:00'],
        'overtime' => ['09:00:00', '20:00:00'],
        'late' => ['09:30:00', '18:00:00'],
        'early' => ['09:00:00', '17:00:00'],
        'long' => ['08:00:00', '21:00:00'],
    ];

    private const LUNCH_BREAK = ['12:00:00', '13:00:00'];

    /**
     * 勤怠記録と休憩のダミーデータを作成する。
     * user1 はレポート予測値と一致する固定データ、それ以外はランダムデータとする。
     */
    public function run(): void
    {
        $fixedUser = User::where('email', self::FIXED_USER_EMAIL)->firstOrFail();
        $this->seedFixedRecords($fixedUser);

        User::where('email', '!=', self::FIXED_USER_EMAIL)
            ->get()
            ->each(fn (User $user) => $this->seedRandomRecords($user));
    }

    /**
     * user1 の意図的データ（過去5ヶ月×平日15日 + 当月平日17日）を作成する。
     */
    private function seedFixedRecords(User $user): void
    {
        $currentMonth = Carbon::today()->startOfMonth();

        $pastDays = collect(range(5, 1))->flatMap(
            fn (int $monthsAgo): Collection => $this->weekdaysOf($currentMonth->copy()->subMonthsNoOverflow($monthsAgo), 15)
                ->map(fn (CarbonInterface $date): array => [$date, 'normal'])
        );

        $currentPatterns = collect([
            ...array_fill(0, 10, 'normal'),
            ...array_fill(0, 3, 'overtime'),
            'late', 'late', 'early', 'long',
        ]);

        $currentDays = $this->weekdaysOf($currentMonth, 17)
            ->zip($currentPatterns)
            ->map(fn (Collection $pair): array => $pair->all());

        $pastDays->concat($currentDays)->each(
            fn (array $day) => $this->createRecord($user, $day[0], ...self::PATTERNS[$day[1]], breaks: [self::LUNCH_BREAK])
        );
    }

    /**
     * 過去6ヶ月〜昨日までの平日について、実運用に近いばらつきのある勤怠を作成する。
     */
    private function seedRandomRecords(User $user): void
    {
        $start = Carbon::today()->subMonthsNoOverflow(5)->startOfMonth();

        collect(CarbonPeriod::create($start, Carbon::yesterday()))
            ->filter(fn (CarbonInterface $date): bool => $date->isWeekday())
            ->reject(fn (): bool => fake()->boolean(10))
            ->each(function (CarbonInterface $date) use ($user): void {
                $clockIn = $date->copy()->setTime(9, 0)->addMinutes(fake()->numberBetween(-3, 3) * 5);
                $clockOut = $date->copy()->setTime(18, 0)->addMinutes(fake()->numberBetween(-3, 18) * 5);
                $breaks = fake()->boolean(30)
                    ? [self::LUNCH_BREAK, ['15:00:00', '15:15:00']]
                    : [self::LUNCH_BREAK];

                $this->createRecord($user, $date, $clockIn->format('H:i:s'), $clockOut->format('H:i:s'), $breaks);
            });
    }

    /**
     * 指定月の平日を先頭から指定件数取得する。
     *
     * @return Collection<int, CarbonInterface>
     */
    private function weekdaysOf(CarbonInterface $month, int $count): Collection
    {
        return collect(CarbonPeriod::create($month->copy()->startOfMonth(), $month->copy()->endOfMonth()))
            ->filter(fn (CarbonInterface $date): bool => $date->isWeekday())
            ->take($count)
            ->values();
    }

    /**
     * 勤怠記録1件と、それに紐づく休憩を作成する。
     *
     * @param  array<int, array{0: string, 1: string}>  $breaks
     */
    private function createRecord(User $user, CarbonInterface $date, string $clockIn, string $clockOut, array $breaks): void
    {
        $record = $user->attendanceRecords()->create([
            'date' => $date->toDateString(),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
        ]);

        $record->breaks()->createMany(
            collect($breaks)->map(fn (array $break): array => [
                'break_in' => $break[0],
                'break_out' => $break[1],
            ])->all()
        );
    }
}
