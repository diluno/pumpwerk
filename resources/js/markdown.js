// Minimal, safe markdown renderer for AI feedback (headings, bold, italics, lists, paragraphs).
function escapeHtml(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function inline(s) {
    return s
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
}

export function renderMarkdown(md) {
    const lines = escapeHtml(md).split('\n');
    const out = [];
    let list = null;

    const closeList = () => {
        if (list) {
            out.push(`</${list}>`);
            list = null;
        }
    };

    for (const raw of lines) {
        const line = raw.trimEnd();
        const h = line.match(/^(#{1,4})\s+(.*)/);
        const ul = line.match(/^\s*[-*]\s+(.*)/);
        const ol = line.match(/^\s*\d+[.)]\s+(.*)/);

        if (h) {
            closeList();
            const level = Math.min(h[1].length + 2, 6);
            out.push(`<h${level}>${inline(h[2])}</h${level}>`);
        } else if (ul) {
            if (list !== 'ul') {
                closeList();
                out.push('<ul>');
                list = 'ul';
            }
            out.push(`<li>${inline(ul[1])}</li>`);
        } else if (ol) {
            if (list !== 'ol') {
                closeList();
                out.push('<ol>');
                list = 'ol';
            }
            out.push(`<li>${inline(ol[1])}</li>`);
        } else if (line.trim() === '') {
            closeList();
        } else {
            closeList();
            out.push(`<p>${inline(line)}</p>`);
        }
    }
    closeList();

    return out.join('\n');
}
