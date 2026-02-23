/** @module jsdoc-plugins/beforeParse */
exports.handlers = {
    beforeParse(e) {
        if (e.filename.match(/\.php$/)) {
            const tags = e.source.match(/<\?php(.*?)\?>/gs);
            const overview = [];
            for (const line of tags[0].split('\n')) {
                if (/\/\*\*/.test(line))
                    overview.push(line);
                else if (/^ \* [^@\s]/.test(line))
                    overview.push(line.replace(/\* ([^\s]+)/, '* @file $1'));
                else if (/@(version|author|license)/.test(line))
                    overview.push(line);
                else if (/\*\//.test(line))
                    overview.push(line);
            }
            e.source = overview.join('\n') + e.source;
            e.source = e.source.replace(/<\?php(.*?)\?>/gs, '\n'.repeat(tags[0].match(/\n/g).length - overview.length + 1));
            e.source = e.source.replace(/<script[^>]*?>(.*?)<\/script>/gs, '$1');
            e.source = e.source.replace(/\(function\(wp, jQuery\) \{(.*?)\}\)\(wp, jQuery\);/gs, '$1');
        }
    }
};
