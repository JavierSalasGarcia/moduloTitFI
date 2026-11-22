(() => {
 'use strict';
 const make=(tag,text)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;return n;};
 const visibility=document.querySelector('#grupo-visibilidad'),audience=document.querySelector('#grupo-audiencia');
 if(visibility&&audience){const sync=()=>{audience.hidden=visibility.value!=='publico';};visibility.addEventListener('change',sync);sync();}
})();
