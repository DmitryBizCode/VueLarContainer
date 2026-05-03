<?php

namespace App\Services\Telegram;

use App\Models\TelegramLinkCode;
use App\Models\User;
use Illuminate\Support\Str;

final class TelegramLinkCodeService
{
    private const TTL_MINUTES = 15;

    public static function hashPlain(string $plain): string
    {
        return hash('sha256', strtoupper(trim($plain)));
    }

    /**
     * @return array{plain: string, expires_at: \Illuminate\Support\Carbon}
     */
    public function issueForUser(User $user): array
    {
        TelegramLinkCode::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $plain = strtoupper(Str::random(14));
        $row = TelegramLinkCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => self::hashPlain($plain),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        return [
            'plain' => $plain,
            'expires_at' => $row->expires_at,
        ];
    }

    public function findValidByPlain(string $plain): ?TelegramLinkCode
    {
        $hash = self::hashPlain($plain);

        return TelegramLinkCode::query()
            ->where('code_hash', $hash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>=', now())
            ->with('user')
            ->first();
    }

    public function consume(TelegramLinkCode $code): void
    {
        $code->forceFill(['consumed_at' => now()])->save();
    }
}
