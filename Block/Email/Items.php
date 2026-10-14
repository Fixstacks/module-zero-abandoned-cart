<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\AbandonedCart\Block\Email;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Helper\Product\Configuration;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;

/**
 * The cart's items in the reminder email (layout handle
 * zero_abandoned_cart_email_items, given the quote_id: email templates pass
 * scalars to layout blocks, not objects).
 */
class Items extends Template
{
    /** @var string */
    protected $_template = 'Zero_AbandonedCart::email/items.phtml';

    public function __construct(
        Template\Context $context,
        private readonly Image $imageHelper,
        private readonly Configuration $configuration,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly CartRepositoryInterface $quoteRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return Item[]
     */
    public function getItems(): array
    {
        $quote = $this->getQuote();

        return $quote ? $quote->getAllVisibleItems() : [];
    }

    public function getQuote(): ?Quote
    {
        if (!$this->hasData('quote')) {
            try {
                $quote = $this->quoteRepository->get((int) $this->getData('quote_id'));
            } catch (NoSuchEntityException) {
                $quote = null;
            }
            $this->setData('quote', $quote);
        }

        return $this->getData('quote');
    }

    /**
     * "Size: 92" and the like.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function getOptions(Item $item): array
    {
        // Custom options, and a configurable product's (Magento_ConfigurableProduct's plugin).
        return array_map(
            static fn (array $option) => [
                'label' => (string) $option['label'],
                'value' => strip_tags((string) ($option['print_value'] ?? $option['value'])),
            ],
            $this->configuration->getOptions($item)
        );
    }

    public function getImageUrl(Item $item): string
    {
        $child = $item->getOptionByCode('simple_product')?->getProduct();
        $product = $child && $child->getThumbnail() && $child->getThumbnail() !== 'no_selection'
            ? $child
            : $item->getProduct();

        return $this->imageHelper->init($product, 'cart_page_product_thumbnail')->getUrl();
    }

    public function getRowTotal(Item $item): string
    {
        $quote = $this->getQuote();

        return $this->priceCurrency->format(
            (float) ($item->getRowTotalInclTax() ?: $item->getRowTotal()),
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            $quote->getStore(),
            $quote->getQuoteCurrencyCode()
        );
    }
}
