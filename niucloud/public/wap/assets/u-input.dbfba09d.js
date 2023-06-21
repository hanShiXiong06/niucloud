import{_ as e}from"./u-icon.a36c3c0e.js";import{t,x as n,y as o,l as i,m as a,e as l,f as r,w as u,h as s,A as p,k as d,n as c,D as f,p as h,I as y}from"./index-e80c244a.js";import{_ as m}from"./_plugin-vue_export-helper.1b428a4d.js";const g={props:{modelValue:{type:[String,Number],default:t.input.value},type:{type:String,default:t.input.type},fixed:{type:Boolean,default:t.input.fixed},disabled:{type:Boolean,default:t.input.disabled},disabledColor:{type:String,default:t.input.disabledColor},clearable:{type:Boolean,default:t.input.clearable},password:{type:Boolean,default:t.input.password},maxlength:{type:[String,Number],default:t.input.maxlength},placeholder:{type:String,default:t.input.placeholder},placeholderClass:{type:String,default:t.input.placeholderClass},placeholderStyle:{type:[String,Object],default:t.input.placeholderStyle},showWordLimit:{type:Boolean,default:t.input.showWordLimit},confirmType:{type:String,default:t.input.confirmType},confirmHold:{type:Boolean,default:t.input.confirmHold},holdKeyboard:{type:Boolean,default:t.input.holdKeyboard},focus:{type:Boolean,default:t.input.focus},autoBlur:{type:Boolean,default:t.input.autoBlur},disableDefaultPadding:{type:Boolean,default:t.input.disableDefaultPadding},cursor:{type:[String,Number],default:t.input.cursor},cursorSpacing:{type:[String,Number],default:t.input.cursorSpacing},selectionStart:{type:[String,Number],default:t.input.selectionStart},selectionEnd:{type:[String,Number],default:t.input.selectionEnd},adjustPosition:{type:Boolean,default:t.input.adjustPosition},inputAlign:{type:String,default:t.input.inputAlign},fontSize:{type:[String,Number],default:t.input.fontSize},color:{type:String,default:t.input.color},prefixIcon:{type:String,default:t.input.prefixIcon},prefixIconStyle:{type:[String,Object],default:t.input.prefixIconStyle},suffixIcon:{type:String,default:t.input.suffixIcon},suffixIconStyle:{type:[String,Object],default:t.input.suffixIconStyle},border:{type:String,default:t.input.border},readonly:{type:Boolean,default:t.input.readonly},shape:{type:String,default:t.input.shape},formatter:{type:[Function,null],default:t.input.formatter},ignoreCompositionEvent:{type:Boolean,default:!0}}};const S=m({name:"u-input",mixins:[n,o,g],data:()=>({innerValue:"",focused:!1,firstChange:!0,changeFromInner:!1,innerFormatter:e=>e}),watch:{modelValue:{immediate:!0,handler(e,t){this.innerValue=e,!1===this.firstChange&&!1===this.changeFromInner&&this.valueChange(),this.firstChange=!1,this.changeFromInner=!1}}},computed:{isShowClear(){const{clearable:e,readonly:t,focused:n,innerValue:o}=this;return!!e&&!t&&!!n&&""!==o},inputClass(){let e=[],{border:t,disabled:n,shape:o}=this;return"surround"===t&&(e=e.concat(["u-border","u-input--radius"])),e.push(`u-input--${o}`),"bottom"===t&&(e=e.concat(["u-border-bottom","u-input--no-radius"])),e.join(" ")},wrapperStyle(){const e={};return this.disabled&&(e.backgroundColor=this.disabledColor),"none"===this.border?e.padding="0":(e.paddingTop="6px",e.paddingBottom="6px",e.paddingLeft="9px",e.paddingRight="9px"),uni.$u.deepMerge(e,uni.$u.addStyle(this.customStyle))},inputStyle(){return{color:this.color,fontSize:uni.$u.addUnit(this.fontSize),textAlign:this.inputAlign}}},emits:["update:modelValue","focus","blur","change","confirm","clear","keyboardheightchange"],methods:{setFormatter(e){this.innerFormatter=e},onInput(e){let{value:t=""}=e.detail||{};const n=(this.formatter||this.innerFormatter)(t);this.innerValue=t,this.$nextTick((()=>{this.innerValue=n,this.valueChange()}))},onBlur(e){this.$emit("blur",e.detail.value),uni.$u.sleep(50).then((()=>{this.focused=!1})),uni.$u.formValidate(this,"blur")},onFocus(e){this.focused=!0,this.$emit("focus")},onConfirm(e){this.$emit("confirm",this.innerValue)},onkeyboardheightchange(){this.$emit("keyboardheightchange")},valueChange(){const e=this.innerValue;this.$nextTick((()=>{this.$emit("update:modelValue",e),this.changeFromInner=!0,this.$emit("change",e),uni.$u.formValidate(this,"change")}))},onClear(){this.innerValue="",this.$nextTick((()=>{this.valueChange(),this.$emit("clear")}))},clickHandler(){}}},[["render",function(t,n,o,m,g,S){const b=i(a("u-icon"),e),x=h,_=y;return l(),r(x,{class:f(["u-input",S.inputClass]),style:c([S.wrapperStyle])},{default:u((()=>[s(x,{class:"u-input__content"},{default:u((()=>[t.prefixIcon||t.$slots.prefix?(l(),r(x,{key:0,class:"u-input__content__prefix-icon"},{default:u((()=>[p(t.$slots,"prefix",{},(()=>[s(b,{name:t.prefixIcon,size:"18",customStyle:t.prefixIconStyle},null,8,["name","customStyle"])]),!0)])),_:3})):d("",!0),s(x,{class:"u-input__content__field-wrapper",onClick:S.clickHandler},{default:u((()=>[s(_,{class:"u-input__content__field-wrapper__field",style:c([S.inputStyle]),type:t.type,focus:t.focus,cursor:t.cursor,value:g.innerValue,"auto-blur":t.autoBlur,disabled:t.disabled||t.readonly,maxlength:t.maxlength,placeholder:t.placeholder,"placeholder-style":t.placeholderStyle,"placeholder-class":t.placeholderClass,"confirm-type":t.confirmType,"confirm-hold":t.confirmHold,"hold-keyboard":t.holdKeyboard,"cursor-spacing":t.cursorSpacing,"adjust-position":t.adjustPosition,"selection-end":t.selectionEnd,"selection-start":t.selectionStart,password:t.password||"password"===t.type||void 0,ignoreCompositionEvent:t.ignoreCompositionEvent,onInput:S.onInput,onBlur:S.onBlur,onFocus:S.onFocus,onConfirm:S.onConfirm,onKeyboardheightchange:S.onkeyboardheightchange},null,8,["style","type","focus","cursor","value","auto-blur","disabled","maxlength","placeholder","placeholder-style","placeholder-class","confirm-type","confirm-hold","hold-keyboard","cursor-spacing","adjust-position","selection-end","selection-start","password","ignoreCompositionEvent","onInput","onBlur","onFocus","onConfirm","onKeyboardheightchange"])])),_:1},8,["onClick"]),S.isShowClear?(l(),r(x,{key:1,class:"u-input__content__clear",onClick:S.onClear},{default:u((()=>[s(b,{name:"close",size:"11",color:"#ffffff",customStyle:"line-height: 12px"})])),_:1},8,["onClick"])):d("",!0),t.suffixIcon||t.$slots.suffix?(l(),r(x,{key:2,class:"u-input__content__subfix-icon"},{default:u((()=>[p(t.$slots,"suffix",{},(()=>[s(b,{name:t.suffixIcon,size:"18",customStyle:t.suffixIconStyle},null,8,["name","customStyle"])]),!0)])),_:3})):d("",!0)])),_:3})])),_:3},8,["class","style"])}],["__scopeId","data-v-afd9dafc"]]);export{S as _,g as p};
