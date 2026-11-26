import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(process.argv[2],'utf8');
const start=source.indexOf('  async function catalogs(){');
const end=source.indexOf("\n  if(key==='recepciones')",start);
assert(start>=0&&end>start);
const fragment=source.slice(start,end);
function fixture({context={bodegas:[{id:7,nombre:'Bodega autorizada'}]},closed=false,fail=false}={}){
 const calls=[],alerts=[];
 const select=()=>({items:[],append(x){this.items.push(x)},replaceChildren(...x){this.items=x}});
 const docType=select(),depot=select(),dialog={open:!closed};
 const api={
  documentTypes:async({page})=>{calls.push(['tipos',page]);return {data:page===1?[{id:33,nombre:'Factura',documento_compra:1,familia:'FACTURA',codigo_sii:33}]:[{id:61,nombre:'NC',documento_compra:1,familia:'NC'}],last_page:2}},
  purchaseConsultation:async(...args)=>{calls.push(args);if(fail)throw Error('Sesión vencida');return context},
  resources:()=>{throw Error('No debe utilizar catálogo global')}
 };
 const sandbox={api,dialog,docType,depot,docStatus:{},option:(value,text)=>({value,text,dataset:{}}),hd93Alert:(_,m)=>alerts.push(m),hd93Error:e=>e.message};
 const run=vm.runInNewContext(fragment+'\n catalogs();',sandbox);
 return {run,calls,alerts,docType,depot};
}
let f=fixture();await f.run;
assert.equal(JSON.stringify(f.calls.filter(x=>x[0]==='recepciones')),JSON.stringify([['recepciones',1,'',{}]]));
assert.deepEqual(f.depot.items.map(x=>x.value),['','7']);
assert.deepEqual(f.docType.items.map(x=>x.value),['33']);
for(const options of [{context:{}},{context:{bodegas:[]}},{fail:true}]){
 f=fixture(options);await assert.rejects(f.run);assert.equal(f.depot.items.length,0);assert.equal(f.alerts.length,1);
}
f=fixture({closed:true});await f.run;assert.equal(f.depot.items.length,0);
console.log('V108B4_FRONT_CATALOGOS_OK');
