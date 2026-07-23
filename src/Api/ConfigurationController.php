<?php declare(strict_types=1);

namespace Frosh\Jetpack\Api;

use Frosh\Jetpack\Configuration\ConfigurationAddress;
use Frosh\Jetpack\Configuration\ConfigurationAdminReader;
use Frosh\Jetpack\Configuration\ConfigurationException;
use Frosh\Jetpack\Configuration\ConfigurationWriteService;
use Frosh\Jetpack\Configuration\StoredConfigurationValue;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
final class ConfigurationController
{
    public function __construct(
        private readonly ConfigurationAdminReader $reader,
        private readonly ConfigurationWriteService $writer,
    ) {
    }

    #[Route(
        path: '/api/_action/frosh-jetpack/configuration',
        name: 'api.action.frosh-jetpack.configuration.list',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['frosh_jetpack_configuration:read']],
        methods: [Request::METHOD_GET],
    )]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->reader->list());
    }

    #[Route(
        path: '/api/_action/frosh-jetpack/configuration/{bundle}',
        name: 'api.action.frosh-jetpack.configuration.detail',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['frosh_jetpack_configuration:read']],
        methods: [Request::METHOD_GET],
    )]
    public function detail(string $bundle, Request $request, Context $context): JsonResponse
    {
        $languageId = $this->nullableString($request->query->get('languageId'));
        if ($languageId !== null && $context->getLanguageId() !== $languageId) {
            throw new BadRequestHttpException('The languageId query parameter must match the sw-language-id header.');
        }

        try {
            return new JsonResponse($this->reader->read(
                $bundle,
                $context,
                $this->nullableString($request->query->get('salesChannelId')),
                $languageId,
            ));
        } catch (ConfigurationException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }
    }

    #[Route(
        path: '/api/_action/frosh-jetpack/configuration/{bundle}',
        name: 'api.action.frosh-jetpack.configuration.save',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => [
            'frosh_jetpack_configuration:create',
            'frosh_jetpack_configuration:update',
            'frosh_jetpack_configuration:delete',
        ]],
        methods: [Request::METHOD_PATCH],
    )]
    public function save(string $bundle, Request $request): JsonResponse
    {
        try {
            $writes = array_values(array_map($this->write(...), $request->request->all('writes')));
            $deletes = array_values(array_map($this->address(...), $request->request->all('deletes')));
            $this->writer->apply($bundle, $writes, $deletes);
        } catch (ConfigurationException|\TypeError $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string, mixed> $operation
     */
    private function write(array $operation): StoredConfigurationValue
    {
        if (!\array_key_exists('value', $operation)) {
            throw new BadRequestHttpException('A write operation requires a value.');
        }

        return new StoredConfigurationValue($this->address($operation), $operation['value']);
    }

    /**
     * @param array<string, mixed> $operation
     */
    private function address(array $operation): ConfigurationAddress
    {
        if (!isset($operation['key']) || !\is_string($operation['key'])) {
            throw new BadRequestHttpException('A configuration operation requires a string key.');
        }

        return new ConfigurationAddress(
            $operation['key'],
            $this->nullableString($operation['salesChannelId'] ?? null),
            $this->nullableString($operation['languageId'] ?? null),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!\is_string($value)) {
            throw new BadRequestHttpException('Scope identifiers must be strings or null.');
        }

        return $value;
    }
}
