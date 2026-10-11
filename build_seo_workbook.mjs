import fs from "node:fs/promises";
import { FileBlob, SpreadsheetFile } from "file:///C:/Users/PC/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/@oai/artifact-tool/dist/artifact_tool.mjs";

const workbookPath = "outputs/01a0f5ad-fea7-73c0-9115-ba2b2f66ac3a/Ke-hoach-SEO-Bao-gia-EGO-Solar.xlsx";
const previewDir = "outputs/seo-egosolar/edited-previews";
const font = "Arial";
const c = { navy:"#16324F", blue:"#235789", amber:"#FFF2CC", red:"#F4CCCC", green:"#E2F0D9", white:"#FFFFFF", text:"#1F2937", border:"#D0D5DD", muted:"#667085" };

const input = await FileBlob.load(workbookPath);
const wb = await SpreadsheetFile.importXlsx(input);
console.log((await wb.inspect({kind:"sheet", include:"id,name", maxChars:4000})).ndjson);

const budget = wb.worksheets.getItem("Báo giá");
const keyword = wb.worksheets.getItem("Checklist Keyword");
const entity = wb.worksheets.getItem("Entity 300");
const traffic = wb.worksheets.getItem("Traffic User");
const report = wb.worksheets.getItem("Báo cáo tháng");
const oldKeywords = keyword.getRange("A7:K46").values;

function title(sheet, text, note, lastCol) {
  sheet.showGridLines = false;
  sheet.getRange(`A1:${lastCol}500`).format.font = {name:font,size:10,color:c.text};
  sheet.getRange("A2").values = [[text]];
  sheet.getRange("A2").format.font = {name:font,size:16,bold:true,color:c.navy};
  sheet.getRange(`A3:${lastCol}3`).format.borders = {bottom:{style:"medium",color:c.blue}};
  sheet.getRange("A4").values = [[note]];
  sheet.getRange("A4").format.font = {name:font,size:10,italic:true,color:c.muted};
}
function header(sheet, range) {
  const r=sheet.getRange(range);
  r.format={fill:c.navy,font:{name:font,size:10,bold:true,color:c.white},horizontalAlignment:"center",verticalAlignment:"center",wrapText:true,borders:{preset:"all",style:"thin",color:c.white}};
  r.format.rowHeight=38;
}
function body(sheet, range) {
  const r=sheet.getRange(range);
  r.format.borders={insideHorizontal:{style:"thin",color:c.border},bottom:{style:"thin",color:c.border}};
  r.format.verticalAlignment="center";
}
function statusValidation(range, values) {
  range.dataValidation={rule:{type:"list",values}};
  range.conditionalFormats.add("containsText",{text:"Hoàn thành",format:{fill:c.green,font:{color:"#2E7D32",bold:true}}});
  range.conditionalFormats.add("containsText",{text:"Live",format:{fill:c.green,font:{color:"#2E7D32",bold:true}}});
  range.conditionalFormats.add("containsText",{text:"Đang làm",format:{fill:c.amber,font:{color:"#8A5A00",bold:true}}});
  range.conditionalFormats.add("containsText",{text:"Chưa làm",format:{fill:c.red,font:{color:"#9C0006"}}});
}

