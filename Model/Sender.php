<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\AbandonedCart\Model;

use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Url;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends the reminders due in the store views where they're on
 * (zero_abandoned_cart/general/*).
 */
class Sender
{
    /** Carts per store view per run (cron runs every 10 minutes). */
    public const BATCH = 100;

    public function __construct(
        private readonly Reminders $reminders,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Url $url,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<int, array{quote_id: int, store_id: int, email: string, firstname: string}>
     */
    public function due(?int $now = null): array
    {
        $now ??= time();
        $due = [];
        foreach ($this->storeManager->getStores() as $store) {
            if (!$store->isActive() || !$this->scopeConfig->isSetFlag(
                'zero_abandoned_cart/general/enabled',
                ScopeInterface::SCOPE_STORE,
                $store->getId()
            )) {
                continue;
            }
            $delay = max(1, (int) $this->value('delay', $store));
            $maxAge = max(1, (int) $this->value('max_age', $store));
            array_push($due, ...$this->reminders->due(
                (int) $store->getId(),
                gmdate('Y-m-d H:i:s', $now - $maxAge * 86400),
                gmdate('Y-m-d H:i:s', $now - $delay * 60),
                self::BATCH
            ));
        }

        return $due;
    }

    /**
     * @return int reminders sent
     */
    public function sendDue(): int
    {
        $sent = 0;
        foreach ($this->due() as $cart) {
            try {
                $this->send($cart);
                $sent++;
            } catch (\Throwable $e) {
                $this->logger->error('Zero_AbandonedCart: cart ' . $cart['quote_id'] . ': ' . $e->getMessage());
            }
        }

        return $sent;
    }

    /**
     * @param array{quote_id: int, store_id: int, email: string, firstname: string} $cart
     */
    public function send(array $cart): void
    {
        $token = bin2hex(random_bytes(16));
        // Recorded first: a cart whose email fails isn't retried every 10 minutes.
        $this->reminders->add($cart['quote_id'], $token);
        $this->message($cart, $token)->getTransport()->sendMessage();
    }

    /**
     * The email's HTML without sending or recording it (its link restores nothing).
     *
     * @param array{quote_id: int, store_id: int, email: string, firstname: string} $cart
     */
    public function preview(array $cart): string
    {
        return quoted_printable_decode(
            $this->message($cart, str_repeat('0', 32))->getTransport()->getMessage()->getBodyText()
        );
    }

    /**
     * @param array{quote_id: int, store_id: int, email: string, firstname: string} $cart
     */
    private function message(array $cart, string $token): TransportBuilder
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore($cart['store_id']);
        $quote = $this->quoteRepository->get($cart['quote_id']);

        return $this->transportBuilder
            ->setTemplateIdentifier((string) $this->value('template', $store))
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => (int) $store->getId()])
            ->setTemplateVars([
                'quote_id' => (int) $quote->getId(),
                'store' => $store,
                'customer_name' => $cart['firstname'],
                // Not $store->getUrl(): for a store other than the current one it adds ?___store=.
                'restore_url' => $this->url->getUrl(
                    'abandonedcart/restore/index',
                    ['_scope' => $store, 'token' => $token, '_nosid' => true]
                ),
            ])
            ->setFromByScope((string) $this->value('identity', $store), (int) $store->getId())
            ->addTo($cart['email'], $cart['firstname']);
    }

    private function value(string $field, Store $store): mixed
    {
        return $this->scopeConfig->getValue(
            'zero_abandoned_cart/general/' . $field,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }
}
