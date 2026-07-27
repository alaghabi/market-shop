<?php

namespace App\Tests\Service\Delivery;

use App\Dto\Delivery\BoutiqueDeliveryAccountInput;
use App\Entity\DeliveryCompany;
use App\Enum\DeliveryAuthType;
use App\Service\Delivery\DeliveryCredentialFieldSchema;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class DeliveryCredentialFieldSchemaTest extends TestCase
{
    public function testFieldsForUsesCompanyCredentialSchema(): void
    {
        $company = new DeliveryCompany(
            name: 'First Delivery',
            slug: 'first-delivery',
            baseUrl: 'https://example.test',
            provider: 'first_delivery',
            authType: DeliveryAuthType::Bearer,
            authConfig: [
                'credentialFields' => [
                    ['key' => 'token', 'label' => 'Jeton API', 'type' => 'password', 'required' => true],
                ],
            ],
        );

        $fields = (new DeliveryCredentialFieldSchema())->fieldsFor($company);

        self::assertCount(1, $fields);
        self::assertSame('token', $fields[0]['key']);
        self::assertTrue($fields[0]['required']);
    }

    public function testAssertValidRejectsMissingRequiredField(): void
    {
        $company = new DeliveryCompany(
            name: 'Navex',
            slug: 'navex',
            baseUrl: 'https://example.test',
            provider: 'navex',
            authType: DeliveryAuthType::Basic,
            authConfig: [
                'credentialFields' => [
                    ['key' => 'token', 'label' => 'Jeton', 'required' => true],
                ],
            ],
        );
        $input = new BoutiqueDeliveryAccountInput();
        $input->deliveryCompanyId = 'x';

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Jeton');

        (new DeliveryCredentialFieldSchema())->assertValid($company, $input);
    }

    public function testAssertValidAcceptsProvidedRequiredField(): void
    {
        $company = new DeliveryCompany(
            name: 'Navex',
            slug: 'navex',
            baseUrl: 'https://example.test',
            provider: 'navex',
            authType: DeliveryAuthType::Basic,
            authConfig: [
                'credentialFields' => [
                    ['key' => 'token', 'label' => 'Jeton', 'required' => true],
                ],
            ],
        );
        $input = new BoutiqueDeliveryAccountInput();
        $input->token = 'secret-token';

        (new DeliveryCredentialFieldSchema())->assertValid($company, $input);
        $this->addToAssertionCount(1);
    }
}
