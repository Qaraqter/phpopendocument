<?php
namespace OpenDocument\Twig;

class OpenDocumentLoader extends \Twig_Loader_Filesystem
{
//     private $lastTemplate;

    /**
     * {@inheritdoc}
     */
    public function getSource($name)
    {
        $source = parent::getSource($name);

        $this->fixStyledTags($source);
        $this->fixTableRowForLoop($source);

        return $source;
    }

    public function getLatestTemplate()
    {
        return basename(current($this->cache));
    }

    /**
     * Fix tags in the template.
     *
     * When copying a tag in LibreOffice somtimes the tag name is put in a
     * <span> element inside the containing <p> element, while the tag delimiters
     * remain in the <p> element.
     *
     * An example:
     *     <text:p text:style-name="Standard">{<text:span text:style-name="T1">chart</text:span>}</text:p>
     * becomes
     *     <text:p text:style-name="Standard">{chart}</text:p>
     */
    private function fixStyledTags(&$source)
    {
//         $document = new \DOMDocument();
//         $document->loadXML($source);

//         $xpath = new \DOMXpath($document);

//         $elements = $xpath->query('/office:document-content/office:body/office:text/*');
//         foreach ($elements as $element) {
//             if (preg_match('/^{{.*}}$/', $element->nodeValue)
//                 && $element->childNodes->length > 1
//             ) {
//                 $element->nodeValue = $element->nodeValue;
//             }
//         }
//         var_dump(__FILE__, __LINE__, $source); die;
    }

    /**
     * Fix for-loops in tables.
     *
     * If a for-loop applies to the whole row, the start-tag should be placed
     * right before the table-row tag and the end-tag after it.
     */
    private function fixTableRowForLoop(&$source)
    {
        $document = new \DOMDocument();
        $document->loadXML($source);

        $xpath = new \DOMXpath($document);

        $tables = $document->getElementsByTagName('table');
        foreach ($tables as $table) {
            $tableRows = $xpath->query('table:table-row', $table);
            foreach ($tableRows as $tableRow) {
                if (preg_match('/^({%\s*for.*%}).*({%\s*endfor\s*%})$/', $tableRow->nodeValue)) {
                    $tableXml = $document->saveXML($table);
                    $pattern = '/^(.*)(<table:table-row.*>)({%\s*for.*%})(.*)({%\s*endfor\s*%})(.*<\/table:table-row>)(.*)$/';
                    $tableXml = preg_replace($pattern, '$1$3$2$4$6$5$7', $tableXml);

                    $fragment = $document->createDocumentFragment();
                    @$fragment->appendXML($tableXml);
                    $xpath->query('..', $table)->item(0)->replaceChild($fragment, $table);
                }
            }
        }

        $source = $document->saveXML();
    }
}