// Ngân sách: chỉ giữ 3 khoản chi phí theo yêu cầu.
budget.getRange("A1:J60").clear({applyTo:"all"});
budget.name = "Ngân sách";
title(budget,"NGÂN SÁCH SEO EGO SOLAR","Ngân sách dự kiến cho ba hạng mục có chi phí. Đơn giá và số lượng là dữ liệu có thể chỉnh.","H");
budget.getRange("A6:H6").values=[["STT","Hạng mục","Đơn vị","Số lượng","Đơn giá","Thành tiền","Thời gian","Ghi chú"]];
header(budget,"A6:H6");
budget.getRange("A7:H9").values=[
  [1,"Backlink Entity 300","Link",300,5000,null,"30–45 ngày","Ngân sách 1.500.000đ"],
  [2,"Textlink báo / hệ thống 1.000 link","Link",1000,5000,null,"45–60 ngày","Ngân sách khoảng 5.000.000đ"],
  [3,"Traffic User người dùng thật","Lượt",3500,2000,null,"Theo tuần","2.000đ/lượt; ngân sách 7.000.000đ"]
];
budget.getRange("F7").formulas=[["=D7*E7"]]; budget.getRange("F7:F9").fillDown();
budget.getRange("A11:E11").values=[["TỔNG NGÂN SÁCH",null,null,null,null]];
budget.getRange("F11").formulas=[["=SUM(F7:F9)"]];
body(budget,"A7:H9");
budget.getRange("D7:E9").format.fill=c.amber;
budget.getRange("E7:F11").format.numberFormat="#,##0\" ₫\"";
budget.getRange("A11:H11").format={fill:c.navy,font:{name:font,bold:true,color:c.white}};
budget.getRange("A14:B17").values=[
  ["Quy ước", "Entity: 300 link × 5.000đ = 1.500.000đ"],
  ["Quy ước", "Textlink: 1.000 link × 5.000đ = 5.000.000đ"],
  ["Quy ước", "Traffic User: 3.500 lượt × 2.000đ = 7.000.000đ"],
  ["Tổng", "13.500.000đ"]
];
budget.getRange("A14:A17").format.font={name:font,bold:true,color:c.navy};
budget.getRange("A:A").format.columnWidth=7; budget.getRange("B:B").format.columnWidth=38; budget.getRange("C:C").format.columnWidth=13; budget.getRange("D:F").format.columnWidth=16; budget.getRange("G:G").format.columnWidth=16; budget.getRange("H:H").format.columnWidth=36;
budget.freezePanes.freezeRows(6);

// Keyword: kiểm tra đầu mỗi tuần từ 05/10/2026.
keyword.getRange("A1:W70").clear({applyTo:"all"});
title(keyword,"CHECKLIST KEYWORD THEO TUẦN","Kiểm tra một lần vào đầu tuần. Lịch bắt đầu từ thứ Hai 05/10/2026.","W");
const weekDates=[];
for(let i=0;i<13;i++) weekDates.push(new Date(2026,9,5+i*7));
const kwHeaders=["ID","Từ khóa","Cụm chủ đề","Ý định","Funnel","URL đích đề xuất","Ưu tiên","Volume",...weekDates,"Trạng thái","Ghi chú"];
keyword.getRange("A6:W6").values=[kwHeaders];
header(keyword,"A6:W6");
keyword.getRange("I6:U6").format.numberFormat="dd/mm";
const remapped=oldKeywords.map(row=>[
  row[0],row[1],row[2],row[3],row[4],row[5],row[6],row[7],
  row[8]??null,null,null,null,null,null,null,null,null,null,null,null,null,
  row[9]??"Chưa làm",row[10]??null
]);
keyword.getRange("A7:W46").values=remapped;
body(keyword,"A7:W46");
keyword.getRange("H7:U46").format.fill=c.amber;
keyword.getRange("I7:U46").format.numberFormat="0";
statusValidation(keyword.getRange("V7:V46"),["Chưa làm","Đang làm","Hoàn thành","Tạm dừng"]);
keyword.getRange("D7:D46").dataValidation={rule:{type:"list",values:["Informational","Commercial","Transactional","Navigational"]}};
keyword.getRange("G7:G46").dataValidation={rule:{type:"list",values:["P0","P1","P2"]}};
keyword.freezePanes.freezeRows(6); keyword.freezePanes.freezeColumns(2);
const kwWidths=[8,32,18,15,10,34,9,10]; kwWidths.forEach((w,i)=>keyword.getRangeByIndexes(0,i,1,1).format.columnWidth=w);
for(let i=8;i<=20;i++) keyword.getRangeByIndexes(0,i,1,1).format.columnWidth=10;
keyword.getRange("V:V").format.columnWidth=14; keyword.getRange("W:W").format.columnWidth=28;

