<?php
/**
 * AlipayOpenSpOperationApplyModel
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
 * AlipayOpenSpOperationApplyModel Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class AlipayOpenSpOperationApplyModel implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'AlipayOpenSpOperationApplyModel';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'accessProductCode' => 'string',
        'alipayAccount' => 'string',
        'isvScenePermissions' => 'string',
        'merchantNo' => 'string',
        'operateType' => 'string',
        'outBizNo' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'accessProductCode' => null,
        'alipayAccount' => null,
        'isvScenePermissions' => null,
        'merchantNo' => null,
        'operateType' => null,
        'outBizNo' => null
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
        'accessProductCode' => 'access_product_code',
        'alipayAccount' => 'alipay_account',
        'isvScenePermissions' => 'isv_scene_permissions',
        'merchantNo' => 'merchant_no',
        'operateType' => 'operate_type',
        'outBizNo' => 'out_biz_no'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'accessProductCode' => 'setAccessProductCode',
        'alipayAccount' => 'setAlipayAccount',
        'isvScenePermissions' => 'setIsvScenePermissions',
        'merchantNo' => 'setMerchantNo',
        'operateType' => 'setOperateType',
        'outBizNo' => 'setOutBizNo'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'accessProductCode' => 'getAccessProductCode',
        'alipayAccount' => 'getAlipayAccount',
        'isvScenePermissions' => 'getIsvScenePermissions',
        'merchantNo' => 'getMerchantNo',
        'operateType' => 'getOperateType',
        'outBizNo' => 'getOutBizNo'
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
        $this->container['accessProductCode'] = $data['accessProductCode'] ?? null;
        $this->container['alipayAccount'] = $data['alipayAccount'] ?? null;
        $this->container['isvScenePermissions'] = $data['isvScenePermissions'] ?? null;
        $this->container['merchantNo'] = $data['merchantNo'] ?? null;
        $this->container['operateType'] = $data['operateType'] ?? null;
        $this->container['outBizNo'] = $data['outBizNo'] ?? null;
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
     * Gets accessProductCode
     *
     * @return string|null
     */
    public function getAccessProductCode()
    {
        return $this->container['accessProductCode'];
    }

    /**
     * Sets accessProductCode
     *
     * @param string|null $accessProductCode 接入的产品编号。 枚举如下： * OPENAPI_BIND_DEFAULT：操作类型为账号绑定。 * OPENAPI_AUTH_DEFAULT：操作类型为代运营授权时。
     *
     * @return self
     */
    public function setAccessProductCode($accessProductCode)
    {
        $this->container['accessProductCode'] = $accessProductCode;

        return $this;
    }

    /**
     * Gets alipayAccount
     *
     * @return string|null
     */
    public function getAlipayAccount()
    {
        return $this->container['alipayAccount'];
    }

    /**
     * Sets alipayAccount
     *
     * @param string|null $alipayAccount 支付宝登录账号，通常为手机号或者邮箱。 间连场景必填。 直连场景选填，特别注意merchant_no和alipay_account不能同时为空，都有值优先取merchant_no。
     *
     * @return self
     */
    public function setAlipayAccount($alipayAccount)
    {
        $this->container['alipayAccount'] = $alipayAccount;

        return $this;
    }

    /**
     * Gets isvScenePermissions
     *
     * @return string|null
     */
    public function getIsvScenePermissions()
    {
        return $this->container['isvScenePermissions'];
    }

    /**
     * Sets isvScenePermissions
     *
     * @param string|null $isvScenePermissions 场景授权列表结构结构：场景codeA:权限code1,权限code2;场景codeB:权限code1,权限code2; 说明： * 本参数和access_product_code只需要传一个。 * 场景 + 权限Code含义：    SHOP_MANAGE:SHOP_MANAGE_BASE：管理门店信息    MINI_APP_OPER:MINI_APP_OPER_BASE：运营支付宝小程序    PROMOTION_MANAGE:PROMOTION_MANAGE_BASE：运营营销活动    OPERATION_POINTS:OPERATION_POINTS_BASE：管理运营积分    INCENTIVE_POINT_MANAGE:INCENTIVE_POINT_MANAGE_BASE：管理激励点数
     *
     * @return self
     */
    public function setIsvScenePermissions($isvScenePermissions)
    {
        $this->container['isvScenePermissions'] = $isvScenePermissions;

        return $this;
    }

    /**
     * Gets merchantNo
     *
     * @return string|null
     */
    public function getMerchantNo()
    {
        return $this->container['merchantNo'];
    }

    /**
     * Sets merchantNo
     *
     * @param string|null $merchantNo 支付宝商户号。 间连场景，merchant_no必填，传入商户smid，特别注意仅支持2088开头的间连商户。 直连场景，merchant_no选填，传入商户支付宝pid，特别注意merchant_no和alipay_account不能同时为空，优先取merchant_no。
     *
     * @return self
     */
    public function setMerchantNo($merchantNo)
    {
        $this->container['merchantNo'] = $merchantNo;

        return $this;
    }

    /**
     * Gets operateType
     *
     * @return string|null
     */
    public function getOperateType()
    {
        return $this->container['operateType'];
    }

    /**
     * Sets operateType
     *
     * @param string|null $operateType 代运营操作类型。枚举如下： * ACCOUNT_BIND：代表绑定支付宝账号，仅对于间连商户。 * OPERATION_AUTH：代表代运营授权，支持间连和直连商户，其中间连场景包含绑定支付宝账号。
     *
     * @return self
     */
    public function setOperateType($operateType)
    {
        $this->container['operateType'] = $operateType;

        return $this;
    }

    /**
     * Gets outBizNo
     *
     * @return string|null
     */
    public function getOutBizNo()
    {
        return $this->container['outBizNo'];
    }

    /**
     * Sets outBizNo
     *
     * @param string|null $outBizNo 外部操作流水，ISV自定义。每次操作需要确保唯一。
     *
     * @return self
     */
    public function setOutBizNo($outBizNo)
    {
        $this->container['outBizNo'] = $outBizNo;

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


