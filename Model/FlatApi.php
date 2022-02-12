<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Model;

use JohnRogar\FlatApi\Api\DataProviderInterface;
use JohnRogar\FlatApi\Api\FlatApiInterface;
use JohnRogar\FlatApi\Api\ManagerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Webapi\Exception as WebException;

class FlatApi implements FlatApiInterface
{
    private RequestInterface $request;

    /**
     * @var iterable<DataProviderInterface>
     */
    private iterable $providers;

    /**
     * @param DataProviderInterface[] $providers
     */
    public function __construct(RequestInterface $request, iterable $providers = [])
    {
        $this->request = $request;
        $this->providers = $providers;
    }

    public function fetch(string $dataIdentifier): array
    {
        if (array_key_exists($dataIdentifier, $this->providers)) {
            $provider = $this->providers[$dataIdentifier];

            $order = $this->request->getParam(DataProviderInterface::DEFAULT_PARAM_ORDER);

            if (!is_array($order)) {
                $order = [];
            }

            $page = (int)$this->request->getParam(DataProviderInterface::DEFAULT_PARAM_PAGE);

            if ($page < 1) {
                $page = ManagerInterface::DEFAULT_PAGE;
            }

            $size = (int)$this->request->getParam(DataProviderInterface::DEFAULT_PARAM_SIZE);

            if ($size < 1) {
                $size = ManagerInterface::DEFAULT_SIZE;
            }

            $filters = [];

            foreach ($this->request->getParams() as $key => $value) {
                if (
                    !in_array(
                        $key,
                        [
                        DataProviderInterface::DEFAULT_PARAM_ORDER,
                        DataProviderInterface::DEFAULT_PARAM_PAGE,
                        DataProviderInterface::DEFAULT_PARAM_SIZE,

                        ]
                    )
                ) {
                    $filters[$key] = $value;
                }
            }

            $resultSet = $provider->getMany($filters, $order, $page, $size);

            return $resultSet->toArray();
        }

        throw new WebException(
            __('Unknown data identifier'),
            0,
            WebException::HTTP_BAD_REQUEST
        );
    }

    /**
     * @inheritDoc
     */
    public function fetchBySku(string $dataIdentifier, string $sku): array
    {
        // @phpstan-ignore-next-line
        $this->request->setParam('sku', $sku);

        return $this->fetch($dataIdentifier);
    }
}