// Entity: nhật ký chi tiết 300 link.
entity.getRange("A1:N360").clear({applyTo:"all"});
title(entity,"NHẬT KÝ BACKLINK ENTITY 300","Điền từng link thực tế để bàn giao: link mẫu, URL live, tài khoản, mật khẩu, Gmail và trạng thái index.","N");
entity.getRange("A6:N6").values=[["STT","Nhóm Entity","Nền tảng","Link mẫu","Link URL","URL đích","Tài khoản","Mật khẩu","Gmail","Trạng thái link","Index / No Index","Ngày tạo","Ngày check","Ghi chú"]];
header(entity,"A6:N6");
const allocations=[["Social profile",35],["Business directory",40],["Map và local citation",20],["Web 2.0 branded",30],["Forum profile",30],["Image sharing",20],["Video profile",15],["Document sharing",15],["Audio/Podcast profile",10],["Citation doanh nghiệp Việt Nam",25],["Danh bạ ngành năng lượng",15],["Brand mention có link",20],["Hồ sơ nhà cung cấp/đối tác",10],["Q&A profile",10],["Company/press profile",5]];
const entityRows=[]; let stt=1;
for(const [group,count] of allocations){ for(let i=0;i<count;i++) entityRows.push([stt++,group,null,null,null,"https://egosolar.com.vn/",null,null,null,"Chưa làm","Chưa kiểm",null,null,null]); }
entity.getRange("A7:N306").values=entityRows;
body(entity,"A7:N306");
entity.getRange("C7:E306").format.fill=c.amber; entity.getRange("G7:I306").format.fill=c.amber; entity.getRange("L7:M306").format.fill=c.amber;
entity.getRange("L7:M306").format.numberFormat="dd/mm/yyyy";
statusValidation(entity.getRange("J7:J306"),["Chưa làm","Đang làm","Live","Lỗi","Cần thay"]);
entity.getRange("K7:K306").dataValidation={rule:{type:"list",values:["Chưa kiểm","Index","No Index"]}};
entity.getRange("K7:K306").conditionalFormats.add("containsText",{text:"Index",format:{fill:c.green,font:{color:"#2E7D32",bold:true}}});
entity.getRange("K7:K306").conditionalFormats.add("containsText",{text:"No Index",format:{fill:c.red,font:{color:"#9C0006",bold:true}}});
entity.getRange("A308:C308").values=[["TỔNG",300,null]];
entity.getRange("D308").values=[["Live"]]; entity.getRange("E308").formulas=[["=COUNTIF(J7:J306,\"Live\")"]];
entity.getRange("F308").values=[["Index"]]; entity.getRange("G308").formulas=[["=COUNTIF(K7:K306,\"Index\")"]];
entity.getRange("H308").values=[["No Index"]]; entity.getRange("I308").formulas=[["=COUNTIF(K7:K306,\"No Index\")"]];
entity.getRange("A308:N308").format={fill:c.navy,font:{name:font,bold:true,color:c.white}};
entity.freezePanes.freezeRows(6); entity.freezePanes.freezeColumns(2);
const entWidths=[7,27,22,34,38,30,18,18,28,16,16,13,13,28]; entWidths.forEach((w,i)=>entity.getRangeByIndexes(0,i,1,1).format.columnWidth=w);

