<?php

namespace App\Service\Cart;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class CartCookieSubscriber implements EventSubscriberInterface
{
    public const COOKIE_NAME_ATTRIBUTE = '_market_shop_cart_cookie_name';
    public const COOKIE_VALUE_ATTRIBUTE = '_market_shop_cart_cookie_value';
    public const COOKIE_CLEAR_ATTRIBUTE = '_market_shop_cart_cookie_clear';

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $cookieName = $request->attributes->get(self::COOKIE_NAME_ATTRIBUTE);

        if (!is_string($cookieName) || '' === $cookieName) {
            return;
        }

        if ($request->attributes->getBoolean(self::COOKIE_CLEAR_ATTRIBUTE)) {
            $event->getResponse()->headers->clearCookie($cookieName, '/', null, $request->isSecure(), true, 'lax');

            return;
        }

        $cookieValue = $request->attributes->get(self::COOKIE_VALUE_ATTRIBUTE);
        if (!is_string($cookieValue) || '' === $cookieValue) {
            return;
        }

        $event->getResponse()->headers->setCookie(Cookie::create(
            $cookieName,
            $cookieValue,
            new \DateTimeImmutable('+7 days'),
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_LAX,
        ));
    }
}
