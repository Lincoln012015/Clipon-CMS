export function getEditorExtensions(mode, bubbleMenuEl) {
    const { 
        Extension, StarterKit, Image, Link, TextStyle, Color, Highlight, TextAlign, Underline, 
        BubbleMenu, Table, TableRow, TableCell, TableHeader, Iframe, Video
    } = window.CliponTiptap;

    const PreserveHtmlAttributes = Extension.create({
        name: 'preserveHtmlAttributes',
        addGlobalAttributes() {
            return [
                {
                    types: [
                        'paragraph',
                        'heading',
                        'blockquote',
                        'bulletList',
                        'orderedList',
                        'listItem',
                        'codeBlock',
                        'horizontalRule',
                        'textStyle',
                        'bold',
                        'italic',
                        'strike',
                        'underline',
                        'code',
                        'link'
                    ],
                    attributes: {
                        class: {
                            default: null,
                            parseHTML: element => element.getAttribute('class') || null,
                            renderHTML: attributes => attributes.class ? { class: attributes.class } : {}
                        },
                        id: {
                            default: null,
                            parseHTML: element => element.getAttribute('id') || null,
                            renderHTML: attributes => attributes.id ? { id: attributes.id } : {}
                        },
                        style: {
                            default: null,
                            parseHTML: element => element.getAttribute('style') || null,
                            renderHTML: attributes => attributes.style ? { style: attributes.style } : {}
                        }
                    }
                }
            ];
        }
    });

    const extensions = [
        PreserveHtmlAttributes,
        StarterKit.configure({
            history: (mode === 'full'),
        }),
        Image.configure({ inline: false, allowBase64: true }),
        Link.configure({ openOnClick: false }),
        Iframe,
        Video,
        TextStyle,
        Color,
        TextAlign.configure({ types: ['heading', 'paragraph', 'image', 'iframe', 'video'] }),
        Underline,
        BubbleMenu.configure({ 
            element: bubbleMenuEl, 
            shouldShow: ({ editor, view, state, from, to }) => {
                const isSelectionNotEmpty = from !== to;
                return isSelectionNotEmpty || editor.isActive('image') || editor.isActive('iframe');
            },
            tippyOptions: { 
                duration: 100, 
                zIndex: 10001,
                maxWidth: 'none',
                appendTo: () => document.body,
                popperOptions: {
                    modifiers: [
                        {
                            name: 'preventOverflow',
                            options: {
                                boundary: 'viewport',
                                padding: 12,
                            },
                        },
                        {
                            name: 'flip',
                            options: {
                                padding: 10,
                                fallbackPlacements: ['top', 'bottom', 'bottom-start', 'top-start'],
                            },
                        }
                    ],
                },
            } 
        })
    ];

    if (mode === 'full') {
        extensions.push(
            Highlight,
            Table.configure({ resizable: true }),
            TableRow,
            TableHeader,
            TableCell
        );
    }

    return extensions;
}

export function getEditorProps(getEditor) {
    return {
        handlePaste: (view, event) => {
            const text = event.clipboardData.getData('text/plain');
            const html = event.clipboardData.getData('text/html');

            if (html) return false;

            if (text && (
                /^#\s/m.test(text) || 
                /\*\*.*\*\*/.test(text) || 
                /\[.*\]\(.*\)/.test(text) || 
                /^[-*+]\s/m.test(text) || 
                /^\d+\.\s/m.test(text) || 
                /^>\s/m.test(text) || 
                /`.*`/.test(text) || 
                /^```/m.test(text)
            )) {
                const parsedHtml = window.CliponTiptap.marked.parse(text, { gfm: true, breaks: true });
                const editor = getEditor();
                if (editor) {
                    editor.commands.insertContent(parsedHtml);
                    return true;
                }
            }
            return false;
        }
    };
}
