import QRCode from 'qrcode';

const table = document.querySelector('[data-producer-qr-list]');
const dialog = document.getElementById('product-qr-dialog');

if (table && dialog) {
    const canvasHost = document.getElementById('product-qr-canvas');
    const title = document.getElementById('product-qr-title');
    const reference = document.getElementById('product-qr-reference');
    const status = document.getElementById('product-qr-status');
    const publicLink = document.getElementById('product-qr-public-link');
    const downloadButton = document.getElementById('product-qr-download');
    const printButton = document.getElementById('product-qr-print');
    let currentCanvas = null;
    let currentName = '';
    let currentReference = '';

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]);
    }

    table.addEventListener('click', async event => {
        const trigger = event.target.closest('[data-open-product-qr]');
        if (!trigger) return;
        const url = trigger.dataset.url;
        if (!url) return;

        currentName = trigger.dataset.name || 'Produit NutriTrace';
        currentReference = trigger.dataset.reference || '';
        title.textContent = currentName;
        reference.textContent = currentReference ? `Référence : ${currentReference}` : '';
        publicLink.href = url;
        canvasHost.replaceChildren();
        currentCanvas = document.createElement('canvas');
        canvasHost.append(currentCanvas);
        status.hidden = false;
        status.textContent = 'Génération du QR code…';
        status.dataset.kind = 'info';
        downloadButton.disabled = true;
        printButton.disabled = true;
        if (!dialog.open) dialog.showModal();

        try {
            await QRCode.toCanvas(currentCanvas, url, {
                width: 320,
                margin: 3,
                errorCorrectionLevel: 'H',
                color: { dark: '#10251c', light: '#ffffff' },
            });
            status.textContent = 'QR code prêt. Il ouvre la fiche publique actualisée du produit.';
            status.dataset.kind = 'success';
            downloadButton.disabled = false;
            printButton.disabled = false;
        } catch {
            status.textContent = 'Le QR code n’a pas pu être généré. Fermez cette fenêtre et réessayez.';
            status.dataset.kind = 'error';
        }
    });

    downloadButton.addEventListener('click', () => {
        if (!currentCanvas) return;
        const fileRef = currentReference.replace(/[^a-z0-9_-]/gi, '_') || 'produit';
        const link = document.createElement('a');
        link.download = `nutritrace-${fileRef}.png`;
        try {
            const png = currentCanvas.toDataURL('image/png');
            if (!png.startsWith('data:image/png;base64,')) throw new Error('PNG indisponible');
            link.href = png;
            link.click();
        } catch {
            status.textContent = 'Le telechargement PNG a echoue. Generez de nouveau le QR code puis reessayez.';
            status.dataset.kind = 'error';
            status.hidden = false;
        }
    });

    printButton.addEventListener('click', () => {
        if (!currentCanvas) return;
        const printWindow = window.open('', '_blank', 'width=650,height=750');
        if (!printWindow) {
            status.textContent = 'La fenêtre d’impression a été bloquée. Autorisez les fenêtres contextuelles pour imprimer ce QR code.';
            status.dataset.kind = 'error';
            status.hidden = false;
            return;
        }
        printWindow.opener = null;
        let dataUrl;
        try {
            dataUrl = currentCanvas.toDataURL('image/png');
        } catch {
            printWindow.close();
            status.textContent = 'La creation du document a echoue. Generez de nouveau le QR code puis reessayez.';
            status.dataset.kind = 'error';
            status.hidden = false;
            return;
        }
        printWindow.document.open();
        printWindow.addEventListener('load', () => { printWindow.focus(); printWindow.print(); }, { once: true });
        printWindow.document.write(`<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>QR code — ${escapeHtml(currentName)}</title><style>body{font:16px Arial,sans-serif;color:#172b22;text-align:center;padding:32px}img{display:block;width:320px;height:320px;margin:28px auto}h1{font-size:24px;margin:0}p{color:#58665e}@media print{body{padding:0}}</style></head><body><h1>${escapeHtml(currentName)}</h1><p>${escapeHtml(currentReference)}</p><img src="${dataUrl}" alt="QR code du produit ${escapeHtml(currentName)}"><p>NutriTrace · Traçabilité alimentaire</p></body></html>`);
        printWindow.document.close();
    });

    dialog.querySelectorAll('[data-close-product-qr]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
}
