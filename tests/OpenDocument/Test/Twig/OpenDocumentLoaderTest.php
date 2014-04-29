<?php
namespace OpenDocument\Twig;

use OpenDocument\Twig\OpenDocumentLoader;

class OpenDocumentLoaderTest extends \PHPUnit_Framework_TestCase
{
    private $twig;

    public function setUp()
    {
        parent::setup();

        $this->twig = new \Twig_Environment(new \Twig_Loader_String());
        $this->twig->addExtension(new OpenDocumentExtension());
    }

    /**
     * @dataProvider providerFixTagFormatting
     */
    public function testFixTagFormatting($source, $expected)
    {
        OpenDocumentLoader::fix($source);
        $this->assertEquals($expected, $source);
    }

    /**
     * @dataProvider providerFixForLoops
     */
    public function testFixForLoops($source, $expected)
    {
        OpenDocumentLoader::fix($source);
        $this->assertEquals($expected, $source);
    }

    public function testForLoopSimple()
    {
        $templateDir = realpath(dirname(__FILE__) . '/../../../data/for-loop');

        // load original template
        $template = 'simple.xml';
        $loader = new OpenDocumentLoader(array($templateDir));
        $source = $loader->getSource($template);

        // load fixed template
        $fixed = file_get_contents($templateDir . '/simple-fixed.xml');

        $this->assertEquals($fixed, $source);

        // validate XML
        $rendered = $this->twig->render($source);
        $document = new \DOMDocument();
        $document->loadXML($rendered);
    }

    public function testTableInForLoopSimple()
    {
        $templateDir = realpath(dirname(__FILE__) . '/../../../data/table-in-for-loop');

        // load original template
        $template = 'simple.xml';
        $loader = new OpenDocumentLoader(array($templateDir));
        $source = $loader->getSource($template);

        // load fixed template
        $fixed = file_get_contents($templateDir . '/simple-fixed.xml');

        $this->assertEquals($fixed, $source);

        // validate XML
        $rendered = $this->twig->render($source);
        $document = new \DOMDocument();
        $document->loadXML($rendered);
    }

    public function testTableInForLoopComplete()
    {
        $templateDir = realpath(dirname(__FILE__) . '/../../../data/table-in-for-loop');

        // load original template
        $template = 'complete.xml';
        $loader = new OpenDocumentLoader(array($templateDir));
        $source = $loader->getSource($template);

        // load fixed template
        $fixed = file_get_contents($templateDir . '/complete-fixed.xml');

        $this->assertEquals($fixed, $source);

        // validate XML
        $rendered = $this->twig->render($source);
        $document = new \DOMDocument();
        $document->loadXML($rendered);
    }

    public function testTableRowInForLoopSimple()
    {
        $templateDir = realpath(dirname(__FILE__) . '/../../../data/table-row-in-for-loop');

        // load original template
        $template = 'simple.xml';
        $loader = new OpenDocumentLoader(array($templateDir));
        $source = $loader->getSource($template);

        // load fixed template
        $fixed = file_get_contents($templateDir . '/simple-fixed.xml');

        $this->assertEquals($fixed, $source);

        // validate XML
        $rendered = $this->twig->render($source);
        $document = new \DOMDocument();
        $document->loadXML($rendered);
    }

    public function testTableRowInForLoopComplete()
    {
        $templateDir = realpath(dirname(__FILE__) . '/../../../data/table-row-in-for-loop');

        // load original template
        $template = 'complete.xml';
        $loader = new OpenDocumentLoader(array($templateDir));
        $source = $loader->getSource($template);

        // load fixed template
        $fixed = file_get_contents($templateDir . '/complete-fixed.xml');

        $this->assertEquals($fixed, $source);

        // validate XML
        $rendered = $this->twig->render($source);
        $document = new \DOMDocument();
        $document->loadXML($rendered);
    }

    public function testTableWithTitleInForLoopSimple()
    {
        $templateDir = realpath(dirname(__FILE__) . '/../../../data/table-with-title-in-for-loop');

        // load original template
        $template = 'simple.xml';
        $loader = new OpenDocumentLoader(array($templateDir));
        $source = $loader->getSource($template);

        // load fixed template
        $fixed = file_get_contents($templateDir . '/simple-fixed.xml');

        $this->assertEquals($fixed, $source);

        // validate XML
        $rendered = $this->twig->render($source);
        $document = new \DOMDocument();
        $document->loadXML($rendered);
    }

    public function testKoeKompasDierenartsSimple()
    {
        $templateDir = realpath(dirname(__FILE__) . '/../../../data/koekompas-dierenarts');

        // load original template
        $template = 'simple.xml';
        $loader = new OpenDocumentLoader(array($templateDir));
        $source = $loader->getSource($template);

        // load fixed template
        $fixed = file_get_contents($templateDir . '/simple-fixed.xml');

        $this->assertEquals($fixed, $source);

        // validate XML
        $rendered = $this->twig->render($source);
        $document = new \DOMDocument();
        $document->loadXML($rendered);
    }

//     public function testKoeKompasDierenartsComplete()
//     {
//         $templateDir = realpath(dirname(__FILE__) . '/../../../data/koekompas-dierenarts');

//         // load original template
//         $template = 'complete.xml';
//         $loader = new OpenDocumentLoader(array($templateDir));
//         $source = $loader->getSource($template);

//         // load fixed template
//         $fixed = file_get_contents($templateDir . '/complete-fixed.xml');

//         $this->assertEquals($fixed, $source);
//     }

