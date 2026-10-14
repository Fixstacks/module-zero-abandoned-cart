<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\AbandonedCart\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * The carts due a reminder and the reminders sent (zero_abandoned_cart_reminder:
 * one row per cart, so a cart is reminded of once).
 */
class Reminders
{
    private const TABLE = 'zero_abandoned_cart_reminder';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Active carts of the store view with items and an email, last changed
     * between $since and $until (UTC, Y-m-d H:i:s), not reminded of yet.
     *
     * @return array<int, array{quote_id: int, store_id: int, email: string, firstname: string}>
     */
    public function due(int $storeId, string $since, string $until, int $limit): array
    {
        $db = $this->resource->getConnection('checkout');
        $select = $db->select()
            ->from(
                ['q' => $this->resource->getTableName('quote', 'checkout')],
                ['entity_id', 'store_id', 'customer_email', 'customer_firstname']
            )
            ->joinLeft(['r' => $this->table()], 'r.quote_id = q.entity_id', [])
            ->where('r.quote_id IS NULL')
            ->where('q.is_active = 1')
            ->where('q.items_count > 0')
            ->where('q.customer_email LIKE ?', '%@%')
            ->where('q.store_id = ?', $storeId)
            ->where('q.updated_at >= ?', $since)
            ->where('q.updated_at <= ?', $until)
            ->order('q.updated_at ASC')
            ->limit($limit);

        return array_map(
            static fn (array $row) => [
                'quote_id' => (int) $row['entity_id'],
                'store_id' => (int) $row['store_id'],
                'email' => (string) $row['customer_email'],
                'firstname' => trim((string) $row['customer_firstname']),
            ],
            $db->fetchAll($select)
        );
    }

    public function add(int $quoteId, string $token): void
    {
        $this->resource->getConnection('checkout')->insert(
            $this->table(),
            ['quote_id' => $quoteId, 'token' => $token, 'sent_at' => gmdate('Y-m-d H:i:s')]
        );
    }

    /**
     * The cart a restore link is for.
     */
    public function quoteIdByToken(string $token): ?int
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
            return null;
        }
        $db = $this->resource->getConnection('checkout');
        $id = $db->fetchOne(
            $db->select()->from($this->table(), ['quote_id'])->where('token = ?', $token)
        );

        return $id === false ? null : (int) $id;
    }

    public function markRestored(int $quoteId): void
    {
        $this->resource->getConnection('checkout')->update(
            $this->table(),
            ['restored_at' => gmdate('Y-m-d H:i:s')],
            ['quote_id = ?' => $quoteId, 'restored_at IS NULL']
        );
    }

    private function table(): string
    {
        return $this->resource->getTableName(self::TABLE, 'checkout');
    }
}
