<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\AbandonedCart\Test\Unit\Controller\Restore;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zero\AbandonedCart\Controller\Restore\Index;
use Zero\AbandonedCart\Model\Reminders;

/**
 * The reminder's link: whose cart, in which browser.
 *
 * The mocks are shared by the tests; each sets expectations on the ones it's about.
 */
#[AllowMockObjectsWithoutExpectations]
class IndexTest extends TestCase
{
    private const TOKEN = 'b7dcd27f8a0836a18397791efada108c';

    private Reminders&MockObject $reminders;
    private CartRepositoryInterface&MockObject $quoteRepository;
    private CheckoutSession&MockObject $checkoutSession;
    private CustomerSession&MockObject $customerSession;
    private ManagerInterface&MockObject $messages;
    private ?string $path = null;
    private Index $controller;

    protected function setUp(): void
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn(self::TOKEN);
        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function (string $path) use ($redirect) {
            $this->path = $path;
            return $redirect;
        });
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);
        $this->reminders = $this->createMock(Reminders::class);
        $this->quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->messages = $this->createMock(ManagerInterface::class);
        $stores = [];
        foreach ([5 => 2, 1 => 1] as $storeId => $websiteId) {
            $stores[$storeId] = $this->createStub(Store::class);
            $stores[$storeId]->method('getWebsiteId')->willReturn($websiteId);
        }
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturnCallback(static fn ($id = null) => $stores[$id ?? 5]);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturn('https://shop.test/ua/checkout/cart/');
        $this->controller = new Index(
            $request,
            $redirectFactory,
            $this->reminders,
            $this->quoteRepository,
            $this->checkoutSession,
            $this->customerSession,
            $storeManager,
            $url,
            $this->messages
        );
    }

    public function testAGuestsCartBecomesTheBrowsersCart(): void
    {
        $this->givenCart(42, storeId: 5);
        $this->reminders->expects($this->once())->method('markRestored')->with(42);
        $this->checkoutSession->expects($this->once())->method('setQuoteId')->with(42);

        $this->controller->execute();

        $this->assertSame('checkout/cart', $this->path);
    }

    public function testACustomerSignsInToTheirCart(): void
    {
        $this->givenCart(42, storeId: 5, customerId: 7);
        $this->customerSession->method('getCustomerId')->willReturn(null);
        $this->customerSession->expects($this->once())->method('setBeforeAuthUrl')->with('https://shop.test/ua/checkout/cart/');
        $this->checkoutSession->expects($this->never())->method('setQuoteId');

        $this->controller->execute();

        $this->assertSame('customer/account/login', $this->path);
    }

    public function testTheCustomerSignedInGoesStraightToTheCart(): void
    {
        $this->givenCart(42, storeId: 5, customerId: 7);
        $this->customerSession->method('getCustomerId')->willReturn(7);
        $this->checkoutSession->expects($this->never())->method('setQuoteId');

        $this->controller->execute();

        $this->assertSame('checkout/cart', $this->path);
    }

    public function testAGuestsCartJoinsTheSignedInCustomersCartOnce(): void
    {
        $guest = $this->givenCart(42, storeId: 5);
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $current = $this->quote(50);
        $current->expects($this->once())->method('merge')->with($guest)->willReturnSelf();
        $current->method('collectTotals')->willReturnSelf();
        $this->checkoutSession->method('getQuote')->willReturn($current);
        $this->quoteRepository->expects($this->exactly(2))->method('save');
        $this->checkoutSession->expects($this->never())->method('setQuoteId');

        $this->controller->execute();

        $this->assertFalse($guest->getIsActive());
    }

    public function testAnOrderedCartIsNotRestored(): void
    {
        $this->givenCart(42, storeId: 5, active: false);
        $this->messages->expects($this->once())->method('addNoticeMessage');
        $this->reminders->expects($this->never())->method('markRestored');
        $this->checkoutSession->expects($this->never())->method('setQuoteId');

        $this->controller->execute();

        $this->assertSame('checkout/cart', $this->path);
    }

    public function testAnotherWebsitesCartOrAnUnknownTokenIsRefused(): void
    {
        $this->givenCart(42, storeId: 1);
        $this->messages->expects($this->once())->method('addErrorMessage');
        $this->checkoutSession->expects($this->never())->method('setQuoteId');
        $this->controller->execute();

        $this->setUp();
        $this->reminders->method('quoteIdByToken')->willReturn(null);
        $this->messages->expects($this->once())->method('addErrorMessage');
        $this->controller->execute();
    }

    private function givenCart(int $id, int $storeId, ?int $customerId = null, bool $active = true): Quote&MockObject
    {
        // getCustomerId(), getIsActive(), setIsActive() are magic: real data.
        $quote = $this->quote($id);
        $quote->method('getStoreId')->willReturn($storeId);
        $quote->setData(['customer_id' => $customerId, 'is_active' => $active]);
        $this->reminders->method('quoteIdByToken')->with(self::TOKEN)->willReturn($id);
        $this->quoteRepository->method('get')->with($id)->willReturn($quote);

        return $quote;
    }

    private function quote(int $id): Quote&MockObject
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getStoreId', 'merge', 'collectTotals'])
            ->getMock();
        $quote->method('getId')->willReturn($id);

        return $quote;
    }
}
