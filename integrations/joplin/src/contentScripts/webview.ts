declare const webviewApi: { postMessage: (contentScriptId: string, message: any) => any; };

document.addEventListener('click', (event) => {
    handleInteraction(event);
}, true);

document.addEventListener("keydown", (event) => {
    if (!(event.key === 'Enter' || event.key === ' ')) return;
    handleInteraction(event);
});

const idPattern = /^[0-9a-f]{32}$/;

function handleInteraction(event: UIEvent) {
    const imgTarget: HTMLImageElement | null = (event.target as HTMLImageElement);
    if (!imgTarget || imgTarget.alt !== 'bfly') return;
    const previewId = imgTarget.dataset.resourceId;
    if (!previewId || !idPattern.test(previewId)) return;
    const aTarget: HTMLAnchorElement | null = imgTarget.parentElement as HTMLAnchorElement;
    if (!aTarget) return;
    const documentId = aTarget.dataset.resourceId;
    if (!documentId || !idPattern.test(documentId)) return;
    const message = {documentId, previewId};

    event.preventDefault();
    event.stopPropagation();
    webviewApi.postMessage('butterfly-md', message);
}

const processImages = () => {
    const images = document.querySelectorAll<HTMLImageElement>('img[alt="bfly"]');
    for (const image of images) {
        image.classList.add('butterfly-clickable-image');
        image.title = 'Butterfly'; // ask plugin which title to use?
    }
};

document.addEventListener('joplin-noteDidUpdate', () => {
    processImages();
});

processImages();
