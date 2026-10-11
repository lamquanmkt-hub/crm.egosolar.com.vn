const fs = require('fs');
const path = require('path');

const viewsDir = path.join(process.cwd(), 'resources', 'views');
const results = [];
let fileCount = 0;

function walkDir(dir) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            if (file === 'vendor') continue;
            walkDir(fullPath);
        } else if (fullPath.endsWith('.blade.php') && !fullPath.includes('technical.guides')) {
            analyzeFile(fullPath);
        }
    }
}

function analyzeFile(filePath) {
    fileCount++;
    const content = fs.readFileSync(filePath, 'utf8');
    const relativePath = filePath.substring(viewsDir.length + 1).replace(/\\/g, '/');
    
    // Find style blocks
    const styleRegex = /<style[^>]*>([\s\S]*?)<\/style>/gi;
    const styleBlocks = (content.match(styleRegex) || []).length;
    
    // Find script blocks without src
    const scriptRegex = /<script(?![^>]*src=)[^>]*>([\s\S]*?)<\/script>/gi;
    const scriptBlocks = (content.match(scriptRegex) || []).length;
    
    // Find style attributes
    const styleAttrRegex = /style\s*=\s*"[^"]*"/gi;
    const styleAttrs = (content.match(styleAttrRegex) || []).length;
    
    // Find inline events (on*)
    const onAttrRegex = /\son[a-z]+\s*=\s*"[^"]*"/gi;
    const onAttrs = (content.match(onAttrRegex) || []).length;
    
    // Check for blade dynamics in script/style
    let hasBladeDynamic = 0;
    const allScriptsStyles = (content.match(/<(style|script)[^>]*>([\s\S]*?)<\/\1>/gi) || []);
    for (const block of allScriptsStyles) {
        if (/\{\{|@|route\(|asset\(/.test(block)) {
            hasBladeDynamic = 1;
            break;
        }
    }
    
    // Get extends
    let extendsLayout = '';
    const extendsMatch = content.match(/@extends\(['"]([^'"]+)['"]\)/);
    if (extendsMatch) {
        extendsLayout = extendsMatch[1];
    }
    
    // Identify Module
    let module = 'General';
    if (relativePath.includes('products/') || relativePath.includes('inventory/')) module = 'Products/Inventory';
    else if (relativePath.includes('warehouses/')) module = 'Warehouse';
    else if (relativePath.includes('orders/')) module = 'Orders';
    else if (relativePath.includes('customers/')) module = 'Customers';
    else if (relativePath.includes('projects/')) module = 'Projects';
    else if (relativePath.includes('technical/') || relativePath.includes('ky-thuat/')) module = 'Technical/Warranty';
    else if (relativePath.includes('finance/')) module = 'Finance';
    else if (relativePath.includes('hr/') || relativePath.includes('nhan-su/')) module = 'HR';
    else if (relativePath.includes('admin/')) module = 'Administration';
    else if (relativePath.includes('chat/')) module = 'Chat/Notifications';
    else if (relativePath.includes('auth/')) module = 'Auth';
    else if (relativePath.includes('dashboard')) module = 'Dashboard';

    // Group classification heuristic
    let group = 'A - Static Duplicate';
    if (hasBladeDynamic) {
        group = 'C - Blade Dynamic';
    } else if (onAttrs > 0) {
        group = 'D - Behavior Sensitive';
    } else if (styleBlocks > 0 || scriptBlocks > 0) {
        group = 'B - Static Unique';
    } else if (styleAttrs > 0) {
        group = 'B - Static Unique';
    }

    if (styleBlocks > 0 || scriptBlocks > 0 || styleAttrs > 0 || onAttrs > 0) {
        results.push({
            Path: relativePath,
            Extends: extendsLayout,
            Module: module,
            StyleBlocks: styleBlocks,
            ScriptBlocks: scriptBlocks,
            StyleAttrs: styleAttrs,
            OnAttrs: onAttrs,
            HasBladeDynamic: hasBladeDynamic,
            Group: group,
            Risk: hasBladeDynamic ? 'High' : (onAttrs > 0 ? 'Medium' : 'Low')
        });
    }
}

walkDir(viewsDir);

// Propose batches
const batches = [];
batches.push({
    Name: 'Batch 3: Static Global CSS/JS',
    Scope: 'Views with Group B/A (no blade variables, no inline events)',
    Criteria: 'StyleBlocks > 0 OR ScriptBlocks > 0 AND HasBladeDynamic == 0',
    Files: results.filter(r => r.Group.startsWith('A') || r.Group.startsWith('B')).length
});
batches.push({
    Name: 'Batch 4: Dashboard Module Inline Styles',
    Scope: 'Dashboard module views with StyleAttrs',
    Criteria: 'Module == Dashboard AND StyleAttrs > 0',
    Files: results.filter(r => r.Module === 'Dashboard' && r.StyleAttrs > 0).length
});
batches.push({
    Name: 'Batch 5: Products/Inventory Module Inline Styles',
    Scope: 'Products module views with inline CSS',
    Criteria: 'Module == Products/Inventory',
    Files: results.filter(r => r.Module === 'Products/Inventory').length
});

const report = {
    TotalFilesScanned: fileCount,
    FilesWithInlineCode: results.length,
    Batches: batches,
    Data: results
};

fs.writeFileSync('C:\\Users\\PC\\.gemini\\antigravity-ide\\brain\\4bdd5c8a-fb7f-402c-b2d8-2e93b31039c4\\scratch\\audit.json', JSON.stringify(report, null, 2));
console.log('Done auditing ' + fileCount + ' files.');
