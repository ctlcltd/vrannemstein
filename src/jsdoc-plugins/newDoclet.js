/** @module jsdoc-plugins/newDoclet */
exports.handlers = {
    newDoclet({doclet}) {
        if (doclet.meta.filename.match(/\.php$/)) {
            if (doclet.requires) {
                for (const i in doclet.requires) {
                    const module = doclet.requires[i];
                    if (/globalThis\./.test(module))
                        doclet.requires[i] = module.replace('module:', '');
                }
            }
        }
    }
};
