import{E as ue}from"./base-962c0c23.js";/* empty css                *//* empty css                     *//* empty css                  *//* empty css                        *//* empty css                 */import"./el-tooltip-58212670.js";import{h as _e,B as fe,d as ve}from"./index-7525671c.js";/* empty css                  *//* empty css                */import{_ as ge}from"./edit-article-b23217e0.js";import{_ as xe}from"./edit-graphic-nav-6f4e54e7.js";import{_ as be}from"./edit-horz-blank-a27f1e70.js";import{_ as Ce}from"./edit-image-ads-f69cdf8b.js";import{_ as he}from"./edit-member-info-cc4c293b.js";import{_ as ye}from"./edit-page-a080ef64.js";import{_ as we}from"./edit-text-6ed236b2.js";import{v as W}from"./event-ff03ec12.js";import{t as a}from"./index-105fbad0.js";import{i as ke,e as Ee,a as Ve}from"./diy-e259258a.js";import{u as Be,a as Se}from"./vue-router-79053937.js";import{u as Te}from"./diy-f6b4263e.js";import{a as ze}from"./storage-abe718b1.js";import{c as De}from"./cloneDeep-b538bbcc.js";import{E as qe}from"./index-50a00d09.js";import{E as Ne}from"./index-bba9e58c.js";import{a as Oe,E as Ie}from"./index-7facdb7a.js";import{E as Re}from"./index-7a123a20.js";import{E as Ue}from"./index-8bcaafa6.js";import{E as $e}from"./index-93f2c618.js";import{a as Pe,E as Je}from"./index-61c777fa.js";import{E as je}from"./index-3220fc45.js";import{E as Fe}from"./index-69523418.js";import{d as Le,r as _,M as G,af as v,w as Me,Q as Ae,b as u,e as N,q as l,p as i,f as n,x as c,u as t,v as U,F as X,t as Y,m as f,L as Z,n as K,U as He,C as x,au as Qe,av as We}from"./runtime-core.esm-bundler-dc7a07d7.js";import{_ as Ge}from"./_plugin-vue_export-helper-c27b6911.js";import"./el-overlay-60700377.js";import"./index-5d86eb33.js";import"./focus-trap-b8b5a003.js";import"./el-radio-bfd4b1ad.js";import"./el-avatar-3bb47ce2.js";import"./index-d57cc47d.js";/* empty css                   *//* empty css                      *//* empty css               *//* empty css                  *//* empty css                  *//* empty css                        *//* empty css                        *//* empty css                    */import"./article-6bb0e548.js";import"./index-92e1b5d5.js";import"./_Uint8Array-6ff3cafa.js";import"./_initCloneObject-28e6bdaa.js";import"./index-df51d91a.js";import"./isEqual-c7d5e6d9.js";import"./flatten-d5d1dc97.js";import"./_isIterateeCall-c579b126.js";import"./index-190f0dba.js";import"./index-100b1469.js";import"./index-b933df38.js";import"./index-4f5c40a5.js";import"./strings-4868a118.js";import"./directive-c0c3e9a3.js";import"./index.vue_vue_type_script_setup_true_lang-12727745.js";import"./index-f4731ae1.js";import"./index.vue_vue_type_style_index_0_lang-d0d31f1b.js";import"./attachment-56217309.js";/* empty css                 *//* empty css                    *//* empty css                   */import"./index-cc03da8f.js";import"./sys-4abedf95.js";import"./index-a9dd5cf5.js";import"./index-9f244af6.js";import"./sortable.esm-be94e56d.js";import"./range-5a416794.js";import"./common-080b9b9f.js";import"./common-2cf17469.js";import"./index-fb71f009.js";import"./index-179d7e6f.js";const Xe=V=>(Qe("data-v-549baf41"),V=V(),We(),V),Ye={class:"main-container flex-1"},Ze={class:"pl-[5px]"},Ke={class:"text-white ml-[10px] flex items-center"},et={class:"mr-[5px]"},tt=Xe(()=>n("div",{class:"flex-1"},null,-1)),ot={class:"full-container flex flex-row flex-1 bg-page"},lt={class:"component-list w-[290px]"},nt={class:"flex flex-row flex-wrap"},at=["title","onClick"],it={class:"block text-base truncate"},st={class:"preview-wrap flex-1 relative mt-[20px]"},rt={class:"diy-view-wrap w-[375px] shadow-lg mx-auto"},pt={class:"text-base block text-center truncate cursor-pointer h-[64px] leading-[84px]"},mt={class:"preview-block relative"},dt={class:"quick-action absolute text-center -right-[70px] top-[20px] w-[42px] rounded shadow-md"},ct=["src"],ut={class:"preview-iframe w-[375px] pt-[20px] px-[20px]"},_t={class:"font-bold text-xl mb-[40px]"},ft={class:"mb-[20px] flex flex-col"},vt={class:"mb-[10px]"},gt={class:"edit-attribute-wrap w-[400px]"},xt={class:"card-header flex justify-between items-center"},bt={class:"title flex-1"},Ct={class:"tab-wrap flex rounded-[50px] bg-gray-100 text-[14px]"},ht={class:"edit-component-wrap"},yt={class:"edit-attr-item-wrap"},wt={class:"mb-[10px]"},kt={class:"text-sm text-gray-400"},Et=Le({__name:"edit",setup(V){const e=Te(),p=Be(),O=Se(),B=_(""),S=_(""),T=_(""),z=_(!1),D=_(!1),ee=_(0),I=p.query.back||"/diy/list",h=_([]),$=G([]),P=_(""),J=_(0),j=_($),te=r=>{};p.query.id=p.query.id||0,p.query.name=p.query.name||"",p.query.template=p.query.template||"",p.query.template_name=p.query.template_name||"",p.query.title=p.query.title||"";const y=G({id:e.id,name:e.name,title:e.global.title,value:JSON.stringify({global:v(e.global),value:v(e.value)})}),F=_(!0),oe=()=>{F.value?O.push(I):qe.confirm(a("leavePageTitleTips"),a("leavePageContentTips"),{confirmButtonText:a("confirm"),cancelButtonText:a("cancel"),type:"warning",autofocus:!1}).then(()=>{O.push(I)}).catch(()=>{})},le=Object.assign({"./components/edit-article.vue":ge,"./components/edit-graphic-nav.vue":xe,"./components/edit-horz-blank.vue":be,"./components/edit-image-ads.vue":Ce,"./components/edit-member-info.vue":he,"./components/edit-page.vue":ye,"./components/edit-text.vue":we}),L={};for(const[r,o]of Object.entries(le)){const b=r.replace(/^\.\/(.*)\.\w+$/,"$1").split("/")[1];L[b]=o.default}Me(()=>e,(r,o)=>{let d={id:r.id,name:r.name,title:r.global.title,value:JSON.stringify({global:v(r.global),value:v(r.value)})};e.postMessage(),F.value=JSON.stringify(d)==JSON.stringify(y)},{deep:!0}),ke({id:p.query.id,name:p.query.name,template:p.query.template,template_name:p.query.template_name,title:p.query.title}).then(r=>{let o=r.data;if(e.init(),e.id=o.id||0,e.name=o.name,e.type=o.type,e.typeName=o.type_name,e.isDefault=o.is_default,o.value){let d=JSON.parse(o.value);e.global=d.global,d.value.length&&(e.value=d.value)}else e.global.title=o.title;y.id=e.id,y.name=e.name,y.title=e.global.title,y.value=JSON.stringify({global:v(e.global),value:v(e.value)}),h.value=o.component;for(let d in h.value){$.push(d);for(let b in h.value[d].list){let m=De(h.value[d].list[b]);m.id=e.generateRandom(),m.componentName=b,m.componentTitle=m.title,Object.assign(m,m.value),delete m.name,delete m.title,delete m.value,delete m.type,delete m.icon,e.components.push(m)}}S.value=o.domain_url.wap_domain,B.value=o.domain_url.wap_url,P.value=o.page,J.value=o.site_id,M()}),window.addEventListener("message",r=>{try{let o=JSON.parse(r.data);if(!o.type)return;switch(o.type){case"init":e.load=!0,e.postMessage();break;case"change":e.changeCurrentIndex(o.index,o.component);break;case"data":e.changeCurrentIndex(o.index,o.component),e.global=o.global,e.value=o.value;break}}catch(o){console.log("后台接受数据错误",o)}},!1);const ne=()=>{B.value=S.value+"/wap",M(),ze.set({key:"wap_domain",data:B.value}),z.value=!0,D.value=!1},M=()=>{T.value=`${B.value}/${P.value}?mode=decorate&site_id=${J.value}`},ae=()=>{if(T.value){var r=new Date().getTime(),o=r-ee.value;o<1e3?(D.value=!0,z.value=!1,T.value=""):(D.value=!1,z.value=!0)}},w=_(!1),ie=()=>{if(!e.verify()||w.value)return;w.value=!0;let r={id:e.id,name:e.name,title:e.global.title,type:e.type,is_default:e.isDefault,value:JSON.stringify({global:v(e.global),value:v(e.value)})};(e.id?Ee:Ve)(r).then(d=>{w.value=!1,d.code==1&&(e.id?w.value=!1:O.push(I))}).catch(d=>{w.value=!1})};return(r,o)=>{const d=Ae("ArrowLeft"),b=ue,m=Ne,se=_e,C=fe,re=Oe,pe=Ie,R=Re,k=Ue,me=$e,A=ve,g=Pe,E=je,de=Je,ce=Fe;return u(),N("div",Ye,[l(se,{class:"flex items-center h-[60px] bg-primary px-[20px]"},{default:i(()=>[n("div",{class:"text-white cursor-pointer flex items-center",onClick:oe},[l(b,{size:"14"},{default:i(()=>[l(d)]),_:1}),n("span",Ze,c(t(a)("back")),1)]),n("div",Ke,[n("span",et," ｜ "+c(t(a)("decorating"))+"："+c(t(e).typeName),1)]),tt,l(m,{onClick:o[0]||(o[0]=s=>ie())},{default:i(()=>[U(c(t(a)("save")),1)]),_:1})]),_:1}),n("div",ot,[n("div",lt,[l(R,{class:"px-[10px]"},{default:i(()=>[l(pe,{modelValue:j.value,"onUpdate:modelValue":o[1]||(o[1]=s=>j.value=s),onChange:te},{default:i(()=>[(u(!0),N(X,null,Y(h.value,(s,H)=>(u(),f(re,{key:H,title:s.title,name:H},{default:i(()=>[n("ul",nt,[(u(!0),N(X,null,Y(s.list,(q,Q)=>(u(),N("li",{key:Q,class:"w-2/6 text-center cursor-pointer h-[75px]",title:q.title,onClick:Vt=>t(e).addComponent(Q,q)},[l(C,{name:q.icon,size:"23px"},null,8,["name"]),n("span",it,c(q.title),1)],8,at))),128))])]),_:2},1032,["title","name"]))),128))]),_:1},8,["modelValue"])]),_:1})]),n("div",st,[l(R,null,{default:i(()=>[l(m,{class:"page-btn absolute right-[20px]",onClick:o[2]||(o[2]=s=>t(e).changeCurrentIndex(-99))},{default:i(()=>[U(c(t(a)("pageSet")),1)]),_:1}),n("div",rt,[n("div",{class:"preview-head bg-no-repeat bg-center bg-cover",onClick:o[3]||(o[3]=s=>t(e).changeCurrentIndex(-99))},[n("span",pt,c(t(e).global.title),1)]),n("div",mt,[n("ul",dt,[l(k,{effect:"light",content:t(a)("moveUpComponent"),placement:"right"},{default:i(()=>[l(C,{name:"iconfont-iconjiantoushang",size:"20px",class:"block cursor-pointer leading-[40px]",onClick:t(e).moveUpComponent},null,8,["onClick"])]),_:1},8,["content"]),l(k,{effect:"light",content:t(a)("moveDownComponent"),placement:"right"},{default:i(()=>[l(C,{name:"iconfont-iconjiantouxia",size:"20px",class:"block cursor-pointer leading-[40px]",onClick:t(e).moveDownComponent},null,8,["onClick"])]),_:1},8,["content"]),l(k,{effect:"light",content:t(a)("copyComponent"),placement:"right"},{default:i(()=>[l(C,{name:"iconfont-iconcopy-line",size:"20px",class:"block cursor-pointer leading-[40px]",onClick:t(e).copyComponent},null,8,["onClick"])]),_:1},8,["content"]),l(k,{effect:"light",content:t(a)("delComponent"),placement:"right"},{default:i(()=>[l(C,{name:"iconfont-icondelete-line",size:"20px",class:"block cursor-pointer leading-[40px]",onClick:t(e).delComponent},null,8,["onClick"])]),_:1},8,["content"]),l(k,{effect:"light",content:t(a)("resetComponent"),placement:"right"},{default:i(()=>[l(C,{name:"iconfont-iconloader-line",size:"20px",class:"block cursor-pointer leading-[40px]",onClick:t(e).resetComponent},null,8,["onClick"])]),_:1},8,["content"])]),Z(n("iframe",{id:"previewIframe",src:T.value,frameborder:"0",class:"preview-iframe w-[375px]",onLoad:ae},null,40,ct),[[W,z.value]]),Z(n("div",ut,[n("div",_t,c(t(a)("developTitle")),1),n("div",ft,[n("text",vt,c(t(a)("wapDomain")),1),l(me,{modelValue:S.value,"onUpdate:modelValue":o[4]||(o[4]=s=>S.value=s),placeholder:t(a)("wapDomainPlaceholder"),clearable:""},null,8,["modelValue","placeholder"])]),l(m,{type:"primary",onClick:ne},{default:i(()=>[U(c(t(a)("confirm")),1)]),_:1})],512),[[W,D.value]])])])]),_:1})]),n("div",gt,[l(R,null,{default:i(()=>[l(ce,{class:"box-card",shadow:"never"},{header:i(()=>[n("div",xt,[n("span",bt,c(t(e).currentIndex==-99?t(a)("pageSet"):t(e).editComponent.componentTitle),1),n("div",Ct,[n("span",{class:K(["cursor-pointer rounded-[50px] py-[5px] px-[15px]",{"bg-primary text-white":t(e).editTab=="content"}]),onClick:o[5]||(o[5]=s=>t(e).editTab="content")},c(t(a)("tabEditContent")),3),n("span",{class:K(["cursor-pointer rounded-[50px] py-[5px] px-[15px]",{"bg-primary text-white":t(e).editTab=="style"}]),onClick:o[6]||(o[6]=s=>t(e).editTab="style")},c(t(a)("tabEditStyle")),3)])])]),default:i(()=>[n("div",ht,[(u(),f(He(L[t(e).currentComponent]),{value:t(e).value[t(e).currentIndex]},{style:i(()=>[n("div",yt,[n("h3",wt,c(t(a)("componentStyleTitle")),1),l(de,{"label-width":"80px",class:"px-[10px]"},{default:i(()=>[t(e).editComponent.ignore.indexOf("pageBgColor")==-1?(u(),f(g,{key:0,label:t(a)("bottomBgColor"),class:"display-block"},{default:i(()=>[l(A,{modelValue:t(e).editComponent.pageBgColor,"onUpdate:modelValue":o[7]||(o[7]=s=>t(e).editComponent.pageBgColor=s),"show-alpha":"",predefine:t(e).predefineColors},null,8,["modelValue","predefine"]),n("div",kt,c(t(a)("bottomBgTips")),1)]),_:1},8,["label"])):x("",!0),t(e).editComponent.ignore.indexOf("componentBgColor")==-1?(u(),f(g,{key:1,label:t(a)("componentBgColor")},{default:i(()=>[l(A,{modelValue:t(e).editComponent.componentBgColor,"onUpdate:modelValue":o[8]||(o[8]=s=>t(e).editComponent.componentBgColor=s),"show-alpha":"",predefine:t(e).predefineColors},null,8,["modelValue","predefine"])]),_:1},8,["label"])):x("",!0),t(e).editComponent.ignore.indexOf("marginTop")==-1?(u(),f(g,{key:2,label:t(a)("marginTop")},{default:i(()=>[l(E,{modelValue:t(e).editComponent.margin.top,"onUpdate:modelValue":o[9]||(o[9]=s=>t(e).editComponent.margin.top=s),"show-input":"",size:"small",min:0,class:"ml-[10px] horz-blank-slider"},null,8,["modelValue"])]),_:1},8,["label"])):x("",!0),t(e).editComponent.ignore.indexOf("marginBottom")==-1?(u(),f(g,{key:3,label:t(a)("marginBottom")},{default:i(()=>[l(E,{modelValue:t(e).editComponent.margin.bottom,"onUpdate:modelValue":o[10]||(o[10]=s=>t(e).editComponent.margin.bottom=s),"show-input":"",size:"small",class:"ml-[10px] horz-blank-slider"},null,8,["modelValue"])]),_:1},8,["label"])):x("",!0),t(e).editComponent.ignore.indexOf("marginBoth")==-1?(u(),f(g,{key:4,label:t(a)("marginBoth")},{default:i(()=>[l(E,{modelValue:t(e).editComponent.margin.both,"onUpdate:modelValue":o[11]||(o[11]=s=>t(e).editComponent.margin.both=s),"show-input":"",size:"small",class:"ml-[10px] horz-blank-slider"},null,8,["modelValue"])]),_:1},8,["label"])):x("",!0),t(e).editComponent.ignore.indexOf("topRounded")==-1?(u(),f(g,{key:5,label:t(a)("topRounded")},{default:i(()=>[l(E,{modelValue:t(e).editComponent.topRounded,"onUpdate:modelValue":o[12]||(o[12]=s=>t(e).editComponent.topRounded=s),"show-input":"",size:"small",class:"ml-[10px] horz-blank-slider",max:50},null,8,["modelValue"])]),_:1},8,["label"])):x("",!0),t(e).editComponent.ignore.indexOf("bottomRounded")==-1?(u(),f(g,{key:6,label:t(a)("bottomRounded")},{default:i(()=>[l(E,{modelValue:t(e).editComponent.bottomRounded,"onUpdate:modelValue":o[13]||(o[13]=s=>t(e).editComponent.bottomRounded=s),"show-input":"",size:"small",class:"ml-[10px] horz-blank-slider",max:50},null,8,["modelValue"])]),_:1},8,["label"])):x("",!0)]),_:1})])]),_:1},8,["value"]))])]),_:1})]),_:1})])])])}}});const el=Ge(Et,[["__scopeId","data-v-549baf41"]]);export{el as default};
