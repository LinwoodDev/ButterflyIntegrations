export class ButterflyDocument {
    documentId: string;
    previewId: string;

    constructor(documentId: string, previewId: string) {
        this.documentId = documentId;
        this.previewId = previewId;
    }
}