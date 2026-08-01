// Minimal, safe markdown renderer for AI feedback (headings, bold, italics, lists, paragraphs).
function escapeHtml(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function inline(s) {
    return s
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
}

function isTableRow(line) {
    return /^\s*\|.*\|\s*$/.test(line);
}

function isTableSeparator(line) {
    return /^\s*\|?\s*:?-{2,}.*\|.*$/.test(line) && /^[\s|:-]+$/.test(line);
}

function splitRow(line) {
    return line
        .trim()
        .replace(/^\||\|$/g, '')
        .split('|')
        .map((c) => c.trim());
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

    for (let i = 0; i < lines.length; i++) {
        const raw = lines[i];
        const line = raw.trimEnd();

        // Pipe table: header row + separator row, then body rows
        if (isTableRow(line) && i + 1 < lines.length && isTableSeparator(lines[i + 1])) {
            closeList();
            const header = splitRow(line);
            const body = [];
            let j = i + 2;
            while (j < lines.length && isTableRow(lines[j])) {
                body.push(splitRow(lines[j]));
                j++;
            }
            out.push('<div class="table-scroll"><table>');
            out.push('<thead><tr>' + header.map((c) => `<th>${inline(c)}</th>`).join('') + '</tr></thead>');
            out.push('<tbody>');
            for (const row of body) {
                out.push('<tr>' + row.map((c) => `<td>${inline(c)}</td>`).join('') + '</tr>');
            }
            out.push('</tbody></table></div>');
            i = j - 1;
            continue;
        }
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
