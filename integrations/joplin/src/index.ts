import joplin from 'api';
import {ContentScriptType, SettingItemType, ToastType, ToolbarButtonLocation} from "../api/types";
import {tmpdir} from 'os'
import {sep} from 'path'
import {v4 as uuidv4} from 'uuid'
import {ButterflyDocument} from "./butterflyDocument";
import {ChangeEvent} from "../api/JoplinSettings";

const fs = joplin.require('fs-extra');

joplin.plugins.register({
    onStart: async function () {
        let loaded: ButterflyDocument | null = null;
        const tempFolder = `${tmpdir}${sep}joplin-butterfly-plugin${sep}`;

        await joplin.settings.registerSection('LinwoodButterfly', {
            label: 'Butterfly', iconName: 'butterfly-icon butterfly-icon-settings',
            description: 'Linwood Butterfly'
        });
        await joplin.settings.registerSettings({
            'butterflyOrigin': {
                value: 'https://preview.butterfly.linwood.dev',
                type: SettingItemType.String,
                public: true,
                label: 'Butterfly Origin',
                section: 'LinwoodButterfly',
            },
        });
        await joplin.settings.registerSettings({
            'butterflyLanguage': {
                value: (await joplin.settings.globalValues(['locale']))[0].toLowerCase().substring(0, 2),
                type: SettingItemType.String,
                public: true,
                label: 'Language',
                section: 'LinwoodButterfly',
            },
        });
        await joplin.settings.onChange(async (_event: ChangeEvent) => {
            await updateIFrameUrl();
        });
        let panelReadinessResolver: (() => void) | null = null;
        const panel = await joplin.views.panels.create('butterfly-panel');
        await joplin.views.panels.setHtml(panel,
            `<iframe id="butterfly" width="100%" height="1000px"></iframe>`
        )
        const installDir = await joplin.plugins.installationDir();
        await joplin.window.loadChromeCssFile(installDir + '/butterfly.css');

        await joplin.contentScripts.register(
            ContentScriptType.MarkdownItPlugin,
            'butterfly-md',
            'contentScripts/markdownItPlugin.js'
        );
        await joplin.contentScripts.register(
            ContentScriptType.CodeMirrorPlugin,
            'butterfly-cm',
            'contentScripts/codemirror.js',
        );

        await joplin.commands.register({
            name: 'clickEditorButton',
            label: 'Butterfly',
            iconName: 'butterfly-icon butterfly-icon-editor',
            execute: async () => {
                if (!await joplin.views.panels.visible(panel)) {
                    await joplin.views.panels.show(panel, true);
                    await waitForPanelReadiness();
                } else {
                    joplin.views.panels.postMessage(panel, {type: 'reset'});
                }
                loaded = null;
            }
        });
        await joplin.views.toolbarButtons.create('butterfly-editor-button', 'clickEditorButton', ToolbarButtonLocation.EditorToolbar);

        async function updateIFrameUrl() {
            const language: string = await joplin.settings.value('butterflyLanguage')
            joplin.views.panels.postMessage(panel, {
                type: 'config',
                origin: await joplin.settings.value('butterflyOrigin'),
                language: language
            });
        }

        await joplin.views.panels.onMessage(panel, async (message: any) => {
            switch (message.type) {
                case 'error':
                    await joplin.views.dialogs.showToast({
                        type: ToastType.Error,
                        message: 'Butterfly-Embed returned an error.',
                    });
                    return;
                case 'panelReady':
                    panelReadinessResolver?.();
                    panelReadinessResolver = null;
                    await updateIFrameUrl();
                    return;
                case 'getSetting':
                    return await joplin.settings.value(message.data);
                case 'embedNotReady':
                    await joplin.views.dialogs.showToast({
                        type: ToastType.Error,
                        message: 'Butterfly-Embed did not become ready.',
                    });
                // fallthrough
                case 'exit':
                    loaded = null;
                    await joplin.views.panels.show(panel, false);
                    return;
                case 'save':
                    if (loaded) {
                        fs.outputFileSync(`${tempFolder}${loaded.documentId}.bfly`, message.data);
                        fs.outputFileSync(`${tempFolder}${loaded.previewId}.svg`, message.img);
                        await joplin.data.put(
                            ['resources', loaded.previewId],
                            null,
                            {
                                updated_time: Date.now(),
                                user_updated_time: Date.now(),
                                blob_updated_time: Date.now(),
                            },
                            [{
                                path: `${tempFolder}${loaded.previewId}.svg`
                            }]
                        );
                        await joplin.data.put(
                            ['resources', loaded.documentId],
                            null,
                            {
                                updated_time: Date.now(),
                                user_updated_time: Date.now(),
                                blob_updated_time: Date.now(),
                            },
                            [{
                                path: `${tempFolder}${loaded.documentId}.bfly`
                            }]
                        );
                    } else {
                        loaded = new ButterflyDocument(generateId(), generateId());
                        fs.outputFileSync(`${tempFolder}${loaded.documentId}.bfly`, message.data);
                        fs.outputFileSync(`${tempFolder}${loaded.previewId}.svg`, message.img);
                        await joplin.data.post(
                            ['resources'],
                            null,
                            {
                                id: loaded.previewId,
                                title: 'temp.svg',
                                filename: 'temp.svg',
                                created_time: Date.now(),
                                user_created_time: Date.now(),
                                updated_time: Date.now(),
                                user_updated_time: Date.now(),
                                blob_updated_time: Date.now(),
                                mime: 'image/svg+xml'
                            },
                            [{
                                path: `${tempFolder}${loaded.previewId}.svg`
                            }]
                        );
                        await joplin.data.post(
                            ['resources'],
                            null,
                            {
                                id: loaded.documentId,
                                title: 'temp.bfly',
                                filename: 'temp.bfly',
                                created_time: Date.now(),
                                user_created_time: Date.now(),
                                updated_time: Date.now(),
                                user_updated_time: Date.now(),
                                blob_updated_time: Date.now(),
                                mime: 'application/x-butterfly'
                            },
                            [{
                                path: `${tempFolder}${loaded.documentId}.bfly`
                            }]
                        );
                        await joplin.commands.execute('insertText', `\n[![bfly](:/${loaded.previewId})](:/${loaded.documentId})\n`);
                    }
                    await joplin.commands.execute('editor.execCommand', {name: 'butterfly_updateCodeMirrorEditorHook'});
                    return;
            }
        })
        await joplin.views.panels.addScript(panel, '/views/panel.js');
        await joplin.contentScripts.onMessage('butterfly-cm', async (message: { type: string }) => {
            if (message.type === 'resourceDir') {
                return await joplin.settings.globalValue('resourceDir');
            }
        });
        await joplin.contentScripts.onMessage('butterfly-md', async (message: any) => {
            loaded = new ButterflyDocument(message.documentId, message.previewId);
            if (!await joplin.views.panels.visible(panel)) {
                await joplin.views.panels.show(panel, true);
                await waitForPanelReadiness();
            }
            const filePath = await joplin.data.resourcePath(message.documentId);
            const file: Uint8Array = await fs.readFile(filePath);

            joplin.views.panels.postMessage(panel, {
                type: 'setData',
                data: file,
            });
        });

        async function waitForPanelReadiness(): Promise<void> {
            return new Promise<void>((resolve, reject) => {
                panelReadinessResolver = resolve;
                window.setTimeout(async () => {
                    if (panelReadinessResolver === resolve) {
                        panelReadinessResolver = null;
                        reject(new Error('Panel did not become ready'));
                        await joplin.views.dialogs.showToast({
                            type: ToastType.Error,
                            message: 'Butterfly-Panel in Joplin did not become ready.',
                        });
                    }
                }, 5000);
            });
        }

        function generateId() {
            return uuidv4().replace(/-/g, '')
        }
    }
});