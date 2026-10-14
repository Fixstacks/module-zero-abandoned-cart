<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\AbandonedCart\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zero\AbandonedCart\Model\Sender;

/**
 * bin/magento zero:abandoned-cart:send: what cron does every 10 minutes, now;
 * --dry-run lists the carts due, --preview=<cart id> prints a due cart's email.
 */
class Send extends Command
{
    public function __construct(
        private readonly Sender $sender,
        private readonly State $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('zero:abandoned-cart:send')
            ->setDescription('Send the abandoned cart reminders due')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the carts due, send nothing')
            ->addOption('preview', null, InputOption::VALUE_REQUIRED, 'Print the email of the due cart with this id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->state->emulateAreaCode(Area::AREA_FRONTEND, fn () => $this->handle($input, $output));
    }

    private function handle(InputInterface $input, OutputInterface $output): int
    {
        $due = $this->sender->due();
        $preview = $input->getOption('preview');
        if ($preview !== null) {
            foreach ($due as $cart) {
                if ($cart['quote_id'] === (int) $preview) {
                    $output->writeln($this->sender->preview($cart));
                    return Cli::RETURN_SUCCESS;
                }
            }
            $output->writeln('<error>Cart ' . $preview . ' is not due a reminder.</error>');
            return Cli::RETURN_FAILURE;
        }
        if ($input->getOption('dry-run')) {
            foreach ($due as $cart) {
                $output->writeln(
                    sprintf('cart %d, store %d: %s', $cart['quote_id'], $cart['store_id'], $cart['email'])
                );
            }
            $output->writeln(count($due) . ' due');
            return Cli::RETURN_SUCCESS;
        }
        $output->writeln($this->sender->sendDue() . ' of ' . count($due) . ' sent');

        return Cli::RETURN_SUCCESS;
    }
}
