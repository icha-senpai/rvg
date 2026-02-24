<?php

namespace App\Domain\Media;

class MediaVisibilityResult
{
    public function __construct(
        public array $filters,
        public ?string $validationErrorMessage = null,
    ) {}
}
