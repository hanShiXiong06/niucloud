/* empty css             *//* empty css                   *//* empty css                      *//* empty css                 *//* empty css               *//* empty css                  *//* empty css                  */import"./index-d5447f06.js";/* empty css                  *//* empty css                        *//* empty css                    */import"./el-tooltip-4ed993c7.js";/* empty css                */import{a as N,E as $}from"./el-form-item-144bc604.js";/* empty css                  */import{t as e}from"./index-ebefdd26.js";import{b as D,d as L}from"./shop_address-a92893e9.js";import{u as R,a as U}from"./vue-router-9f815af7.js";import{E as j}from"./index-b74135ff.js";import{E as I}from"./index-6f670765.js";import{E as M}from"./index-5f2625ed.js";import{E as q}from"./index-c5af06c2.js";import{a as G,E as H}from"./index-6191b0b4.js";import{E as J}from"./index-5c20ff8f.js";import{E as K}from"./index-cefc0b67.js";import{v as O}from"./directive-d226d53f.js";import{d as Q,M as W,r as X,b as p,e as g,q as a,p as s,f as _,x as i,u as o,v as n,L as Y,m as b,C as f}from"./runtime-core.esm-bundler-c17ab5bc.js";import{_ as Z}from"./_plugin-vue_export-helper-c27b6911.js";import"./common-a45d855f.js";import"./index-9ef6974e.js";import"./plugin-vue_export-helper-c018272e.js";import"./event-3e082a4a.js";import"./index-0d36ccbf.js";import"./el-main-03d12282.js";import"./index-e42600b8.js";import"./el-overlay-380df695.js";import"./index-93efb1b5.js";import"./focus-trap-c0148071.js";import"./index-4b62c73a.js";import"./index-332680c2.js";import"./index-a6ffcea0.js";import"./index-138bfa06.js";import"./index-72bf6cb5.js";import"./_baseClone-37ba2e68.js";import"./aria-adfa05c5.js";import"./validator-f5e079db.js";import"./index-784d7581.js";import"./isEqual-ca98cf0c.js";import"./_isIterateeCall-797defa1.js";import"./debounce-196ce6a6.js";import"./index-bfecf17f.js";import"./index-b7b91fcb.js";import"./strings-e72e60f7.js";const ee={class:"main-container"},te={class:"flex justify-between items-center"},oe={class:"text-[20px]"},ae={class:"mt-[10px]"},le={key:0},re={key:1},se={class:"mt-[16px] flex justify-end"},ie=Q({__name:"list",setup(de){const C=R().meta.title,t=W({page:1,limit:10,total:0,loading:!0,data:[],searchParam:{mobile:"",full_address:""}}),v=X(),m=(d=1)=>{t.loading=!0,t.page=d,D({page:t.page,limit:t.limit,...t.searchParam}).then(r=>{t.loading=!1,t.data=r.data.data,t.total=r.data.total}).catch(()=>{t.loading=!1})};m();const y=U(),w=()=>{y.push("/shop/order/address/edit")},A=d=>{y.push("/shop/order/address/edit?id="+d.id)},P=d=>{j.confirm(e("shopAddressDeleteTips"),e("warning"),{confirmButtonText:e("confirm"),cancelButtonText:e("cancel"),type:"warning"}).then(()=>{L(d).then(()=>{m()}).catch(()=>{})})},V=d=>{d&&(d.resetFields(),m())};return(d,r)=>{const u=I,x=M,h=N,T=$,E=q,c=G,k=J,z=H,B=K,S=O;return p(),g("div",ee,[a(E,{class:"box-card !border-none",shadow:"never"},{default:s(()=>[_("div",te,[_("span",oe,i(o(C)),1),a(u,{type:"primary",onClick:w},{default:s(()=>[n(i(o(e)("addShopAddress")),1)]),_:1})]),a(E,{class:"box-card !border-none my-[10px] table-search-wrap",shadow:"never"},{default:s(()=>[a(T,{inline:!0,model:t.searchParam,ref_key:"searchFormRef",ref:v},{default:s(()=>[a(h,{label:o(e)("mobile"),prop:"mobile"},{default:s(()=>[a(x,{modelValue:t.searchParam.mobile,"onUpdate:modelValue":r[0]||(r[0]=l=>t.searchParam.mobile=l),placeholder:o(e)("mobilePlaceholder")},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),a(h,{label:o(e)("fullAddress"),prop:"full_address"},{default:s(()=>[a(x,{modelValue:t.searchParam.full_address,"onUpdate:modelValue":r[1]||(r[1]=l=>t.searchParam.full_address=l),placeholder:o(e)("fullAddressPlaceholder")},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),a(h,null,{default:s(()=>[a(u,{type:"primary",onClick:r[2]||(r[2]=l=>m())},{default:s(()=>[n(i(o(e)("search")),1)]),_:1}),a(u,{onClick:r[3]||(r[3]=l=>V(v.value))},{default:s(()=>[n(i(o(e)("reset")),1)]),_:1})]),_:1})]),_:1},8,["model"])]),_:1}),_("div",ae,[Y((p(),b(z,{data:t.data,size:"large"},{empty:s(()=>[_("span",null,i(t.loading?"":o(e)("emptyData")),1)]),default:s(()=>[a(c,{prop:"contact_name",label:o(e)("contactName"),"min-width":"120"},null,8,["label"]),a(c,{prop:"mobile",label:o(e)("mobile"),"min-width":"120"},null,8,["label"]),a(c,{prop:"full_address",label:o(e)("fullAddress"),"min-width":"120","show-overflow-tooltip":!0},null,8,["label"]),a(c,{prop:"is_delivery_address",label:o(e)("addressType"),"min-width":"120",align:"left"},{default:s(({row:l})=>[l.is_delivery_address?(p(),g("div",le,[n(i(o(e)("deliveryAddress"))+" ",1),l.is_default_delivery?(p(),b(k,{key:0,size:"small"},{default:s(()=>[n(i(o(e)("default")),1)]),_:1})):f("",!0)])):f("",!0),l.is_refund_address?(p(),g("div",re,[n(i(o(e)("refundAddress"))+" ",1),l.is_default_refund?(p(),b(k,{key:0,size:"small"},{default:s(()=>[n(i(o(e)("default")),1)]),_:1})):f("",!0)])):f("",!0)]),_:1},8,["label"]),a(c,{label:o(e)("operation"),fixed:"right","min-width":"120",align:"right"},{default:s(({row:l})=>[a(u,{type:"primary",link:"",onClick:F=>A(l)},{default:s(()=>[n(i(o(e)("edit")),1)]),_:2},1032,["onClick"]),a(u,{type:"primary",link:"",onClick:F=>P(l.id)},{default:s(()=>[n(i(o(e)("delete")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"])]),_:1},8,["data"])),[[S,t.loading]]),_("div",se,[a(B,{"current-page":t.page,"onUpdate:currentPage":r[4]||(r[4]=l=>t.page=l),"page-size":t.limit,"onUpdate:pageSize":r[5]||(r[5]=l=>t.limit=l),layout:"total, sizes, prev, pager, next, jumper",total:t.total,onSizeChange:r[6]||(r[6]=l=>m()),onCurrentChange:m},null,8,["current-page","page-size","total"])])])]),_:1})])}}});const nt=Z(ie,[["__scopeId","data-v-4207ca7a"]]);export{nt as default};
