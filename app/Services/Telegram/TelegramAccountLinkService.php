<?php

namespace App\Services\Telegram;

use App\Models\UserTelegramLink;
use Illuminate\Support\Facades\DB;

final class TelegramAccountLinkService
{
    public function __construct(
        private readonly TelegramLinkCodeService $codes,
    ) {}

    /**
     * @param  array{id?: int, username?: string|null, first_name?: string|null, last_name?: string|null}|null  $from
     */
    public function tryLink(int $chatId, ?array $from, string $plainCode): TelegramLinkAttemptResult
    {
        $plainCode = trim($plainCode);
        if ($plainCode === '') {
            return new TelegramLinkAttemptResult(TelegramLinkAttemptStatus::InvalidCode);
        }

        $codeRow = $this->codes->findValidByPlain($plainCode);
        if ($codeRow === null || $codeRow->user === null) {
            return new TelegramLinkAttemptResult(TelegramLinkAttemptStatus::InvalidCode);
        }

        $targetUserId = (int) $codeRow->user_id;

        $existing = UserTelegramLink::query()
            ->where('telegram_chat_id', $chatId)
            ->first();

        if ($existing !== null && (int) $existing->user_id !== $targetUserId) {
            return new TelegramLinkAttemptResult(TelegramLinkAttemptStatus::TelegramInUseByOtherUser);
        }

        if ($existing !== null && (int) $existing->user_id === $targetUserId) {
            $this->applyTelegramProfile($existing, $from);
            $existing->forceFill([
                'status' => UserTelegramLink::STATUS_ACTIVE,
                'last_error' => null,
                'last_error_at' => null,
                'last_activity_at' => now(),
            ])->save();

            return new TelegramLinkAttemptResult(TelegramLinkAttemptStatus::AlreadyLinked, $existing);
        }

        return DB::transaction(function () use ($chatId, $from, $codeRow, $targetUserId) {
            $this->codes->consume($codeRow);

            $link = UserTelegramLink::query()->create([
                'user_id' => $targetUserId,
                'telegram_chat_id' => $chatId,
                'telegram_user_id' => isset($from['id']) ? (int) $from['id'] : null,
                'telegram_username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
                'status' => UserTelegramLink::STATUS_ACTIVE,
                'linked_at' => now(),
                'last_activity_at' => now(),
            ]);

            return new TelegramLinkAttemptResult(TelegramLinkAttemptStatus::Linked, $link);
        });
    }

    public function unlinkChat(int $chatId): bool
    {
        $deleted = UserTelegramLink::query()
            ->where('telegram_chat_id', $chatId)
            ->delete();

        return $deleted > 0;
    }

    /**
     * @param  array{id?: int, username?: string|null, first_name?: string|null, last_name?: string|null}|null  $from
     */
    private function applyTelegramProfile(UserTelegramLink $link, ?array $from): void
    {
        if ($from === null) {
            return;
        }
        if (isset($from['id'])) {
            $link->telegram_user_id = (int) $from['id'];
        }
        if (array_key_exists('username', $from)) {
            $link->telegram_username = $from['username'];
        }
        if (array_key_exists('first_name', $from)) {
            $link->first_name = $from['first_name'];
        }
        if (array_key_exists('last_name', $from)) {
            $link->last_name = $from['last_name'];
        }
    }

    public function markLinkDisabledForDeliveryFailure(UserTelegramLink $link, string $reason): void
    {
        $link->forceFill([
            'status' => UserTelegramLink::STATUS_DISABLED,
            'last_error' => mb_substr($reason, 0, 500),
            'last_error_at' => now(),
        ])->save();
    }

    public function touchActivity(UserTelegramLink $link): void
    {
        $link->forceFill(['last_activity_at' => now()])->save();
    }
}
