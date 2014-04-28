<?php
namespace OpenDocument\Twig;

use OpenDocument\Twig\OpenDocumentLoader;

class OpenDocumentLoaderTest extends \PHPUnit_Framework_TestCase
{
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
    }
}
