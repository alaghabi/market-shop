<?php

namespace App\ApiResource\Boutique;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\Boutique\ThemeProvider;
use App\State\Boutique\ThemeProcessor;

#[ApiResource(
    shortName: 'Theme',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/themes',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            output: \App\Dto\Boutique\ThemeOutput::class,
            provider: ThemeProvider::class,
        ),
        new Post(
            uriTemplate: '/admin/themes',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            input: \App\Dto\Boutique\ThemeInput::class,
            processor: ThemeProcessor::class,
        ),
        new Get(
            uriTemplate: '/admin/themes/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            output: \App\Dto\Boutique\ThemeOutput::class,
            provider: ThemeProvider::class,
        ),
        new Patch(
            uriTemplate: '/admin/themes/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            input: \App\Dto\Boutique\ThemeInput::class,
            provider: ThemeProvider::class,
            processor: ThemeProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/themes/{id}',
            security: "is_granted('ROLE_SUPER_ADMIN')",
            read: false,
            processor: ThemeProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/themes',
            output: \App\Dto\Boutique\ThemeOutput::class,
            provider: ThemeProvider::class,
        ),
    ],
)]
final class ThemeResource
{
    public ?string $id = null;
    public ?string $name = null;
    public ?string $code = null;
}
