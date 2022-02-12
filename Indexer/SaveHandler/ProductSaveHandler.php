<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Indexer\SaveHandler;

use JohnRogar\FlatApi\Api\DumperInterface;
use JohnRogar\FlatApi\Api\ObjectRepositoryInterface;
use Magento\Framework\Indexer\SaveHandler\IndexerInterface;
use Traversable;

class ProductSaveHandler extends AbstractHandler implements IndexerInterface
{
    private DumperInterface $dumper;
    private ObjectRepositoryInterface $objectRepository;

    public function __construct(
        DumperInterface $dumper,
        ObjectRepositoryInterface $objectRepository
    ) {
        $this->dumper = $dumper;
        $this->objectRepository = $objectRepository;
    }

    /**
     * @inheritDoc
     */
    public function saveIndex($dimensions, Traversable $documents)
    {
        $this
            ->objectRepository
            ->save(
                $this->dumper->dump($documents)
            );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function deleteIndex($dimensions, Traversable $documents)
    {
        $this
            ->objectRepository
            ->delete(
                $this->dumper->dump($documents)
            );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function cleanIndex($dimensions)
    {
        $this->objectRepository->truncate();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isAvailable($dimensions = [])
    {
        return true;
    }
}
