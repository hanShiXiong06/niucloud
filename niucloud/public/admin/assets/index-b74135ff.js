import{d as O,w as z,v as H,T as ve,r as le}from"./event-3e082a4a.js";import{E as ge}from"./index-6f670765.js";import{E as ye}from"./index-5f2625ed.js";import{E as be,u as Ce,b as Ee,c as he}from"./index-93efb1b5.js";import{E as Be,a0 as we,X as x,F as _,v as Me,i as ke,k as Te,Z as ee}from"./index-9ef6974e.js";import{u as Se,_ as Ie}from"./plugin-vue_export-helper-c018272e.js";import{E as Ae,u as Re}from"./index-4b62c73a.js";import{o as ne}from"./aria-adfa05c5.js";import{w as q,i as Ve,A,d as $e,r as h,M as Le,c as B,o as Oe,J as ze,U as Pe,V as w,b as d,m as v,p as f,L as N,q as y,f as c,n as i,h as se,e as K,P,C as M,x as I,g as De,v as j,j as re,Z as ie,S as oe,k as Fe,T as te}from"./runtime-core.esm-bundler-c17ab5bc.js";import{E as Ue,u as He}from"./focus-trap-c0148071.js";import{i as Ne}from"./validator-f5e079db.js";const Ke=(e,n)=>{let a;q(()=>e.value,r=>{var o,t;r?(a=document.activeElement,Ve(n)&&((t=(o=n.value).focus)==null||t.call(o))):a.focus()})},Z="_trap-focus-children",b=[],ae=e=>{if(b.length===0)return;const n=b[b.length-1][Z];if(n.length>0&&e.code===Ae.tab){if(n.length===1){e.preventDefault(),document.activeElement!==n[0]&&n[0].focus();return}const a=e.shiftKey,r=e.target===n[0],o=e.target===n[n.length-1];r&&a&&(e.preventDefault(),n[n.length-1].focus()),o&&!a&&(e.preventDefault(),n[0].focus())}},je={beforeMount(e){e[Z]=ne(e),b.push(e),b.length<=1&&document.addEventListener("keydown",ae)},updated(e){A(()=>{e[Z]=ne(e)})},unmounted(){b.shift(),b.length===0&&document.removeEventListener("keydown",ae)}},qe=$e({name:"ElMessageBox",directives:{TrapFocus:je},components:{ElButton:ge,ElFocusTrap:Ue,ElInput:ye,ElOverlay:be,ElIcon:Be,...we},inheritAttrs:!1,props:{buttonSize:{type:String,validator:Ne},modal:{type:Boolean,default:!0},lockScroll:{type:Boolean,default:!0},showClose:{type:Boolean,default:!0},closeOnClickModal:{type:Boolean,default:!0},closeOnPressEscape:{type:Boolean,default:!0},closeOnHashChange:{type:Boolean,default:!0},center:Boolean,draggable:Boolean,roundButton:{default:!1,type:Boolean},container:{type:String,default:"body"},boxType:{type:String,default:""}},emits:["vanish","action"],setup(e,{emit:n}){const{t:a}=Re(),r=Se("message-box"),o=h(!1),{nextZIndex:t}=He(),s=Le({autofocus:!0,beforeClose:null,callback:null,cancelButtonText:"",cancelButtonClass:"",confirmButtonText:"",confirmButtonClass:"",customClass:"",customStyle:{},dangerouslyUseHTMLString:!1,distinguishCancelAndClose:!1,icon:"",inputPattern:null,inputPlaceholder:"",inputType:"text",inputValue:null,inputValidator:null,inputErrorMessage:"",message:null,modalFade:!0,modalClass:"",showCancelButton:!1,showConfirmButton:!0,type:"",title:void 0,showInput:!1,action:"",confirmButtonLoading:!1,cancelButtonLoading:!1,confirmButtonDisabled:!1,editorErrorMessage:"",validateError:!1,zIndex:t()}),m=B(()=>{const l=s.type;return{[r.bm("icon",l)]:l&&x[l]}}),D=_(),V=_(),F=Me(B(()=>e.buttonSize),{prop:!0,form:!0,formItem:!0}),U=B(()=>s.icon||x[s.type]||""),u=B(()=>!!s.message),C=h(),X=h(),T=h(),$=h(),G=h(),ue=B(()=>s.confirmButtonClass);q(()=>s.inputValue,async l=>{await A(),e.boxType==="prompt"&&l!==null&&W()},{immediate:!0}),q(()=>o.value,l=>{var p,E;l&&(e.boxType!=="prompt"&&(s.autofocus?T.value=(E=(p=G.value)==null?void 0:p.$el)!=null?E:C.value:T.value=C.value),s.zIndex=t()),e.boxType==="prompt"&&(l?A().then(()=>{var Y;$.value&&$.value.$el&&(s.autofocus?T.value=(Y=me())!=null?Y:C.value:T.value=C.value)}):(s.editorErrorMessage="",s.validateError=!1))});const de=B(()=>e.draggable);Ce(C,X,de),Oe(async()=>{await A(),e.closeOnHashChange&&window.addEventListener("hashchange",S)}),ze(()=>{e.closeOnHashChange&&window.removeEventListener("hashchange",S)});function S(){o.value&&(o.value=!1,A(()=>{s.action&&n("action",s.action)}))}const J=()=>{e.closeOnClickModal&&L(s.distinguishCancelAndClose?"close":"cancel")},fe=he(J),ce=l=>{if(s.inputType!=="textarea")return l.preventDefault(),L("confirm")},L=l=>{var p;e.boxType==="prompt"&&l==="confirm"&&!W()||(s.action=l,s.beforeClose?(p=s.beforeClose)==null||p.call(s,l,s,S):S())},W=()=>{if(e.boxType==="prompt"){const l=s.inputPattern;if(l&&!l.test(s.inputValue||""))return s.editorErrorMessage=s.inputErrorMessage||a("el.messagebox.error"),s.validateError=!0,!1;const p=s.inputValidator;if(typeof p=="function"){const E=p(s.inputValue);if(E===!1)return s.editorErrorMessage=s.inputErrorMessage||a("el.messagebox.error"),s.validateError=!0,!1;if(typeof E=="string")return s.editorErrorMessage=E,s.validateError=!0,!1}}return s.editorErrorMessage="",s.validateError=!1,!0},me=()=>{const l=$.value.$refs;return l.input||l.textarea},Q=()=>{L("close")},pe=()=>{e.closeOnPressEscape&&Q()};return e.lockScroll&&Ee(o),Ke(o),{...Pe(s),ns:r,overlayEvent:fe,visible:o,hasMessage:u,typeClass:m,contentId:D,inputId:V,btnSize:F,iconComponent:U,confirmButtonClasses:ue,rootRef:C,focusStartRef:T,headerRef:X,inputRef:$,confirmRef:G,doClose:S,handleClose:Q,onCloseRequested:pe,handleWrapperClick:J,handleInputEnter:ce,handleAction:L,t:a}}}),Ze=["aria-label","aria-describedby"],Xe=["aria-label"],Ge=["id"];function Je(e,n,a,r,o,t){const s=w("el-icon"),m=w("close"),D=w("el-input"),V=w("el-button"),F=w("el-focus-trap"),U=w("el-overlay");return d(),v(ve,{name:"fade-in-linear",onAfterLeave:n[11]||(n[11]=u=>e.$emit("vanish")),persisted:""},{default:f(()=>[N(y(U,{"z-index":e.zIndex,"overlay-class":[e.ns.is("message-box"),e.modalClass],mask:e.modal},{default:f(()=>[c("div",{role:"dialog","aria-label":e.title,"aria-modal":"true","aria-describedby":e.showInput?void 0:e.contentId,class:i(`${e.ns.namespace.value}-overlay-message-box`),onClick:n[8]||(n[8]=(...u)=>e.overlayEvent.onClick&&e.overlayEvent.onClick(...u)),onMousedown:n[9]||(n[9]=(...u)=>e.overlayEvent.onMousedown&&e.overlayEvent.onMousedown(...u)),onMouseup:n[10]||(n[10]=(...u)=>e.overlayEvent.onMouseup&&e.overlayEvent.onMouseup(...u))},[y(F,{loop:"",trapped:e.visible,"focus-trap-el":e.rootRef,"focus-start-el":e.focusStartRef,onReleaseRequested:e.onCloseRequested},{default:f(()=>[c("div",{ref:"rootRef",class:i([e.ns.b(),e.customClass,e.ns.is("draggable",e.draggable),{[e.ns.m("center")]:e.center}]),style:se(e.customStyle),tabindex:"-1",onClick:n[7]||(n[7]=O(()=>{},["stop"]))},[e.title!==null&&e.title!==void 0?(d(),K("div",{key:0,ref:"headerRef",class:i(e.ns.e("header"))},[c("div",{class:i(e.ns.e("title"))},[e.iconComponent&&e.center?(d(),v(s,{key:0,class:i([e.ns.e("status"),e.typeClass])},{default:f(()=>[(d(),v(P(e.iconComponent)))]),_:1},8,["class"])):M("v-if",!0),c("span",null,I(e.title),1)],2),e.showClose?(d(),K("button",{key:0,type:"button",class:i(e.ns.e("headerbtn")),"aria-label":e.t("el.messagebox.close"),onClick:n[0]||(n[0]=u=>e.handleAction(e.distinguishCancelAndClose?"close":"cancel")),onKeydown:n[1]||(n[1]=z(O(u=>e.handleAction(e.distinguishCancelAndClose?"close":"cancel"),["prevent"]),["enter"]))},[y(s,{class:i(e.ns.e("close"))},{default:f(()=>[y(m)]),_:1},8,["class"])],42,Xe)):M("v-if",!0)],2)):M("v-if",!0),c("div",{id:e.contentId,class:i(e.ns.e("content"))},[c("div",{class:i(e.ns.e("container"))},[e.iconComponent&&!e.center&&e.hasMessage?(d(),v(s,{key:0,class:i([e.ns.e("status"),e.typeClass])},{default:f(()=>[(d(),v(P(e.iconComponent)))]),_:1},8,["class"])):M("v-if",!0),e.hasMessage?(d(),K("div",{key:1,class:i(e.ns.e("message"))},[De(e.$slots,"default",{},()=>[e.dangerouslyUseHTMLString?(d(),v(P(e.showInput?"label":"p"),{key:1,for:e.showInput?e.inputId:void 0,innerHTML:e.message},null,8,["for","innerHTML"])):(d(),v(P(e.showInput?"label":"p"),{key:0,for:e.showInput?e.inputId:void 0},{default:f(()=>[j(I(e.dangerouslyUseHTMLString?"":e.message),1)]),_:1},8,["for"]))])],2)):M("v-if",!0)],2),N(c("div",{class:i(e.ns.e("input"))},[y(D,{id:e.inputId,ref:"inputRef",modelValue:e.inputValue,"onUpdate:modelValue":n[2]||(n[2]=u=>e.inputValue=u),type:e.inputType,placeholder:e.inputPlaceholder,"aria-invalid":e.validateError,class:i({invalid:e.validateError}),onKeydown:z(e.handleInputEnter,["enter"])},null,8,["id","modelValue","type","placeholder","aria-invalid","class","onKeydown"]),c("div",{class:i(e.ns.e("errormsg")),style:se({visibility:e.editorErrorMessage?"visible":"hidden"})},I(e.editorErrorMessage),7)],2),[[H,e.showInput]])],10,Ge),c("div",{class:i(e.ns.e("btns"))},[e.showCancelButton?(d(),v(V,{key:0,loading:e.cancelButtonLoading,class:i([e.cancelButtonClass]),round:e.roundButton,size:e.btnSize,onClick:n[3]||(n[3]=u=>e.handleAction("cancel")),onKeydown:n[4]||(n[4]=z(O(u=>e.handleAction("cancel"),["prevent"]),["enter"]))},{default:f(()=>[j(I(e.cancelButtonText||e.t("el.messagebox.cancel")),1)]),_:1},8,["loading","class","round","size"])):M("v-if",!0),N(y(V,{ref:"confirmRef",type:"primary",loading:e.confirmButtonLoading,class:i([e.confirmButtonClasses]),round:e.roundButton,disabled:e.confirmButtonDisabled,size:e.btnSize,onClick:n[5]||(n[5]=u=>e.handleAction("confirm")),onKeydown:n[6]||(n[6]=z(O(u=>e.handleAction("confirm"),["prevent"]),["enter"]))},{default:f(()=>[j(I(e.confirmButtonText||e.t("el.messagebox.confirm")),1)]),_:1},8,["loading","class","round","disabled","size"]),[[H,e.showConfirmButton]])],2)],6)]),_:3},8,["trapped","focus-trap-el","focus-start-el","onReleaseRequested"])],42,Ze)]),_:3},8,["z-index","overlay-class","mask"]),[[H,e.visible]])]),_:3})}var We=Ie(qe,[["render",Je],["__file","/home/runner/work/element-plus/element-plus/packages/components/message-box/src/index.vue"]]);const R=new Map,Qe=e=>{let n=document.body;return e.appendTo&&(re(e.appendTo)&&(n=document.querySelector(e.appendTo)),ee(e.appendTo)&&(n=e.appendTo),ee(n)||(n=document.body)),n},Ye=(e,n,a=null)=>{const r=y(We,e,te(e.message)||ie(e.message)?{default:te(e.message)?e.message:()=>e.message}:null);return r.appContext=a,le(r,n),Qe(e).appendChild(n.firstElementChild),r.component},xe=()=>document.createElement("div"),_e=(e,n)=>{const a=xe();e.onVanish=()=>{le(null,a),R.delete(o)},e.onAction=t=>{const s=R.get(o);let m;e.showInput?m={value:o.inputValue,action:t}:m=t,e.callback?e.callback(m,r.proxy):t==="cancel"||t==="close"?e.distinguishCancelAndClose&&t!=="cancel"?s.reject("close"):s.reject("cancel"):s.resolve(m)};const r=Ye(e,a,n),o=r.proxy;for(const t in e)oe(e,t)&&!oe(o.$props,t)&&(o[t]=e[t]);return o.visible=!0,o};function k(e,n=null){if(!ke)return Promise.reject();let a;return re(e)||ie(e)?e={message:e}:a=e.callback,new Promise((r,o)=>{const t=_e(e,n??k._context);R.set(t,{options:e,callback:a,resolve:r,reject:o})})}const en=["alert","confirm","prompt"],nn={alert:{closeOnPressEscape:!1,closeOnClickModal:!1},confirm:{showCancelButton:!0},prompt:{showCancelButton:!0,showInput:!0}};en.forEach(e=>{k[e]=sn(e)});function sn(e){return(n,a,r,o)=>{let t="";return Fe(a)?(r=a,t=""):Te(a)?t="":t=a,k(Object.assign({title:t,message:n,type:"",...nn[e]},r,{boxType:e}),o)}}k.close=()=>{R.forEach((e,n)=>{n.doClose()}),R.clear()};k._context=null;const g=k;g.install=e=>{g._context=e._context,e.config.globalProperties.$msgbox=g,e.config.globalProperties.$messageBox=g,e.config.globalProperties.$alert=g.alert,e.config.globalProperties.$confirm=g.confirm,e.config.globalProperties.$prompt=g.prompt};const vn=g;export{vn as E};