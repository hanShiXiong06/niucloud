<?php
/**
 * OrderItem
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
 * OrderItem Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class OrderItem implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'OrderItem';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'address' => 'string',
        'brandName' => 'string',
        'category' => 'string',
        'city' => 'string',
        'cityCode' => 'string',
        'commodityId' => 'string',
        'contacts' => 'string',
        'creator' => 'string',
        'expireDate' => 'string',
        'merchantName' => 'string',
        'merchantPid' => 'string',
        'miniAppId' => 'string',
        'miniAppName' => 'string',
        'onlineTime' => 'string',
        'openIdModel' => 'string',
        'orderStatus' => 'string',
        'phoneNo' => 'string',
        'province' => 'string',
        'serviceEffectDate' => 'string',
        'serviceExpireDate' => 'string',
        'shopId' => 'string',
        'shopName' => 'string',
        'shopStatus' => 'string',
        'status' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'address' => null,
        'brandName' => null,
        'category' => null,
        'city' => null,
        'cityCode' => null,
        'commodityId' => null,
        'contacts' => null,
        'creator' => null,
        'expireDate' => null,
        'merchantName' => null,
        'merchantPid' => null,
        'miniAppId' => null,
        'miniAppName' => null,
        'onlineTime' => null,
        'openIdModel' => null,
        'orderStatus' => null,
        'phoneNo' => null,
        'province' => null,
        'serviceEffectDate' => null,
        'serviceExpireDate' => null,
        'shopId' => null,
        'shopName' => null,
        'shopStatus' => null,
        'status' => null
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
        'address' => 'address',
        'brandName' => 'brand_name',
        'category' => 'category',
        'city' => 'city',
        'cityCode' => 'city_code',
        'commodityId' => 'commodity_id',
        'contacts' => 'contacts',
        'creator' => 'creator',
        'expireDate' => 'expire_date',
        'merchantName' => 'merchant_name',
        'merchantPid' => 'merchant_pid',
        'miniAppId' => 'mini_app_id',
        'miniAppName' => 'mini_app_name',
        'onlineTime' => 'online_time',
        'openIdModel' => 'open_id_model',
        'orderStatus' => 'order_status',
        'phoneNo' => 'phone_no',
        'province' => 'province',
        'serviceEffectDate' => 'service_effect_date',
        'serviceExpireDate' => 'service_expire_date',
        'shopId' => 'shop_id',
        'shopName' => 'shop_name',
        'shopStatus' => 'shop_status',
        'status' => 'status'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'address' => 'setAddress',
        'brandName' => 'setBrandName',
        'category' => 'setCategory',
        'city' => 'setCity',
        'cityCode' => 'setCityCode',
        'commodityId' => 'setCommodityId',
        'contacts' => 'setContacts',
        'creator' => 'setCreator',
        'expireDate' => 'setExpireDate',
        'merchantName' => 'setMerchantName',
        'merchantPid' => 'setMerchantPid',
        'miniAppId' => 'setMiniAppId',
        'miniAppName' => 'setMiniAppName',
        'onlineTime' => 'setOnlineTime',
        'openIdModel' => 'setOpenIdModel',
        'orderStatus' => 'setOrderStatus',
        'phoneNo' => 'setPhoneNo',
        'province' => 'setProvince',
        'serviceEffectDate' => 'setServiceEffectDate',
        'serviceExpireDate' => 'setServiceExpireDate',
        'shopId' => 'setShopId',
        'shopName' => 'setShopName',
        'shopStatus' => 'setShopStatus',
        'status' => 'setStatus'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'address' => 'getAddress',
        'brandName' => 'getBrandName',
        'category' => 'getCategory',
        'city' => 'getCity',
        'cityCode' => 'getCityCode',
        'commodityId' => 'getCommodityId',
        'contacts' => 'getContacts',
        'creator' => 'getCreator',
        'expireDate' => 'getExpireDate',
        'merchantName' => 'getMerchantName',
        'merchantPid' => 'getMerchantPid',
        'miniAppId' => 'getMiniAppId',
        'miniAppName' => 'getMiniAppName',
        'onlineTime' => 'getOnlineTime',
        'openIdModel' => 'getOpenIdModel',
        'orderStatus' => 'getOrderStatus',
        'phoneNo' => 'getPhoneNo',
        'province' => 'getProvince',
        'serviceEffectDate' => 'getServiceEffectDate',
        'serviceExpireDate' => 'getServiceExpireDate',
        'shopId' => 'getShopId',
        'shopName' => 'getShopName',
        'shopStatus' => 'getShopStatus',
        'status' => 'getStatus'
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
        $this->container['address'] = $data['address'] ?? null;
        $this->container['brandName'] = $data['brandName'] ?? null;
        $this->container['category'] = $data['category'] ?? null;
        $this->container['city'] = $data['city'] ?? null;
        $this->container['cityCode'] = $data['cityCode'] ?? null;
        $this->container['commodityId'] = $data['commodityId'] ?? null;
        $this->container['contacts'] = $data['contacts'] ?? null;
        $this->container['creator'] = $data['creator'] ?? null;
        $this->container['expireDate'] = $data['expireDate'] ?? null;
        $this->container['merchantName'] = $data['merchantName'] ?? null;
        $this->container['merchantPid'] = $data['merchantPid'] ?? null;
        $this->container['miniAppId'] = $data['miniAppId'] ?? null;
        $this->container['miniAppName'] = $data['miniAppName'] ?? null;
        $this->container['onlineTime'] = $data['onlineTime'] ?? null;
        $this->container['openIdModel'] = $data['openIdModel'] ?? null;
        $this->container['orderStatus'] = $data['orderStatus'] ?? null;
        $this->container['phoneNo'] = $data['phoneNo'] ?? null;
        $this->container['province'] = $data['province'] ?? null;
        $this->container['serviceEffectDate'] = $data['serviceEffectDate'] ?? null;
        $this->container['serviceExpireDate'] = $data['serviceExpireDate'] ?? null;
        $this->container['shopId'] = $data['shopId'] ?? null;
        $this->container['shopName'] = $data['shopName'] ?? null;
        $this->container['shopStatus'] = $data['shopStatus'] ?? null;
        $this->container['status'] = $data['status'] ?? null;
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
     * Gets address
     *
     * @return string|null
     */
    public function getAddress()
    {
        return $this->container['address'];
    }

    /**
     * Sets address
     *
     * @param string|null $address 店铺所在具体位置
     *
     * @return self
     */
    public function setAddress($address)
    {
        $this->container['address'] = $address;

        return $this;
    }

    /**
     * Gets brandName
     *
     * @return string|null
     */
    public function getBrandName()
    {
        return $this->container['brandName'];
    }

    /**
     * Sets brandName
     *
     * @param string|null $brandName 品牌名称
     *
     * @return self
     */
    public function setBrandName($brandName)
    {
        $this->container['brandName'] = $brandName;

        return $this;
    }

    /**
     * Gets category
     *
     * @return string|null
     */
    public function getCategory()
    {
        return $this->container['category'];
    }

    /**
     * Sets category
     *
     * @param string|null $category 店铺品类
     *
     * @return self
     */
    public function setCategory($category)
    {
        $this->container['category'] = $category;

        return $this;
    }

    /**
     * Gets city
     *
     * @return string|null
     */
    public function getCity()
    {
        return $this->container['city'];
    }

    /**
     * Sets city
     *
     * @param string|null $city 店铺所在的市
     *
     * @return self
     */
    public function setCity($city)
    {
        $this->container['city'] = $city;

        return $this;
    }

    /**
     * Gets cityCode
     *
     * @return string|null
     */
    public function getCityCode()
    {
        return $this->container['cityCode'];
    }

    /**
     * Sets cityCode
     *
     * @param string|null $cityCode 城市编码
     *
     * @return self
     */
    public function setCityCode($cityCode)
    {
        $this->container['cityCode'] = $cityCode;

        return $this;
    }

    /**
     * Gets commodityId
     *
     * @return string|null
     */
    public function getCommodityId()
    {
        return $this->container['commodityId'];
    }

    /**
     * Sets commodityId
     *
     * @param string|null $commodityId 订购的服务商品ID
     *
     * @return self
     */
    public function setCommodityId($commodityId)
    {
        $this->container['commodityId'] = $commodityId;

        return $this;
    }

    /**
     * Gets contacts
     *
     * @return string|null
     */
    public function getContacts()
    {
        return $this->container['contacts'];
    }

    /**
     * Sets contacts
     *
     * @param string|null $contacts 订单联系人
     *
     * @return self
     */
    public function setContacts($contacts)
    {
        $this->container['contacts'] = $contacts;

        return $this;
    }

    /**
     * Gets creator
     *
     * @return string|null
     */
    public function getCreator()
    {
        return $this->container['creator'];
    }

    /**
     * Sets creator
     *
     * @param string|null $creator 门店创建人(已删除)
     *
     * @return self
     */
    public function setCreator($creator)
    {
        $this->container['creator'] = $creator;

        return $this;
    }

    /**
     * Gets expireDate
     *
     * @return string|null
     */
    public function getExpireDate()
    {
        return $this->container['expireDate'];
    }

    /**
     * Sets expireDate
     *
     * @param string|null $expireDate 过期时间
     *
     * @return self
     */
    public function setExpireDate($expireDate)
    {
        $this->container['expireDate'] = $expireDate;

        return $this;
    }

    /**
     * Gets merchantName
     *
     * @return string|null
     */
    public function getMerchantName()
    {
        return $this->container['merchantName'];
    }

    /**
     * Sets merchantName
     *
     * @param string|null $merchantName 商户名称
     *
     * @return self
     */
    public function setMerchantName($merchantName)
    {
        $this->container['merchantName'] = $merchantName;

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
     * @param string|null $merchantPid 商户PID
     *
     * @return self
     */
    public function setMerchantPid($merchantPid)
    {
        $this->container['merchantPid'] = $merchantPid;

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
     * @param string|null $miniAppId 小程序APPID，订购实体为小程序的场景该字段必填
     *
     * @return self
     */
    public function setMiniAppId($miniAppId)
    {
        $this->container['miniAppId'] = $miniAppId;

        return $this;
    }

    /**
     * Gets miniAppName
     *
     * @return string|null
     */
    public function getMiniAppName()
    {
        return $this->container['miniAppName'];
    }

    /**
     * Sets miniAppName
     *
     * @param string|null $miniAppName 小程序应用名称，订购实体为小程序的场景该字段必填
     *
     * @return self
     */
    public function setMiniAppName($miniAppName)
    {
        $this->container['miniAppName'] = $miniAppName;

        return $this;
    }

    /**
     * Gets onlineTime
     *
     * @return string|null
     */
    public function getOnlineTime()
    {
        return $this->container['onlineTime'];
    }

    /**
     * Sets onlineTime
     *
     * @param string|null $onlineTime 上架时间
     *
     * @return self
     */
    public function setOnlineTime($onlineTime)
    {
        $this->container['onlineTime'] = $onlineTime;

        return $this;
    }

    /**
     * Gets openIdModel
     *
     * @return string|null
     */
    public function getOpenIdModel()
    {
        return $this->container['openIdModel'];
    }

    /**
     * Sets openIdModel
     *
     * @param string|null $openIdModel 应用用户标识模式
     *
     * @return self
     */
    public function setOpenIdModel($openIdModel)
    {
        $this->container['openIdModel'] = $openIdModel;

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
     * @param string|null $orderStatus TO_DO-未实施,DOING-实施中,TO_CONFIRM-待商户确认,CONFIRMED-商户已确认,DONE-已完成,MERCHANT_REJECTED-商户已回绝,MERCHANT_CANCELLED-商户已取消,ISV_REJECTED-服务商已回绝,ISV_CANCELLED-服务商已取消
     *
     * @return self
     */
    public function setOrderStatus($orderStatus)
    {
        $this->container['orderStatus'] = $orderStatus;

        return $this;
    }

    /**
     * Gets phoneNo
     *
     * @return string|null
     */
    public function getPhoneNo()
    {
        return $this->container['phoneNo'];
    }

    /**
     * Sets phoneNo
     *
     * @param string|null $phoneNo 订单所属人联系方式（手机或者座机）
     *
     * @return self
     */
    public function setPhoneNo($phoneNo)
    {
        $this->container['phoneNo'] = $phoneNo;

        return $this;
    }

    /**
     * Gets province
     *
     * @return string|null
     */
    public function getProvince()
    {
        return $this->container['province'];
    }

    /**
     * Sets province
     *
     * @param string|null $province 店铺所在的省份
     *
     * @return self
     */
    public function setProvince($province)
    {
        $this->container['province'] = $province;

        return $this;
    }

    /**
     * Gets serviceEffectDate
     *
     * @return string|null
     */
    public function getServiceEffectDate()
    {
        return $this->container['serviceEffectDate'];
    }

    /**
     * Sets serviceEffectDate
     *
     * @param string|null $serviceEffectDate 订购的服务有效期生效时间
     *
     * @return self
     */
    public function setServiceEffectDate($serviceEffectDate)
    {
        $this->container['serviceEffectDate'] = $serviceEffectDate;

        return $this;
    }

    /**
     * Gets serviceExpireDate
     *
     * @return string|null
     */
    public function getServiceExpireDate()
    {
        return $this->container['serviceExpireDate'];
    }

    /**
     * Sets serviceExpireDate
     *
     * @param string|null $serviceExpireDate 服务有效期截止时间
     *
     * @return self
     */
    public function setServiceExpireDate($serviceExpireDate)
    {
        $this->container['serviceExpireDate'] = $serviceExpireDate;

        return $this;
    }

    /**
     * Gets shopId
     *
     * @return string|null
     */
    public function getShopId()
    {
        return $this->container['shopId'];
    }

    /**
     * Sets shopId
     *
     * @param string|null $shopId 店铺ID，订购实体为口碑门店的场景该字段必填
     *
     * @return self
     */
    public function setShopId($shopId)
    {
        $this->container['shopId'] = $shopId;

        return $this;
    }

    /**
     * Gets shopName
     *
     * @return string|null
     */
    public function getShopName()
    {
        return $this->container['shopName'];
    }

    /**
     * Sets shopName
     *
     * @param string|null $shopName 店铺名称，订购实体为口碑门店的场景该字段必填
     *
     * @return self
     */
    public function setShopName($shopName)
    {
        $this->container['shopName'] = $shopName;

        return $this;
    }

    /**
     * Gets shopStatus
     *
     * @return string|null
     */
    public function getShopStatus()
    {
        return $this->container['shopStatus'];
    }

    /**
     * Sets shopStatus
     *
     * @param string|null $shopStatus 店铺状态（ONLINE--已上架 OFFLINE--未上架 AVAILABLE--已开通 INIT--未开通 EXPIRED--已过期）
     *
     * @return self
     */
    public function setShopStatus($shopStatus)
    {
        $this->container['shopStatus'] = $shopStatus;

        return $this;
    }

    /**
     * Gets status
     *
     * @return string|null
     */
    public function getStatus()
    {
        return $this->container['status'];
    }

    /**
     * Sets status
     *
     * @param string|null $status 待服务商接单
     *
     * @return self
     */
    public function setStatus($status)
    {
        $this->container['status'] = $status;

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


