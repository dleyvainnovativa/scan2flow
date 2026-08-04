<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a tenant's page balance can't cover a requested debit. Ingestion
 * catches this and marks the record failed with a clear "sin saldo" message;
 * the manual upload path surfaces it as a 402/422 to the user.
 */
class InsufficientPagesException extends RuntimeException
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(
            "Saldo de páginas insuficiente: se requieren {$requested}, disponibles {$available}."
        );
    }
}