// Traffic User: 2.000đ/lượt, ngân sách 7 triệu, theo dõi đầu tuần.
traffic.getRange("A1:K80").clear({applyTo:"all"});
title(traffic,"NGÂN SÁCH VÀ CHECKLIST TRAFFIC USER","Đơn giá 2.000đ/lượt. Ngân sách 7.000.000đ tương ứng 3.500 lượt người dùng thật.","I");
traffic.getRange("A6:H6").values=[["Gói","Đơn giá/lượt","Ngân sách","Lượt dự kiến","Lượt thực tế","Chi phí thực tế","Ngân sách còn lại","Ghi chú"]];
header(traffic,"A6:H6");
traffic.getRange("A7:H7").values=[["Traffic User người dùng thật",2000,7000000,null,null,null,null,"Không sử dụng bot hoặc auto-refresh"]];
traffic.getRange("D7").formulas=[["=C7/B7"]]; traffic.getRange("F7").formulas=[["=IF(E7=\"\",0,E7*B7)"]]; traffic.getRange("G7").formulas=[["=C7-F7"]];
body(traffic,"A7:H7");
traffic.getRange("B7:C7").format.fill=c.amber; traffic.getRange("E7").format.fill=c.amber;
traffic.getRange("B7:C7").format.numberFormat="#,##0\" ₫\""; traffic.getRange("F7:G7").format.numberFormat="#,##0\" ₫\"";
traffic.getRange("A10:I10").values=[["Tuần","Ngày check","Mục tiêu lũy kế","Thực tế lũy kế","Chi phí lũy kế","Chênh lệch lượt","Engaged rate","Lead","Ghi chú"]];
header(traffic,"A10:I10");
const trafficRows=weekDates.map((d,i)=>[`Tuần ${i+1}`,d,null,null,null,null,null,null,null]);
traffic.getRange("A11:I23").values=trafficRows;
for(let r=11;r<=23;r++){ const idx=r-10; traffic.getRange(`C${r}`).formulas=[[`=ROUND($D$7/13*${idx},0)`]]; traffic.getRange(`E${r}`).formulas=[[`=IF(D${r}=\"\",0,D${r}*$B$7)`]]; traffic.getRange(`F${r}`).formulas=[[`=IF(D${r}=\"\",\"\",D${r}-C${r})`]]; }
body(traffic,"A11:I23");
traffic.getRange("B11:B23").format.numberFormat="dd/mm/yyyy"; traffic.getRange("D11:D23").format.fill=c.amber; traffic.getRange("G11:I23").format.fill=c.amber;
traffic.getRange("E11:E23").format.numberFormat="#,##0\" ₫\""; traffic.getRange("G11:G23").format.numberFormat="0.0%";
traffic.freezePanes.freezeRows(10);
const trWidths=[14,16,18,18,18,18,16,12,36]; trWidths.forEach((w,i)=>traffic.getRangeByIndexes(0,i,1,1).format.columnWidth=w);

report.getRange("G27").values=[[3500]];
wb.recalculate();

console.log((await wb.inspect({kind:"table",range:"Ngân sách!A6:H17",include:"values,formulas",tableMaxRows:20,tableMaxCols:10})).ndjson);
console.log((await wb.inspect({kind:"table",range:"Checklist Keyword!A6:W9",include:"values,formulas",tableMaxRows:6,tableMaxCols:23})).ndjson);
console.log((await wb.inspect({kind:"table",range:"Entity 300!A6:N12",include:"values,formulas",tableMaxRows:8,tableMaxCols:14})).ndjson);
console.log((await wb.inspect({kind:"match",searchTerm:"#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A|#NUM!|#NULL!|#SPILL!|#CALC!",options:{useRegex:true,maxResults:300},summary:"final formula error scan"})).ndjson);

await fs.mkdir(previewDir,{recursive:true});
for(const name of ["Ngân sách","Checklist Keyword","Entity 300","Traffic User","Báo cáo tháng"]){
  const preview=await wb.render({sheetName:name,autoCrop:"all",scale:1,format:"png"});
  await fs.writeFile(`${previewDir}/${name.replace(/[^a-zA-Z0-9]/g,"-")}.png`,new Uint8Array(await preview.arrayBuffer()));
}
const output=await SpreadsheetFile.exportXlsx(wb);
await output.save(workbookPath);
console.log(`SAVED ${workbookPath}`);
