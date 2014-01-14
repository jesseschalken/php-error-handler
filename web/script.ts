module PrettyPrinter {

    function wrapNode(node:Node):HTMLElement {
        var wrapper = document.createElement('span');
        wrapper.style.margin = '4px';
        wrapper.style.display = 'inline-block';
        wrapper.style.verticalAlign = 'middle';
        wrapper.appendChild(node);
        return wrapper;
    }

    function wrap(text:string):HTMLElement {
        var node = document.createTextNode(text);
        return wrapNode(node);
    }

    function collect(nodes:Node[]):Node {
        var x = document.createDocumentFragment();
        for (var i = 0; i < nodes.length; i++) {
            x.appendChild(nodes[i]);
        }
        return x;
    }

    function expandable2(headContent:Node, content:() => Node):Node {
        var container = document.createElement('div');
        container.style.display = 'inline-block';
        container.style.borderWidth = '1px';
        container.style.borderStyle = 'solid';
        container.style.borderColor = '#888';
        container.style.backgroundColor = 'white';
        container.style.verticalAlign = 'middle';
        container.style.margin = '4px';
        var head = document.createElement('div');
        head.style.cursor = 'pointer';
        head.style.msUserSelect = 'none';
        head.style.MozUserSelect = 'none';
        head.style.WebkitUserSelect = 'none';
        head.style.KhtmlUserSelect = 'none';
        head.addEventListener('mouseenter', function () {
            container.style.borderColor = '#000';
        });
        head.addEventListener('mouseleave', function () {
            container.style.borderColor = '#888';
        });
        head.appendChild(headContent);
        container.appendChild(head);
        var body = document.createElement('div');
        body.style.borderTopWidth = '1px';
        body.style.borderTopStyle = 'dashed';
        body.style.borderTopColor = '#888';
        var open = false;

        head.addEventListener('click', function () {
            if (open) {
                body.innerHTML = '';
                container.removeChild(body);
            } else {
                body.appendChild(content());
                container.appendChild(body);
            }

            open = !open;
        });

        return container;
    }

    function createTable(data:Node[][]):HTMLTableElement {
        var table = document.createElement('table');
        table.style.borderSpacing = '0';
        table.style.padding = '0';

        for (var x = 0; x < data.length; x++) {
            var row = document.createElement('tr');
            table.appendChild(row);
            for (var y = 0; y < data[x].length; y++) {
                var td = document.createElement('td');
                td.style.width = y == data[x].length - 1 ? '100%' : '0';
                td.style.padding = '0';
                td.appendChild(data[x][y]);
                row.appendChild(td);
            }
            row.style.backgroundColor = '#fff';

            (function (row) {
                var oldbackgorund = row.style.backgroundColor;
                row.addEventListener('mouseenter', function () {
                    row.style.backgroundColor = '#eef';
                });
                row.addEventListener('mouseleave', function () {
                    row.style.backgroundColor = oldbackgorund;
                });
            })(row);
        }

        return table;
    }

    function bold(content:string):Node {
        var box = wrap(content);
        box.style.fontWeight = 'bold';
        return box;
    }

    function keyword(word:string) {
        var box = wrap(word);
        box.style.color = '#008';
        box.style.fontWeight = 'bold';
        return box;
    }

    function renderString(x:string):Node {
        var result = document.createElement('span');
        result.style.color = '#080';
        result.style.backgroundColor = '#dFd';
        result.style.fontWeight = 'bold';
        result.style.display = 'inline';

        var translate = {
            '\\': '\\\\',
            '$': '\\$',
            '\r': '\\r',
            '\v': '\\v',
            '\f': '\\f',
            '"': '\\"'
        };

        result.appendChild(document.createTextNode('"'));

        for (var i = 0; i < x.length; i++) {
            var char:string = x.charAt(i);
            var code:number = x.charCodeAt(i);

            function escaped(x:string):Node {
                var box = document.createElement('span');
                box.appendChild(document.createTextNode(x));
                box.style.color = '#008';
                box.style.fontWeight = 'bold';
                return box;
            }

            if (translate[char] !== undefined) {
                result.appendChild(escaped(translate[char]));
            } else if ((code >= 32 && code <= 126) || char === '\n' || char === '\t') {
                result.appendChild(document.createTextNode(char));
            } else {
                result.appendChild(escaped('\\x' + (code < 10 ? '0' + code.toString(16) : code.toString(16))));
            }
        }

        result.appendChild(document.createTextNode('"'));


        return wrapNode(result);
    }

    function renderInt(x:number):Node {
        var result = wrap(String(x));
        result.style.color = '#00F';
        return result;
    }

    function renderFloat(x:number):Node {
        var str = x % 1 == 0 ? String(x) + '.0' : String(x);
        var result = wrap(str);
        result.style.color = '#00F';
        return result;
    }

    function renderBool(x:boolean):Node {
        return keyword(x ? 'true' : 'false');
    }

    function renderNull():Node {
        return keyword('null');
    }

    function renderArray(x:any, root):Node {
        var array = root['arrays'][x[1]];
        var entries = array['entries'];
        return expandable2(keyword('array'), function () {
            if (entries.length == 0)
                return wrap('empty');

            var rows:Node[][] = [];
            for (var i = 0; i < entries.length; i++) {
                var entry = entries[i];
                rows.push([
                    renderAny(entry[0], root),
                    wrap('=>'),
                    renderAny(entry[1], root)
                ]);
            }
            return  createTable(rows);
        });
    }

    function renderUnknown() {
        return bold('unknown type');
    }

    function renderObject(x, root):Node {
        var object = root['objects'][x[1]];
        var result = document.createDocumentFragment();
        result.appendChild(keyword('new'));
        result.appendChild(wrap(object['class']));

        function body() {
            var properties:Array<any> = object['properties'];
            var rows:Node[][] = [];
            for (var i = 0; i < properties.length; i++) {
                var property = properties[i];
                var variable = renderVariable(property['name']);
                var value = renderAny(property['value'], root);
                rows.push([
                    collect([keyword(property['access']), variable]),
                    wrap('='),
                    value
                ]);
            }
            return createTable(rows);
        }

        return expandable2(result, body);
    }

    function renderStack(stack:any[], root):Node {
        return expandable2(bold('stack trace'), function () {
            var rows = [];

            for (var x = 0; x < stack.length; x++) {
                rows.push([
                    renderLocation(stack[x]['location']),
                    renderFunctionCall(stack[x], root)
                ]);
            }

            return createTable(rows);
        });
    }

    function renderFunctionCall(call:any, root):Node {
        var result = document.createDocumentFragment();
        if (call['object']) {
            result.appendChild(renderObject(call['object'], root));
            result.appendChild(wrap('->'));
        } else if (call['class']) {
            result.appendChild(wrap(call['class']));
            result.appendChild(wrap(call['isStatic'] ? '::' : '->'));
        }

        result.appendChild(wrap(call['function']));
        result.appendChild(wrap('('));

        for (var i = 0; i < call['args'].length; i++) {
            if (i != 0)
                result.appendChild(wrap(','));

            result.appendChild(renderAny(call['args'][i], root));
        }

        result.appendChild(wrap(')'));

        return result;
    }

    function renderVariable(name:string):Node {
        var result = wrap('$' + name);
        result.style.color = '#800';
        return result;
    }

    function renderLocals(locals, root):Node {
        return expandable2(bold('local variables'), function () {
            var rows = [];

            if (locals instanceof Array) {
                if (!locals) {
                    rows.push([wrap('none')]);
                } else {
                    for (var i = 0; i < locals.length; i++) {
                        var local = locals[i];
                        var name = local['name'];
                        rows.push([
                            renderVariable(name),
                            wrap('='),
                            renderAny(local['value'], root)
                        ]);
                    }
                }
            } else {
                rows.push([wrap('n/a')]);
            }

            return createTable(rows);
        });
    }

    function renderGlobals(globals, root) {
        if (!globals)
            return wrap('n/a');

        return expandable2(bold('global variables'), function () {
            var staticVariables = globals['staticVariables'];
            var staticProperties = globals['staticProperties'];
            var globalVariables = globals['globalVariables'];
            var rows = [];

            for (var i = 0; i < staticVariables.length; i++) {
                var v = staticVariables[i];
                var pieces = document.createDocumentFragment();
                pieces.appendChild(keyword(v['access']));

                rows.push([
                    pieces,
                    renderAny(v['value'], root)
                ]);
            }

            return createTable(rows);
        });
    }

    function renderException(x, root):Node {
        if (!x)
            return wrap('none');

        return expandable2(collect([keyword('new'), wrap(x['class'])]), function () {
            return createTable([
                [bold('code '), wrap(x['code'])],
                [bold('message '), wrap(x['message'])],
                [bold('location '), renderLocation(x['location'])],
                [bold('stack '), renderStack(x['stack'], root)],
                [bold('locals '), renderLocals(x['locals'], root)],
                [bold('globals '), renderGlobals(x['globals'], root)],
                [bold('previous '), renderException(x['preivous'], root)]
            ]);
        });
    }

    function renderLocation(location):Node {
        var wrapper = document.createDocumentFragment();
        var file = location['file'];
        var line = location['line'];
        wrapper.appendChild(wrap(file));
        wrapper.appendChild(renderInt(line));

        return expandable2(wrapper, function () {
            var sourceCode = location['sourceCode'];

            if (!sourceCode)
                return wrap('n/a');

            var rows:Node[][] = [];

            for (var codeLine in sourceCode) {
                if (!sourceCode.hasOwnProperty(codeLine))
                    continue;

                var highlight = (function (doHighlight:boolean) {
                    return function (t:Node) {
                        var x = document.createElement('div');
                        x.appendChild(t);
                        if (doHighlight)
                            x.style.backgroundColor = '#fcc';
                        return x;
                    }
                })(codeLine == line);

                rows.push([
                    highlight(document.createTextNode(codeLine)),
                    highlight(document.createTextNode(sourceCode[codeLine]))
                ]);
            }

            return createTable(rows);
        });
    }

    function renderAny(root, v):Node {
        if (typeof root === 'string')
            return renderString(root);
        else if (typeof root === 'number')
            if (root % 1 === 0)
                return renderInt(root);
            else
                return renderFloat(root);
        else if (typeof root === 'boolean')
            return renderBool(root);
        else if (root === null)
            return renderNull();
        else if (root[0] === 'float')
            if (root[1] === 'inf' || root[1] === '+inf')
                return renderFloat(Infinity);
            else if (root[1] === '-inf')
                return renderFloat(-Infinity);
            else if (root[1] === 'nan')
                return renderFloat(NaN);
            else
                return renderFloat(root[1]);
        else if (root[0] === 'array')
            return renderArray(root, v);
        else if (root[0] === 'unknown')
            return renderUnknown();
        else if (root[0] === 'object')
            return renderObject(root, v);
        else if (root[0] === 'exception')
            return renderException(root[1], v);
        else
            throw { message: "not goord" };
    }

    function renderWhole(v):Node {
        return renderAny(v['root'], v);
    }

    function start() {
        var body = document.getElementsByTagName('body')[0];
        var text = document.createElement('textarea');
        body.appendChild(text);
        text.style.width = '800px';
        text.style.height = '500px';
        var container = document.createElement('div');
        body.appendChild(container);

        function onchange() {
            var json:string = text.value;
            var parsedJSON = JSON.parse(json);
            var rendered:Node = renderWhole(parsedJSON);
            container.innerHTML = '';
            container.appendChild(rendered);
        }

        text.addEventListener('change', onchange);

        text.value = "{\"root\":[\"exception\",{\"class\":\"MuhMockException\",\"code\":\"Dummy exception code\",\"message\":\"This is a dummy exception message.\\n\\nlololool\",\"location\":{\"line\":9000,\"file\":\"\\/path\\/to\\/muh\\/file\",\"sourceCode\":null},\"previous\":null,\"stack\":[{\"function\":\"aFunction\",\"class\":\"DummyClass1\",\"isStatic\":null,\"location\":{\"line\":1928,\"file\":\"\\/path\\/to\\/muh\\/file\",\"sourceCode\":null},\"object\":[\"object\",0],\"args\":[[\"object\",1]]},{\"function\":\"aFunction\",\"class\":null,\"isStatic\":null,\"location\":{\"line\":1928,\"file\":\"\\/path\\/to\\/muh\\/file\",\"sourceCode\":null},\"object\":null,\"args\":[[\"object\",2]]}],\"locals\":[{\"name\":\"lol\",\"value\":8},{\"name\":\"foo\",\"value\":\"bar\"}],\"globals\":{\"staticProperties\":[{\"name\":\"blahProperty\",\"value\":null,\"class\":\"BlahClass\",\"access\":\"private\",\"isDefault\":false}],\"globalVariables\":[{\"name\":\"lol global\",\"value\":null},{\"name\":\"blahVariable\",\"value\":null}],\"staticVariables\":[{\"name\":\"public\",\"value\":null,\"class\":null,\"function\":\"BlahAnotherClass\"},{\"name\":\"lolStatic\",\"value\":null,\"class\":\"BlahYetAnotherClass\",\"function\":\"blahMethod\"}]}}],\"arrays\":[],\"objects\":[{\"class\":\"ErrorHandler\\\\DummyClass1\",\"hash\":\"0000000058b5388000000000367cf886\",\"properties\":[{\"name\":\"private1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"public\",\"isDefault\":true}]},{\"class\":\"ErrorHandler\\\\DummyClass2\",\"hash\":\"0000000058b5388300000000367cf886\",\"properties\":[{\"name\":\"private2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"public\",\"isDefault\":true},{\"name\":\"private1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"public\",\"isDefault\":true}]},{\"class\":\"ErrorHandler\\\\DummyClass2\",\"hash\":\"0000000058b5388a00000000367cf886\",\"properties\":[{\"name\":\"private2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public2\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass2\",\"access\":\"public\",\"isDefault\":true},{\"name\":\"private1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"private\",\"isDefault\":true},{\"name\":\"protected1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"protected\",\"isDefault\":true},{\"name\":\"public1\",\"value\":null,\"class\":\"ErrorHandler\\\\DummyClass1\",\"access\":\"public\",\"isDefault\":true}]}]}";
        onchange();
    }

    document.addEventListener('DOMContentLoaded', start);
}

interface CSSStyleDeclaration {
    MozUserSelect: string;
    WebkitUserSelect: string;
    KhtmlUserSelect: string;
}