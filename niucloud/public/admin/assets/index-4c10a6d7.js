/* empty css             *//* empty css                     */import"./el-tooltip-4ed993c7.js";/* empty css                  */import{x as v}from"./index-d5447f06.js";/* empty css               */import{t as s}from"./index-ebefdd26.js";import{i as T}from"./index-0ca8babb.js";import{E as k,a as B}from"./index-d6b4c236.js";import{E as F}from"./index-9ef6974e.js";import{E as M}from"./index-138bfa06.js";import{E as D}from"./index-910198ab.js";import{d as N,r as u,V as G,b as I,e as L,f as t,q as e,p as a,x as o,u as l}from"./runtime-core.esm-bundler-c17ab5bc.js";import"./vue-router-9f815af7.js";import"./common-a45d855f.js";import"./event-3e082a4a.js";import"./plugin-vue_export-helper-c018272e.js";import"./index-0d36ccbf.js";import"./el-main-03d12282.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./index-e42600b8.js";import"./el-overlay-380df695.js";import"./index-93efb1b5.js";import"./focus-trap-c0148071.js";import"./index-4b62c73a.js";import"./index-332680c2.js";/* empty css                  */import"./el-form-item-144bc604.js";import"./_baseClone-37ba2e68.js";/* empty css                 */import"./index-a6ffcea0.js";import"./index-5f2625ed.js";import"./index-6f670765.js";import"./index-72bf6cb5.js";function V(){return v.get("shop/stat/total")}function q(){return v.get("shop/stat/today")}function P(){return v.get("shop/stat/yesterday")}function Q(){return v.get("shop/stat")}function Y(){return v.get("shop/stat/order")}function H(){return v.get("shop/stat/goods")}const R={class:"main-container p-4 bg-[#eff0f4]"},j={class:"realtime-overview bg-white p-4"},z={class:"mb-[20px]"},J={class:"text-lg font-extrabold mr-[10px]"},K={class:"text-sm text-[#a19f98]"},U={class:"text-sm text-[#a19f98]"},W={class:"ml-[10px]"},X={class:"text-sm text-[#a19f98] leading-8"},Z={style:{display:"inline-flex","align-items":"center"}},$={class:"mr-[5px]"},tt={class:"text-sm text-[#a19f98] leading-8"},et={class:"text-sm text-[#a19f98] leading-8 mt-[15px]"},at={class:"ml-[10px]"},st={class:"text-sm text-[#a19f98] leading-8"},lt={style:{display:"inline-flex","align-items":"center"}},ot={class:"mr-[5px]"},nt={class:"text-sm text-[#a19f98] leading-8"},it={class:"text-sm text-[#a19f98] leading-8 mt-[15px]"},dt={class:"ml-[10px]"},rt={class:"text-sm text-[#a19f98] leading-8"},ut={style:{display:"inline-flex","align-items":"center"}},_t={class:"mr-[5px]"},ct={class:"text-sm text-[#a19f98] leading-8"},pt={class:"text-sm text-[#a19f98] leading-8 mt-[15px]"},mt={class:"ml-[10px]"},vt={class:"text-sm text-[#a19f98] leading-8"},ft={style:{display:"inline-flex","align-items":"center"}},xt={class:"mr-[5px]"},ht={class:"text-sm text-[#a19f98] leading-8"},gt={class:"text-sm text-[#a19f98] leading-8 mt-[15px]"},yt={class:"agent-matters bg-white p-4 mt-[15px]"},wt={class:"mb-[20px] text-lg font-extrabold mr-[10px]"},St={class:"ml-[10px]"},bt={style:{display:"inline-flex","align-items":"center"}},Ct={class:"mr-[5px]"},Ot={style:{display:"inline-flex","align-items":"center"}},Tt={style:{display:"inline-flex","align-items":"center"}},Et=t("div",{style:{display:"inline-flex","align-items":"center"}}," 退款订单 ",-1),At={style:{display:"inline-flex","align-items":"center"}},kt={style:{display:"inline-flex","align-items":"center"}},Bt={class:"rder-trend bg-white p-4 mt-[15px]"},me=N({__name:"index",setup(Ft){const w=u(null),S=u(null),f=u([]),x=u([]),h=u([]),g=u([]),y=u([]),b=u([]);(async()=>{f.value=await(await V()).data,x.value=await(await q()).data,h.value=await(await P()).data,y.value=await(await Y()).data,b.value=await(await H()).data,g.value=await(await Q()).data,setTimeout(()=>{E(""),A("")},20)})();const E=r=>{let _=g.value.order_num;if(r&&(_=r),!w.value)return;const n=T(w.value),d=u({title:{text:"订单量趋势"},legend:{},xAxis:{data:[]},yAxis:{},tooltip:{trigger:"axis"},series:[{type:"line",data:[]}]});d.value.xAxis.data=g.value.time,d.value.series[0].data=_,n.setOption(d.value)},A=r=>{let _=g.value.sale_money;if(r&&(_=r),!S.value)return;const n=T(S.value),d=u({title:{text:"销售额（元）"},legend:{},xAxis:{data:[]},yAxis:{},tooltip:{trigger:"axis"},series:[{type:"line",data:[]}]});d.value.xAxis.data=g.value.time,d.value.series[0].data=_,n.setOption(d.value)},O=u("");return(()=>{const r=new Date,_=r.getFullYear(),n=r.getMonth()+1,d=r.getDate(),c=i(r.getHours()),p=i(r.getMinutes()),m=i(r.getSeconds());function i(C){return C<10?"0"+C:C}O.value=_+"-"+n+"-"+d+" "+c+":"+p+":"+m})(),(r,_)=>{const n=k,d=B,c=G("QuestionFilled"),p=F,m=M,i=D;return I(),L("div",R,[t("div",j,[e(d,null,{default:a(()=>[e(n,{span:24},{default:a(()=>[t("div",z,[t("span",J,o(l(s)("realtimeOverview")),1),t("span",K,o(l(s)("updateTime")),1),t("span",U,o(O.value),1)])]),_:1})]),_:1}),e(d,null,{default:a(()=>[e(n,{span:6},{default:a(()=>[t("div",W,[t("div",X,[e(i,{value:x.value.order_num},{title:a(()=>[t("div",Z,[t("span",$,o(l(s)("todayOrderCount")),1),e(m,{class:"box-item",effect:"light",content:l(s)("todayOrderCount"),placement:"top"},{default:a(()=>[e(p,null,{default:a(()=>[e(c)]),_:1})]),_:1},8,["content"])])]),_:1},8,["value"])]),t("div",tt,[t("span",null,o(l(s)("yesterday")),1),t("span",null,o(h.value.order_num),1)]),t("div",et,[e(i,{title:l(s)("orderCount"),value:f.value.order_num},null,8,["title","value"])])])]),_:1}),e(n,{span:6},{default:a(()=>[t("div",at,[t("div",st,[e(i,{value:x.value.sale_money},{title:a(()=>[t("div",lt,[t("span",ot,o(l(s)("todayOrderSale")),1),e(m,{class:"box-item",effect:"light",content:l(s)("todayOrderSale"),placement:"top"},{default:a(()=>[e(p,null,{default:a(()=>[e(c)]),_:1})]),_:1},8,["content"])])]),_:1},8,["value"])]),t("div",nt,[t("span",null,o(l(s)("yesterday")),1),t("span",null,o(h.value.sale_money),1)]),t("div",it,[e(i,{title:l(s)("salesTotal"),value:f.value.sale_money},null,8,["title","value"])])])]),_:1}),e(n,{span:6},{default:a(()=>[t("div",dt,[t("div",rt,[e(i,{value:x.value.refund_money},{title:a(()=>[t("div",ut,[t("span",_t,o(l(s)("todayAddMemberCount")),1),e(m,{class:"box-item",effect:"light",content:l(s)("todayAddMemberCount"),placement:"top"},{default:a(()=>[e(p,null,{default:a(()=>[e(c)]),_:1})]),_:1},8,["content"])])]),_:1},8,["value"])]),t("div",ct,[t("span",null,o(l(s)("yesterday")),1),t("span",null,o(h.value.refund_money),1)]),t("div",pt,[e(i,{title:l(s)("memberTotal"),value:f.value.refund_money},null,8,["title","value"])])])]),_:1}),e(n,{span:6},{default:a(()=>[t("div",mt,[t("div",vt,[e(i,{value:x.value.access_sum},{title:a(()=>[t("div",ft,[t("span",xt,o(l(s)("todayBrowseCount")),1),e(m,{class:"box-item",effect:"light",content:l(s)("todayBrowseCount"),placement:"top"},{default:a(()=>[e(p,null,{default:a(()=>[e(c)]),_:1})]),_:1},8,["content"])])]),_:1},8,["value"])]),t("div",ht,[t("span",null,o(l(s)("yesterday")),1),t("span",null,o(h.value.access_sum),1)]),t("div",gt,[e(i,{title:l(s)("browseTotal"),value:f.value.access_sum},null,8,["title","value"])])])]),_:1})]),_:1})]),t("div",yt,[e(d,null,{default:a(()=>[e(n,{span:24},{default:a(()=>[t("div",wt,o(l(s)("agentMatters")),1)]),_:1})]),_:1}),e(d,null,{default:a(()=>[e(n,{span:4},{default:a(()=>[t("div",St,[e(i,{value:y.value.wait_pay_order},{title:a(()=>[t("div",bt,[t("span",Ct,o(l(s)("waitPayOrder")),1),e(m,{class:"box-item",effect:"light",content:l(s)("waitPayOrder"),placement:"top"},{default:a(()=>[e(p,null,{default:a(()=>[e(c)]),_:1})]),_:1},8,["content"])])]),_:1},8,["value"])])]),_:1}),e(n,{span:4},{default:a(()=>[e(i,{value:y.value.wait_delivery_order},{title:a(()=>[t("div",Ot,o(l(s)("waitDeliveryOrder")),1)]),_:1},8,["value"])]),_:1}),e(n,{span:4},{default:a(()=>[e(i,{value:y.value.wait_take_order},{title:a(()=>[t("div",Tt,o(l(s)("waitTakeOrder")),1)]),_:1},8,["value"])]),_:1}),e(n,{span:4},{default:a(()=>[e(i,{value:y.value.refund_order},{title:a(()=>[Et]),_:1},8,["value"])]),_:1}),e(n,{span:4},{default:a(()=>[e(i,{value:b.value.sale_goods_num},{title:a(()=>[t("div",At,o(l(s)("saleGoodsNum")),1)]),_:1},8,["value"])]),_:1}),e(n,{span:4},{default:a(()=>[e(i,{value:b.value.warehouse_goods_num},{title:a(()=>[t("div",kt,o(l(s)("warehouseGoodsNum")),1)]),_:1},8,["value"])]),_:1})]),_:1})]),t("div",Bt,[e(d,null,{default:a(()=>[e(n,{span:12},{default:a(()=>[t("div",{ref_key:"visitStat",ref:w,style:{width:"100%",height:"300px"}},null,512)]),_:1}),e(n,{span:12},{default:a(()=>[t("div",{ref_key:"hourStat",ref:S,style:{width:"100%",height:"300px"}},null,512)]),_:1})]),_:1})])])}}});export{me as default};
