<?php
/**
 * AlipayCommerceEcEmployeeIdlistQueryResponseModel
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
 * AlipayCommerceEcEmployeeIdlistQueryResponseModel Class Doc Comment
 *
 * @category Class
 * @package  Alipay\OpenAPISDK
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<TKey, TValue>
 * @template TKey int|null
 * @template TValue mixed|null
 */
class AlipayCommerceEcEmployeeIdlistQueryResponseModel implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'AlipayCommerceEcEmployeeIdlistQueryResponseModel';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'currentPage' => 'int',
        'employeeIdList' => 'string[]',
        'totalNum' => 'int',
        'totalPages' => 'int'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'currentPage' => null,
        'employeeIdList' => null,
        'totalNum' => null,
        'totalPages' => null
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
        'currentPage' => 'current_page',
        'employeeIdList' => 'employee_id_list',
        'totalNum' => 'total_num',
        'totalPages' => 'total_pages'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'currentPage' => 'setCurrentPage',
        'employeeIdList' => 'setEmployeeIdList',
        'totalNum' => 'setTotalNum',
        'totalPages' => 'setTotalPages'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'currentPage' => 'getCurrentPage',
        'employeeIdList' => 'getEmployeeIdList',
        'totalNum' => 'getTotalNum',
        'totalPages' => 'getTotalPages'
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
        $this->container['currentPage'] = $data['currentPage'] ?? null;
        $this->container['employeeIdList'] = $data['employeeIdList'] ?? null;
        $this->container['totalNum'] = $data['totalNum'] ?? null;
        $this->container['totalPages'] = $data['totalPages'] ?? null;
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
     * Gets currentPage
     *
     * @return int|null
     */
    public function getCurrentPage()
    {
        return $this->container['currentPage'];
    }

    /**
     * Sets currentPage
     *
     * @param int|null $currentPage 当前页数
     *
     * @return self
     */
    public function setCurrentPage($currentPage)
    {
        $this->container['currentPage'] = $currentPage;

        return $this;
    }

    /**
     * Gets employeeIdList
     *
     * @return string[]|null
     */
    public function getEmployeeIdList()
    {
        return $this->container['employeeIdList'];
    }

    /**
     * Sets employeeIdList
     *
     * @param string[]|null $employeeIdList 员工id列表
     *
     * @return self
     */
    public function setEmployeeIdList($employeeIdList)
    {
        $this->container['employeeIdList'] = $employeeIdList;

        return $this;
    }

    /**
     * Gets totalNum
     *
     * @return int|null
     */
    public function getTotalNum()
    {
        return $this->container['totalNum'];
    }

    /**
     * Sets totalNum
     *
     * @param int|null $totalNum 员工总数
     *
     * @return self
     */
    public function setTotalNum($totalNum)
    {
        $this->container['totalNum'] = $totalNum;

        return $this;
    }

    /**
     * Gets totalPages
     *
     * @return int|null
     */
    public function getTotalPages()
    {
        return $this->container['totalPages'];
    }

    /**
     * Sets totalPages
     *
     * @param int|null $totalPages 总页数
     *
     * @return self
     */
    public function setTotalPages($totalPages)
    {
        $this->container['totalPages'] = $totalPages;

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


