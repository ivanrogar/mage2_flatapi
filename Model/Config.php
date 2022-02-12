<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    public const XML_BASE_PATH = 'johnrogar/flatapi/';

    public const XML_PATH_MONGO_URI = self::XML_BASE_PATH . 'mongo_uri';
    public const XML_PATH_MANAGER = self::XML_BASE_PATH . 'manager';

    private ScopeConfigInterface $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function getMongoUri()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_MONGO_URI);
    }

    public function getManager()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_MANAGER);
    }
}
