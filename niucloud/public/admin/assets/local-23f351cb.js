/* empty css             *//* empty css                   *//* empty css                */import{ac as te}from"./index-596de8a9.js";import{a as le,E as re}from"./el-form-item-144bc604.js";/* empty css                 *//* empty css                       *//* empty css                 *//* empty css                          *//* empty css                    *//* empty css                  */import{v as I,d as N}from"./event-3e082a4a.js";import{t}from"./index-6b4cbbe4.js";import{u as oe,a as se}from"./vue-router-9f815af7.js";import{j as B}from"./common-a45d855f.js";import{c as ie,b as U,d as $,e as G,s as de}from"./qqmap-237c7a77.js";import{j as ne,k as ue}from"./delivery-44173086.js";import{T as V}from"./test-1df70a90.js";import{c as pe}from"./shop_address-a501fa30.js";import{E as me}from"./index-6f670765.js";import{E as _e,a as ce}from"./index-784d7581.js";import{E as ve,b as fe}from"./index-6ff31475.js";import{E as ye}from"./index-5f2625ed.js";import{E as ge}from"./index-72bf6cb5.js";import{E as be}from"./index-c5af06c2.js";import{v as xe}from"./directive-d226d53f.js";import{d as he,r as g,c as we,o as Ve,J as ke,b as w,e as E,f as d,x as u,u as l,q as r,p as s,L as C,m as O,v as p,C as Ee,F as Ce,t as Te,n as Ue,at as qe,au as Re}from"./runtime-core.esm-bundler-c17ab5bc.js";import{_ as je}from"./_plugin-vue_export-helper-c27b6911.js";import"./index-0d36ccbf.js";import"./el-main-03d12282.js";import"./plugin-vue_export-helper-c018272e.js";import"./index-e42600b8.js";import"./el-overlay-380df695.js";import"./index-93efb1b5.js";import"./index-9ef6974e.js";import"./focus-trap-c0148071.js";import"./index-4b62c73a.js";import"./index-332680c2.js";/* empty css                  */import"./index-a6ffcea0.js";import"./index-138bfa06.js";import"./_baseClone-37ba2e68.js";import"./isEqual-ca98cf0c.js";const q=k=>(qe("data-v-0827605b"),k=k(),Re(),k),De={class:"main-container"},Se={class:"detail-head"},Ae=q(()=>d("span",{class:"iconfont iconxiangzuojiantou !text-xs"},null,-1)),Me={class:"ml-[1px]"},Pe=q(()=>d("span",{class:"adorn"},"|",-1)),Fe={class:"right"},Le={class:"flex flex-col"},ze={class:"flex"},Ie={key:0,class:"text-error leading-none"},Ne={class:"flex"},Be={class:"w-[60px] mx-[5px]"},$e={class:"w-[60px] mx-[5px]"},Ge={class:"w-[60px] mx-[5px]"},Oe={class:"w-[60px] mx-[5px]"},Je={class:"flex"},He={class:"w-[60px] mx-[5px]"},Ke={class:"w-[60px] mx-[5px]"},Qe={class:"w-[60px] mx-[5px]"},We={class:"relative"},Xe=q(()=>d("div",{id:"container",class:"w-full h-[520px]"},null,-1)),Ye={class:"absolute bg-white w-[270px] h-[500px] z-[1000] top-[10px] left-[10px] region-list"},Ze=["onClick"],ea={class:"pb-[18px]"},aa={class:"pb-[18px]"},ta={class:"pb-[10px]"},la={class:"p-[10px] text-center"},ra={class:"fixed-footer-wrap"},oa={class:"fixed-footer"},sa=he({__name:"local",setup(k){const J=oe(),R=se(),b=g(!1),H=J.meta.title,j=g(),T=g(),_=g(null);(async()=>{await pe().then(({data:o})=>{_.value=o}).catch()})();const e=g({center:{lat:"",lng:""},delivery_type:["business"],fee_type:"region",base_dist:"",base_price:"",grad_dist:"",grad_price:"",weight_start:0,weight_unit:0,weight_price:0,area:[{area_name:"",area_type:"radius",start_price:0,delivery_price:0,area_json:{key:B()}}]}),D=we(()=>({delivery_address:[{validator:(o,a,i)=>{_.value||i(new Error(t("defaultDeliveryAddressEmpty"))),i()}}],delivery_type:[{validator:(o,a,i)=>{e.value.delivery_type.length||i(new Error(t("deliveryTypeRequire"))),i()}}],distance:[{validator:(o,a,i)=>{e.value.fee_type=="distance"&&(V.require(e.value.base_dist)&&i(new Error(t("baseDistRequire"))),V.require(e.value.base_price)&&i(new Error(t("basePriceRequire"))),V.require(e.value.grad_dist)&&i(new Error(t("gradDistRequire"))),V.require(e.value.grad_price)&&i(new Error(t("gradPriceRequire")))),i()},trigger:"blur"}],area_name:[{required:!0,message:t("areaNameRequire"),trigger:"blur"}],start_price:[{required:!0,message:t("startPriceRequire"),trigger:"blur"},{min:0,message:t("startPriceMin"),trigger:"blur"}],delivery_price:[{required:e.value.fee_type=="region",message:t("deliveryPriceRequire"),trigger:"blur"},{min:0,message:t("deliveryPriceMin"),trigger:"blur"}],area:[{validator:(o,a,i)=>{V.empty(e.value.area)&&i(new Error(t("areaPlaceholder"))),i()},trigger:"blur"}]}));ne().then(({data:o})=>{o&&Object.assign(e.value,o)}).catch(),Ve(()=>{const o=document.createElement("script");te().then(a=>{o.type="text/javascript",o.src="https://map.qq.com/api/gljs?libraries=tools,service&v=1.exp&key="+a.data.key,document.body.appendChild(o)}),o.onload=()=>{setTimeout(()=>{K()},500)}});let y;const S=g(!0),K=()=>{const o=window.TMap,a=o.LatLng,i=new a(_.value?_.value.lat:39.980619,_.value?_.value.lng:116.321277);y=new o.Map("container",{center:i,zoom:14}),ie(y),y.on("tilesloaded",()=>{S.value=!1}),e.value.area.forEach(m=>{m.area_type=="radius"?U(y,m.area_json):$(y,m.area_json)})},A=g(0),Q=()=>{e.value.area.push({area_name:"",area_type:"radius",start_price:0,delivery_price:0,area_json:{key:B()}});const o=e.value.area.length-1;U(y,e.value.area[o].area_json)},W=o=>{const a=e.value.area[o];G(a.area_json.key),e.value.area.splice(o,1)},X=o=>{A.value=o;const a=e.value.area[o];de(a.area_json.key)},Y=o=>{const a=e.value.area[o];G(a.area_json.key),a.area_type=="radius"?U(y,a.area_json):$(y,a.area_json)};ke(()=>{y.destroy()});const Z=async o=>{b.value||!o||await o.validate(async a=>{var m;let i=!0;for(let x=0;x<((m=T.value)==null?void 0:m.length)&&(await T.value[x].validate(async h=>{i=h}),!!i);x++);i&&a&&(b.value=!0,e.value.center={lat:_.value.lat,lng:_.value.lng},await o.validate(async x=>{ue(e.value).then(()=>{b.value=!1}).catch(()=>{b.value=!1})}))})},M=()=>{R.push({path:"/shop/order/delivery"})};return(o,a)=>{const i=me,m=le,x=_e,P=ce,h=ve,F=fe,v=ye,L=re,ee=ge,ae=be,z=xe;return w(),E("div",De,[d("div",Se,[d("div",{class:"left",onClick:M},[Ae,d("span",Me,u(l(t)("returnToPreviousPage")),1)]),Pe,d("span",Fe,u(l(H)),1)]),r(ae,{class:"box-card !border-none",shadow:"never"},{default:s(()=>[C((w(),O(L,{"label-width":"120px",ref_key:"formRef",ref:j,rules:l(D),model:e.value,class:"page-form"},{default:s(()=>[r(m,{label:l(t)("deliveryAddress"),prop:"delivery_address"},{default:s(()=>[d("div",Le,[d("div",ze,[p(u(_.value?_.value.full_address:l(t)("defaultDeliveryAddressEmpty"))+" ",1),r(i,{type:"primary",onClick:a[0]||(a[0]=n=>l(R).push("/shop/order/address")),link:"",class:"ml-[10px]"},{default:s(()=>[p(u(_.value?l(t)("update"):l(t)("toSetting")),1)]),_:1})]),e.value.center.lat&&_.value&&(e.value.center.lat!=_.value.lat||e.value.center.lng!=_.value.lng)?(w(),E("div",Ie,u(l(t)("deliveryAddressChange")),1)):Ee("",!0)])]),_:1},8,["label"]),r(m,{label:l(t)("deliveryType"),prop:"delivery_type"},{default:s(()=>[r(P,{modelValue:e.value.delivery_type,"onUpdate:modelValue":a[1]||(a[1]=n=>e.value.delivery_type=n)},{default:s(()=>[r(x,{label:"business"},{default:s(()=>[p(u(l(t)("business")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),r(m,{label:l(t)("feeType")},{default:s(()=>[r(F,{modelValue:e.value.fee_type,"onUpdate:modelValue":a[2]||(a[2]=n=>e.value.fee_type=n)},{default:s(()=>[r(h,{label:"region"},{default:s(()=>[p(u(l(t)("region")),1)]),_:1}),r(h,{label:"distance"},{default:s(()=>[p(u(l(t)("distance")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),C(r(m,{label:l(t)("feeSetting"),prop:"distance"},{default:s(()=>[d("div",Ne,[d("div",Be,[r(v,{modelValue:e.value.base_dist,"onUpdate:modelValue":a[3]||(a[3]=n=>e.value.base_dist=n),modelModifiers:{number:!0},type:"text"},null,8,["modelValue"])]),p(" "+u(l(t)("feeSettingTextOne"))+" ",1),d("div",$e,[r(v,{modelValue:e.value.base_price,"onUpdate:modelValue":a[4]||(a[4]=n=>e.value.base_price=n),type:"text"},null,8,["modelValue"])]),p(" "+u(l(t)("feeSettingTextTwo"))+" ",1),d("div",Ge,[r(v,{modelValue:e.value.grad_dist,"onUpdate:modelValue":a[5]||(a[5]=n=>e.value.grad_dist=n),modelModifiers:{number:!0},type:"text"},null,8,["modelValue"])]),p(" "+u(l(t)("feeSettingTextThree"))+" ",1),d("div",Oe,[r(v,{modelValue:e.value.grad_price,"onUpdate:modelValue":a[6]||(a[6]=n=>e.value.grad_price=n),type:"text"},null,8,["modelValue"])]),p(" "+u(l(t)("priceUnit")),1)])]),_:1},8,["label"]),[[I,e.value.fee_type=="distance"]]),r(m,{label:l(t)("weightFee"),prop:""},{default:s(()=>[d("div",Je,[p(u(l(t)("weightFeeTextOne"))+" ",1),d("div",He,[r(v,{modelValue:e.value.weight_start,"onUpdate:modelValue":a[7]||(a[7]=n=>e.value.weight_start=n),type:"text"},null,8,["modelValue"])]),p(" "+u(l(t)("weightFeeTextTwo"))+" ",1),d("div",Ke,[r(v,{modelValue:e.value.weight_unit,"onUpdate:modelValue":a[8]||(a[8]=n=>e.value.weight_unit=n),type:"text"},null,8,["modelValue"])]),p(" "+u(l(t)("weightFeeTextThree"))+" ",1),d("div",Qe,[r(v,{modelValue:e.value.weight_price,"onUpdate:modelValue":a[9]||(a[9]=n=>e.value.weight_price=n),type:"text"},null,8,["modelValue"])]),p(" "+u(l(t)("priceUnit")),1)])]),_:1},8,["label"]),C((w(),O(m,{prop:"area"},{default:s(()=>[d("div",We,[Xe,d("div",Ye,[r(ee,null,{default:s(()=>[(w(!0),E(Ce,null,Te(e.value.area,(n,c)=>(w(),E("div",{class:Ue(["p-[10px] region-item pr-[50px] relative",{"!border-primary":c==A.value}]),key:c,onClick:f=>X(c)},[r(L,{"label-width":"80px",model:n,rules:l(D),class:"page-form",ref_for:!0,ref_key:"areaFromRef",ref:T},{default:s(()=>[d("div",ea,[r(m,{label:l(t)("areaName"),prop:"area_name"},{default:s(()=>[r(v,{modelValue:e.value.area[c].area_name,"onUpdate:modelValue":f=>e.value.area[c].area_name=f,type:"text"},null,8,["modelValue","onUpdate:modelValue"])]),_:2},1032,["label"])]),d("div",aa,[r(m,{label:l(t)("startPrice"),prop:"start_price"},{default:s(()=>[r(v,{modelValue:e.value.area[c].start_price,"onUpdate:modelValue":f=>e.value.area[c].start_price=f,type:"text"},null,8,["modelValue","onUpdate:modelValue"])]),_:2},1032,["label"])]),C(d("div",ta,[r(m,{label:l(t)("deliveryPrice"),prop:"delivery_price"},{default:s(()=>[r(v,{modelValue:e.value.area[c].delivery_price,"onUpdate:modelValue":f=>e.value.area[c].delivery_price=f,type:"text"},null,8,["modelValue","onUpdate:modelValue"])]),_:2},1032,["label"])],512),[[I,e.value.fee_type=="region"]]),r(m,{label:l(t)("areaType")},{default:s(()=>[r(F,{modelValue:e.value.area[c].area_type,"onUpdate:modelValue":f=>e.value.area[c].area_type=f,onClick:a[10]||(a[10]=N(()=>{},["stop"])),onChange:f=>Y(c)},{default:s(()=>[r(h,{label:"radius",size:"large",class:"!mr-[10px]"},{default:s(()=>[p(u(l(t)("radius")),1)]),_:1}),r(h,{label:"custom",size:"large",class:"!mr-[0px]"},{default:s(()=>[p(u(l(t)("custom")),1)]),_:1})]),_:2},1032,["modelValue","onUpdate:modelValue","onChange"])]),_:2},1032,["label"])]),_:2},1032,["model","rules"]),r(i,{type:"primary",link:"",class:"absolute z-1 top-[10px] right-[10px]",onClick:N(f=>W(c),["stop"])},{default:s(()=>[p(u(l(t)("delete")),1)]),_:2},1032,["onClick"])],10,Ze))),128)),d("div",la,[r(i,{type:"default",plain:"",onClick:Q},{default:s(()=>[p(u(l(t)("addDeliveryArea")),1)]),_:1})])]),_:1})])])]),_:1})),[[z,S.value]])]),_:1},8,["rules","model"])),[[z,b.value]])]),_:1}),d("div",ra,[d("div",oa,[r(i,{type:"primary",onClick:a[11]||(a[11]=n=>Z(j.value)),disabled:b.value},{default:s(()=>[p(u(l(t)("save")),1)]),_:1},8,["disabled"]),r(i,{onClick:a[12]||(a[12]=n=>M())},{default:s(()=>[p(u(l(t)("cancel")),1)]),_:1})])])])}}});const Xa=je(sa,[["__scopeId","data-v-0827605b"]]);export{Xa as default};
