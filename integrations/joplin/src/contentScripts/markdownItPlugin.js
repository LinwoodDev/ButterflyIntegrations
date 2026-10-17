module.exports = {
    default: function (_context) {
        return {
            plugin: function (_markdownIt, _pluginOptions) {
            },
            assets: function () {
                return [
                    {name: 'webview.js'},
                    {name: 'webview.css'}
                ];
            },
        };
    },
};