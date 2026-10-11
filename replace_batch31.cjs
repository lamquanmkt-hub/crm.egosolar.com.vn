const fs = require('fs');
const path = require('path');

const files = [
    'resources/views/marketing/plans/index.blade.php',
    'resources/views/marketing/plans/create.blade.php',
    'resources/views/marketing/plans/edit.blade.php',
    'resources/views/marketing/plans/show.blade.php'
];

const replacement = @push('styles')
    @vite('resources/css/crm-marketing-plans.css')
@endpush;

for (const file of files) {
    const filePath = path.join('D:\\Source Dev\\crm.egosolar.com.vn\\crm.egosolar.com.vn', file);
    const content = fs.readFileSync(filePath, 'utf8');
    
    // Replace the exact style block
    const newContent = content.replace(/<style[^>]*>[\s\S]*?<\/style>/i, replacement);
    
    fs.writeFileSync(filePath, newContent);
    console.log('Replaced in ' + file);
}
