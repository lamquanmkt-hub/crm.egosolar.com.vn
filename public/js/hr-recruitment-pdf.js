import { getDocument, GlobalWorkerOptions } from '../vendor/recruitment-pdfjs/pdf.mjs';

const viewer = document.querySelector('[data-rec-pdf]');
if (viewer) {
    GlobalWorkerOptions.workerSrc = new URL('../vendor/recruitment-pdfjs/pdf.worker.mjs', import.meta.url).href;
    const assets = new URL('../vendor/recruitment-pdfjs/', import.meta.url).href;
    const canvas = viewer.querySelector('[data-pdf-canvas]');
    const previous = viewer.querySelector('[data-pdf-prev]');
    const next = viewer.querySelector('[data-pdf-next]');
    const label = viewer.querySelector('[data-pdf-page]');
    const error = viewer.querySelector('[data-pdf-error]');
    let document, pageNumber = 1, busy = false;
    const showPage = async number => {
        if (busy) return;
        busy = true; previous.disabled = next.disabled = true;
        try {
            const page = await document.getPage(number);
            const natural = page.getViewport({scale:1});
            const width = Math.max(240, viewer.querySelector('.rec-pdf-canvas').clientWidth - 24);
            const viewport = page.getViewport({scale:Math.min(2,width / natural.width)});
            canvas.width = Math.ceil(viewport.width); canvas.height = Math.ceil(viewport.height);
            await page.render({canvasContext:canvas.getContext('2d'),viewport,enableScripting:false}).promise;
            pageNumber = number; label.textContent = `Trang ${number} / ${document.numPages}`;
            canvas.setAttribute('aria-label',`Trang PDF ${number} / ${document.numPages}`);
            page.cleanup();
        } catch (_) {
            error.hidden = false; error.textContent = 'Không xem được PDF này. Tải tệp gốc để kiểm tra; nếu có mật khẩu, mở bằng ứng dụng đọc PDF.';
        } finally {
            busy = false; previous.disabled = pageNumber <= 1; next.disabled = pageNumber >= document.numPages;
        }
    };
    previous.addEventListener('click',()=>showPage(pageNumber - 1));
    next.addEventListener('click',()=>showPage(pageNumber + 1));
    try {
        document = await getDocument({url:viewer.dataset.url,cMapUrl:assets+'cmaps/',cMapPacked:true,standardFontDataUrl:assets+'standard_fonts/',wasmUrl:assets+'wasm/',isEvalSupported:false}).promise;
        await showPage(1);
    } catch (_) {
        label.textContent = 'Chưa mở được PDF'; error.hidden = false; error.textContent = 'Tệp lỗi hoặc cần mật khẩu. Bấm “Tải xuống” để mở tệp gốc.';
    }
}
