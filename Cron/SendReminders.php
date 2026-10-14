<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\AbandonedCart\Cron;

use Zero\AbandonedCart\Model\Sender;

class SendReminders
{
    public function __construct(
        private readonly Sender $sender
    ) {
    }

    public function execute(): void
    {
        $this->sender->sendDue();
    }
}
