# Zero_AbandonedCart

One reminder email for a cart left with items in it.
The cart's email is known (a customer, or a guest who got as far as the
checkout's address), the cart hasn't changed for a while (an hour by
default) and it isn't too old (a week). The email lists the items and links
back to the cart: a guest's cart is restored in the browser, a customer is
asked to sign in to theirs.

## Install

Requires Magento Open Source 2.4.7–2.4.9 and PHP 8.2–8.4.

```bash
composer require fixstacks/module-zero-abandoned-cart
bin/magento setup:upgrade
bin/magento setup:di:compile           # production mode
bin/magento setup:static-content:deploy # production mode
bin/magento cache:flush
```

Composer installs `fixstacks/module-zero-core` with it.

Part of [`fixstacks/zero`](https://fixstacks.com/zero), which installs the Zero theme and every Zero module at once.

## License

OSL-3.0 or AFL-3.0, your choice: see `NOTICE.md`.

---

Zero is made by [Fixstacks](https://fixstacks.com/zero) in partnership with [Vilmate](https://vilmate.com).
Documentation: https://fixstacks.com/zero/docs. Demo store: https://zero.fixstacks.com.
