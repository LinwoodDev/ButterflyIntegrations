import {Decoration, type DecorationSet, EditorView, ViewPlugin, ViewUpdate, WidgetType,} from '@codemirror/view';
import {RangeSet, RangeSetBuilder} from '@codemirror/state';
import {CodeMirrorControl, ContentScriptContext} from "../../api/types";

class ImageWidget extends WidgetType {
    private readonly previewId: string;
    private readonly resourceDir: string;
    private readonly width: number | undefined;
    private readonly height: number | undefined;

    constructor(resourceDir: string, previewId: string, width: number | undefined, height: number | undefined) {
        super();
        this.previewId = previewId;
        this.resourceDir = resourceDir;
        this.width = width;
        this.height = height;
    }

    toDOM() {
        const wrapper = document.createElement('div');
        const img = document.createElement('img');
        img.src = 'joplin-content://note-viewer/' + this.resourceDir + '/' + this.previewId + '.svg?t=' + Date.now();
        if (this.width) img.width = this.width;
        if (this.height) img.height = this.height;

        wrapper.appendChild(img);
        return wrapper;
    }

}

const imagePlugin = (codeMirrorWrapper: CodeMirrorControl, resourceDir: string) => {
    return ViewPlugin.fromClass(class {
        decorations: DecorationSet;

        constructor(view: EditorView) {
            this.decorations = this.buildDecorations(view);
            codeMirrorWrapper.registerCommand('butterfly_updateCodeMirrorEditorHook', () => {
                this.decorations = this.buildDecorations(view);
                view.update([]);
            })
        }

        buildDecorations(view: EditorView): RangeSet<Decoration> {
            const builder = new RangeSetBuilder<Decoration>();
            const text = view.state.doc.toString();

            const butterflyRegex =
                /\[(?:!\[bfly\]\(:\/([0-9a-f]{32})\)|<img\b(?=[^>]*\bsrc=["']:\/([0-9a-f]{32})["'])[^>]*>)\]\(:\/(?:[0-9a-f]{32})\)/gi;
            butterflyRegex.lastIndex = 0;
            let match: RegExpExecArray | null;
            while ((match = butterflyRegex.exec(text)) !== null) {
                const widthMatch = match[0].match(/\bwidth=["'](\d+)["']/i);
                const width = widthMatch ? Number(widthMatch[1]) : undefined;
                const heightMatch = match[0].match(/\bheight=["'](\d+)["']/i);
                const height = heightMatch ? Number(heightMatch[1]) : undefined;


                const previewId = match[1] || match[2];
                builder.add(
                    match.index + match[0].length,
                    match.index + match[0].length,
                    Decoration.widget({
                        widget: new ImageWidget(resourceDir, previewId, width, height),
                        side: -1,
                    }),
                );
            }

            return builder.finish();
        }

        update(update: ViewUpdate) {
            if (update.docChanged || update.viewportChanged) {
                this.decorations = this.buildDecorations(update.view);
            }
        }
    }, {
        decorations: value => value.decorations,
    });

}
export default (context: ContentScriptContext) => {
    return {
        plugin: async (codeMirrorWrapper: CodeMirrorControl) => {
            const resourceDir: string = await context.postMessage({type: 'resourceDir'});
            codeMirrorWrapper.addExtension(imagePlugin(codeMirrorWrapper, resourceDir));
        },
    };
};