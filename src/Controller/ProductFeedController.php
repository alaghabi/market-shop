<?php

namespace App\Controller;

use App\Entity\Boutique;
use App\Repository\ProductRepository;
use App\Service\Boutique\SubdomainResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class ProductFeedController
{
    public function __construct(
        private ProductRepository $products,
        private SubdomainResolver $subdomainResolver,
        private string $rootDomain,
    ) {
    }

    #[Route('/api/products/feed.xml', name: 'product_feed', methods: ['GET'], priority: 100)]
    #[Route('/products/feed.xml', name: 'product_feed_short', methods: ['GET'], priority: 100)]
    public function __invoke(Request $request): Response
    {
        $boutique = $request->attributes->get('_boutique')
            ?? $this->subdomainResolver->resolveFromRequest($request);

        if (!$boutique instanceof Boutique) {
            return new Response('<?xml version="1.0"?><error>Boutique non trouvée</error>', 404, ['Content-Type' => 'text/xml; charset=utf-8']);
        }

        $shopUrl = $boutique->getSubdomainUrl($this->rootDomain);
        $productList = $this->products->findSeoIndexedByBoutique($boutique);
        $currency = $boutique->getSettings()?->getLanguageConfig()['default_currency'] ?? 'TND';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">'."\n";
        $xml .= '  <channel>'."\n";
        $xml .= '    <title><![CDATA['.htmlspecialchars($boutique->getName() ?? '').']]></title>'."\n";
        $xml .= '    <link>'.htmlspecialchars($shopUrl).'</link>'."\n";
        $xml .= '    <description><![CDATA[Catalog '.htmlspecialchars($boutique->getName() ?? '').']]></description>'."\n";

        foreach ($productList as $product) {
            $images = $product->getImages();
            $firstImage = $images->first();
            $imageUrl = $firstImage ? $firstImage->getUrl() : '';

            $brand = $product->getBrand();
            $brandName = $brand?->getName() ?? '';
            $availability = $product->getManageStock() && $product->getStockQuantity() < 1 ? 'out of stock' : 'in stock';
            $price = number_format($product->getSellingPrice() / 1000, 3, '.', '');
            $link = $shopUrl.'/p/'.$product->getSlug();

            $xml .= '    <item>'."\n";
            $xml .= '      <g:id>'.htmlspecialchars($product->getSku()).'</g:id>'."\n";
            $xml .= '      <g:title><![CDATA['.htmlspecialchars($product->getName()).']]></g:title>'."\n";
            $xml .= '      <g:description><![CDATA['.htmlspecialchars(strip_tags((string) $product->getShortDescription() ?: (string) $product->getDescription() ?: '')).']]></g:description>'."\n";
            $xml .= '      <g:link>'.htmlspecialchars($link).'</g:link>'."\n";
            if ($imageUrl) {
                $xml .= '      <g:image_link>'.htmlspecialchars($imageUrl).'</g:image_link>'."\n";
            }
            $xml .= '      <g:condition>new</g:condition>'."\n";
            $xml .= '      <g:availability>'.$availability.'</g:availability>'."\n";
            $xml .= '      <g:price>'.$price.' '.$currency.'</g:price>'."\n";
            if ($brandName) {
                $xml .= '      <g:brand><![CDATA['.htmlspecialchars($brandName).']]></g:brand>'."\n";
            }
            if ($product->getBarcode()) {
                $xml .= '      <g:gtin>'.htmlspecialchars($product->getBarcode()).'</g:gtin>'."\n";
            }
            $xml .= '      <g:mpn>'.htmlspecialchars($product->getSku()).'</g:mpn>'."\n";
            $xml .= '    </item>'."\n";
        }

        $xml .= '  </channel>'."\n";
        $xml .= '</rss>'."\n";

        return new Response($xml, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
    }
}
