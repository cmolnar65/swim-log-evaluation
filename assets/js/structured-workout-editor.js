/* Structured workout tree editor: DOM-only, no remote calls or HTML injection. */
(function () {
  'use strict';
  const initialize = function (root) {
  const hidden = document.getElementById(root.dataset.input || 'swimlog-structure-document');
  const form = root.closest('form');
  let doc;
  try { doc = JSON.parse(root.dataset.document); } catch (e) { return; }
  const strokes = ['FR', 'BR', 'BK', 'FL', 'MX'];
  const standardEquipment = ['Kickboard', 'Pull buoy', 'Paddles', 'Fins', 'Snorkel', 'Ankle strap', 'Tempo trainer', 'Parachute'];
  const make = (tag, label, parent) => {
    const el = document.createElement(tag);
    if (label !== null) el.textContent = label;
    if (parent) parent.appendChild(el);
    return el;
  };
  const field = (parent, label, value, callback, type = 'text') => {
    const wrap = make('label', null, parent);
    wrap.className = 'swimlog-structure-field';
    make('span', label + ' ', wrap);
    const input = make('input', null, wrap);
    input.type = type;
    input.value = value == null ? '' : value;
    if (type === 'number') { input.min = '0'; input.step = 'any'; }
    input.addEventListener('input', () => { callback(input.value); preview(); });
    return input;
  };
  const select = (parent, label, value, choices, callback) => {
    const wrap = make('label', null, parent);
    wrap.className = 'swimlog-structure-field';
    make('span', label + ' ', wrap);
    const input = make('select', null, wrap);
    choices.forEach(x => { const opt = make('option', x, input); opt.value = x; });
    input.value = value;
    input.addEventListener('change', () => { callback(input.value); render(); });
  };
  const button = (parent, label, action) => {
    const b = make('button', label, parent);
    b.type = 'button'; b.className = 'button';
    b.addEventListener('click', action);
  };
  const equipmentField = (parent, node) => {
    const group = make('fieldset', null, parent);
    group.className = 'swimlog-equipment';
    make('legend', 'Equipment', group);
    const selected = Array.isArray(node.equipment) ? node.equipment.slice() : [];
    const known = standardEquipment.map(x => x.toLowerCase());
    const options = make('div', null, group);
    options.className = 'swimlog-equipment-options';
    standardEquipment.forEach(name => {
      const label = make('label', null, options);
      const check = make('input', null, label);
      check.type = 'checkbox';
      check.checked = selected.some(x => x.toLowerCase() === name.toLowerCase());
      make('span', name, label);
      check.addEventListener('change', () => {
        node.equipment = (node.equipment || []).filter(x => x.toLowerCase() !== name.toLowerCase());
        if (check.checked) node.equipment.push(name);
        preview();
      });
    });
    const custom = make('label', null, group);
    custom.className = 'swimlog-structure-field';
    make('span', 'Other equipment (comma separated)', custom);
    const input = make('input', null, custom);
    input.type = 'text';
    input.value = selected.filter(x => !known.includes(x.toLowerCase())).join(', ');
    input.addEventListener('input', () => {
      const standard = (node.equipment || []).filter(x => known.includes(x.toLowerCase()));
      const extra = input.value.split(',').map(x => x.trim()).filter(Boolean);
      node.equipment = standard.concat(extra);
      preview();
    });
  };
  const step = () => ({type:'step', distance:25, stroke:'FR', pace:null, rest:null, description:null, drill:false, equipment:[]});
  const repeat = () => ({type:'repeat', count:2, children:[step()]});
  const ladder = () => ({type:'ladder', rungs:[repeat()], rest:'0'});
  const section = () => ({type:'section', label:'New Section', children:[step()]});
  const addControls = (el, children, isLadder) => {
    button(el, '+ Step', () => { children.push(step()); render(); });
    button(el, '+ Repeat', () => { children.push(repeat()); render(); });
    if (!isLadder) button(el, '+ Ladder', () => { children.push(ladder()); render(); });
  };
  const nodeUI = (parent, node, siblings, index, inLadder) => {
    const panel = make('fieldset', null, parent);
    panel.className = 'swimlog-structure-panel';
    make('legend', node.type.toUpperCase(), panel);
    const actions = make('div', null, panel);
    actions.className = 'swimlog-structure-actions';
    const fields = make('div', null, panel);
    fields.className = 'swimlog-structure-fields';
    button(actions, '↑', () => { if(index){ [siblings[index-1],siblings[index]]=[siblings[index],siblings[index-1]];render(); } });
    button(actions, '↓', () => { if(index<siblings.length-1){ [siblings[index+1],siblings[index]]=[siblings[index],siblings[index+1]];render(); } });
    button(actions, 'Remove', () => { if(siblings.length>1){siblings.splice(index,1);render();} else alert('At least one item is required in each group.'); });
    if (node.type === 'section') field(fields, 'Section', node.label, v=>node.label=v);
    if (node.type === 'repeat') field(fields, 'Repeat count', node.count, v=>node.count=Number(v), 'number');
    if (node.type === 'step') {
      field(fields, 'Distance', node.distance, v=>node.distance=Number(v), 'number');
      select(fields, 'Stroke', node.stroke, inLadder ? strokes.slice(0,4) : strokes, v=>node.stroke=v);
      const wrap = make('label', 'Drill ', fields);
      wrap.className = 'swimlog-structure-checkbox';
      const check = make('input', null, wrap); check.type='checkbox';check.checked=!!node.drill;
      check.addEventListener('change',()=>{node.drill=check.checked;preview();});
    }
    field(fields, 'Pace target', node.pace, v=>node.pace=v===''?null:v);
    field(fields, 'Rest (0 = none; blank = unspecified)', node.rest, v=>node.rest=v===''?null:v);
    field(fields, 'Description', node.description, v=>node.description=v===''?null:v);
    if (node.type === 'step') equipmentField(panel, node);
    if (node.type !== 'step') {
      const children = node.type==='ladder' ? node.rungs : node.children;
      const nested = make('div', null, panel);nested.className='swimlog-structure-nested';
      children.forEach((child,i)=>nodeUI(nested,child,children,i,inLadder||node.type==='ladder'));
      addControls(panel,children,node.type==='ladder'||inLadder);
    }
  };
  const fmt = n => Number.isFinite(n) ? Number(n.toFixed(3)).toLocaleString() : 'Invalid';
  const summarize = (node, factor, totals, ladderContext) => {
    if (node.type==='step') {
      const d=Number(node.distance)*factor;
      if (!Number.isFinite(d)||d<=0||!strokes.includes(node.stroke)||(ladderContext&&node.stroke==='MX')) throw Error('Invalid step distance or stroke');
      totals.distance+=d;totals.strokes[node.stroke]+=d;totals.steps+=factor;
      if(totals.steps>10000)throw Error('Too many expanded steps');
    } else {
      const kids=node.type==='ladder'?node.rungs:node.children;
      if(!Array.isArray(kids)||!kids.length)throw Error('Empty group');
      const times=node.type==='repeat'?Number(node.count):1;
      if(!Number.isInteger(times)||times<1||times>100)throw Error('Invalid repeat count');
      kids.forEach(x=>summarize(x,factor*times,totals,ladderContext||node.type==='ladder'));
    }
  };
  const preview = () => {
    hidden.value=JSON.stringify(doc);
    const output=root.querySelector('[data-swimlog-preview]');
    const totals={distance:0,steps:0,strokes:{FR:0,BR:0,BK:0,FL:0,MX:0}};
    try {
      doc.sections.forEach(s=>summarize(s,1,totals,false));
      output.textContent='Total: '+fmt(totals.distance)+' '+doc.unit+' · '+totals.steps+' swim steps · '+
        Object.entries(totals.strokes).filter(x=>x[1]).map(x=>x[0]+': '+fmt(x[1])).join(' · ');
    } catch(e) { output.textContent='Check workout: '+e.message; }
  };
  const render = () => {
    root.replaceChildren();
    const unitRow = make('div', null, root); unitRow.className = 'swimlog-structure-unit';
    select(unitRow,'Distance unit',doc.unit,['m','yd'],v=>doc.unit=v);
    doc.sections.forEach((s,i)=>nodeUI(root,s,doc.sections,i,false));
    button(root,'+ Section',()=>{doc.sections.push(section());render();});
    const box=make('div',null,root);
    box.style.cssText='margin:16px 0;padding:12px;background:#f0f6fc;border-left:4px solid #2271b1';
    make('strong','Workout preview',box);
    const out=make('p',null,box);out.dataset.swimlogPreview='1';
    preview();
  };
  form.addEventListener('submit',()=>{hidden.value=JSON.stringify(doc);});
  render();
  };
  document.querySelectorAll('[data-swimlog-pdf-import]').forEach(form => {
    const candidates = Array.from(form.querySelectorAll('.swimlog-pdf-candidate'));
    candidates.forEach(candidate => {
      const selection = candidate.querySelector('[name="swimlog_pdf_selected[]"]');
      const required = Array.from(candidate.querySelectorAll('[name$="[total]"], [name$="[unit]"], [name$="[reviewed]"]'));
      const update = () => { required.forEach(input => { input.required = selection.checked; }); };
      const approval = candidate.querySelector('[name$="[reviewed]"]');
      const draft = event => { if (event.target !== selection && event.target !== approval) approval.checked = false; };
      candidate.addEventListener('input', draft);
      candidate.addEventListener('change', draft);
      candidate.addEventListener('click', event => {
        if (event.target.closest('[data-swimlog-pdf-editor]') && event.target.closest('button')) approval.checked = false;
      });
      selection.addEventListener('change', update);
      update();
    });
    form.addEventListener('submit', () => {
      // Exclude unselected candidates so large plans do not exhaust PHP max_input_vars.
      candidates.forEach(candidate => {
        if (candidate.querySelector('[name="swimlog_pdf_selected[]"]').checked) return;
        candidate.querySelectorAll('input, select, textarea').forEach(input => { input.disabled = true; });
      });
    });
  });
  document.querySelectorAll('#swimlog-structure-editor, [data-swimlog-pdf-editor]').forEach(root => {
    const details = root.closest('details');
    if (!root.hasAttribute('data-swimlog-pdf-editor') || !details || details.open) initialize(root);
    else {
      const open = () => {
        if (!details.open) return;
        details.removeEventListener('toggle', open);
        initialize(root);
      };
      details.addEventListener('toggle', open);
    }
  });
}());
