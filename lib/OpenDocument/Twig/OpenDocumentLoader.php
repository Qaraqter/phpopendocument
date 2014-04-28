<?php
namespace OpenDocument\Twig;

class OpenDocumentLoader extends \Twig_Loader_Filesystem
{
    /**
     * {@inheritdoc}
     */
    public function getSource($name)
    {
        $source = parent::getSource($name);

        $this->fixStyledTags($source);
        $this->fixEmptyParagraphs($source);
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
     * Fix empty paragraphs.
     *
     * Strips paragraph tags from paragraphs that only contain Twig-tags and do
     * not output anything.
     */
    private function fixEmptyParagraphs(&$source)
    {
        $pattern = '/\<text:p[^>]*?\>({%.*?%})<\/text:p\>/';

        $callback = function(array $matches) {
            // no replacement if the tag contains Twig output tags
            if (preg_match('/({{|}})/', $matches[1], $newMatches)) {
                return $matches[0];
            }
            return $matches[1];
        };

        $source = preg_replace_callback($pattern, $callback, $source);
    }

    /**
     * Fix for-loops in tables.
     *
     * If a for-loop applies to the whole row, the start-tag should be placed
     * right before the table-row tag and the end-tag after it.
     */
    private function fixTableRowForLoop(&$source)
    {
        $pattern = '#(<table:table-row[^>]*?><table:table-cell[^>]*?><text:p[^>]*?>)({% for .* %})(.*)({% endfor %})(<\/text:p><\/table:table-cell><\/table:table-row>)#';

        $callback = function(array $matches) {
            return $matches[2] . $matches[1] . $matches[3] . $matches[5] . $matches[4];
        };

        $source = preg_replace_callback($pattern, $callback, $source);
    }
}
