/* empty css             */import{E as Q}from"./el-overlay-380df695.js";import{a as X,E as Y}from"./el-form-item-144bc604.js";/* empty css                 *//* empty css               *//* empty css                  *//* empty css                  */import"./index-596de8a9.js";/* empty css                  *//* empty css                  */import{v as _}from"./event-3e082a4a.js";import{t as i}from"./index-6b4cbbe4.js";import{c as Z,a as ee}from"./common-a45d855f.js";import{a as te}from"./vue-router-9f815af7.js";import{b as le,c as ae,d as oe}from"./diy-c85eb989.js";import{a as O}from"./index-a6ffcea0.js";import{E as ie}from"./index-5f2625ed.js";import{E as se}from"./index-6f670765.js";import{a as pe,E as re}from"./index-b7b91fcb.js";import{d as ne,M as N,r as C,w as $,b as c,e as w,f as n,F as P,t as E,n as S,x as d,L as v,u as r,q as s,p as m,v as g,C as F,m as T}from"./runtime-core.esm-bundler-c17ab5bc.js";import{_ as me}from"./_plugin-vue_export-helper-c27b6911.js";import"./index-93efb1b5.js";import"./index-9ef6974e.js";import"./plugin-vue_export-helper-c018272e.js";import"./focus-trap-c0148071.js";import"./index-4b62c73a.js";import"./index-332680c2.js";import"./_baseClone-37ba2e68.js";import"./index-0d36ccbf.js";import"./el-main-03d12282.js";import"./index-e42600b8.js";import"./index-138bfa06.js";import"./index-72bf6cb5.js";import"./index-5c20ff8f.js";import"./strings-e72e60f7.js";import"./isEqual-ca98cf0c.js";import"./debounce-196ce6a6.js";import"./index-bfecf17f.js";import"./validator-f5e079db.js";const de={class:"flex flex-wrap"},ue={class:"absolute top-[46px] left-[50%] translate-x-[-50%] text-[14px] truncate w-[130px] text-center"},fe={class:"w-[282px] h-[493px] mx-auto"},ce=["id","src"],_e={class:"w-[282px] h-[493px] mx-auto bg-body pt-[20px] px-[20px]"},ve={class:"font-bold text-xl mb-[40px]"},ge={class:"mb-[20px] flex flex-col"},xe={class:"mb-[10px]"},we={class:"overflow-hidden w-[282px] h-[493px] mx-auto"},ye=["src"],he={class:"text-[12px] text-[#999] mt-[10px] mx-auto truncate text-center w-[250px]"},be={class:"item-btn-box absolute top-[50%] left-[50%] translate-x-[-50%] translate-y-[-50%] flex flex-col flex-wrap"},De={class:"text-primary px-[5px]"},Ve={class:"mt-[10px]"},Pe={class:"dialog-footer"},ke=ne({__name:"index",setup(Ce){const a=N({}),h=C(!1),I=te(),f=C("template"),b=C(""),t=N({type:"",mode:"",template:"",id:"",page:"",title:"",action:""}),L=()=>{t.type="",t.mode="",t.template="",t.id="",t.page="",t.title="",t.action="",le({}).then(l=>{for(let e in l.data)a[e]=l.data[e];for(let e in a)a[e].use_template.url&&(a[e].loadingIframe=!1,a[e].loadingDev=!1,a[e].isDisabledPop=!1,a[e].difference=0,b.value=a[e].domain_url.wap_domain,a[e].wapUrl=a[e].domain_url.wap_url,B(e))})};L(),window.addEventListener("message",l=>{try{let p=JSON.parse(l.data);if(["appOnLaunch","appOnReady"].indexOf(p.type)!=-1)for(let u in a){a[u].loadingDev=!1,a[u].loadingIframe=!0;var e=new Date().getTime();a[u].difference=e-a[u].timeIframe,a[u].isDisabledPop=!1}}catch(p){for(let u in a)M(u);console.log("后台接受数据错误",p)}},!1);const R=l=>{var e=JSON.stringify({type:"appOnReady",message:"加载完成"});window["previewIframe_"+l]&&window["previewIframe_"+l].contentWindow.postMessage(e,"*")},M=l=>{a[l].loadingDev=!0,a[l].isDisabledPop=!0,a[l].loadingIframe=!1},q=()=>{if(b.value.trim().length==0){O({type:"warning",message:`${i("wapDomainPlaceholder")}`});return}let l=b.value+"/wap";ee.set({key:"wap_domain",data:l});for(let e in a)a[e].use_template.url&&(a[e].wapUrl=l,B(e));setTimeout(()=>{for(let e in a)a[e].use_template.url&&(a[e].loadingIframe=!0,a[e].loadingDev=!1,a[e].isDisabledPop=!1)},100*3)},B=l=>{a[l].use_template.wapPreview=a[l].wapUrl+a[l].use_template.url,a[l].timeIframe=new Date().getTime(),R(l),setTimeout(()=>{a[l].difference==0&&M(l)},1e3*2)},J=(l,e)=>{h.value=!0,f.value=e.use_template.hope,t.type=l,t.mode=e.use_template.mode,t.action=e.use_template.action,f.value=="template"?t.template=e.use_template.template:f.value=="diy"?t.id=e.use_template.id:f.value=="other"&&(t.page=e.use_template.page,t.title=e.use_template.title)},j=l=>{let e={back:"/website/diy/index"};l.id?e.id=l.id:l.name?e.name=l.name:l.url&&(e.url=l.url);let p=I.resolve({path:"/decorate/edit",query:e});window.open(p.href)},z=l=>{let e=l.page;l.url?e=l.url:l.id&&(e+="?id="+l.id);let p=I.resolve({path:"/preview/wap",query:{page:e}});window.open(p.href)},A=l=>{let e=I.resolve({path:"/website/diy/list"});window.open(e.href)},W=()=>{ae({type:t.type}).then(l=>{let e=!0;for(let p=0;p<l.data.length;p++)if(t.id==l.data[p].id){e=!1;break}e&&(t.id=""),a[t.type].my_page={},Object.assign(a[t.type].my_page,l.data)})};$(()=>f.value,(l,e)=>{l=="template"?(t.id="",t.page="",t.action="decorate"):l=="diy"?(t.mode="diy",t.template="",t.page="",t.action="decorate"):l=="other"&&(t.mode="other",t.template="",t.id="")}),$(()=>t.template,(l,e)=>{l&&(t.mode=a[t.type].template[l].mode)}),$(()=>t.page,(l,e)=>{if(l){for(let p=0;p<a[t.type].other_page.length;p++)if(a[t.type].other_page[p].page==l){t.title=a[t.type].other_page[p].title,t.action=a[t.type].other_page[p].action;break}}});const U=C(!1),G=()=>{if(f.value=="template"){if(t.template==""){O({type:"warning",message:`${i("placeholderTemplate")}`});return}}else if(f.value=="diy"&&t.id==""){O({type:"warning",message:`${i("placeholderMyPage")}`});return}U.value||(U.value=!0,oe({...t}).then(l=>{U.value=!1,h.value=!1,L()}))};return(l,e)=>{const p=ie,u=se,D=X,y=pe,k=re,H=Y,K=Q;return c(),w(P,null,[n("div",de,[(c(!0),w(P,null,E(a,(o,x)=>(c(),w("div",{class:S(["page-item relative bg-no-repeat ml-[20px] mr-[40px] mt-[20px] bg-[#f7f7f7] w-[300px] pt-[80px] pb-[20px]",{"cursor-pointer":!o.isDisabledPop}]),key:x},[n("p",ue,d(o.use_template.title),1),v(n("div",fe,[v(n("iframe",{id:"previewIframe_"+x,class:"w-[282px] h-[493px] mx-auto",src:o.use_template.wapPreview,frameborder:"0"},null,8,ce),[[_,o.loadingIframe]]),v(n("div",_e,[n("div",ve,d(r(i)("developTitle")),1),n("div",ge,[n("text",xe,d(r(i)("wapDomain")),1),s(p,{modelValue:b.value,"onUpdate:modelValue":e[0]||(e[0]=V=>b.value=V),placeholder:r(i)("wapDomainPlaceholder"),clearable:""},null,8,["modelValue","placeholder"])]),s(u,{type:"primary",onClick:e[1]||(e[1]=V=>q())},{default:m(()=>[g(d(r(i)("confirm")),1)]),_:1})],512),[[_,o.loadingDev]])],512),[[_,o.use_template.url]]),v(n("div",we,[o.use_template.cover?(c(),w("img",{key:0,class:"max-w-full",src:r(Z)(o.use_template.cover)},null,8,ye)):F("",!0)],512),[[_,!o.use_template.wapPreview]]),n("p",he,d(o.use_template.desc),1),n("div",{class:S(["item-hide absolute inset-x-0 inset-y-0 bg-black bg-opacity-50 text-center",{disabled:o.isDisabledPop}])},[n("div",be,[s(u,{onClick:V=>J(x,o)},{default:m(()=>[g(d(r(i)("changePage")),1)]),_:2},1032,["onClick"]),v(s(u,{onClick:V=>j(o.use_template)},{default:m(()=>[g(d(r(i)("decorate")),1)]),_:2},1032,["onClick"]),[[_,o.use_template.mode!="other"||o.use_template.action=="decorate"]]),s(u,{onClick:V=>z(o.use_template)},{default:m(()=>[g(d(r(i)("preview")),1)]),_:2},1032,["onClick"])])],2)],2))),128))]),s(K,{modelValue:h.value,"onUpdate:modelValue":e[7]||(e[7]=o=>h.value=o),title:r(i)("changeTemplate"),width:"400px","close-on-press-escape":!1,"destroy-on-close":!0,"close-on-click-modal":!1},{footer:m(()=>[n("span",Pe,[s(u,{onClick:e[6]||(e[6]=o=>h.value=!1)},{default:m(()=>[g(d(r(i)("cancel")),1)]),_:1}),s(u,{type:"primary",onClick:G},{default:m(()=>[g(d(r(i)("confirm")),1)]),_:1})])]),default:m(()=>[t.type?(c(),T(H,{key:0,model:l.form,"label-width":"0px"},{default:m(()=>[s(D,{label:""},{default:m(()=>[n("div",null,[g(d(r(i)("hopeBeforeTip")),1),n("span",De,d(a[t.type].title),1),g(d(r(i)("hopeAfterTip")),1)])]),_:1}),s(D,{label:""},{default:m(()=>[s(k,{modelValue:f.value,"onUpdate:modelValue":e[2]||(e[2]=o=>f.value=o),class:"w-full"},{default:m(()=>[s(y,{label:r(i)("changeTemplateTip")+" "+a[t.type].title+" "+r(i)("template"),value:"template"},null,8,["label"]),s(y,{label:r(i)("changeMyPageTip")+" "+a[t.type].title,value:"diy"},null,8,["label"]),s(y,{label:r(i)("changeOtherPageTip")+" "+a[t.type].title,value:"other"},null,8,["label"])]),_:1},8,["modelValue"])]),_:1}),v(s(D,{label:""},{default:m(()=>[s(k,{modelValue:t.template,"onUpdate:modelValue":e[3]||(e[3]=o=>t.template=o),class:"w-full"},{default:m(()=>[(c(!0),w(P,null,E(a[t.type].template,(o,x)=>(c(),T(y,{label:o.title,value:x},null,8,["label","value"]))),256))]),_:1},8,["modelValue"])]),_:1},512),[[_,f.value=="template"]]),v(s(D,{label:""},{default:m(()=>[s(k,{modelValue:t.id,"onUpdate:modelValue":e[4]||(e[4]=o=>t.id=o),class:"w-full"},{default:m(()=>[(c(!0),w(P,null,E(a[t.type].my_page,(o,x)=>(c(),T(y,{label:o.title,value:o.id},null,8,["label","value"]))),256))]),_:1},8,["modelValue"]),n("div",Ve,[n("span",{class:"cursor-pointer text-primary mr-[10px]",onClick:A},d(r(i)("createPage")),1),n("span",{class:"cursor-pointer text-primary",onClick:W},d(r(i)("refreshPage")),1)])]),_:1},512),[[_,f.value=="diy"]]),v(s(D,{label:""},{default:m(()=>[s(k,{modelValue:t.page,"onUpdate:modelValue":e[5]||(e[5]=o=>t.page=o),class:"w-full"},{default:m(()=>[(c(!0),w(P,null,E(a[t.type].other_page,(o,x)=>(c(),T(y,{label:o.title,value:o.page},null,8,["label","value"]))),256))]),_:1},8,["modelValue"])]),_:1},512),[[_,f.value=="other"]])]),_:1},8,["model"])):F("",!0)]),_:1},8,["modelValue","title"])],64)}}});const ft=me(ke,[["__scopeId","data-v-e52bfba5"]]);export{ft as default};
