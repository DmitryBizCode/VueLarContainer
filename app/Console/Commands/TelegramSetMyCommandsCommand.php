<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class TelegramSetMyCommandsCommand extends Command
{
    protected $signature = 'telegram:set-my-commands';

    protected $description = 'Register the bot command list with Telegram (Bot API setMyCommands)';

    public function handle(): int
    {
        $client = TelegramBotClient::fromConfig();

        try {
            $client->registerDefaultBotCommands();
            Cache::forever(TelegramBotClient::myCommandsCacheKey(), true);
        } catch (Throwable $e) {
            $this->error('setMyCommands failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Telegram bot commands registered.');

        return self::SUCCESS;
    }
}
