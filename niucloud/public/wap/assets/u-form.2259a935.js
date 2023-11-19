import{_ as e}from"./u-icon.d822f266.js";import{V as t,W as r,X as n,q as i,t as o,i as s,j as a,w as l,k as u,T as d,m as f,p as c,I as p,x as h,an as m,K as y,L as g,N as b}from"./index-42b5dd66.js";import{_}from"./_plugin-vue_export-helper.1b428a4d.js";import{_ as v}from"./u-line.9c8e4680.js";const x=_({name:"u-input",mixins:[r,n,{props:{modelValue:{type:[String,Number],default:t.input.value},type:{type:String,default:t.input.type},fixed:{type:Boolean,default:t.input.fixed},disabled:{type:Boolean,default:t.input.disabled},disabledColor:{type:String,default:t.input.disabledColor},clearable:{type:Boolean,default:t.input.clearable},password:{type:Boolean,default:t.input.password},maxlength:{type:[String,Number],default:t.input.maxlength},placeholder:{type:String,default:t.input.placeholder},placeholderClass:{type:String,default:t.input.placeholderClass},placeholderStyle:{type:[String,Object],default:t.input.placeholderStyle},showWordLimit:{type:Boolean,default:t.input.showWordLimit},confirmType:{type:String,default:t.input.confirmType},confirmHold:{type:Boolean,default:t.input.confirmHold},holdKeyboard:{type:Boolean,default:t.input.holdKeyboard},focus:{type:Boolean,default:t.input.focus},autoBlur:{type:Boolean,default:t.input.autoBlur},disableDefaultPadding:{type:Boolean,default:t.input.disableDefaultPadding},cursor:{type:[String,Number],default:t.input.cursor},cursorSpacing:{type:[String,Number],default:t.input.cursorSpacing},selectionStart:{type:[String,Number],default:t.input.selectionStart},selectionEnd:{type:[String,Number],default:t.input.selectionEnd},adjustPosition:{type:Boolean,default:t.input.adjustPosition},inputAlign:{type:String,default:t.input.inputAlign},fontSize:{type:[String,Number],default:t.input.fontSize},color:{type:String,default:t.input.color},prefixIcon:{type:String,default:t.input.prefixIcon},prefixIconStyle:{type:[String,Object],default:t.input.prefixIconStyle},suffixIcon:{type:String,default:t.input.suffixIcon},suffixIconStyle:{type:[String,Object],default:t.input.suffixIconStyle},border:{type:String,default:t.input.border},readonly:{type:Boolean,default:t.input.readonly},shape:{type:String,default:t.input.shape},formatter:{type:[Function,null],default:t.input.formatter},ignoreCompositionEvent:{type:Boolean,default:!0}}}],data:()=>({innerValue:"",focused:!1,firstChange:!0,changeFromInner:!1,innerFormatter:e=>e}),watch:{modelValue:{immediate:!0,handler(e,t){this.innerValue=e,!1===this.firstChange&&!1===this.changeFromInner&&this.valueChange(),this.firstChange=!1,this.changeFromInner=!1}}},computed:{isShowClear(){const{clearable:e,readonly:t,focused:r,innerValue:n}=this;return!!e&&!t&&!!r&&""!==n},inputClass(){let e=[],{border:t,disabled:r,shape:n}=this;return"surround"===t&&(e=e.concat(["u-border","u-input--radius"])),e.push(`u-input--${n}`),"bottom"===t&&(e=e.concat(["u-border-bottom","u-input--no-radius"])),e.join(" ")},wrapperStyle(){const e={};return this.disabled&&(e.backgroundColor=this.disabledColor),"none"===this.border?e.padding="0":(e.paddingTop="6px",e.paddingBottom="6px",e.paddingLeft="9px",e.paddingRight="9px"),uni.$u.deepMerge(e,uni.$u.addStyle(this.customStyle))},inputStyle(){return{color:this.color,fontSize:uni.$u.addUnit(this.fontSize),textAlign:this.inputAlign}}},emits:["update:modelValue","focus","blur","change","confirm","clear","keyboardheightchange"],methods:{setFormatter(e){this.innerFormatter=e},onInput(e){let{value:t=""}=e.detail||{};const r=(this.formatter||this.innerFormatter)(t);this.innerValue=t,this.$nextTick((()=>{this.innerValue=r,this.valueChange()}))},onBlur(e){this.$emit("blur",e.detail.value),uni.$u.sleep(50).then((()=>{this.focused=!1})),uni.$u.formValidate(this,"blur")},onFocus(e){this.focused=!0,this.$emit("focus")},onConfirm(e){this.$emit("confirm",this.innerValue)},onkeyboardheightchange(){this.$emit("keyboardheightchange")},valueChange(){const e=this.innerValue;this.$nextTick((()=>{this.$emit("update:modelValue",e),this.changeFromInner=!0,this.$emit("change",e),uni.$u.formValidate(this,"change")}))},onClear(){this.innerValue="",this.$nextTick((()=>{this.valueChange(),this.$emit("clear")}))},clickHandler(){}}},[["render",function(t,r,n,y,g,b){const _=i(o("u-icon"),e),v=h,x=m;return s(),a(v,{class:p(["u-input",b.inputClass]),style:c([b.wrapperStyle])},{default:l((()=>[u(v,{class:"u-input__content"},{default:l((()=>[t.prefixIcon||t.$slots.prefix?(s(),a(v,{key:0,class:"u-input__content__prefix-icon"},{default:l((()=>[d(t.$slots,"prefix",{},(()=>[u(_,{name:t.prefixIcon,size:"18",customStyle:t.prefixIconStyle},null,8,["name","customStyle"])]),!0)])),_:3})):f("v-if",!0),u(v,{class:"u-input__content__field-wrapper",onClick:b.clickHandler},{default:l((()=>[f(" 根据uni-app的input组件文档，H5和APP中只要声明了password参数(无论true还是false)，type均失效，此时\n\t\t\t\t\t为了防止type=number时，又存在password属性，type无效，此时需要设置password为undefined\n\t\t\t\t "),u(x,{class:"u-input__content__field-wrapper__field",style:c([b.inputStyle]),type:t.type,focus:t.focus,cursor:t.cursor,value:g.innerValue,"auto-blur":t.autoBlur,disabled:t.disabled||t.readonly,maxlength:t.maxlength,placeholder:t.placeholder,"placeholder-style":t.placeholderStyle,"placeholder-class":t.placeholderClass,"confirm-type":t.confirmType,"confirm-hold":t.confirmHold,"hold-keyboard":t.holdKeyboard,"cursor-spacing":t.cursorSpacing,"adjust-position":t.adjustPosition,"selection-end":t.selectionEnd,"selection-start":t.selectionStart,password:t.password||"password"===t.type||void 0,ignoreCompositionEvent:t.ignoreCompositionEvent,onInput:b.onInput,onBlur:b.onBlur,onFocus:b.onFocus,onConfirm:b.onConfirm,onKeyboardheightchange:b.onkeyboardheightchange},null,8,["style","type","focus","cursor","value","auto-blur","disabled","maxlength","placeholder","placeholder-style","placeholder-class","confirm-type","confirm-hold","hold-keyboard","cursor-spacing","adjust-position","selection-end","selection-start","password","ignoreCompositionEvent","onInput","onBlur","onFocus","onConfirm","onKeyboardheightchange"])])),_:1},8,["onClick"]),b.isShowClear?(s(),a(v,{key:1,class:"u-input__content__clear",onClick:b.onClear},{default:l((()=>[u(_,{name:"close",size:"11",color:"#ffffff",customStyle:"line-height: 12px"})])),_:1},8,["onClick"])):f("v-if",!0),t.suffixIcon||t.$slots.suffix?(s(),a(v,{key:2,class:"u-input__content__subfix-icon"},{default:l((()=>[d(t.$slots,"suffix",{},(()=>[u(_,{name:t.suffixIcon,size:"18",customStyle:t.suffixIconStyle},null,8,["name","customStyle"])]),!0)])),_:3})):f("v-if",!0)])),_:3})])),_:3},8,["class","style"])}],["__scopeId","data-v-afd9dafc"]]);const S=_({name:"u-form-item",mixins:[r,n,{props:{label:{type:String,default:t.formItem.label},prop:{type:String,default:t.formItem.prop},borderBottom:{type:[String,Boolean],default:t.formItem.borderBottom},labelWidth:{type:[String,Number],default:t.formItem.labelWidth},rightIcon:{type:String,default:t.formItem.rightIcon},leftIcon:{type:String,default:t.formItem.leftIcon},required:{type:Boolean,default:t.formItem.required},leftIconStyle:{type:[String,Object],default:t.formItem.leftIconStyle}}}],data:()=>({message:"",parentData:{labelPosition:"left",labelAlign:"left",labelStyle:{},labelWidth:45,errorType:"message"}}),computed:{propsLine:()=>uni.$u.props.line},mounted(){this.init()},methods:{init(){this.updateParentData(),this.parent||uni.$u.error("u-form-item需要结合u-form组件使用")},updateParentData(){this.getParentData("u-form")},clearValidate(){this.message=null},resetField(){const e=uni.$u.getProperty(this.parent.originalModel,this.prop);uni.$u.setProperty(this.parent.model,this.prop,e),this.message=null},clickHandler(){this.$emit("click")}}},[["render",function(t,r,n,p,m,_){const x=b,S=i(o("u-icon"),e),q=h,w=i(o("u-line"),v);return s(),a(q,{class:"u-form-item"},{default:l((()=>[u(q,{class:"u-form-item__body",onClick:_.clickHandler,style:c([t.$u.addStyle(t.customStyle),{flexDirection:"left"===m.parentData.labelPosition?"row":"column"}])},{default:l((()=>[f(' 微信小程序中，将一个参数设置空字符串，结果会变成字符串"true" '),d(t.$slots,"label",{},(()=>[f(" {{required}} "),t.required||t.leftIcon||t.label?(s(),a(q,{key:0,class:"u-form-item__body__left",style:c({width:t.$u.addUnit(t.labelWidth||m.parentData.labelWidth),marginBottom:"left"===m.parentData.labelPosition?0:"5px"})},{default:l((()=>[f(" 为了块对齐 "),u(q,{class:"u-form-item__body__left__content"},{default:l((()=>[f(" nvue不支持伪元素before "),t.required?(s(),a(x,{key:0,class:"u-form-item__body__left__content__required"},{default:l((()=>[y("*")])),_:1})):f("v-if",!0),t.leftIcon?(s(),a(q,{key:1,class:"u-form-item__body__left__content__icon"},{default:l((()=>[u(S,{name:t.leftIcon,"custom-style":t.leftIconStyle},null,8,["name","custom-style"])])),_:1})):f("v-if",!0),u(x,{class:"u-form-item__body__left__content__label",style:c([m.parentData.labelStyle,{justifyContent:"left"===m.parentData.labelAlign?"flex-start":"center"===m.parentData.labelAlign?"center":"flex-end"}])},{default:l((()=>[y(g(t.label),1)])),_:1},8,["style"])])),_:1})])),_:1},8,["style"])):f("v-if",!0)]),!0),u(q,{class:"u-form-item__body__right"},{default:l((()=>[u(q,{class:"u-form-item__body__right__content"},{default:l((()=>[u(q,{class:"u-form-item__body__right__content__slot"},{default:l((()=>[d(t.$slots,"default",{},void 0,!0)])),_:3}),t.$slots.right?(s(),a(q,{key:0,class:"item__body__right__content__icon"},{default:l((()=>[d(t.$slots,"right",{},void 0,!0)])),_:3})):f("v-if",!0)])),_:3})])),_:3})])),_:3},8,["onClick","style"]),d(t.$slots,"error",{},(()=>[m.message&&"message"===m.parentData.errorType?(s(),a(x,{key:0,class:"u-form-item__body__right__message",style:c({marginLeft:t.$u.addUnit("top"===m.parentData.labelPosition?0:t.labelWidth||m.parentData.labelWidth)})},{default:l((()=>[y(g(m.message),1)])),_:1},8,["style"])):f("v-if",!0)]),!0),t.borderBottom?(s(),a(w,{key:0,color:m.message&&"border-bottom"===m.parentData.errorType?t.$u.color.error:_.propsLine.color,customStyle:`margin-top: ${m.message&&"message"===m.parentData.errorType?"5px":0}`},null,8,["color","customStyle"])):f("v-if",!0)])),_:3})}],["__scopeId","data-v-a180cd81"]]),q={props:{model:{type:Object,default:t.form.model},rules:{type:[Object,Function,Array],default:t.form.rules},errorType:{type:String,default:t.form.errorType},borderBottom:{type:Boolean,default:t.form.borderBottom},labelPosition:{type:String,default:t.form.labelPosition},labelWidth:{type:[String,Number],default:t.form.labelWidth},labelAlign:{type:String,default:t.form.labelAlign},labelStyle:{type:Object,default:t.form.labelStyle}}},w=/%[sdj%]/g;let $=function(){};function P(e){if(!e||!e.length)return null;const t={};return e.forEach((e=>{const{field:r}=e;t[r]=t[r]||[],t[r].push(e)})),t}function F(){for(var e=arguments.length,t=new Array(e),r=0;r<e;r++)t[r]=arguments[r];let n=1;const i=t[0],o=t.length;if("function"==typeof i)return i.apply(null,t.slice(1));if("string"==typeof i){let e=String(i).replace(w,(e=>{if("%%"===e)return"%";if(n>=o)return e;switch(e){case"%s":return String(t[n++]);case"%d":return Number(t[n++]);case"%j":try{return JSON.stringify(t[n++])}catch(r){return"[Circular]"}break;default:return e}}));for(let r=t[n];n<o;r=t[++n])e+=` ${r}`;return e}return i}function I(e,t){return null==e||(!("array"!==t||!Array.isArray(e)||e.length)||!(!function(e){return"string"===e||"url"===e||"hex"===e||"email"===e||"pattern"===e}(t)||"string"!=typeof e||e))}function k(e,t,r){let n=0;const i=e.length;!function o(s){if(s&&s.length)return void r(s);const a=n;n+=1,a<i?t(e[a],o):r([])}([])}function C(e,t,r,n){if(t.first){const t=new Promise(((t,i)=>{const o=function(e){const t=[];return Object.keys(e).forEach((r=>{t.push.apply(t,e[r])})),t}(e);k(o,r,(function(e){return n(e),e.length?i({errors:e,fields:P(e)}):t()}))}));return t.catch((e=>e)),t}let i=t.firstFields||[];!0===i&&(i=Object.keys(e));const o=Object.keys(e),s=o.length;let a=0;const l=[],u=new Promise(((t,u)=>{const d=function(e){if(l.push.apply(l,e),a++,a===s)return n(l),l.length?u({errors:l,fields:P(l)}):t()};o.length||(n(l),t()),o.forEach((t=>{const n=e[t];-1!==i.indexOf(t)?k(n,r,d):function(e,t,r){const n=[];let i=0;const o=e.length;function s(e){n.push.apply(n,e),i++,i===o&&r(n)}e.forEach((e=>{t(e,s)}))}(n,r,d)}))}));return u.catch((e=>e)),u}function j(e){return function(t){return t&&t.message?(t.field=t.field||e.fullField,t):{message:"function"==typeof t?t():t,field:t.field||e.fullField}}}function O(e,t){if(t)for(const r in t)if(t.hasOwnProperty(r)){const n=t[r];"object"==typeof n&&"object"==typeof e[r]?e[r]={...e[r],...n}:e[r]=n}return e}function A(e,t,r,n,i,o){!e.required||r.hasOwnProperty(e.field)&&!I(t,o||e.type)||n.push(F(i.messages.required,e.fullField))}"undefined"!=typeof process&&process.env;const B={email:/^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/,url:new RegExp("^(?!mailto:)(?:(?:http|https|ftp)://|//)(?:\\S+(?::\\S*)?@)?(?:(?:(?:[1-9]\\d?|1\\d\\d|2[01]\\d|22[0-3])(?:\\.(?:1?\\d{1,2}|2[0-4]\\d|25[0-5])){2}(?:\\.(?:[0-9]\\d?|1\\d\\d|2[0-4]\\d|25[0-4]))|(?:(?:[a-z\\u00a1-\\uffff0-9]+-*)*[a-z\\u00a1-\\uffff0-9]+)(?:\\.(?:[a-z\\u00a1-\\uffff0-9]+-*)*[a-z\\u00a1-\\uffff0-9]+)*(?:\\.(?:[a-z\\u00a1-\\uffff]{2,})))|localhost)(?::\\d{2,5})?(?:(/|\\?|#)[^\\s]*)?$","i"),hex:/^#?([a-f0-9]{6}|[a-f0-9]{3})$/i};var D={integer:function(e){return/^(-)?\d+$/.test(e)},float:function(e){return/^(-)?\d+(\.\d+)?$/.test(e)},array:function(e){return Array.isArray(e)},regexp:function(e){if(e instanceof RegExp)return!0;try{return!!new RegExp(e)}catch(t){return!1}},date:function(e){return"function"==typeof e.getTime&&"function"==typeof e.getMonth&&"function"==typeof e.getYear},number:function(e){return!isNaN(e)&&"number"==typeof+e},object:function(e){return"object"==typeof e&&!D.array(e)},method:function(e){return"function"==typeof e},email:function(e){return"string"==typeof e&&!!e.match(B.email)&&e.length<255},url:function(e){return"string"==typeof e&&!!e.match(B.url)},hex:function(e){return"string"==typeof e&&!!e.match(B.hex)}};const E="enum";const T={required:A,whitespace:function(e,t,r,n,i){(/^\s+$/.test(t)||""===t)&&n.push(F(i.messages.whitespace,e.fullField))},type:function(e,t,r,n,i){if(e.required&&void 0===t)return void A(e,t,r,n,i);const o=e.type;["integer","float","array","regexp","object","method","email","number","date","url","hex"].indexOf(o)>-1?D[o](t)||n.push(F(i.messages.types[o],e.fullField,e.type)):o&&typeof t!==e.type&&n.push(F(i.messages.types[o],e.fullField,e.type))},range:function(e,t,r,n,i){const o="number"==typeof e.len,s="number"==typeof e.min,a="number"==typeof e.max,l=/[\uD800-\uDBFF][\uDC00-\uDFFF]/g;let u=t,d=null;const f="number"==typeof t,c="string"==typeof t,p=Array.isArray(t);if(f?d="number":c?d="string":p&&(d="array"),!d)return!1;p&&(u=t.length),c&&(u=t.replace(l,"_").length),o?u!==e.len&&n.push(F(i.messages[d].len,e.fullField,e.len)):s&&!a&&u<e.min?n.push(F(i.messages[d].min,e.fullField,e.min)):a&&!s&&u>e.max?n.push(F(i.messages[d].max,e.fullField,e.max)):s&&a&&(u<e.min||u>e.max)&&n.push(F(i.messages[d].range,e.fullField,e.min,e.max))},enum:function(e,t,r,n,i){e[E]=Array.isArray(e[E])?e[E]:[],-1===e[E].indexOf(t)&&n.push(F(i.messages[E],e.fullField,e[E].join(", ")))},pattern:function(e,t,r,n,i){if(e.pattern)if(e.pattern instanceof RegExp)e.pattern.lastIndex=0,e.pattern.test(t)||n.push(F(i.messages.pattern.mismatch,e.fullField,t,e.pattern));else if("string"==typeof e.pattern){new RegExp(e.pattern).test(t)||n.push(F(i.messages.pattern.mismatch,e.fullField,t,e.pattern))}}};function V(e,t,r,n,i){const o=e.type,s=[];if(e.required||!e.required&&n.hasOwnProperty(e.field)){if(I(t,o)&&!e.required)return r();T.required(e,t,n,s,i,o),I(t,o)||T.type(e,t,n,s,i)}r(s)}const N={string:function(e,t,r,n,i){const o=[];if(e.required||!e.required&&n.hasOwnProperty(e.field)){if(I(t,"string")&&!e.required)return r();T.required(e,t,n,o,i,"string"),I(t,"string")||(T.type(e,t,n,o,i),T.range(e,t,n,o,i),T.pattern(e,t,n,o,i),!0===e.whitespace&&T.whitespace(e,t,n,o,i))}r(o)},method:function(e,t,r,n,i){const o=[];if(e.required||!e.required&&n.hasOwnProperty(e.field)){if(I(t)&&!e.required)return r();T.required(e,t,n,o,i),void 0!==t&&T.type(e,t,n,o,i)}r(o)},number:function(e,t,r,n,i){const o=[];if(e.required||!e.required&&n.hasOwnProperty(e.field)){if(""===t&&(t=void 0),I(t)&&!e.required)return r();T.required(e,t,n,o,i),void 0!==t&&(T.type(e,t,n,o,i),T.range(e,t,n,o,i))}r(o)},boolean:function(e,t,r,n,i){const o=[];if(e.required||!e.required&&n.hasOwnProperty(e.field)){if(I(t)&&!e.required)return r();T.required(e,t,n,o,i),void 0!==t&&T.type(e,t,n,o,i)}r(o)},regexp:function(e,t,r,n,i){const o=[];if(e.required||!e.required&&n.hasOwnProperty(e.field)){if(I(t)&&!e.required)return r();T.required(e,t,n,o,i),I(t)||T.type(e,t,n,o,i)}r(o)},integer:function(e,t,r,n,i){const o=[];if(e.required||!e.required&&n.hasOwnProperty(e.field)){if(I(t)&&!e.required)return r();T.required(e,t,n,o,i),void 0!==t&&(T.type(e,t,n,o,i),T.range(e,t,n,o,i))}r(o)},float:function(e,t,r,n,i){const o=[];if(e.required||!e.required&&n.hasOwnProperty(e.field)){if(I(t)&&!e.required)return r();T.required(e,t,n,o,i),void 0!==t&&(T.type(e,t,n,o,i),T.range(e,t,n,o,i))}r(o)},array:function(e,t,r,n,i){const o=[];if(e.required||!e.required&&n.hasOwnProperty(e.field)){if(I(t,"array")&&!e.required)return r();T.required(e,t,n,o,i,"array"),I(t,"array")||(T.type(e,t,n,o,i),T.range(e,t,n,o,i))}r(o)},object:function(e,t,r,n,i){const o=[];if(e.required||!e.required&&n.hasOwnProperty(e.field)){if(I(t)&&!e.required)return r();T.required(e,t,n,o,i),void 0!==t&&T.type(e,t,n,o,i)}r(o)},enum:function(e,t,r,n,i){const o=[];if(e.required||!e.required&&n.hasOwnProperty(e.field)){if(I(t)&&!e.required)return r();T.required(e,t,n,o,i),void 0!==t&&T.enum(e,t,n,o,i)}r(o)},pattern:function(e,t,r,n,i){const o=[];if(e.required||!e.required&&n.hasOwnProperty(e.field)){if(I(t,"string")&&!e.required)return r();T.required(e,t,n,o,i),I(t,"string")||T.pattern(e,t,n,o,i)}r(o)},date:function(e,t,r,n,i){const o=[];if(e.required||!e.required&&n.hasOwnProperty(e.field)){if(I(t)&&!e.required)return r();if(T.required(e,t,n,o,i),!I(t)){let r;r="number"==typeof t?new Date(t):t,T.type(e,r,n,o,i),r&&T.range(e,r.getTime(),n,o,i)}}r(o)},url:V,hex:V,email:V,required:function(e,t,r,n,i){const o=[],s=Array.isArray(t)?"array":typeof t;T.required(e,t,n,o,i,s),r(o)},any:function(e,t,r,n,i){const o=[];if(e.required||!e.required&&n.hasOwnProperty(e.field)){if(I(t)&&!e.required)return r();T.required(e,t,n,o,i)}r(o)}};function z(){return{default:"Validation error on field %s",required:"%s is required",enum:"%s must be one of %s",whitespace:"%s cannot be empty",date:{format:"%s date %s is invalid for format %s",parse:"%s date could not be parsed, %s is invalid ",invalid:"%s date %s is invalid"},types:{string:"%s is not a %s",method:"%s is not a %s (function)",array:"%s is not an %s",object:"%s is not an %s",number:"%s is not a %s",date:"%s is not a %s",boolean:"%s is not a %s",integer:"%s is not an %s",float:"%s is not a %s",regexp:"%s is not a valid %s",email:"%s is not a valid %s",url:"%s is not a valid %s",hex:"%s is not a valid %s"},string:{len:"%s must be exactly %s characters",min:"%s must be at least %s characters",max:"%s cannot be longer than %s characters",range:"%s must be between %s and %s characters"},number:{len:"%s must equal %s",min:"%s cannot be less than %s",max:"%s cannot be greater than %s",range:"%s must be between %s and %s"},array:{len:"%s must be exactly %s in length",min:"%s cannot be less than %s in length",max:"%s cannot be greater than %s in length",range:"%s must be between %s and %s in length"},pattern:{mismatch:"%s value %s does not match pattern %s"},clone:function(){const e=JSON.parse(JSON.stringify(this));return e.clone=this.clone,e}}}const R=z();function W(e){this.rules=null,this._messages=R,this.define(e)}W.prototype={messages:function(e){return e&&(this._messages=O(z(),e)),this._messages},define:function(e){if(!e)throw new Error("Cannot configure a schema with no rules");if("object"!=typeof e||Array.isArray(e))throw new Error("Rules must be an object");let t,r;for(t in this.rules={},e)e.hasOwnProperty(t)&&(r=e[t],this.rules[t]=Array.isArray(r)?r:[r])},validate:function(e,t,r){const n=this;void 0===t&&(t={}),void 0===r&&(r=function(){});let i,o,s=e,a=t,l=r;if("function"==typeof a&&(l=a,a={}),!this.rules||0===Object.keys(this.rules).length)return l&&l(),Promise.resolve();if(a.messages){let e=this.messages();e===R&&(e=z()),O(e,a.messages),a.messages=e}else a.messages=this.messages();const u={};(a.keys||Object.keys(this.rules)).forEach((t=>{i=n.rules[t],o=s[t],i.forEach((r=>{let i=r;"function"==typeof i.transform&&(s===e&&(s={...s}),o=s[t]=i.transform(o)),i="function"==typeof i?{validator:i}:{...i},i.validator=n.getValidationMethod(i),i.field=t,i.fullField=i.fullField||t,i.type=n.getType(i),i.validator&&(u[t]=u[t]||[],u[t].push({rule:i,value:o,source:s,field:t}))}))}));const d={};return C(u,a,((e,t)=>{const{rule:r}=e;let n,i=!("object"!==r.type&&"array"!==r.type||"object"!=typeof r.fields&&"object"!=typeof r.defaultField);function o(e,t){return{...t,fullField:`${r.fullField}.${e}`}}function s(n){void 0===n&&(n=[]);let s=n;if(Array.isArray(s)||(s=[s]),!a.suppressWarning&&s.length&&W.warning("async-validator:",s),s.length&&r.message&&(s=[].concat(r.message)),s=s.map(j(r)),a.first&&s.length)return d[r.field]=1,t(s);if(i){if(r.required&&!e.value)return s=r.message?[].concat(r.message).map(j(r)):a.error?[a.error(r,F(a.messages.required,r.field))]:[],t(s);let n={};if(r.defaultField)for(const t in e.value)e.value.hasOwnProperty(t)&&(n[t]=r.defaultField);n={...n,...e.rule.fields};for(const e in n)if(n.hasOwnProperty(e)){const t=Array.isArray(n[e])?n[e]:[n[e]];n[e]=t.map(o.bind(null,e))}const i=new W(n);i.messages(a.messages),e.rule.options&&(e.rule.options.messages=a.messages,e.rule.options.error=a.error),i.validate(e.value,e.rule.options||a,(e=>{const r=[];s&&s.length&&r.push.apply(r,s),e&&e.length&&r.push.apply(r,e),t(r.length?r:null)}))}else t(s)}i=i&&(r.required||!r.required&&e.value),r.field=e.field,r.asyncValidator?n=r.asyncValidator(r,e.value,s,e.source,a):r.validator&&(n=r.validator(r,e.value,s,e.source,a),!0===n?s():!1===n?s(r.message||`${r.field} fails`):n instanceof Array?s(n):n instanceof Error&&s(n.message)),n&&n.then&&n.then((()=>s()),(e=>s(e)))}),(e=>{!function(e){let t,r=[],n={};function i(e){if(Array.isArray(e)){let t;r=(t=r).concat.apply(t,e)}else r.push(e)}for(t=0;t<e.length;t++)i(e[t]);r.length?n=P(r):(r=null,n=null),l(r,n)}(e)}))},getType:function(e){if(void 0===e.type&&e.pattern instanceof RegExp&&(e.type="pattern"),"function"!=typeof e.validator&&e.type&&!N.hasOwnProperty(e.type))throw new Error(F("Unknown rule type %s",e.type));return e.type||"string"},getValidationMethod:function(e){if("function"==typeof e.validator)return e.validator;const t=Object.keys(e),r=t.indexOf("message");return-1!==r&&t.splice(r,1),1===t.length&&"required"===t[0]?N.required:N[this.getType(e)]||!1}},W.register=function(e,t){if("function"!=typeof t)throw new Error("Cannot register a validator by type, validator is not a function");N[e]=t},W.warning=$,W.messages=R,W.warning=function(){};const M=_({name:"u-form",mixins:[r,n,q],provide(){return{uForm:this}},data:()=>({formRules:{},validator:{},originalModel:null}),watch:{rules:{immediate:!0,handler(e){this.setRules(e)}},propsChange(e){var t;(null==(t=this.children)?void 0:t.length)&&this.children.map((e=>{"function"==typeof e.updateParentData&&e.updateParentData()}))},model:{immediate:!0,handler(e){this.originalModel||(this.originalModel=uni.$u.deepClone(e))}}},computed:{propsChange(){return[this.errorType,this.borderBottom,this.labelPosition,this.labelWidth,this.labelAlign,this.labelStyle]}},created(){this.children=[]},methods:{setRules(e){0!==Object.keys(e).length&&(this.formRules=e,this.validator=new W(e))},resetFields(){this.resetModel()},resetModel(e){this.children.map((e=>{const t=null==e?void 0:e.prop,r=uni.$u.getProperty(this.originalModel,t);uni.$u.setProperty(this.model,t,r)}))},clearValidate(e){e=[].concat(e),this.children.map((t=>{(void 0===e[0]||e.includes(t.prop))&&(t.message=null)}))},async validateField(e,t,r=null){this.$nextTick((()=>{const n=[];e=[].concat(e),this.children.map((t=>{const i=[];if(e.includes(t.prop)){const e=uni.$u.getProperty(this.model,t.prop),o=t.prop.split("."),s=o[o.length-1],a=this.formRules[t.prop];if(!a)return;const l=[].concat(a);for(let u=0;u<l.length;u++){const o=l[u],a=[].concat(null==o?void 0:o.trigger);if(r&&!a.includes(r))continue;new W({[s]:o}).validate({[s]:e},((e,r)=>{var o;uni.$u.test.array(e)&&(n.push(...e),i.push(...e)),t.message=(null==(o=i[0])?void 0:o.message)?i[0].message:null}))}}})),"function"==typeof t&&t(n)}))},validate(e){return new Promise(((e,t)=>{this.$nextTick((()=>{const r=this.children.map((e=>e.prop));this.validateField(r,(r=>{r.length?("toast"===this.errorType&&uni.$u.toast(r[0].message),t(r)):e(!0)}))}))}))}}},[["render",function(e,t,r,n,i,o){const u=h;return s(),a(u,{class:"u-form"},{default:l((()=>[d(e.$slots,"default")])),_:3})}]]);export{x as _,S as a,M as b};
