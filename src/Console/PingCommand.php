<?php

namespace OmniConva\Laravel\Console;

use Illuminate\Console\Command;
use OmniConva\Laravel\Exceptions\OmniConvaException;
use OmniConva\Laravel\OmniConva;
use Throwable;

class PingCommand extends Command
{
    protected $signature = 'omniconva:ping';

    protected $description = 'Check the OmniConva webhook URL, token and signing secret';

    public function handle(OmniConva $omniconva): int
    {
        try {
            $result = $omniconva->ping();
        } catch (OmniConvaException $error) {
            $this->components->error($error->getMessage());

            return self::FAILURE;
        } catch (Throwable $error) {
            $this->components->error('Could not reach OmniConva: '.$error->getMessage());

            return self::FAILURE;
        }
        $this->components->info('OmniConva accepted the token and signature'.(isset($result['store']) ? " (store {$result['store']})." : '.'));

        return self::SUCCESS;
    }
}
