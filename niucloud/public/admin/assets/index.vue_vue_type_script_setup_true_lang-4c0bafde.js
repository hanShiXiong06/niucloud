import"./base-0e92f4db.js";import{E as q}from"./el-overlay-3eff2fc5.js";/* empty css                  */import{a as z}from"./el-form-item-c2dd2ffe.js";import"./index-95d7b9b8.js";/* empty css                 */import{d as Q}from"./event-a537c4cb.js";import{t as i}from"./index-bf9b1162.js";import{g as G}from"./diy-0aeb3728.js";import{c as y}from"./cloneDeep-195867dd.js";import{a as E}from"./index-e9d9b1a1.js";import{E as H}from"./index-72686045.js";import{E as J}from"./index-8cefa3ab.js";import{E as W}from"./index-de22cd40.js";import{E as X}from"./index-e09a20f5.js";import{d as Z,c as ee,r as _,Q as N,b as d,e as m,f as p,g as le,q as t,p as a,u as s,m as I,v as U,x,F as b,t as D,n as B}from"./runtime-core.esm-bundler-67034826.js";const te=["onClick"],ae={class:"flex items-start"},oe=["onClick"],se={class:"mb-[16px]"},ne={class:"mb-[16px]"},re=p("div",{class:"text-sm text-gray-400 select-text"},"路径必须以“/”开头，例：/app/pages/index/index",-1),ie=p("div",{class:"text-sm text-gray-400 select-text"},"跳转外部链接“/”开头，例：http://www.niucloud.com",-1),ue={key:1,class:"flex flex-wrap"},de=["onClick"],pe={class:"dialog-footer"},Ne=Z({__name:"index",props:{modelValue:{type:String,default:""}},emits:["update:modelValue"],setup(P,{expose:S,emit:$}){const j=P,u=ee({get(){return j.modelValue},set(o){$("update:modelValue",o)}}),c=_(!1),v=_([]),n=_(""),h=_([]),e=_([]);G({}).then(o=>{v.value=o.data,h.value=Object.values(v.value)[0].child_list,u.value.name!=""?e.value=y(u.value):e.value={parent:Object.values(v.value)[0].name},n.value=e.value.parent});const F=()=>{u.value.name!=""&&(e.value=y(u.value),n.value=e.value.parent,n.value&&V(v.value[n.value])),c.value=!0},V=o=>{h.value=o.child_list,n.value=o.name},O=o=>{delete o.is_share,Object.assign(e.value,o)},A=()=>{u.value={name:"",parent:"",title:"",url:""}},K=()=>{if(n.value==="DIY_LINK"){if(e.value.parent=n.value,e.value.name=n.value,!e.value.title){E({message:i("diyLinkNameNotEmpty"),type:"warning"});return}if(!e.value.url){E({message:i("diyLinkUrlNotEmpty"),type:"warning"});return}}u.value=y(e.value),c.value=!1};return S({showDialog:c}),(o,r)=>{const M=N("Close"),w=H,R=N("ArrowRight"),g=J,C=W,f=z,L=X,T=q;return d(),m("div",null,[p("div",{onClick:F},[le(o.$slots,"default",{},()=>[t(g,{modelValue:s(u).title,"onUpdate:modelValue":r[0]||(r[0]=l=>s(u).title=l),placeholder:s(i)("linkPlaceholder"),readonly:""},{suffix:a(()=>[p("div",{onClick:Q(A,["stop"])},[s(u).name?(d(),I(w,{key:0},{default:a(()=>[t(M)]),_:1})):(d(),I(w,{key:1},{default:a(()=>[t(R)]),_:1}))],8,te)]),_:1},8,["modelValue","placeholder"])])]),t(T,{modelValue:c.value,"onUpdate:modelValue":r[4]||(r[4]=l=>c.value=l),title:s(i)("selectLinkTips"),width:"40%","close-on-press-escape":!1,"destroy-on-close":!0,"close-on-click-modal":!1},{footer:a(()=>[p("span",pe,[t(L,{onClick:r[3]||(r[3]=l=>c.value=!1)},{default:a(()=>[U(x(s(i)("cancel")),1)]),_:1}),t(L,{type:"primary",onClick:K},{default:a(()=>[U(x(s(i)("confirm")),1)]),_:1})])]),default:a(()=>[p("div",ae,[t(C,{class:"w-[140px] border-r",height:"360px"},{default:a(()=>[(d(!0),m(b,null,D(v.value,(l,k)=>(d(),m("div",{key:k,class:B(["h-[40px] leading-[40px] cursor-pointer hover:bg-primary-light-9 px-[10px] hover:text-primary",[l.name==n.value?"bg-primary-light-9 text-primary":""]]),onClick:Y=>V(l)},x(l.title),11,oe))),128))]),_:1}),t(C,{class:"pl-4 h-[350px] flex-1"},{default:a(()=>[n.value=="DIY_LINK"?(d(),m(b,{key:0},[p("div",se,[t(f,{label:s(i)("diyLinkName")},{default:a(()=>[t(g,{modelValue:e.value.title,"onUpdate:modelValue":r[1]||(r[1]=l=>e.value.title=l),placeholder:s(i)("diyLinkNamePlaceholder"),class:"w-6/12"},null,8,["modelValue","placeholder"])]),_:1},8,["label"])]),p("div",ne,[t(f,{label:s(i)("diyLinkUrl")},{default:a(()=>[t(g,{modelValue:e.value.url,"onUpdate:modelValue":r[2]||(r[2]=l=>e.value.url=l),placeholder:s(i)("diyLinkUrlPlaceholder"),class:"w-6/12"},null,8,["modelValue","placeholder"])]),_:1},8,["label"])]),t(f,{label:" "},{default:a(()=>[re]),_:1}),t(f,{label:" "},{default:a(()=>[ie]),_:1})],64)):(d(),m("div",ue,[(d(!0),m(b,null,D(h.value,(l,k)=>(d(),m("div",{key:k,class:B(["border border-br rounded-[3px] mr-[10px] mb-[10px] px-4 h-[32px] leading-[32px] cursor-pointer hover:bg-primary-light-9 px-[10px] hover:text-primary",[l.name==e.value.name?"border-primary text-primary":""]]),onClick:Y=>O(l)},x(l.title),11,de))),128))]))]),_:1})])]),_:1},8,["modelValue","title"])])}}});export{Ne as _};
