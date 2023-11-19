import{b as G,t as X,d as re,E as ce,a7 as qe,e as Fe,a8 as se,a9 as ye,C as Ve,s as He,j as Ue,N as $e,aa as Ge}from"./index-72686045.js";import{u as T,_ as K,w as Ie,a as de}from"./base-0e92f4db.js";import{d as A,r as k,E as ue,o as pe,b as g,e as $,g as w,n as E,u as P,D as te,Y as Ke,f as U,m as ne,p as j,U as Je,x as ke,K as me,c as m,Q as ee,q as Z,L as Be,y as Ne,C as V,a1 as Qe,M as Ee,w as ae,J as Pe,P as M,j as H,F as Se,a as Ye,k as Ze,A as Xe,$ as et,v as tt}from"./runtime-core.esm-bundler-67034826.js";import{d as nt,v as Oe,T as Ae,t as ie,e as ot}from"./event-a537c4cb.js";import{E as st,f as at}from"./index-defed8ff.js";import{d as lt,a as rt,u as ut}from"./el-overlay-3eff2fc5.js";import{E as it}from"./focus-trap-83769a43.js";import{u as Te}from"./index-d87ae4a2.js";import{u as ct,E as O}from"./index-6cae7119.js";import{t as le}from"./aria-adfa05c5.js";import{_ as dt}from"./index-97d638b4.js";import{E as ze}from"./index-ef31373f.js";import{T as pt}from"./index-e09a20f5.js";import{m as mt}from"./index-8cefa3ab.js";const _e=Symbol("breadcrumbKey"),ft=G({separator:{type:String,default:"/"},separatorIcon:{type:X}}),vt=A({name:"ElBreadcrumb"}),bt=A({...vt,props:ft,setup(e){const n=e,t=T("breadcrumb"),r=k();return ue(_e,n),pe(()=>{const o=r.value.querySelectorAll(`.${t.e("item")}`);o.length&&o[o.length-1].setAttribute("aria-current","page")}),(o,v)=>(g(),$("div",{ref_key:"breadcrumb",ref:r,class:E(P(t).b()),"aria-label":"Breadcrumb",role:"navigation"},[w(o.$slots,"default")],2))}});var ht=K(bt,[["__file","/home/runner/work/element-plus/element-plus/packages/components/breadcrumb/src/breadcrumb.vue"]]);const yt=G({to:{type:re([String,Object]),default:""},replace:{type:Boolean,default:!1}}),Mt=A({name:"ElBreadcrumbItem"}),gt=A({...Mt,props:yt,setup(e){const n=e,t=me(),r=te(_e,void 0),o=T("breadcrumb"),{separator:v,separatorIcon:i}=Ke(r),c=t.appContext.config.globalProperties.$router,a=k(),d=()=>{!n.to||!c||(n.replace?c.replace(n.to):c.push(n.to))};return(f,h)=>(g(),$("span",{class:E(P(o).e("item"))},[U("span",{ref_key:"link",ref:a,class:E([P(o).e("inner"),P(o).is("link",!!f.to)]),role:"link",onClick:d},[w(f.$slots,"default")],2),P(i)?(g(),ne(P(ce),{key:0,class:E(P(o).e("separator"))},{default:j(()=>[(g(),ne(Je(P(i))))]),_:1},8,["class"])):(g(),$("span",{key:1,class:E(P(o).e("separator")),role:"presentation"},ke(P(v)),3))],2))}});var Re=K(gt,[["__file","/home/runner/work/element-plus/element-plus/packages/components/breadcrumb/src/breadcrumb-item.vue"]]);const cn=Ie(ht,{BreadcrumbItem:Re}),dn=de(Re),Ct=G({...lt,direction:{type:String,default:"rtl",values:["ltr","rtl","ttb","btt"]},size:{type:[String,Number],default:"30%"},withHeader:{type:Boolean,default:!0},modalFade:{type:Boolean,default:!0}}),It=rt,kt=A({name:"ElDrawer",components:{ElOverlay:st,ElFocusTrap:it,ElIcon:ce,Close:qe},inheritAttrs:!1,props:Ct,emits:It,setup(e,{slots:n}){Te({scope:"el-drawer",from:"the title slot",replacement:"the header slot",version:"3.0.0",ref:"https://element-plus.org/en-US/component/drawer.html#slots"},m(()=>!!n.title)),Te({scope:"el-drawer",from:"custom-class",replacement:"class",version:"2.3.0",ref:"https://element-plus.org/en-US/component/drawer.html#attributes",type:"Attribute"},m(()=>!!e.customClass));const t=k(),r=k(),o=T("drawer"),{t:v}=ct(),i=m(()=>e.direction==="rtl"||e.direction==="ltr"),c=m(()=>Fe(e.size));return{...ut(e,t),drawerRef:t,focusStartRef:r,isHorizontal:i,drawerSize:c,ns:o,t:v}}}),Et=["aria-label","aria-labelledby","aria-describedby"],St=["id"],wt=["aria-label"],$t=["id"];function Tt(e,n,t,r,o,v){const i=ee("close"),c=ee("el-icon"),a=ee("el-focus-trap"),d=ee("el-overlay");return g(),ne(Qe,{to:"body",disabled:!e.appendToBody},[Z(Ae,{name:e.ns.b("fade"),onAfterEnter:e.afterEnter,onAfterLeave:e.afterLeave,onBeforeLeave:e.beforeLeave,persisted:""},{default:j(()=>[Be(Z(d,{mask:e.modal,"overlay-class":e.modalClass,"z-index":e.zIndex,onClick:e.onModalClick},{default:j(()=>[Z(a,{loop:"",trapped:e.visible,"focus-trap-el":e.drawerRef,"focus-start-el":e.focusStartRef,onReleaseRequested:e.onCloseRequested},{default:j(()=>[U("div",Ne({ref:"drawerRef","aria-modal":"true","aria-label":e.title||void 0,"aria-labelledby":e.title?void 0:e.titleId,"aria-describedby":e.bodyId},e.$attrs,{class:[e.ns.b(),e.direction,e.visible&&"open",e.customClass],style:e.isHorizontal?"width: "+e.drawerSize:"height: "+e.drawerSize,role:"dialog",onClick:n[1]||(n[1]=nt(()=>{},["stop"]))}),[U("span",{ref:"focusStartRef",class:E(e.ns.e("sr-focus")),tabindex:"-1"},null,2),e.withHeader?(g(),$("header",{key:0,class:E(e.ns.e("header"))},[e.$slots.title?w(e.$slots,"title",{key:1},()=>[V(" DEPRECATED SLOT ")]):w(e.$slots,"header",{key:0,close:e.handleClose,titleId:e.titleId,titleClass:e.ns.e("title")},()=>[e.$slots.title?V("v-if",!0):(g(),$("span",{key:0,id:e.titleId,role:"heading",class:E(e.ns.e("title"))},ke(e.title),11,St))]),e.showClose?(g(),$("button",{key:2,"aria-label":e.t("el.drawer.close"),class:E(e.ns.e("close-btn")),type:"button",onClick:n[0]||(n[0]=(...f)=>e.handleClose&&e.handleClose(...f))},[Z(c,{class:E(e.ns.e("close"))},{default:j(()=>[Z(i)]),_:1},8,["class"])],10,wt)):V("v-if",!0)],2)):V("v-if",!0),e.rendered?(g(),$("div",{key:1,id:e.bodyId,class:E(e.ns.e("body"))},[w(e.$slots,"default")],10,$t)):V("v-if",!0),e.$slots.footer?(g(),$("div",{key:2,class:E(e.ns.e("footer"))},[w(e.$slots,"footer")],2)):V("v-if",!0)],16,Et)]),_:3},8,["trapped","focus-trap-el","focus-start-el","onReleaseRequested"])]),_:3},8,["mask","overlay-class","z-index","onClick"]),[[Oe,e.visible]])]),_:3},8,["name","onAfterEnter","onAfterLeave","onBeforeLeave"])],8,["disabled"])}var Bt=K(kt,[["render",Tt],["__file","/home/runner/work/element-plus/element-plus/packages/components/drawer/src/drawer.vue"]]);const pn=Ie(Bt);let Nt=class{constructor(n,t){this.parent=n,this.domNode=t,this.subIndex=0,this.subIndex=0,this.init()}init(){this.subMenuItems=this.domNode.querySelectorAll("li"),this.addListeners()}gotoSubIndex(n){n===this.subMenuItems.length?n=0:n<0&&(n=this.subMenuItems.length-1),this.subMenuItems[n].focus(),this.subIndex=n}addListeners(){const n=this.parent.domNode;Array.prototype.forEach.call(this.subMenuItems,t=>{t.addEventListener("keydown",r=>{let o=!1;switch(r.code){case O.down:{this.gotoSubIndex(this.subIndex+1),o=!0;break}case O.up:{this.gotoSubIndex(this.subIndex-1),o=!0;break}case O.tab:{le(n,"mouseleave");break}case O.enter:case O.space:{o=!0,r.currentTarget.click();break}}return o&&(r.preventDefault(),r.stopPropagation()),!1})})}},Pt=class{constructor(n,t){this.domNode=n,this.submenu=null,this.submenu=null,this.init(t)}init(n){this.domNode.setAttribute("tabindex","0");const t=this.domNode.querySelector(`.${n}-menu`);t&&(this.submenu=new Nt(this,t)),this.addListeners()}addListeners(){this.domNode.addEventListener("keydown",n=>{let t=!1;switch(n.code){case O.down:{le(n.currentTarget,"mouseenter"),this.submenu&&this.submenu.gotoSubIndex(0),t=!0;break}case O.up:{le(n.currentTarget,"mouseenter"),this.submenu&&this.submenu.gotoSubIndex(this.submenu.subMenuItems.length-1),t=!0;break}case O.tab:{le(n.currentTarget,"mouseleave");break}case O.enter:case O.space:{t=!0,n.currentTarget.click();break}}t&&n.preventDefault()})}},Ot=class{constructor(n,t){this.domNode=n,this.init(t)}init(n){const t=this.domNode.childNodes;Array.from(t).forEach(r=>{r.nodeType===1&&new Pt(r,n)})}};const At=A({name:"ElMenuCollapseTransition",setup(){const e=T("menu");return{listeners:{onBeforeEnter:t=>t.style.opacity="0.2",onEnter(t,r){se(t,`${e.namespace.value}-opacity-transition`),t.style.opacity="1",r()},onAfterEnter(t){ye(t,`${e.namespace.value}-opacity-transition`),t.style.opacity=""},onBeforeLeave(t){t.dataset||(t.dataset={}),Ve(t,e.m("collapse"))?(ye(t,e.m("collapse")),t.dataset.oldOverflow=t.style.overflow,t.dataset.scrollWidth=t.clientWidth.toString(),se(t,e.m("collapse"))):(se(t,e.m("collapse")),t.dataset.oldOverflow=t.style.overflow,t.dataset.scrollWidth=t.clientWidth.toString(),ye(t,e.m("collapse"))),t.style.width=`${t.scrollWidth}px`,t.style.overflow="hidden"},onLeave(t){se(t,"horizontal-collapse-transition"),t.style.width=`${t.dataset.scrollWidth}px`}}}}});function zt(e,n,t,r,o,v){return g(),ne(Ae,Ne({mode:"out-in"},e.listeners),{default:j(()=>[w(e.$slots,"default")]),_:3},16)}var _t=K(At,[["render",zt],["__file","/home/runner/work/element-plus/element-plus/packages/components/menu/src/menu-collapse-transition.vue"]]);function Le(e,n){const t=m(()=>{let o=e.parent;const v=[n.value];for(;o.type.name!=="ElMenu";)o.props.index&&v.unshift(o.props.index),o=o.parent;return v});return{parentMenu:m(()=>{let o=e.parent;for(;o&&!["ElMenu","ElSubMenu"].includes(o.type.name);)o=o.parent;return o}),indexPath:t}}function Rt(e){return m(()=>{const t=e.backgroundColor;return t?new pt(t).shade(20).toString():""})}const De=(e,n)=>{const t=T("menu");return m(()=>t.cssVarBlock({"text-color":e.textColor||"","hover-text-color":e.textColor||"","bg-color":e.backgroundColor||"","hover-bg-color":Rt(e).value||"","active-color":e.activeTextColor||"",level:`${n}`}))},Lt=G({index:{type:String,required:!0},showTimeout:{type:Number,default:300},hideTimeout:{type:Number,default:300},popperClass:String,disabled:Boolean,popperAppendToBody:{type:Boolean,default:void 0},popperOffset:{type:Number,default:6},expandCloseIcon:{type:X},expandOpenIcon:{type:X},collapseCloseIcon:{type:X},collapseOpenIcon:{type:X}}),Me="ElSubMenu";var we=A({name:Me,props:Lt,setup(e,{slots:n,expose:t}){const r=me(),{indexPath:o,parentMenu:v}=Le(r,m(()=>e.index)),i=T("menu"),c=T("sub-menu"),a=te("rootMenu");a||ie(Me,"can not inject root menu");const d=te(`subMenu:${v.value.uid}`);d||ie(Me,"can not inject sub menu");const f=k({}),h=k({});let S;const R=k(!1),fe=k(),oe=k(null),x=m(()=>l.value==="horizontal"&&q.value?"bottom-start":"right-start"),W=m(()=>l.value==="horizontal"&&q.value||l.value==="vertical"&&!a.props.collapse?e.expandCloseIcon&&e.expandOpenIcon?N.value?e.expandOpenIcon:e.expandCloseIcon:He:e.collapseCloseIcon&&e.collapseOpenIcon?N.value?e.collapseOpenIcon:e.collapseCloseIcon:Ue),q=m(()=>d.level===0),J=m(()=>e.popperAppendToBody===void 0?q.value:Boolean(e.popperAppendToBody)),ve=m(()=>a.props.collapse?`${i.namespace.value}-zoom-in-left`:`${i.namespace.value}-zoom-in-top`),be=m(()=>l.value==="horizontal"&&q.value?["bottom-start","bottom-end","top-start","top-end","right-start","left-start"]:["right-start","left-start","bottom-start","bottom-end","top-start","top-end"]),N=m(()=>a.openedMenus.includes(e.index)),L=m(()=>{let p=!1;return Object.values(f.value).forEach(b=>{b.active&&(p=!0)}),Object.values(h.value).forEach(b=>{b.active&&(p=!0)}),p}),Q=m(()=>a.props.backgroundColor||""),F=m(()=>a.props.activeTextColor||""),s=m(()=>a.props.textColor||""),l=m(()=>a.props.mode),u=Ee({index:e.index,indexPath:o,active:L}),C=m(()=>l.value!=="horizontal"?{color:s.value}:{borderBottomColor:L.value?a.props.activeTextColor?F.value:"":"transparent",color:L.value?F.value:s.value}),y=()=>{var p,b,I;return(I=(b=(p=oe.value)==null?void 0:p.popperRef)==null?void 0:b.popperInstanceRef)==null?void 0:I.destroy()},z=p=>{p||y()},D=()=>{a.props.menuTrigger==="hover"&&a.props.mode==="horizontal"||a.props.collapse&&a.props.mode==="vertical"||e.disabled||a.handleSubMenuClick({index:e.index,indexPath:o.value,active:L.value})},_=(p,b=e.showTimeout)=>{var I;p.type!=="focus"&&(a.props.menuTrigger==="click"&&a.props.mode==="horizontal"||!a.props.collapse&&a.props.mode==="vertical"||e.disabled||(d.mouseInChild.value=!0,S==null||S(),{stop:S}=$e(()=>{a.openMenu(e.index,o.value)},b),J.value&&((I=v.value.vnode.el)==null||I.dispatchEvent(new MouseEvent("mouseenter")))))},B=(p=!1)=>{var b,I;a.props.menuTrigger==="click"&&a.props.mode==="horizontal"||!a.props.collapse&&a.props.mode==="vertical"||(S==null||S(),d.mouseInChild.value=!1,{stop:S}=$e(()=>!R.value&&a.closeMenu(e.index,o.value),e.hideTimeout),J.value&&p&&((b=r.parent)==null?void 0:b.type.name)==="ElSubMenu"&&((I=d.handleMouseleave)==null||I.call(d,!0)))};ae(()=>a.props.collapse,p=>z(Boolean(p)));{const p=I=>{h.value[I.index]=I},b=I=>{delete h.value[I.index]};ue(`subMenu:${r.uid}`,{addSubMenu:p,removeSubMenu:b,handleMouseleave:B,mouseInChild:R,level:d.level+1})}return t({opened:N}),pe(()=>{a.addSubMenu(u),d.addSubMenu(u)}),Pe(()=>{d.removeSubMenu(u),a.removeSubMenu(u)}),()=>{var p;const b=[(p=n.title)==null?void 0:p.call(n),M(ce,{class:c.e("icon-arrow"),style:{transform:N.value?e.expandCloseIcon&&e.expandOpenIcon||e.collapseCloseIcon&&e.collapseOpenIcon&&a.props.collapse?"none":"rotateZ(180deg)":"none"}},{default:()=>H(W.value)?M(r.appContext.components[W.value]):M(W.value)})],I=De(a.props,d.level+1),We=a.isMenuPopup?M(ze,{ref:oe,visible:N.value,effect:"light",pure:!0,offset:e.popperOffset,showArrow:!1,persistent:!0,popperClass:e.popperClass,placement:x.value,teleported:J.value,fallbackPlacements:be.value,transition:ve.value,gpuAcceleration:!1},{content:()=>{var Y;return M("div",{class:[i.m(l.value),i.m("popup-container"),e.popperClass],onMouseenter:he=>_(he,100),onMouseleave:()=>B(!0),onFocus:he=>_(he,100)},[M("ul",{class:[i.b(),i.m("popup"),i.m(`popup-${x.value}`)],style:I.value},[(Y=n.default)==null?void 0:Y.call(n)])])},default:()=>M("div",{class:c.e("title"),style:[C.value,{backgroundColor:Q.value}],onClick:D},b)}):M(Se,{},[M("div",{class:c.e("title"),style:[C.value,{backgroundColor:Q.value}],ref:fe,onClick:D},b),M(dt,{},{default:()=>{var Y;return Be(M("ul",{role:"menu",class:[i.b(),i.m("inline")],style:I.value},[(Y=n.default)==null?void 0:Y.call(n)]),[[Oe,N.value]])}})]);return M("li",{class:[c.b(),c.is("active",L.value),c.is("opened",N.value),c.is("disabled",e.disabled)],role:"menuitem",ariaHaspopup:!0,ariaExpanded:N.value,onMouseenter:_,onMouseleave:()=>B(!0),onFocus:_},[We])}}});const Dt=G({mode:{type:String,values:["horizontal","vertical"],default:"vertical"},defaultActive:{type:String,default:""},defaultOpeneds:{type:re(Array),default:()=>mt([])},uniqueOpened:Boolean,router:Boolean,menuTrigger:{type:String,values:["hover","click"],default:"hover"},collapse:Boolean,backgroundColor:String,textColor:String,activeTextColor:String,collapseTransition:{type:Boolean,default:!0},ellipsis:{type:Boolean,default:!0},popperEffect:{type:String,values:["dark","light"],default:"dark"}}),ge=e=>Array.isArray(e)&&e.every(n=>H(n)),jt={close:(e,n)=>H(e)&&ge(n),open:(e,n)=>H(e)&&ge(n),select:(e,n,t,r)=>H(e)&&ge(n)&&Ze(t)&&(r===void 0||r instanceof Promise)};var xt=A({name:"ElMenu",props:Dt,emits:jt,setup(e,{emit:n,slots:t,expose:r}){const o=me(),v=o.appContext.config.globalProperties.$router,i=k(),c=T("menu"),a=T("sub-menu"),d=k(-1),f=k(e.defaultOpeneds&&!e.collapse?e.defaultOpeneds.slice(0):[]),h=k(e.defaultActive),S=k({}),R=k({}),fe=m(()=>e.mode==="horizontal"||e.mode==="vertical"&&e.collapse),oe=()=>{const s=h.value&&S.value[h.value];if(!s||e.mode==="horizontal"||e.collapse)return;s.indexPath.forEach(u=>{const C=R.value[u];C&&x(u,C.indexPath)})},x=(s,l)=>{f.value.includes(s)||(e.uniqueOpened&&(f.value=f.value.filter(u=>l.includes(u))),f.value.push(s),n("open",s,l))},W=(s,l)=>{const u=f.value.indexOf(s);u!==-1&&f.value.splice(u,1),n("close",s,l)},q=({index:s,indexPath:l})=>{f.value.includes(s)?W(s,l):x(s,l)},J=s=>{(e.mode==="horizontal"||e.collapse)&&(f.value=[]);const{index:l,indexPath:u}=s;if(!(l===void 0||u===void 0))if(e.router&&v){const C=s.route||l,y=v.push(C).then(z=>(z||(h.value=l),z));n("select",l,u,{index:l,indexPath:u,route:C},y)}else h.value=l,n("select",l,u,{index:l,indexPath:u})},ve=s=>{const l=S.value,u=l[s]||h.value&&l[h.value]||l[e.defaultActive];u?h.value=u.index:h.value=s},be=()=>{var s,l;if(!i.value)return-1;const u=Array.from((l=(s=i.value)==null?void 0:s.childNodes)!=null?l:[]).filter(p=>p.nodeName!=="#text"||p.nodeValue),C=64,y=Number.parseInt(getComputedStyle(i.value).paddingLeft,10),z=Number.parseInt(getComputedStyle(i.value).paddingRight,10),D=i.value.clientWidth-y-z;let _=0,B=0;return u.forEach((p,b)=>{_+=p.offsetWidth||0,_<=D-C&&(B=b+1)}),B===u.length?-1:B},N=(s,l=33.34)=>{let u;return()=>{u&&clearTimeout(u),u=setTimeout(()=>{s()},l)}};let L=!0;const Q=()=>{const s=()=>{d.value=-1,Xe(()=>{d.value=be()})};L?s():N(s)(),L=!1};ae(()=>e.defaultActive,s=>{S.value[s]||(h.value=""),ve(s)}),ae(()=>e.collapse,s=>{s&&(f.value=[])}),ae(S.value,oe);let F;Ye(()=>{e.mode==="horizontal"&&e.ellipsis?F=ot(i,Q).stop:F==null||F()});{const s=y=>{R.value[y.index]=y},l=y=>{delete R.value[y.index]};ue("rootMenu",Ee({props:e,openedMenus:f,items:S,subMenus:R,activeIndex:h,isMenuPopup:fe,addMenuItem:y=>{S.value[y.index]=y},removeMenuItem:y=>{delete S.value[y.index]},addSubMenu:s,removeSubMenu:l,openMenu:x,closeMenu:W,handleMenuItemClick:J,handleSubMenuClick:q})),ue(`subMenu:${o.uid}`,{addSubMenu:s,removeSubMenu:l,mouseInChild:k(!1),level:0})}return pe(()=>{e.mode==="horizontal"&&new Ot(o.vnode.el,c.namespace.value)}),r({open:l=>{const{indexPath:u}=R.value[l];u.forEach(C=>x(C,u))},close:W,handleResize:Q}),()=>{var s,l;let u=(l=(s=t.default)==null?void 0:s.call(t))!=null?l:[];const C=[];if(e.mode==="horizontal"&&i.value){const D=at(u),_=d.value===-1?D:D.slice(0,d.value),B=d.value===-1?[]:D.slice(d.value);B!=null&&B.length&&e.ellipsis&&(u=_,C.push(M(we,{index:"sub-menu-more",class:a.e("hide-arrow")},{title:()=>M(ce,{class:a.e("icon-more")},{default:()=>M(Ge)}),default:()=>B})))}const y=De(e,0),z=M("ul",{key:String(e.collapse),role:"menubar",ref:i,style:y.value,class:{[c.b()]:!0,[c.m(e.mode)]:!0,[c.m("collapse")]:e.collapse}},[...u,...C]);return e.collapseTransition&&e.mode==="vertical"?M(_t,()=>z):z}}});const Wt=G({index:{type:re([String,null]),default:null},route:{type:re([String,Object])},disabled:Boolean}),qt={click:e=>H(e.index)&&Array.isArray(e.indexPath)},Ce="ElMenuItem",Ft=A({name:Ce,components:{ElTooltip:ze},props:Wt,emits:qt,setup(e,{emit:n}){const t=me(),r=te("rootMenu"),o=T("menu"),v=T("menu-item");r||ie(Ce,"can not inject root menu");const{parentMenu:i,indexPath:c}=Le(t,et(e,"index")),a=te(`subMenu:${i.value.uid}`);a||ie(Ce,"can not inject sub menu");const d=m(()=>e.index===r.activeIndex),f=Ee({index:e.index,indexPath:c,active:d}),h=()=>{e.disabled||(r.handleMenuItemClick({index:e.index,indexPath:c.value,route:e.route}),n("click",f))};return pe(()=>{a.addSubMenu(f),r.addMenuItem(f)}),Pe(()=>{a.removeSubMenu(f),r.removeMenuItem(f)}),{parentMenu:i,rootMenu:r,active:d,nsMenu:o,nsMenuItem:v,handleClick:h}}});function Vt(e,n,t,r,o,v){const i=ee("el-tooltip");return g(),$("li",{class:E([e.nsMenuItem.b(),e.nsMenuItem.is("active",e.active),e.nsMenuItem.is("disabled",e.disabled)]),role:"menuitem",tabindex:"-1",onClick:n[0]||(n[0]=(...c)=>e.handleClick&&e.handleClick(...c))},[e.parentMenu.type.name==="ElMenu"&&e.rootMenu.props.collapse&&e.$slots.title?(g(),ne(i,{key:0,effect:e.rootMenu.props.popperEffect,placement:"right","fallback-placements":["left"],persistent:""},{content:j(()=>[w(e.$slots,"title")]),default:j(()=>[U("div",{class:E(e.nsMenu.be("tooltip","trigger"))},[w(e.$slots,"default")],2)]),_:3},8,["effect"])):(g(),$(Se,{key:1},[w(e.$slots,"default"),w(e.$slots,"title")],64))],2)}var je=K(Ft,[["render",Vt],["__file","/home/runner/work/element-plus/element-plus/packages/components/menu/src/menu-item.vue"]]);const Ht={title:String},Ut="ElMenuItemGroup",Gt=A({name:Ut,props:Ht,setup(){return{ns:T("menu-item-group")}}});function Kt(e,n,t,r,o,v){return g(),$("li",{class:E(e.ns.b())},[U("div",{class:E(e.ns.e("title"))},[e.$slots.title?w(e.$slots,"title",{key:1}):(g(),$(Se,{key:0},[tt(ke(e.title),1)],64))],2),U("ul",null,[w(e.$slots,"default")])],2)}var xe=K(Gt,[["render",Kt],["__file","/home/runner/work/element-plus/element-plus/packages/components/menu/src/menu-item-group.vue"]]);const bn=Ie(xt,{MenuItem:je,MenuItemGroup:xe,SubMenu:we}),hn=de(je),yn=de(xe),Mn=de(we);export{cn as E,dn as a,pn as b,bn as c,hn as d,yn as e,Mn as f};