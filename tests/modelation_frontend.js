"use strict";
const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('assets/modelation.js','utf8');
const listeners={};
const context={console,Intl,JSON,Number,Math,Date,Set,Error,Promise,setTimeout,clearTimeout,location:{hash:''},document:{addEventListener:(n,f)=>{listeners[n]=f;}},addEventListener:(n,f)=>{listeners[n]=f;},crypto:{randomUUID:()=> 'uuid-test'}};
context.globalThis=context;
vm.createContext(context);vm.runInContext(source,context,{filename:'assets/modelation.js'});
const api=context.MojePenizeModelation;
assert(api,'frontend API must be exported');
assert.strictEqual(api.ENDPOINT,'/api/modelations/calculate.php');
const valid={success:true,calculation_status:'OK',engine_version:'1.0.0',nominal_value:100,real_value:90,cumulative_contributions:80,cumulative_entry_fees:1,cumulative_ongoing_fees:2,cumulative_fixed_fees:3,cumulative_total_fees:6,net_gain:20,assets:{asset_1:{nominal_value:100,real_value:90,cumulative_contributions:80,cumulative_entry_fees:1,cumulative_ongoing_fees:2,cumulative_fixed_fees:3,cumulative_total_fees:6,net_gain:20}}};
assert.strictEqual(api.validateSuccess(valid),true,'valid CE success response accepted');
for(const bad of [{...valid,success:false},{...valid,engine_version:'1.0.1'},{...valid,calculation_status:'FAIL'},{...valid,nominal_value:'100'},{...valid,assets:null},{...valid,assets:{asset_1:{...valid.assets.asset_1,net_gain:NaN}}}])assert.strictEqual(api.validateSuccess(bad),false,'invalid success response must fail closed');

function fakeRow(id){return{querySelector(selector){if(selector==='[name="asset_id"]')return{value:id};return null;}};}
const first=fakeRow('asset-first'),second=fakeRow('asset-second');
const fakeForm={querySelectorAll(selector){return selector==='.modelation-asset'?[first,second]:[];}};
let target=api.resolve422Target(fakeForm,{code:'FIXED_FEE_NEGATIVE',asset_index:1,asset_id:'asset-second'});
assert.strictEqual(target.field,'fixed_fee_monthly','422 field resolved');
assert.strictEqual(target.row,second,'422 for second asset maps to second row');
target=api.resolve422Target(fakeForm,{code:'ONGOING_FEE_OUT_OF_RANGE',asset_index:0,asset_id:'asset-second'});
assert.strictEqual(target.row,second,'asset_id wins over stale index');
target=api.resolve422Target(fakeForm,{code:'FIXED_FEE_NEGATIVE'});
assert.strictEqual(target.row,null,'asset error without identity must not be guessed onto first row');

assert(source.includes("method:'POST'"),'POST is used');
assert(source.includes("'Content-Type':'application/json'"),'JSON content type is used');
assert(source.includes('response.status===422'),'HTTP 422 is handled');
assert(source.includes('map422(form,body)'),'422 is mapped to form errors');
assert(source.includes("throw new Error('Odpověď serveru neodpovídá kontraktu CE-1.0.0. Výsledek nebyl použit.')"),'invalid response fails closed');
assert(source.includes("preview.innerHTML='<h2>Výsledek modelace</h2><p class=\"error\">Výsledek není dostupný. Neplatná nebo neověřená odpověď se nezobrazuje.</p>'"),'fail-closed UI suppresses unverified result');
console.log('OK modelation frontend: POST + 422 correct asset row + success validation + fail-closed');
