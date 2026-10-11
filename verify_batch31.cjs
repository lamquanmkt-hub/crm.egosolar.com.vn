const fs = require('fs');
const crypto = require('crypto');
const path = require('path');

const files = [
    'resources/views/marketing/plans/index.blade.php',
    'resources/views/marketing/plans/create.blade.php',
    'resources/views/marketing/plans/edit.blade.php',
    'resources/views/marketing/plans/show.blade.php'
];

function getHash(text) {
    return crypto.createHash('sha256').update(text).digest('hex');
}

let firstHash = null;
let firstBlock = null;

for (const file of files) {
    const filePath = path.join('D:\\Source Dev\\crm.egosolar.com.vn\\crm.egosolar.com.vn', file);
    if (!fs.existsSync(filePath)) {
        console.error('File not found: ' + file);
        process.exit(1);
    }
    const content = fs.readFileSync(filePath, 'utf8');
    const styleRegex = /<style[^>]*>([\s\S]*?)<\/style>/i;
    const match = content.match(styleRegex);
    
    if (!match) {
        console.error('No style block found in: ' + file);
        process.exit(1);
    }
    
    // Extract inner content
    let block = match[1];
    
    // Normalize CRLF to LF
    block = block.replace(/\r\n/g, '\n');
    
    const hash = getHash(block);
    
    if (firstHash === null) {
        firstHash = hash;
        firstBlock = block;
    } else if (firstHash !== hash) {
        console.error('Hash mismatch in file ' + file + '! Expected ' + firstHash + ', got ' + hash);
        process.exit(1);
    }
    
    // Check for blade syntax
    if (/\{\{|\{\!\!|@json|@if|@foreach|\/i.test(block)) {
        console.error('Found Blade syntax in style block of ' + file);
        process.exit(1);
    }
    
    console.log(file + ' Hash: ' + hash);
}

console.log('All 4 files have matching style blocks.');

const reportDir = 'D:\\Source Dev\\crm.egosolar.com.vn\\crm.egosolar.com.vn\\Report\\ui\\phase-2b-batch-3-1';
if (!fs.existsSync(reportDir)) fs.mkdirSync(reportDir, { recursive: true });

fs.writeFileSync(path.join(reportDir, 'original-marketing-plan-style.css'), firstBlock);
console.log('Saved original-marketing-plan-style.css');

const cssPath = path.join('D:\\Source Dev\\crm.egosolar.com.vn\\crm.egosolar.com.vn', 'resources/css/crm-marketing-plans.css');
fs.writeFileSync(cssPath, firstBlock);
console.log('Created resources/css/crm-marketing-plans.css with Hash: ' + getHash(firstBlock));

