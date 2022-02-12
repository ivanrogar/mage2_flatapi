<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Api;

use Generator;
use JohnRogar\FlatApi\Model\Io\Dto;

interface DumperInterface
{
    public function getName(): string;

    public function supports(iterable $objects = []): bool;

    public function setRelativeUrls(bool $relative): self;

    public function isRelativeUrls(): bool;

    /**
     * @param object[] $objects
     * @return array<Dto>
     */
    public function dump(iterable $objects): array;

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function process($key, &$value): void;
}
