( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
 'use strict';
 var registerBlockType=blocks.registerBlockType,InspectorControls=blockEditor.InspectorControls,PanelBody=components.PanelBody,SelectControl=components.SelectControl,TextControl=components.TextControl,createElement=element.createElement,__=i18n.__,domain='chriss-swim-training-progress-evaluation';
 var distances=[0,50,100,200,500,800,1000,1500,1650,2000,2500,3300,5000];
 registerBlockType('swimlog/progress',{
  edit:function(props){
   var a=props.attributes;
   var distanceOptions=distances.map(function(v){return {label:v?String(v):__('Select distance',domain),value:v};});
   var controls=createElement(InspectorControls,null,createElement(PanelBody,{title:__('Progress settings',domain),initialOpen:true},
    createElement(TextControl,{label:__('Swimmer user ID override',domain),help:__('Leave blank to use the page or post author. There is no logged-in-viewer fallback.',domain),type:'number',min:1,value:a.user||'',onChange:function(v){props.setAttributes({user:v?parseInt(v,10):0});}}),
    createElement(SelectControl,{label:__('Course',domain),help:__('Configured default uses the site default course.',domain),value:a.course||'',options:[{label:__('Configured default',domain),value:''},{label:__('Meters',domain),value:'m'},{label:__('Yards',domain),value:'yd'}],onChange:function(v){props.setAttributes({course:v});}}),
    createElement(SelectControl,{label:__('Stroke',domain),value:a.stroke||'',options:[{label:__('Select stroke',domain),value:''},{label:__('Freestyle',domain),value:'FR'},{label:__('Breaststroke',domain),value:'BR'},{label:__('Backstroke',domain),value:'BACK'},{label:__('Butterfly',domain),value:'FLY'},{label:__('Mixed',domain),value:'MIXED'}],onChange:function(v){props.setAttributes({stroke:v});}}),
    createElement(SelectControl,{label:__('Distance',domain),value:a.distance||0,options:distanceOptions,onChange:function(v){props.setAttributes({distance:parseInt(v,10)||0});}}),
    createElement(SelectControl,{label:__('Heading',domain),value:a.titleMode||'default',options:[{label:__('Default heading',domain),value:'default'},{label:__('Hide heading',domain),value:'hidden'},{label:__('Custom heading',domain),value:'custom'}],onChange:function(v){props.setAttributes({titleMode:v});}}),
    a.titleMode==='custom'?createElement(TextControl,{label:__('Custom heading text',domain),value:a.customTitle||'',onChange:function(v){props.setAttributes({customTitle:v});}}):null
   ));
   var ready=a.stroke&&a.distance;
   var preview=ready?createElement(ServerSideRender,{block:'swimlog/progress',attributes:a}):createElement('p',null,__('Select a stroke and distance to preview progress.',domain));
   return createElement('div',blockEditor.useBlockProps(),controls,preview);
  },
  save:function(){return null;}
 });
})(window.wp.blocks,window.wp.blockEditor,window.wp.components,window.wp.element,window.wp.i18n,window.wp.serverSideRender);
