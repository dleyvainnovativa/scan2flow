<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when creating a resource would exceed the tenant's plan limit
 * (max templates/areas/users). Controllers render this as a 403/422 with the
 * message so the tenant-admin sees a clear "upgrade your plan" signal.
 */
class PlanLimitException extends RuntimeException
{
    public function __construct(
        public readonly string $resource,
        public readonly int $limit,
    ) {
        $labels = [
            'templates' => 'plantillas',
            'areas'     => 'áreas',
            'users'     => 'usuarios',
        ];
        $label = $labels[$resource] ?? $resource;
        parent::__construct(
            "Has alcanzado el límite de tu plan ({$limit} {$label}). Contacta al administrador para ampliarlo."
        );
    }
}
