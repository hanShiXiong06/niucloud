<?php
/**
 * PluginUseRelationInfo
 *
 * PHP version 7.4
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 */

/**
 * 支付宝开放平台API
 *
 * 支付宝开放平台v3协议文档
 *
 * The version of the OpenAPI document: 2023-10-25
 * Generated by: https://openapi-generator.tech
 * OpenAPI Generator version: 6.2.1
 */

/**
 * NOTE: This class is auto generated by OpenAPI Generator (https://openapi-generator.tech).
 * https://openapi-generator.tech
 * Do not edit the class manually.
 */

namespace Alipay\OpenAPISDK\Model;

use \ArrayAccess;
use \Alipay\OpenAPISDK\ObjectSerializer;

/**
 * PluginUseRelationInfo Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class PluginUseRelationInfo implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'PluginUseRelationInfo';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'betaMemo' => 'string',
        'betaPluginVersion' => 'string',
        'betaQrCodeUrl' => 'string',
        'betaStatus' => 'string',
        'gmtActive' => 'string',
        'gmtCreate' => 'string',
        'gmtInvalid' => 'string',
        'miniAppId' => 'string',
        'pluginDeployVersion' => 'string',
        'pluginId' => 'string',
        'pluginStatus' => 'string',
        'pluginUseConfigInfoList' => '\Alipay\OpenAPISDK\Model\PluginUseConfigInfo[]',
        'pluginVersion' => 'string',
        'runModeType' => 'string',
        'sourceFrom' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'betaMemo' => null,
        'betaPluginVersion' => null,
        'betaQrCodeUrl' => null,
        'betaStatus' => null,
        'gmtActive' => null,
        'gmtCreate' => null,
        'gmtInvalid' => null,
        'miniAppId' => null,
        'pluginDeployVersion' => null,
        'pluginId' => null,
        'pluginStatus' => null,
        'pluginUseConfigInfoList' => null,
        'pluginVersion' => null,
        'runModeType' => null,
        'sourceFrom' => null
    ];

    /**
     * Array of property to type mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function openAPITypes()
    {
        return self::$openAPITypes;
    }

    /**
     * Array of property to format mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function openAPIFormats()
    {
        return self::$openAPIFormats;
    }

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @var string[]
     */
    protected static $attributeMap = [
        'betaMemo' => 'beta_memo',
        'betaPluginVersion' => 'beta_plugin_version',
        'betaQrCodeUrl' => 'beta_qr_code_url',
        'betaStatus' => 'beta_status',
        'gmtActive' => 'gmt_active',
        'gmtCreate' => 'gmt_create',
        'gmtInvalid' => 'gmt_invalid',
        'miniAppId' => 'mini_app_id',
        'pluginDeployVersion' => 'plugin_deploy_version',
        'pluginId' => 'plugin_id',
        'pluginStatus' => 'plugin_status',
        'pluginUseConfigInfoList' => 'plugin_use_config_info_list',
        'pluginVersion' => 'plugin_version',
        'runModeType' => 'run_mode_type',
        'sourceFrom' => 'source_from'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'betaMemo' => 'setBetaMemo',
        'betaPluginVersion' => 'setBetaPluginVersion',
        'betaQrCodeUrl' => 'setBetaQrCodeUrl',
        'betaStatus' => 'setBetaStatus',
        'gmtActive' => 'setGmtActive',
        'gmtCreate' => 'setGmtCreate',
        'gmtInvalid' => 'setGmtInvalid',
        'miniAppId' => 'setMiniAppId',
        'pluginDeployVersion' => 'setPluginDeployVersion',
        'pluginId' => 'setPluginId',
        'pluginStatus' => 'setPluginStatus',
        'pluginUseConfigInfoList' => 'setPluginUseConfigInfoList',
        'pluginVersion' => 'setPluginVersion',
        'runModeType' => 'setRunModeType',
        'sourceFrom' => 'setSourceFrom'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'betaMemo' => 'getBetaMemo',
        'betaPluginVersion' => 'getBetaPluginVersion',
        'betaQrCodeUrl' => 'getBetaQrCodeUrl',
        'betaStatus' => 'getBetaStatus',
        'gmtActive' => 'getGmtActive',
        'gmtCreate' => 'getGmtCreate',
        'gmtInvalid' => 'getGmtInvalid',
        'miniAppId' => 'getMiniAppId',
        'pluginDeployVersion' => 'getPluginDeployVersion',
        'pluginId' => 'getPluginId',
        'pluginStatus' => 'getPluginStatus',
        'pluginUseConfigInfoList' => 'getPluginUseConfigInfoList',
        'pluginVersion' => 'getPluginVersion',
        'runModeType' => 'getRunModeType',
        'sourceFrom' => 'getSourceFrom'
    ];

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @return array
     */
    public static function attributeMap()
    {
        return self::$attributeMap;
    }

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @return array
     */
    public static function setters()
    {
        return self::$setters;
    }

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @return array
     */
    public static function getters()
    {
        return self::$getters;
    }

    /**
     * The original name of the model.
     *
     * @return string
     */
    public function getModelName()
    {
        return self::$openAPIModelName;
    }


    /**
     * Associative array for storing property values
     *
     * @var mixed[]
     */
    protected $container = [];

    /**
     * Constructor
     *
     * @param mixed[] $data Associated array of property values
     *                      initializing the model
     */
    public function __construct(array $data = null)
    {
        $this->container['betaMemo'] = $data['betaMemo'] ?? null;
        $this->container['betaPluginVersion'] = $data['betaPluginVersion'] ?? null;
        $this->container['betaQrCodeUrl'] = $data['betaQrCodeUrl'] ?? null;
        $this->container['betaStatus'] = $data['betaStatus'] ?? null;
        $this->container['gmtActive'] = $data['gmtActive'] ?? null;
        $this->container['gmtCreate'] = $data['gmtCreate'] ?? null;
        $this->container['gmtInvalid'] = $data['gmtInvalid'] ?? null;
        $this->container['miniAppId'] = $data['miniAppId'] ?? null;
        $this->container['pluginDeployVersion'] = $data['pluginDeployVersion'] ?? null;
        $this->container['pluginId'] = $data['pluginId'] ?? null;
        $this->container['pluginStatus'] = $data['pluginStatus'] ?? null;
        $this->container['pluginUseConfigInfoList'] = $data['pluginUseConfigInfoList'] ?? null;
        $this->container['pluginVersion'] = $data['pluginVersion'] ?? null;
        $this->container['runModeType'] = $data['runModeType'] ?? null;
        $this->container['sourceFrom'] = $data['sourceFrom'] ?? null;
    }

    /**
     * Show all the invalid properties with reasons.
     *
     * @return array invalid properties with reasons
     */
    public function listInvalidProperties()
    {
        $invalidProperties = [];

        return $invalidProperties;
    }

    /**
     * Validate all the properties in the model
     * return true if all passed
     *
     * @return bool True if all properties are valid
     */
    public function valid()
    {
        return count($this->listInvalidProperties()) === 0;
    }


    /**
     * Gets betaMemo
     *
     * @return string|null
     */
    public function getBetaMemo()
    {
        return $this->container['betaMemo'];
    }

    /**
     * Sets betaMemo
     *
     * @param string|null $betaMemo 邀测驳回原因
     *
     * @return self
     */
    public function setBetaMemo($betaMemo)
    {
        $this->container['betaMemo'] = $betaMemo;

        return $this;
    }

    /**
     * Gets betaPluginVersion
     *
     * @return string|null
     */
    public function getBetaPluginVersion()
    {
        return $this->container['betaPluginVersion'];
    }

    /**
     * Sets betaPluginVersion
     *
     * @param string|null $betaPluginVersion 邀测插件版本号
     *
     * @return self
     */
    public function setBetaPluginVersion($betaPluginVersion)
    {
        $this->container['betaPluginVersion'] = $betaPluginVersion;

        return $this;
    }

    /**
     * Gets betaQrCodeUrl
     *
     * @return string|null
     */
    public function getBetaQrCodeUrl()
    {
        return $this->container['betaQrCodeUrl'];
    }

    /**
     * Sets betaQrCodeUrl
     *
     * @param string|null $betaQrCodeUrl 邀测二维码
     *
     * @return self
     */
    public function setBetaQrCodeUrl($betaQrCodeUrl)
    {
        $this->container['betaQrCodeUrl'] = $betaQrCodeUrl;

        return $this;
    }

    /**
     * Gets betaStatus
     *
     * @return string|null
     */
    public function getBetaStatus()
    {
        return $this->container['betaStatus'];
    }

    /**
     * Sets betaStatus
     *
     * @param string|null $betaStatus WAITCHECK-待确认;CHECKED-确认;REJECT-拒绝
     *
     * @return self
     */
    public function setBetaStatus($betaStatus)
    {
        $this->container['betaStatus'] = $betaStatus;

        return $this;
    }

    /**
     * Gets gmtActive
     *
     * @return string|null
     */
    public function getGmtActive()
    {
        return $this->container['gmtActive'];
    }

    /**
     * Sets gmtActive
     *
     * @param string|null $gmtActive 激活时间
     *
     * @return self
     */
    public function setGmtActive($gmtActive)
    {
        $this->container['gmtActive'] = $gmtActive;

        return $this;
    }

    /**
     * Gets gmtCreate
     *
     * @return string|null
     */
    public function getGmtCreate()
    {
        return $this->container['gmtCreate'];
    }

    /**
     * Sets gmtCreate
     *
     * @param string|null $gmtCreate 订购时间
     *
     * @return self
     */
    public function setGmtCreate($gmtCreate)
    {
        $this->container['gmtCreate'] = $gmtCreate;

        return $this;
    }

    /**
     * Gets gmtInvalid
     *
     * @return string|null
     */
    public function getGmtInvalid()
    {
        return $this->container['gmtInvalid'];
    }

    /**
     * Sets gmtInvalid
     *
     * @param string|null $gmtInvalid 插件失效时间
     *
     * @return self
     */
    public function setGmtInvalid($gmtInvalid)
    {
        $this->container['gmtInvalid'] = $gmtInvalid;

        return $this;
    }

    /**
     * Gets miniAppId
     *
     * @return string|null
     */
    public function getMiniAppId()
    {
        return $this->container['miniAppId'];
    }

    /**
     * Sets miniAppId
     *
     * @param string|null $miniAppId 应用ID
     *
     * @return self
     */
    public function setMiniAppId($miniAppId)
    {
        $this->container['miniAppId'] = $miniAppId;

        return $this;
    }

    /**
     * Gets pluginDeployVersion
     *
     * @return string|null
     */
    public function getPluginDeployVersion()
    {
        return $this->container['pluginDeployVersion'];
    }

    /**
     * Sets pluginDeployVersion
     *
     * @param string|null $pluginDeployVersion 插件构建版本
     *
     * @return self
     */
    public function setPluginDeployVersion($pluginDeployVersion)
    {
        $this->container['pluginDeployVersion'] = $pluginDeployVersion;

        return $this;
    }

    /**
     * Gets pluginId
     *
     * @return string|null
     */
    public function getPluginId()
    {
        return $this->container['pluginId'];
    }

    /**
     * Sets pluginId
     *
     * @param string|null $pluginId 插件ID
     *
     * @return self
     */
    public function setPluginId($pluginId)
    {
        $this->container['pluginId'] = $pluginId;

        return $this;
    }

    /**
     * Gets pluginStatus
     *
     * @return string|null
     */
    public function getPluginStatus()
    {
        return $this->container['pluginStatus'];
    }

    /**
     * Sets pluginStatus
     *
     * @param string|null $pluginStatus 插件状态，取值包括EXECUTING/WAIT_WORKING/WORKING/STOP_WORKING/WAIT_BUY
     *
     * @return self
     */
    public function setPluginStatus($pluginStatus)
    {
        $this->container['pluginStatus'] = $pluginStatus;

        return $this;
    }

    /**
     * Gets pluginUseConfigInfoList
     *
     * @return \Alipay\OpenAPISDK\Model\PluginUseConfigInfo[]|null
     */
    public function getPluginUseConfigInfoList()
    {
        return $this->container['pluginUseConfigInfoList'];
    }

    /**
     * Sets pluginUseConfigInfoList
     *
     * @param \Alipay\OpenAPISDK\Model\PluginUseConfigInfo[]|null $pluginUseConfigInfoList 分端版本配置信息列表
     *
     * @return self
     */
    public function setPluginUseConfigInfoList($pluginUseConfigInfoList)
    {
        $this->container['pluginUseConfigInfoList'] = $pluginUseConfigInfoList;

        return $this;
    }

    /**
     * Gets pluginVersion
     *
     * @return string|null
     */
    public function getPluginVersion()
    {
        return $this->container['pluginVersion'];
    }

    /**
     * Sets pluginVersion
     *
     * @param string|null $pluginVersion 插件版本
     *
     * @return self
     */
    public function setPluginVersion($pluginVersion)
    {
        $this->container['pluginVersion'] = $pluginVersion;

        return $this;
    }

    /**
     * Gets runModeType
     *
     * @return string|null
     */
    public function getRunModeType()
    {
        return $this->container['runModeType'];
    }

    /**
     * Sets runModeType
     *
     * @param string|null $runModeType 插件运行状态，取值包括ONLINE/TRIAL/REVIEW/DEBUG
     *
     * @return self
     */
    public function setRunModeType($runModeType)
    {
        $this->container['runModeType'] = $runModeType;

        return $this;
    }

    /**
     * Gets sourceFrom
     *
     * @return string|null
     */
    public function getSourceFrom()
    {
        return $this->container['sourceFrom'];
    }

    /**
     * Sets sourceFrom
     *
     * @param string|null $sourceFrom 渠道来源，取值包括SHOP_MINI/PLUGIN_DEBUG/PLUGIN_TRIAL/PLUGIN_AUDIT/GENERAL_SHOP_ID
     *
     * @return self
     */
    public function setSourceFrom($sourceFrom)
    {
        $this->container['sourceFrom'] = $sourceFrom;

        return $this;
    }
    /**
     * Returns true if offset exists. False otherwise.
     *
     * @param integer $offset Offset
     *
     * @return boolean
     */
    public function offsetExists($offset): bool
    {
        return isset($this->container[$offset]);
    }

    /**
     * Gets offset.
     *
     * @param integer $offset Offset
     *
     * @return mixed|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->container[$offset] ?? null;
    }

    /**
     * Sets value based on offset.
     *
     * @param int|null $offset Offset
     * @param mixed    $value  Value to be set
     *
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * Unsets offset.
     *
     * @param integer $offset Offset
     *
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->container[$offset]);
    }

    /**
     * Serializes the object to a value that can be serialized natively by json_encode().
     * @link https://www.php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @return mixed Returns data which can be serialized by json_encode(), which is a value
     * of any type other than a resource.
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
       return ObjectSerializer::sanitizeForSerialization($this);
    }

    /**
     * Gets the string presentation of the object
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode(
            ObjectSerializer::sanitizeForSerialization($this),
            JSON_PRETTY_PRINT
        );
    }

    /**
     * Gets a header-safe presentation of the object
     *
     * @return string
     */
    public function toHeaderValue()
    {
        return json_encode(ObjectSerializer::sanitizeForSerialization($this));
    }
}


