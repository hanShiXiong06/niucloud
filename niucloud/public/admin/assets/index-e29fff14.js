import{e as d,u as a,E as R}from"./index-9670e877.js";import{b as T,f as U,g,j as n,h as D,r as $,u as p,l as V,m as u,D as L,E as c,n as O,v as H,L as v,T as b,t as m,K as I,M as K,_ as j,ch as z,y as F}from"./base-45eb5090.js";import{x as f}from"./index-341914e3.js";const M=T({trigger:d.trigger,placement:f.placement,disabled:d.disabled,visible:a.visible,transition:a.transition,popperOptions:f.popperOptions,tabindex:f.tabindex,content:a.content,popperStyle:a.popperStyle,popperClass:a.popperClass,enterable:{...a.enterable,default:!0},effect:{...a.effect,default:"light"},teleported:a.teleported,title:String,width:{type:[String,Number],default:150},offset:{type:Number,default:void 0},showAfter:{type:Number,default:0},hideAfter:{type:Number,default:200},autoClose:{type:Number,default:0},showArrow:{type:Boolean,default:!0},persistent:{type:Boolean,default:!0},"onUpdate:visible":{type:Function}}),q={"update:visible":t=>U(t),"before-enter":()=>!0,"before-leave":()=>!0,"after-enter":()=>!0,"after-leave":()=>!0},G="onUpdate:visible",J=g({name:"ElPopover"}),Q=g({...J,props:M,emits:q,setup(t,{expose:r,emit:s}){const o=t,w=n(()=>o[G]),l=D("popover"),i=$(),y=n(()=>{var e;return(e=p(i))==null?void 0:e.popperRef}),E=n(()=>[{width:V(o.width)},o.popperStyle]),P=n(()=>[l.b(),o.popperClass,{[l.m("plain")]:!!o.content}]),C=n(()=>o.transition===`${l.namespace.value}-fade-in-linear`),k=()=>{var e;(e=i.value)==null||e.hide()},S=()=>{s("before-enter")},B=()=>{s("before-leave")},N=()=>{s("after-enter")},A=()=>{s("update:visible",!1),s("after-leave")};return r({popperRef:y,hide:k}),(e,_)=>(u(),L(p(R),K({ref_key:"tooltipRef",ref:i},e.$attrs,{trigger:e.trigger,placement:e.placement,disabled:e.disabled,visible:e.visible,transition:e.transition,"popper-options":e.popperOptions,tabindex:e.tabindex,content:e.content,offset:e.offset,"show-after":e.showAfter,"hide-after":e.hideAfter,"auto-close":e.autoClose,"show-arrow":e.showArrow,"aria-label":e.title,effect:e.effect,enterable:e.enterable,"popper-class":p(P),"popper-style":p(E),teleported:e.teleported,persistent:e.persistent,"gpu-acceleration":p(C),"onUpdate:visible":p(w),onBeforeShow:S,onBeforeHide:B,onShow:N,onHide:A}),{content:c(()=>[e.title?(u(),O("div",{key:0,class:H(p(l).e("title")),role:"title"},v(e.title),3)):b("v-if",!0),m(e.$slots,"default",{},()=>[I(v(e.content),1)])]),default:c(()=>[e.$slots.reference?m(e.$slots,"reference",{key:0}):b("v-if",!0)]),_:3},16,["trigger","placement","disabled","visible","transition","popper-options","tabindex","content","offset","show-after","hide-after","auto-close","show-arrow","aria-label","effect","enterable","popper-class","popper-style","teleported","persistent","gpu-acceleration","onUpdate:visible"]))}});var W=j(Q,[["__file","/home/runner/work/element-plus/element-plus/packages/components/popover/src/popover.vue"]]);const h=(t,r)=>{const s=r.arg||r.value,o=s==null?void 0:s.popperRef;o&&(o.triggerRef=t)};var X={mounted(t,r){h(t,r)},updated(t,r){h(t,r)}};const Y="popover",Z=z(X,Y),oe=F(W,{directive:Z});export{oe as E,Z as a};