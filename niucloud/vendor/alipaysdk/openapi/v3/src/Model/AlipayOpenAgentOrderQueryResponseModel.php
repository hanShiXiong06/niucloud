<?php
/**
 * AlipayOpenAgentOrderQueryResponseModel
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
 * AlipayOpenAgentOrderQueryResponseModel Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class AlipayOpenAgentOrderQueryResponseModel implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'AlipayOpenAgentOrderQueryResponseModel';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'agentAppId' => 'string',
        'confirmUrl' => 'string',
        'merchantPid' => 'string',
        'orderNo' => 'string',
        'orderStatus' => 'string',
        'productAgentStatusInfos' => '\Alipay\OpenAPISDK\Model\ProductAgentStatusInfo[]',
        'rejectReason' => 'string',
        'restrictInfos' => '\Alipay\OpenAPISDK\Model\SignRestrictInfo[]'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'agentAppId' => null,
        'confirmUrl' => null,
        'merchantPid' => null,
        'orderNo' => null,
        'orderStatus' => null,
        'productAgentStatusInfos' => null,
        'rejectReason' => null,
        'restrictInfos' => null
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
        'agentAppId' => 'agent_app_id',
        'confirmUrl' => 'confirm_url',
        'merchantPid' => 'merchant_pid',
        'orderNo' => 'order_no',
        'orderStatus' => 'order_status',
        'productAgentStatusInfos' => 'product_agent_status_infos',
        'rejectReason' => 'reject_reason',
        'restrictInfos' => 'restrict_infos'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'agentAppId' => 'setAgentAppId',
        'confirmUrl' => 'setConfirmUrl',
        'merchantPid' => 'setMerchantPid',
        'orderNo' => 'setOrderNo',
        'orderStatus' => 'setOrderStatus',
        'productAgentStatusInfos' => 'setProductAgentStatusInfos',
        'rejectReason' => 'setRejectReason',
        'restrictInfos' => 'setRestrictInfos'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'agentAppId' => 'getAgentAppId',
        'confirmUrl' => 'getConfirmUrl',
        'merchantPid' => 'getMerchantPid',
        'orderNo' => 'getOrderNo',
        'orderStatus' => 'getOrderStatus',
        'productAgentStatusInfos' => 'getProductAgentStatusInfos',
        'rejectReason' => 'getRejectReason',
        'restrictInfos' => 'getRestrictInfos'
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
        $this->container['agentAppId'] = $data['agentAppId'] ?? null;
        $this->container['confirmUrl'] = $data['confirmUrl'] ?? null;
        $this->container['merchantPid'] = $data['merchantPid'] ?? null;
        $this->container['orderNo'] = $data['orderNo'] ?? null;
        $this->container['orderStatus'] = $data['orderStatus'] ?? null;
        $this->container['productAgentStatusInfos'] = $data['productAgentStatusInfos'] ?? null;
        $this->container['rejectReason'] = $data['rejectReason'] ?? null;
        $this->container['restrictInfos'] = $data['restrictInfos'] ?? null;
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
     * Gets agentAppId
     *
     * @return string|null
     */
    public function getAgentAppId()
    {
        return $this->container['agentAppId'];
    }

    /**
     * Sets agentAppId
     *
     * @param string|null $agentAppId 代理创建的应用ID，如果有代理商户创建应用，商户确认成功后，才返回应用ID，否则不返回。
     *
     * @return self
     */
    public function setAgentAppId($agentAppId)
    {
        $this->container['agentAppId'] = $agentAppId;

        return $this;
    }

    /**
     * Gets confirmUrl
     *
     * @return string|null
     */
    public function getConfirmUrl()
    {
        return $this->container['confirmUrl'];
    }

    /**
     * Sets confirmUrl
     *
     * @param string|null $confirmUrl 只有申请单状态在MERCHANT_CONFIRM状态下，才会返回商户确认签约链接
     *
     * @return self
     */
    public function setConfirmUrl($confirmUrl)
    {
        $this->container['confirmUrl'] = $confirmUrl;

        return $this;
    }

    /**
     * Gets merchantPid
     *
     * @return string|null
     */
    public function getMerchantPid()
    {
        return $this->container['merchantPid'];
    }

    /**
     * Sets merchantPid
     *
     * @param string|null $merchantPid 商户pid
     *
     * @return self
     */
    public function setMerchantPid($merchantPid)
    {
        $this->container['merchantPid'] = $merchantPid;

        return $this;
    }

    /**
     * Gets orderNo
     *
     * @return string|null
     */
    public function getOrderNo()
    {
        return $this->container['orderNo'];
    }

    /**
     * Sets orderNo
     *
     * @param string|null $orderNo 签约单号
     *
     * @return self
     */
    public function setOrderNo($orderNo)
    {
        $this->container['orderNo'] = $orderNo;

        return $this;
    }

    /**
     * Gets orderStatus
     *
     * @return string|null
     */
    public function getOrderStatus()
    {
        return $this->container['orderStatus'];
    }

    /**
     * Sets orderStatus
     *
     * @param string|null $orderStatus 支付宝商户入驻申请单状态，申请单状态包括：  MERCHANT_INFO_HOLD=暂存，提交事务出现业务校验异常时，会暂存申请单信息，可以调用业务接口修正参数，并重新提交  MERCHANT_AUDITING=审核中，申请信息正在人工审核中  MERCHANT_CONFIRM=待商户确认，申请信息审核通过，等待联系人确认签约或授权  MERCHANT_CONFIRM_SUCCESS=商户确认成功，商户同意签约或授权  MERCHANT_CONFIRM_TIME_OUT=商户超时未确认，如果商户受到确认信息15天内未确认，则需要重新提交申请信息  MERCHANT_APPLY_ORDER_CANCELED=审核失败或商户拒绝，申请信息审核被驳回，或者商户选择拒绝签约或授权
     *
     * @return self
     */
    public function setOrderStatus($orderStatus)
    {
        $this->container['orderStatus'] = $orderStatus;

        return $this;
    }

    /**
     * Gets productAgentStatusInfos
     *
     * @return \Alipay\OpenAPISDK\Model\ProductAgentStatusInfo[]|null
     */
    public function getProductAgentStatusInfos()
    {
        return $this->container['productAgentStatusInfos'];
    }

    /**
     * Sets productAgentStatusInfos
     *
     * @param \Alipay\OpenAPISDK\Model\ProductAgentStatusInfo[]|null $productAgentStatusInfos 申请单中每个产品的签约状态
     *
     * @return self
     */
    public function setProductAgentStatusInfos($productAgentStatusInfos)
    {
        $this->container['productAgentStatusInfos'] = $productAgentStatusInfos;

        return $this;
    }

    /**
     * Gets rejectReason
     *
     * @return string|null
     */
    public function getRejectReason()
    {
        return $this->container['rejectReason'];
    }

    /**
     * Sets rejectReason
     *
     * @param string|null $rejectReason 审核失败的拒绝原因，只有审核失败才会返回该值
     *
     * @return self
     */
    public function setRejectReason($rejectReason)
    {
        $this->container['rejectReason'] = $rejectReason;

        return $this;
    }

    /**
     * Gets restrictInfos
     *
     * @return \Alipay\OpenAPISDK\Model\SignRestrictInfo[]|null
     */
    public function getRestrictInfos()
    {
        return $this->container['restrictInfos'];
    }

    /**
     * Sets restrictInfos
     *
     * @param \Alipay\OpenAPISDK\Model\SignRestrictInfo[]|null $restrictInfos 受限信息
     *
     * @return self
     */
    public function setRestrictInfos($restrictInfos)
    {
        $this->container['restrictInfos'] = $restrictInfos;

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


