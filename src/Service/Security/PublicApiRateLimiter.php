<?php

namespace App\Service\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class PublicApiRateLimiter
{
    public function __construct(
        private RequestStack $requestStack,
        private Security $security,
        #[Autowire(service: 'limiter.public_cart')]
        private RateLimiterFactory $publicCartLimiter,
        #[Autowire(service: 'limiter.public_checkout')]
        private RateLimiterFactory $publicCheckoutLimiter,
        #[Autowire(service: 'limiter.public_reference')]
        private RateLimiterFactory $publicReferenceLimiter,
        #[Autowire(service: 'limiter.public_suggestion')]
        private RateLimiterFactory $publicSuggestionLimiter,
        #[Autowire(service: 'limiter.public_review')]
        private RateLimiterFactory $publicReviewLimiter,
    ) {
    }

    public function consumeCart(string $scope = 'default'): void
    {
        $this->consume($this->publicCartLimiter, 'cart:'.$scope, 'Too many cart requests. Please try again in a minute.');
    }

    public function consumeCheckout(string $scope = 'default'): void
    {
        $this->consume($this->publicCheckoutLimiter, 'checkout:'.$scope, 'Too many checkout attempts. Please wait before retrying.');
    }

    public function consumeReference(string $scope = 'default'): void
    {
        $this->consume($this->publicReferenceLimiter, 'reference:'.$scope, 'Too many reference requests. Please try again shortly.');
    }

    public function consumeSuggestion(string $scope = 'create'): void
    {
        $this->consume($this->publicSuggestionLimiter, 'suggestion:'.$scope, 'Too many suggestion requests. Please try again shortly.');
    }

    public function consumeReview(string $scope = 'create'): void
    {
        $this->consume($this->publicReviewLimiter, 'review:'.$scope, 'Too many review requests. Please try again later.');
    }

    private function consume(RateLimiterFactory $factory, string $scope, string $message): void
    {
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $ip = $request?->getClientIp() ?? 'unknown';
        $host = $request?->getHost() ?? 'unknown';
        $key = sprintf('%s:%s:%s', $ip, $host, $scope);
        $limit = $factory->create($key)->consume();

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();
            $seconds = null !== $retryAfter ? max(1, $retryAfter->getTimestamp() - time()) : null;

            throw new TooManyRequestsHttpException($seconds, $message);
        }
    }
}
