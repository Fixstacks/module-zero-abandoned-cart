<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 *
 * Zero_AbandonedCart: one reminder email for a cart left with items in it.
 * The cart's email is known (a customer, or a guest who got as far as the
 * checkout's address), the cart hasn't changed for a while (an hour by
 * default) and it isn't too old (a week). The email lists the items and links
 * back to the cart: a guest's cart is restored in the browser, a customer is
 * asked to sign in to theirs.
 */
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Zero_AbandonedCart', __DIR__);
