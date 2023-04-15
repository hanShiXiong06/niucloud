<?php
/*
 * Copyright (c) 2017-2018 THL A29 Limited, a Tencent company. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace TencentCloud\Tcss\V20201101\Models;
use TencentCloud\Common\AbstractModel;

/**
 * DescribeAffectedNodeList请求参数结构体
 *
 * @method integer getCheckItemId() 获取唯一的检测项的ID
 * @method void setCheckItemId(integer $CheckItemId) 设置唯一的检测项的ID
 * @method integer getOffset() 获取偏移量
 * @method void setOffset(integer $Offset) 设置偏移量
 * @method integer getLimit() 获取每次查询的最大记录数量
 * @method void setLimit(integer $Limit) 设置每次查询的最大记录数量
 * @method array getFilters() 获取Name - String
Name 可取值：ClusterName, ClusterId,InstanceId,PrivateIpAddresses
 * @method void setFilters(array $Filters) 设置Name - String
Name 可取值：ClusterName, ClusterId,InstanceId,PrivateIpAddresses
 * @method string getBy() 获取排序字段
 * @method void setBy(string $By) 设置排序字段
 * @method string getOrder() 获取排序方式 asc,desc
 * @method void setOrder(string $Order) 设置排序方式 asc,desc
 */
class DescribeAffectedNodeListRequest extends AbstractModel
{
    /**
     * @var integer 唯一的检测项的ID
     */
    public $CheckItemId;

    /**
     * @var integer 偏移量
     */
    public $Offset;

    /**
     * @var integer 每次查询的最大记录数量
     */
    public $Limit;

    /**
     * @var array Name - String
Name 可取值：ClusterName, ClusterId,InstanceId,PrivateIpAddresses
     */
    public $Filters;

    /**
     * @var string 排序字段
     */
    public $By;

    /**
     * @var string 排序方式 asc,desc
     */
    public $Order;

    /**
     * @param integer $CheckItemId 唯一的检测项的ID
     * @param integer $Offset 偏移量
     * @param integer $Limit 每次查询的最大记录数量
     * @param array $Filters Name - String
Name 可取值：ClusterName, ClusterId,InstanceId,PrivateIpAddresses
     * @param string $By 排序字段
     * @param string $Order 排序方式 asc,desc
     */
    function __construct()
    {

    }

    /**
     * For internal only. DO NOT USE IT.
     */
    public function deserialize($param)
    {
        if ($param === null) {
            return;
        }
        if (array_key_exists("CheckItemId",$param) and $param["CheckItemId"] !== null) {
            $this->CheckItemId = $param["CheckItemId"];
        }

        if (array_key_exists("Offset",$param) and $param["Offset"] !== null) {
            $this->Offset = $param["Offset"];
        }

        if (array_key_exists("Limit",$param) and $param["Limit"] !== null) {
            $this->Limit = $param["Limit"];
        }

        if (array_key_exists("Filters",$param) and $param["Filters"] !== null) {
            $this->Filters = [];
            foreach ($param["Filters"] as $key => $value){
                $obj = new ComplianceFilters();
                $obj->deserialize($value);
                array_push($this->Filters, $obj);
            }
        }

        if (array_key_exists("By",$param) and $param["By"] !== null) {
            $this->By = $param["By"];
        }

        if (array_key_exists("Order",$param) and $param["Order"] !== null) {
            $this->Order = $param["Order"];
        }
    }
}
