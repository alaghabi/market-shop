<?php

namespace App\ApiResource\PlatformModule;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\PlatformModule\PlatformModuleInput;
use App\Dto\PlatformModule\PlatformModuleOutput;
use App\State\PlatformModule\PlatformModuleProcessor;
use App\State\PlatformModule\PlatformModuleProvider;

#[ApiResource(
    shortName: 'PlatformModule',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/platform-modules',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            output: PlatformModuleOutput::class,
            provider: PlatformModuleProvider::class,
        ),
        new Post(
            uriTemplate: '/admin/platform-modules',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            read: false,
            input: PlatformModuleInput::class,
            output: PlatformModuleOutput::class,
            processor: PlatformModuleProcessor::class,
        ),
        new Get(
            uriTemplate: '/admin/platform-modules/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            output: PlatformModuleOutput::class,
            provider: PlatformModuleProvider::class,
        ),
        new Patch(
            uriTemplate: '/admin/platform-modules/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            input: PlatformModuleInput::class,
            output: PlatformModuleOutput::class,
            provider: PlatformModuleProvider::class,
            processor: PlatformModuleProcessor::class,
        ),
    ],
)]
final class PlatformModuleResource
{
    public ?string $id = null;
    public ?string $moduleId = null;
    public bool $isEnabled = true;
    public ?string $reasonDisabled = null;
}
