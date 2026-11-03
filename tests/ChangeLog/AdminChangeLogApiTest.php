<?php declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use MuckiLogPlugin\Core\AdminChangeLog\AdminChangeLogDefinition;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Response\Type\Api\JsonType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

/**
 * Guards the admin module (Task 10): the Admin API must expose every field
 * the change log list and diff render.
 *
 * The response is built with the Admin API JSON response type (JsonType, the
 * one `POST /api/search/...` uses for `Accept: application/json`), which strips
 * every field that is not ApiAware for the `/api` base URL. A full HTTP round
 * trip is not possible in this shop's test environment: symfony/browser-kit
 * (needed by AdminApiTestBehaviour::getBrowser()) is not installed and the
 * test JWT secret is too short to sign an OAuth token.
 */
class AdminChangeLogApiTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSearchResponseContainsAllFieldsUsedByTheAdministration(): void
    {
        $id = $this->createEntry();
        $entry = $this->searchViaAdminApiResponse($id);

        static::assertSame($id, $entry['id'] ?? null);
        static::assertSame('Rotes T-Shirt', $entry['entityLabel'] ?? null);
        static::assertSame('user', $entry['sourceType'] ?? null);
        static::assertSame('192.168.1.20', $entry['ipAddress'] ?? null);
        static::assertSame('Torsten', $entry['userName'] ?? null);
        static::assertSame('update', $entry['action'] ?? null);
        static::assertSame('product', $entry['entityName'] ?? null);
        static::assertArrayHasKey('createdAt', $entry);
        // assertEquals: MySQL JSON columns do not keep the key order.
        static::assertEquals(
            [['field' => 'name', 'subEntity' => 'product_translation', 'context' => ['languageId' => 'abc'], 'old' => 'Red shirt', 'new' => 'Red T-Shirt', 'masked' => false]],
            $entry['changes'] ?? null
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function searchViaAdminApiResponse(string $id): array
    {
        $context = new Context(new AdminApiSource(null));
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('user');
        $definition = $this->getContainer()->get(AdminChangeLogDefinition::class);
        $result = $this->repo()->search($criteria, $context);
        $request = Request::create('http://localhost/api/search/muwa-admin-change-log', 'POST');

        $response = $this->getContainer()->get(JsonType::class)
            ->createListingResponse($criteria, $result, $definition, $request, $context);
        $body = json_decode((string) $response->getContent(), true);

        return $body['data'][0] ?? [];
    }

    private function repo(): EntityRepository
    {
        return $this->getContainer()->get(AdminChangeLogDefinition::ENTITY_NAME . '.repository');
    }

    private function createEntry(): string
    {
        $id = Uuid::randomHex();
        $this->repo()->create([[
            'id' => $id,
            'entityName' => 'product',
            'entityId' => Uuid::randomHex(),
            'entityLabel' => 'Rotes T-Shirt',
            'action' => 'update',
            'sourceType' => 'user',
            'userName' => 'Torsten',
            'ipAddress' => '192.168.1.20',
            'changes' => [[
                'field' => 'name',
                'subEntity' => 'product_translation',
                'context' => ['languageId' => 'abc'],
                'old' => 'Red shirt',
                'new' => 'Red T-Shirt',
                'masked' => false,
            ]],
        ]], Context::createDefaultContext());

        return $id;
    }
}