    public function providerFixTagFormatting()
    {
        $data = array();

        $source = '<text:p text:style-name="Standard">{{ field.value }}</text:p>';
        $expected = '<text:p text:style-name="Standard">{{ field.value }}</text:p>';
        $data[] = array($source, $expected);

        $source = '<text:p text:style-name="Standard">{{field.value}}</text:p>';
        $expected = '<text:p text:style-name="Standard">{{ field.value }}</text:p>';
        $data[] = array($source, $expected);

        $source = '<text:p text:style-name="Standard">{{  field.value  }}</text:p>';
        $expected = '<text:p text:style-name="Standard">{{ field.value }}</text:p>';
        $data[] = array($source, $expected);

        $source = '<text:p text:style-name="Standard">{%image()%}</text:p>';
        $expected = '<text:p text:style-name="Standard">{% image() %}</text:p>';
        $data[] = array($source, $expected);

        return $data;
    }

    public function providerFixForLoops()
    {
        $data = array();

        $source = <<<XML
<text:p text:style-name="Standard">{% for row in rows %}{{ row.id }}{% endfor %}</text:p>
<text:p text:style-name="Standard" />
<text:p text:style-name="Standard">{% for row in rows %}{{ row.id }}</text:p>
<text:p text:style-name="Standard">{% endfor %}</text:p>
<text:p text:style-name="Standard" />
<text:p text:style-name="Standard">{% for row in rows %}</text:p>
<text:p text:style-name="Standard">{{ row.id }}{% endfor %}</text:p>
<text:p text:style-name="Standard" />
XML;

        $expected = <<<XML
<text:p text:style-name="Standard">{% for row in rows %}{{ row.id }}{% endfor %}</text:p>
<text:p text:style-name="Standard" />
{% for row in rows %}<text:p text:style-name="Standard">{{ row.id }}</text:p>
{% endfor %}
<text:p text:style-name="Standard" />
{% for row in rows %}
<text:p text:style-name="Standard">{{ row.id }}</text:p>{% endfor %}
<text:p text:style-name="Standard" />
XML;

        $data[] = array($source, $expected);

        $source = <<<XML
<table:table>
    <table:table-column />
    <table:table-column />
    <table:table-row>
        <table:table-cell>
            <text:p>ID</text:p>
        </table:table-cell>
        <table:table-cell>
            <text:p>NAME</text:p>
        </table:table-cell>
    </table:table-row>
    <table:table-row>
        <table:table-cell>
            <text:p>
                {% for row in rows %}
                <text:span>{{ row.id }}</text:span>
            </text:p>
        </table:table-cell>
        <table:table-cell>
            <text:p>
                <text:span>{{ row.name }}</text:span>
                {% endfor %}
            </text:p>
        </table:table-cell>
    </table:table-row>
</table:table>
XML;

        $expected = <<<XML
<table:table>
    <table:table-column />
    <table:table-column />
    <table:table-row>
        <table:table-cell>
            <text:p>ID</text:p>
        </table:table-cell>
        <table:table-cell>
            <text:p>NAME</text:p>
        </table:table-cell>
    </table:table-row>
    {% for row in rows %}<table:table-row>
        <table:table-cell>
            <text:p>
                <text:span>{{ row.id }}</text:span>
            </text:p>
        </table:table-cell>
        <table:table-cell>
            <text:p>
                <text:span>{{ row.name }}</text:span>
                </text:p>
        </table:table-cell>
    </table:table-row>{% endfor %}
</table:table>
XML;

        $data[] = array($source, $expected);

        $source = <<<XML
<table:table>
    <table:table-column />
    <table:table-column />
    <table:table-row>
        <table:table-cell>
            <text:p>ID</text:p>
        </table:table-cell>
        <table:table-cell>
            <text:p>NAME</text:p>
        </table:table-cell>
    </table:table-row>
    <table:table-row>
        <table:table-cell>
            <text:p>
                {% for row in rows %}
                    <text:span>{{ row.id }}</text:span>
                {% endfor %}
            </text:p>
        </table:table-cell>
    </table:table-row>
</table:table>
XML;

        $expected = <<<XML
<table:table>
    <table:table-column />
    <table:table-column />
    <table:table-row>
        <table:table-cell>
            <text:p>ID</text:p>
        </table:table-cell>
        <table:table-cell>
            <text:p>NAME</text:p>
        </table:table-cell>
    </table:table-row>
    <table:table-row>
        <table:table-cell>
            <text:p>
                {% for row in rows %}
                    <text:span>{{ row.id }}</text:span>
                {% endfor %}
            </text:p>
        </table:table-cell>
    </table:table-row>
</table:table>
XML;

        $data[] = array($source, $expected);

        $source = <<<XML
<text:p>{% for row in rows %}</text:p>
<table:table>
</table:table>
<text:p>{% endfor %}</text:p>
XML;

        $expected = <<<XML
{% for row in rows %}<table:table>
</table:table>{% endfor %}
XML;

        $data[] = array($source, $expected);

        $source = <<<XML
<text:p>{% for row in rows %}{{ row.title }}</text:p>
<table:table>
</table:table>
<text:p>{% endfor %}</text:p>
XML;

        $expected = <<<XML
{% for row in rows %}<text:p>{{ row.title }}</text:p>
<table:table>
</table:table>{% endfor %}
XML;

        $data[] = array($source, $expected);

        $source = <<<XML
<text:p>{% for row in rows %}</text:p>
<table:table>
</table:table>
<text:p>{{ row.title }}{% endfor %}</text:p>
XML;

        $expected = <<<XML
{% for row in rows %}<table:table>
</table:table>
<text:p>{{ row.title }}</text:p>{% endfor %}
XML;

        $data[] = array($source, $expected);

        $source = <<<XML
<text:p>{% for row in rows %}{{ row.title }}</text:p>
<table:table>
</table:table>
<text:p>{{ row.title }}{% endfor %}</text:p>
XML;

        $expected = <<<XML
{% for row in rows %}<text:p>{{ row.title }}</text:p>
<table:table>
</table:table>
<text:p>{{ row.title }}</text:p>{% endfor %}
XML;

        $data[] = array($source, $expected);

        $source = <<<XML
<text:p text:style-name="P2">{% for groupTitle,fields in groups2 %}{{groupTitle}}</text:p>
<table:table table:name="Table1" table:style-name="Table1">
    <table:table-column table:style-name="Table1.A" />
    <table:table-column table:style-name="Table1.B" />
    <table:table-row>
        <table:table-cell table:style-name="Table1.A1" office:value-type="string">
            <text:p text:style-name="Text_20_body">{% for field in fields %}{{field.label}}</text:p>
        </table:table-cell>
        <table:table-cell table:style-name="Table1.B1" office:value-type="string">
            <text:p text:style-name="Text_20_body">{{field.value}}{% endfor %}</text:p>
        </table:table-cell>
    </table:table-row>
</table:table>
<text:p text:style-name="Text_20_body">{% endfor %}</text:p>
XML;

        $expected = <<<XML
{% for groupTitle,fields in groups2 %}<text:p text:style-name="P2">{{ groupTitle }}</text:p>
<table:table table:name="Table1" table:style-name="Table1">
    <table:table-column table:style-name="Table1.A" />
    <table:table-column table:style-name="Table1.B" />
    {% for field in fields %}<table:table-row>
        <table:table-cell table:style-name="Table1.A1" office:value-type="string">
            <text:p text:style-name="Text_20_body">{{ field.label }}</text:p>
        </table:table-cell>
        <table:table-cell table:style-name="Table1.B1" office:value-type="string">
            <text:p text:style-name="Text_20_body">{{ field.value }}</text:p>
        </table:table-cell>
    </table:table-row>{% endfor %}
</table:table>{% endfor %}
XML;

        $data[] = array($source, $expected);

        $source = <<<XML
<text:p text:style-name="Text_20_body" />
<text:p text:style-name="P2">{% for groupTitle,fields in groups2 %}{{groupTitle}}</text:p>
<table:table table:name="Table1" table:style-name="Table1">
    <table:table-column table:style-name="Table1.A" />
    <table:table-column table:style-name="Table1.B" />
    <table:table-row>
        <table:table-cell table:style-name="Table1.A1" office:value-type="string">
            <text:p text:style-name="Text_20_body">{% for field in fields %}{{field.label}}</text:p>
        </table:table-cell>
        <table:table-cell table:style-name="Table1.B1" office:value-type="string">
            <text:p text:style-name="Text_20_body">{{field.value}}{% endfor %}</text:p>
        </table:table-cell>
    </table:table-row>
</table:table>
<text:p text:style-name="Text_20_body">{% endfor %}</text:p>
<text:p text:style-name="Text_20_body" />
XML;

        $expected = <<<XML
<text:p text:style-name="Text_20_body" />
{% for groupTitle,fields in groups2 %}<text:p text:style-name="P2">{{ groupTitle }}</text:p>
<table:table table:name="Table1" table:style-name="Table1">
    <table:table-column table:style-name="Table1.A" />
    <table:table-column table:style-name="Table1.B" />
    {% for field in fields %}<table:table-row>
        <table:table-cell table:style-name="Table1.A1" office:value-type="string">
            <text:p text:style-name="Text_20_body">{{ field.label }}</text:p>
        </table:table-cell>
        <table:table-cell table:style-name="Table1.B1" office:value-type="string">
            <text:p text:style-name="Text_20_body">{{ field.value }}</text:p>
        </table:table-cell>
    </table:table-row>{% endfor %}
</table:table>{% endfor %}
<text:p text:style-name="Text_20_body" />
XML;

        $data[] = array($source, $expected);

        return $data;
    }
}
