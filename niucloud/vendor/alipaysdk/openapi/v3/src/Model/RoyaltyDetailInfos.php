<?php
/**
 * RoyaltyDetailInfos
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
 * RoyaltyDetailInfos Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class RoyaltyDetailInfos implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'RoyaltyDetailInfos';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'amount' => 'string',
        'amountPercentage' => 'string',
        'batchNo' => 'string',
        'desc' => 'string',
        'outRelationId' => 'string',
        'serialNo' => 'int',
        'transIn' => 'string',
        'transInType' => 'string',
        'transOut' => 'string',
        'transOutType' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'amount' => null,
        'amountPercentage' => null,
        'batchNo' => null,
        'desc' => null,
        'outRelationId' => null,
        'serialNo' => null,
        'transIn' => null,
        'transInType' => null,
        'transOut' => null,
        'transOutType' => null
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
        'amount' => 'amount',
        'amountPercentage' => 'amount_percentage',
        'batchNo' => 'batch_no',
        'desc' => 'desc',
        'outRelationId' => 'out_relation_id',
        'serialNo' => 'serial_no',
        'transIn' => 'trans_in',
        'transInType' => 'trans_in_type',
        'transOut' => 'trans_out',
        'transOutType' => 'trans_out_type'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'amount' => 'setAmount',
        'amountPercentage' => 'setAmountPercentage',
        'batchNo' => 'setBatchNo',
        'desc' => 'setDesc',
        'outRelationId' => 'setOutRelationId',
        'serialNo' => 'setSerialNo',
        'transIn' => 'setTransIn',
        'transInType' => 'setTransInType',
        'transOut' => 'setTransOut',
        'transOutType' => 'setTransOutType'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'amount' => 'getAmount',
        'amountPercentage' => 'getAmountPercentage',
        'batchNo' => 'getBatchNo',
        'desc' => 'getDesc',
        'outRelationId' => 'getOutRelationId',
        'serialNo' => 'getSerialNo',
        'transIn' => 'getTransIn',
        'transInType' => 'getTransInType',
        'transOut' => 'getTransOut',
        'transOutType' => 'getTransOutType'
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
        $this->container['amount'] = $data['amount'] ?? null;
        $this->container['amountPercentage'] = $data['amountPercentage'] ?? null;
        $this->container['batchNo'] = $data['batchNo'] ?? null;
        $this->container['desc'] = $data['desc'] ?? null;
        $this->container['outRelationId'] = $data['outRelationId'] ?? null;
        $this->container['serialNo'] = $data['serialNo'] ?? null;
        $this->container['transIn'] = $data['transIn'] ?? null;
        $this->container['transInType'] = $data['transInType'] ?? null;
        $this->container['transOut'] = $data['transOut'] ?? null;
        $this->container['transOutType'] = $data['transOutType'] ?? null;
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
     * Gets amount
     *
     * @return string|null
     */
    public function getAmount()
    {
        return $this->container['amount'];
    }

    /**
     * Sets amount
     *
     * @param string|null $amount 分账的金额，单位为元
     *
     * @return self
     */
    public function setAmount($amount)
    {
        $this->container['amount'] = $amount;

        return $this;
    }

    /**
     * Gets amountPercentage
     *
     * @return string|null
     */
    public function getAmountPercentage()
    {
        return $this->container['amountPercentage'];
    }

    /**
     * Sets amountPercentage
     *
     * @param string|null $amountPercentage 分账的比例，值为20代表按20%的比例分账
     *
     * @return self
     */
    public function setAmountPercentage($amountPercentage)
    {
        $this->container['amountPercentage'] = $amountPercentage;

        return $this;
    }

    /**
     * Gets batchNo
     *
     * @return string|null
     */
    public function getBatchNo()
    {
        return $this->container['batchNo'];
    }

    /**
     * Sets batchNo
     *
     * @param string|null $batchNo 分账批次号  分账批次号。  目前需要和转入账号类型为bankIndex配合使用。
     *
     * @return self
     */
    public function setBatchNo($batchNo)
    {
        $this->container['batchNo'] = $batchNo;

        return $this;
    }

    /**
     * Gets desc
     *
     * @return string|null
     */
    public function getDesc()
    {
        return $this->container['desc'];
    }

    /**
     * Sets desc
     *
     * @param string|null $desc 分账描述信息
     *
     * @return self
     */
    public function setDesc($desc)
    {
        $this->container['desc'] = $desc;

        return $this;
    }

    /**
     * Gets outRelationId
     *
     * @return string|null
     */
    public function getOutRelationId()
    {
        return $this->container['outRelationId'];
    }

    /**
     * Sets outRelationId
     *
     * @param string|null $outRelationId 商户分账的外部关联号，用于关联到每一笔分账信息，商户需保证其唯一性。  如果为空，该值则默认为“商户网站唯一订单号+分账序列号”
     *
     * @return self
     */
    public function setOutRelationId($outRelationId)
    {
        $this->container['outRelationId'] = $outRelationId;

        return $this;
    }

    /**
     * Gets serialNo
     *
     * @return int|null
     */
    public function getSerialNo()
    {
        return $this->container['serialNo'];
    }

    /**
     * Sets serialNo
     *
     * @param int|null $serialNo 分账序列号，表示分账执行的顺序，必须为正整数
     *
     * @return self
     */
    public function setSerialNo($serialNo)
    {
        $this->container['serialNo'] = $serialNo;

        return $this;
    }

    /**
     * Gets transIn
     *
     * @return string|null
     */
    public function getTransIn()
    {
        return $this->container['transIn'];
    }

    /**
     * Sets transIn
     *
     * @param string|null $transIn 如果转入账号类型为userId，本参数为接受分账金额的支付宝账号对应的支付宝唯一用户号。以2088开头的纯16位数字。  &#61548; 如果转入账号类型为bankIndex，本参数为28位的银行编号（商户和支付宝签约时确定）。  如果转入账号类型为storeId，本参数为商户的门店ID。
     *
     * @return self
     */
    public function setTransIn($transIn)
    {
        $this->container['transIn'] = $transIn;

        return $this;
    }

    /**
     * Gets transInType
     *
     * @return string|null
     */
    public function getTransInType()
    {
        return $this->container['transInType'];
    }

    /**
     * Sets transInType
     *
     * @param string|null $transInType 接受分账金额的账户类型
     *
     * @return self
     */
    public function setTransInType($transInType)
    {
        $this->container['transInType'] = $transInType;

        return $this;
    }

    /**
     * Gets transOut
     *
     * @return string|null
     */
    public function getTransOut()
    {
        return $this->container['transOut'];
    }

    /**
     * Sets transOut
     *
     * @param string|null $transOut 如果转出账号类型为userId，本参数为要分账的支付宝账号对应的支付宝唯一用户号。以2088开头的纯16位数字。
     *
     * @return self
     */
    public function setTransOut($transOut)
    {
        $this->container['transOut'] = $transOut;

        return $this;
    }

    /**
     * Gets transOutType
     *
     * @return string|null
     */
    public function getTransOutType()
    {
        return $this->container['transOutType'];
    }

    /**
     * Sets transOutType
     *
     * @param string|null $transOutType 要分账的账户类型。  目前只支持userId：支付宝账号对应的支付宝唯一用户号。  默认值为userId。
     *
     * @return self
     */
    public function setTransOutType($transOutType)
    {
        $this->container['transOutType'] = $transOutType;

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


