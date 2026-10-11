const fs = require('fs');
const path = require('path');

const inputPath = 'C:\\Users\\PC\\.gemini\\antigravity-ide\\brain\\4bdd5c8a-fb7f-402c-b2d8-2e93b31039c4\\scratch\\audit.json';
const reportDir = 'D:\\Source Dev\\crm.egosolar.com.vn\\crm.egosolar.com.vn\\Report\\ui\\phase-2b-batch-3';

if (!fs.existsSync(reportDir)) {
    fs.mkdirSync(reportDir, { recursive: true });
}

const audit = JSON.parse(fs.readFileSync(inputPath, 'utf8'));
const data = audit.Data;

// Create CSV
let csv = 'Path,Extends,Module,StyleBlocks,ScriptBlocks,StyleAttrs,OnAttrs,HasBladeDynamic,Group,Risk\n';
for (const row of data) {
    csv += "","","",,,,,,"",""\n;
}
fs.writeFileSync(path.join(reportDir, 'inline-code-inventory.csv'), csv);

// Create MD Inventory
let md = '# UI PHASE 2B — BATCH 3: INLINE CODE INVENTORY\n\n';
md += **Total Files Scanned:** \n;
md += **Files with Inline Code:** \n\n;

md += '## Inventory Data\n\n';
md += '| Path | Module | Risk | Group | <style> | <script> | style="" | on*="" |\n';
md += '| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |\n';
for (const row of data) {
    md += | \${row.Path}\ |  | **** |  |  |  |  |  |\n;
}
fs.writeFileSync(path.join(reportDir, 'inline-code-inventory.md'), md);

// Create MD Batches
let batchesMd = '# UI PHASE 2B — BATCH 3: PROPOSED BATCHES\n\n';
batchesMd += D?a trên k?t qu? inventory, d? xu?t chia nh? thành các batch x? lý an toàn (t?i da 10 batch):\n\n;

// Custom Batches logic
const duplicateStatic = data.filter(r => r.Group.startsWith('A') || r.Group.startsWith('B'));
const dashboardModule = data.filter(r => r.Module === 'Dashboard' && r.StyleAttrs > 0);
const productsModule = data.filter(r => r.Module === 'Products/Inventory');
const behaviorSensitive = data.filter(r => r.Group.startsWith('D'));

batchesMd += ## Batch 3.1: Static Global/Shared CSS & JS\n;
batchesMd += - **Ph?m vi:** Các view dùng chung ho?c partials có ch?a mã tinh (không có bi?n Blade, không có DOM Events).\n;
batchesMd += - **S? file:** ~ files.\n;
batchesMd += - **R?i ro:** Th?p.\n;
batchesMd += - **Route Test:** Các màn hình chính (Dashboard, Layout).\n;
batchesMd += - **Tiêu chí:** Extract ra asset \crm-global-shared.css/js\.\n\n;

batchesMd += ## Batch 3.2: Products & Inventory Inline Styles\n;
batchesMd += - **Ph?m vi:** Module Products & Inventory ch?a nhi?u th? \style=""\ và \<style>\.\n;
batchesMd += - **S? file:** ~ files.\n;
batchesMd += - **R?i ro:** Trung bình (c?n c?n th?n Utility Classes Tailwind).\n;
batchesMd += - **Route Test:** \/products\, \/inventory/*\.\n;
batchesMd += - **Tiêu chí:** Chuy?n \style=""\ thành Utility Classes ho?c dua vào \crm-inventory.css\.\n\n;

batchesMd += ## Batch 3.3: Dashboard Inline Styles\n;
batchesMd += - **Ph?m vi:** Module Dashboard.\n;
batchesMd += - **S? file:** ~ files.\n;
batchesMd += - **R?i ro:** Trung bình.\n;
batchesMd += - **Route Test:** \/dashboard\.\n\n;

batchesMd += ## Batch 3.4: Behavior Sensitive Handlers\n;
batchesMd += - **Ph?m vi:** Các th? HTML có ch?a \on[event]="..."\.\n;
batchesMd += - **S? file:** ~ files.\n;
batchesMd += - **R?i ro:** R?t Cao.\n;
batchesMd += - **Route Test:** Các Form tuong tác.\n;
batchesMd += - **Tiêu chí:** Chuy?n event handlers vào file \crm-behavior.js\.\n\n;

fs.writeFileSync(path.join(reportDir, 'proposed-batches.md'), batchesMd);
console.log('Report generation complete.');
