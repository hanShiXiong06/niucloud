/* empty css             *//* empty css                   */import{E as ue}from"./el-overlay-380df695.js";import{aw as me}from"./index-596de8a9.js";/* empty css                *//* empty css                    *//* empty css                */import{a as fe,E as ce}from"./el-form-item-144bc604.js";/* empty css                  *//* empty css                        *//* empty css               */import"./el-tooltip-4ed993c7.js";/* empty css                  *//* empty css                  *//* empty css                       *//* empty css                 *//* empty css                 */import{v as K}from"./event-3e082a4a.js";import{t as a}from"./index-6b4cbbe4.js";import{x as ve,y as he,z as ge}from"./delivery-44173086.js";import{u as ye,a as be}from"./vue-router-9f815af7.js";import{T as j}from"./test-1df70a90.js";import{E as Ve}from"./index-5f2625ed.js";import{E as ke,b as Ee}from"./index-6ff31475.js";import{a as Se,E as we}from"./index-6191b0b4.js";import{E as xe}from"./index-6f670765.js";import{E as Ce}from"./index-1b611f36.js";import{E as Ue}from"./index-c5af06c2.js";import{E as De}from"./index-0ee88e31.js";import{E as Te}from"./index-72bf6cb5.js";import{v as Ne}from"./directive-d226d53f.js";import{d as Ae,r as k,M as Pe,c as D,b as x,e as B,f as v,x as c,u as r,q as l,p as o,L as q,m as M,v as g,C as ze,F as Re,at as Ie,au as Fe}from"./runtime-core.esm-bundler-c17ab5bc.js";import{_ as Oe}from"./_plugin-vue_export-helper-c27b6911.js";import"./index-93efb1b5.js";import"./index-9ef6974e.js";import"./plugin-vue_export-helper-c018272e.js";import"./focus-trap-c0148071.js";import"./index-4b62c73a.js";import"./index-332680c2.js";import"./common-a45d855f.js";import"./index-0d36ccbf.js";import"./el-main-03d12282.js";import"./index-e42600b8.js";import"./index-a6ffcea0.js";import"./index-138bfa06.js";import"./_baseClone-37ba2e68.js";import"./index-784d7581.js";import"./isEqual-ca98cf0c.js";import"./_isIterateeCall-797defa1.js";import"./debounce-196ce6a6.js";import"./index-bfecf17f.js";import"./validator-f5e079db.js";import"./index-0b4c4f48.js";const H=C=>(Ie("data-v-8eddc2d3"),C=C(),Fe(),C),je={class:"main-container"},Be={class:"detail-head"},qe=H(()=>v("span",{class:"iconfont iconxiangzuojiantou !text-xs"},null,-1)),Le={class:"ml-[1px]"},$e=H(()=>v("span",{class:"adorn"},"|",-1)),We={class:"right"},Ze={class:"area-input"},Ge=["onClick"],Ke={key:1},Me={class:"mt-[10px]"},He={class:"area-input"},Je={class:"form-tip"},Qe={class:"mt-[10px]"},Xe={class:"area-input"},Ye={class:"mt-[10px]"},ea={class:"fixed-footer-wrap"},aa={class:"fixed-footer"},la={class:"dialog-footer"},ta=Ae({__name:"template_edit",setup(C){const w=k(!1),T=ye(),L=be(),b=k(!1),J={template_id:"",template_name:"",fee_type:"num",area:[],no_delivery:0,is_free_shipping:0,fee_data:[],free_shipping_data:[],no_delivery_data:[]},Q=T.meta.title,u=Pe({...J}),$=k(),N=k([]);T.query.id&&(b.value=!0,ve(T.query.id).then(({data:n})=>{n&&(Object.keys(u).forEach(t=>{n[t]!=null&&(u[t]=n[t])}),f.value=n.fee_data,y.value=n.no_delivery_data,h.value=n.free_shipping_data),b.value=!1}).catch(()=>{b.value=!1})),me(2).then(n=>{N.value=n.data}).catch();const X=D(()=>({template_name:[{required:!0,message:a("templateNamePlaceholder"),trigger:"blur"}],fee_data:[{validator:Y}],free_shipping_data:[{validator:ee}],no_delivery_data:[{validator:ae}]})),Y=(n,t,i)=>{for(let s=0;s<f.value.length;s++){const p=f.value[s];if(!p.area_ids.length){i(new Error(a("areaPlaceholder")));break}if(j.empty(p.snum)||p.snum<0){i(new Error(U.value.first+a("notUnderZero")));break}if(j.empty(p.xnum)||p.snum<0){i(new Error(U.value.continue+a("notUnderZero")));break}}i()},ee=(n,t,i)=>{if(u.is_free_shipping){for(let s=0;s<h.value.length;s++){const p=h.value[s];if(!p.area_ids.length){i(new Error(a("freeShippingPlaceholder")));break}if(j.empty(p.free_shipping_num)||p.free_shipping_num<0){i(new Error(W.value+a("notUnderZero")));break}}i()}else i()},ae=(n,t,i)=>{if(u.no_delivery){for(let s=0;s<y.value.length;s++)if(!y.value[s].area_ids.length){i(new Error(a("noDeliveryPlaceholder")));break}i()}else i()},U=D(()=>({num:{first:a("firstNum"),continue:a("continueNum")},weight:{first:a("firstWeight"),continue:a("continueWeight")},volume:{first:a("firstVolume"),continue:a("continueVolume")}})[u.fee_type]),W=D(()=>({num:a("freeShippingNum"),weight:a("freeShippingWeight"),volume:a("freeShippingVolume")})[u.fee_type]),f=k([{area_ids:[0],fee_area_names:"全国",snum:1,sprice:0,xnum:1,xprice:0}]),h=k([]),y=k([]),A=n=>{switch(n){case"fee":f.value.push({area_ids:[],fee_area_names:"",snum:1,sprice:0,xnum:1,xprice:0});break;case"free_shipping":h.value.push({area_ids:[],free_shipping_area_names:"",free_shipping_num:0,free_shipping_price:0});break;case"no_delivery":y.value.push({area_ids:[],no_delivery_area_names:""});break}},P=(n,t)=>{switch(n){case"fee":f.value.splice(t,1);break;case"free_shipping":h.value.splice(t,1);break;case"no_delivery":y.value.splice(t,1);break}};let z=[];const R=k([]);let E={type:"",index:0};const I=(n,t)=>{E={type:n,index:t};let i=[];switch(n){case"fee":i=f.value;break;case"free_shipping":i=h.value;break;case"no_delivery":i=y.value;break}z=i[t].area_ids,R.value=[],i.forEach((s,p)=>{t!=p&&R.value.push(...s.area_ids)}),w.value=!0},le=D(()=>(N.value.forEach(n=>{n.child.forEach(t=>{t.disabled=R.value.includes(t.id)})}),N.value)),F=k(),te=()=>{const n=F.value.getCheckedNodes(!1,!1),t=[],i=[];switch(n.forEach(s=>{s.level==2&&(t.push(s.id),i.push(s.name))}),E.type){case"fee":f.value[E.index].area_ids=t,f.value[E.index].fee_area_names=i.toString();break;case"free_shipping":h.value[E.index].area_ids=t,h.value[E.index].free_shipping_area_names=i.toString();break;case"no_delivery":y.value[E.index].area_ids=t,y.value[E.index].no_delivery_area_names=i.toString();break}w.value=!1},re=()=>{F.value.setCheckedKeys(z,!1)},oe=async n=>{if(b.value||!n)return;const t=u.template_id?he:ge;await n.validate(async i=>{if(i){b.value=!0;const s={template_id:u.template_id,template_name:u.template_name,fee_type:u.fee_type,no_delivery:y.value.length?1:0,is_free_shipping:h.value.length?1:0},p={};f.value.forEach(d=>{d.area_ids.forEach(_=>{p["city_"+_]={city_id:_,fee_area_ids:d.area_ids.toString(),fee_area_names:d.fee_area_names,snum:d.snum,sprice:d.sprice,xnum:d.xnum,xprice:d.xprice}})}),h.value.forEach(d=>{d.area_ids.forEach(_=>{p["city_"+_]?Object.assign(p["city_"+_],{free_shipping_area_ids:d.area_ids.toString(),free_shipping_area_names:d.free_shipping_area_names,free_shipping_num:d.free_shipping_num,free_shipping_price:d.free_shipping_price}):p["city_"+_]={city_id:_,free_shipping_area_ids:d.area_ids.toString(),free_shipping_area_names:d.free_shipping_area_names,free_shipping_num:d.free_shipping_num,free_shipping_price:d.free_shipping_price}})}),y.value.forEach(d=>{d.area_ids.forEach(_=>{p["city_"+_]?Object.assign(p["city_"+_],{no_delivery_area_ids:d.area_ids.toString(),no_delivery_area_names:d.no_delivery_area_names}):p["city_"+_]={city_id:_,no_delivery_area_ids:d.area_ids.toString(),no_delivery_area_names:d.no_delivery_area_names}})}),s.area=Object.values(p),t(s).then(()=>{b.value=!1,L.push({path:"/shop/order/shipping/template"})}).catch(()=>{b.value=!1})}})},Z=()=>{L.push({path:"/shop/order/shipping/template"})};return(n,t)=>{const i=Ve,s=fe,p=ke,d=Ee,_=Se,V=xe,O=we,G=Ce,ie=ce,ne=Ue,se=De,pe=Te,de=ue,_e=Ne;return x(),B(Re,null,[v("div",je,[v("div",Be,[v("div",{class:"left",onClick:Z},[qe,v("span",Le,c(r(a)("returnToPreviousPage")),1)]),$e,v("span",We,c(r(Q)),1)]),l(ne,{class:"box-card !border-none",shadow:"never"},{default:o(()=>[q((x(),M(ie,{model:u,"label-width":"120px",ref_key:"formRef",ref:$,rules:r(X),class:"page-form"},{default:o(()=>[l(s,{label:r(a)("templateName"),prop:"template_name"},{default:o(()=>[l(i,{modelValue:u.template_name,"onUpdate:modelValue":t[0]||(t[0]=e=>u.template_name=e),clearable:"",placeholder:r(a)("templateNamePlaceholder"),class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),l(s,{label:r(a)("feeTypeName"),prop:"fee_type"},{default:o(()=>[l(d,{modelValue:u.fee_type,"onUpdate:modelValue":t[1]||(t[1]=e=>u.fee_type=e)},{default:o(()=>[l(p,{label:"num",size:"large"},{default:o(()=>[g(c(r(a)("num")),1)]),_:1}),l(p,{label:"weight",size:"large"},{default:o(()=>[g(c(r(a)("weight")),1)]),_:1}),l(p,{label:"volume",size:"large"},{default:o(()=>[g(c(r(a)("volume")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),l(s,{label:r(a)("feeSetting"),prop:"fee_data"},{default:o(()=>[l(O,{data:f.value,style:{width:"100%"},size:"default"},{default:o(()=>[l(_,{label:r(a)("deliveryArea")},{default:o(({row:e,$index:m})=>[v("div",Ze,[m?(x(),B("span",{key:0,onClick:S=>I("fee",m),class:"cursor-pointer"},c(e.fee_area_names?e.fee_area_names:r(a)("areaPlaceholder")),9,Ge)):(x(),B("span",Ke,c(e.fee_area_names?e.fee_area_names:r(a)("areaPlaceholder")),1))])]),_:1},8,["label"]),l(_,{label:r(U).first},{default:o(({$index:e})=>[l(i,{modelValue:f.value[e].snum,"onUpdate:modelValue":m=>f.value[e].snum=m},null,8,["modelValue","onUpdate:modelValue"])]),_:1},8,["label"]),l(_,{label:r(a)("fee")},{default:o(({$index:e})=>[l(i,{modelValue:f.value[e].sprice,"onUpdate:modelValue":m=>f.value[e].sprice=m},null,8,["modelValue","onUpdate:modelValue"])]),_:1},8,["label"]),l(_,{label:r(U).continue},{default:o(({$index:e})=>[l(i,{modelValue:f.value[e].xnum,"onUpdate:modelValue":m=>f.value[e].xnum=m},null,8,["modelValue","onUpdate:modelValue"])]),_:1},8,["label"]),l(_,{label:r(a)("continueFee")},{default:o(({$index:e})=>[l(i,{modelValue:f.value[e].xprice,"onUpdate:modelValue":m=>f.value[e].xprice=m},null,8,["modelValue","onUpdate:modelValue"])]),_:1},8,["label"]),l(_,{label:r(a)("operation"),align:"right",width:"150"},{default:o(({$index:e})=>[e?(x(),M(V,{key:0,type:"primary",onClick:m=>P("fee",e),link:""},{default:o(()=>[g(c(r(a)("delete")),1)]),_:2},1032,["onClick"])):ze("",!0)]),_:1},8,["label"])]),_:1},8,["data"]),v("div",Me,[l(V,{type:"primary",onClick:t[2]||(t[2]=e=>A("fee"))},{default:o(()=>[g(c(r(a)("addDeliveryArea")),1)]),_:1})])]),_:1},8,["label"]),l(s,{label:r(a)("freeShipping"),prop:"is_free_shipping"},{default:o(()=>[l(G,{modelValue:u.is_free_shipping,"onUpdate:modelValue":t[3]||(t[3]=e=>u.is_free_shipping=e),size:"small","inactive-value":0,"active-value":1},null,8,["modelValue"])]),_:1},8,["label"]),q(l(s,{prop:"free_shipping_data"},{default:o(()=>[l(O,{data:h.value,style:{width:"100%"},size:"default"},{default:o(()=>[l(_,{label:r(a)("freeShippingArea")},{default:o(({row:e,$index:m})=>[v("div",He,[l(i,{modelValue:e.free_shipping_area_names,"onUpdate:modelValue":S=>e.free_shipping_area_names=S,placeholder:r(a)("areaPlaceholder"),readonly:"",onClick:S=>I("free_shipping",m)},null,8,["modelValue","onUpdate:modelValue","placeholder","onClick"])])]),_:1},8,["label"]),l(_,{label:r(W)},{default:o(({$index:e})=>[l(i,{modelValue:h.value[e].free_shipping_num,"onUpdate:modelValue":m=>h.value[e].free_shipping_num=m},null,8,["modelValue","onUpdate:modelValue"])]),_:1},8,["label"]),l(_,{label:r(a)("freeShippingPrice")},{default:o(({$index:e})=>[l(i,{modelValue:h.value[e].free_shipping_price,"onUpdate:modelValue":m=>h.value[e].free_shipping_price=m},null,8,["modelValue","onUpdate:modelValue"])]),_:1},8,["label"]),l(_,{label:r(a)("operation"),align:"right",width:"150"},{default:o(({$index:e})=>[l(V,{type:"primary",onClick:m=>P("free_shipping",e),link:""},{default:o(()=>[g(c(r(a)("delete")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"])]),_:1},8,["data"]),v("div",Je,c(r(a)("freeShippingAreaTips")),1),v("div",Qe,[l(V,{type:"primary",onClick:t[4]||(t[4]=e=>A("free_shipping"))},{default:o(()=>[g(c(r(a)("addFreeShippingArea")),1)]),_:1})])]),_:1},512),[[K,u.is_free_shipping]]),l(s,{label:r(a)("noDelivery"),prop:"no_delivery"},{default:o(()=>[l(G,{modelValue:u.no_delivery,"onUpdate:modelValue":t[5]||(t[5]=e=>u.no_delivery=e),size:"small","inactive-value":0,"active-value":1},null,8,["modelValue"])]),_:1},8,["label"]),q(l(s,{prop:"no_delivery_data"},{default:o(()=>[l(O,{data:y.value,style:{width:"100%"},size:"default"},{default:o(()=>[l(_,{label:r(a)("noDelivery")},{default:o(({row:e,$index:m})=>[v("div",Xe,[l(i,{modelValue:e.no_delivery_area_names,"onUpdate:modelValue":S=>e.no_delivery_area_names=S,readonly:"",onClick:S=>I("no_delivery",m),placeholder:r(a)("areaPlaceholder")},null,8,["modelValue","onUpdate:modelValue","onClick","placeholder"])])]),_:1},8,["label"]),l(_,{label:r(a)("operation"),align:"right",width:"150"},{default:o(({$index:e})=>[l(V,{type:"primary",onClick:m=>P("no_delivery",e),link:""},{default:o(()=>[g(c(r(a)("delete")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"])]),_:1},8,["data"]),v("div",Ye,[l(V,{type:"primary",onClick:t[6]||(t[6]=e=>A("no_delivery"))},{default:o(()=>[g(c(r(a)("addNoDelivery")),1)]),_:1})])]),_:1},512),[[K,u.no_delivery]])]),_:1},8,["model","rules"])),[[_e,b.value]])]),_:1}),v("div",ea,[v("div",aa,[l(V,{type:"primary",onClick:t[7]||(t[7]=e=>oe($.value)),disabled:b.value},{default:o(()=>[g(c(r(a)("save")),1)]),_:1},8,["disabled"]),l(V,{onClick:t[8]||(t[8]=e=>Z())},{default:o(()=>[g(c(r(a)("cancel")),1)]),_:1})])])]),l(de,{modelValue:w.value,"onUpdate:modelValue":t[10]||(t[10]=e=>w.value=e),title:r(a)("selectArea"),width:"80%",class:"diy-dialog-wrap","destroy-on-close":!0,onOpened:re},{footer:o(()=>[v("span",la,[l(V,{onClick:t[9]||(t[9]=e=>w.value=!1)},{default:o(()=>[g(c(r(a)("cancel")),1)]),_:1}),l(V,{type:"primary",loading:b.value,onClick:te},{default:o(()=>[g(c(r(a)("confirm")),1)]),_:1},8,["loading"])])]),default:o(()=>[l(pe,{height:"50vh"},{default:o(()=>[l(se,{data:r(le),props:{children:"child",label:"name"},"default-expand-all":"","show-checkbox":"",ref_key:"areaTreeRef",ref:F,"default-checked-keys":r(z),"node-key":"id"},null,8,["data","default-checked-keys"])]),_:1})]),_:1},8,["modelValue","title"])],64)}}});const tl=Oe(ta,[["__scopeId","data-v-8eddc2d3"]]);export{tl as default};