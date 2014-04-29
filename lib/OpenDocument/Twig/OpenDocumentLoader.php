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

        self::fix($source);

        return $source;
    }

    public function getLatestTemplate()
    {
        return basename(current($this->cache));
    }

    static public function fix(&$source)
    {
        self::fixTagFormatting($source);
        self::fixAposWithinTags($source);
        self::fixForLoops($source);
//         self::fixTableRowForLoop($source);
//         self::fixStyledTags($source);
//         self::fixEmptyParagraphs($source);
    }

    /**
     * Cleans up Twig tags before further processing.
     *
     * Makes sure Twig-tags are always formatted with exactly one space after
     * opening and one space before closing. Like this:
     *  - {{ var }}
     *  - {% function %}
     *
     * @param string $source
     */
    static public function fixTagFormatting(&$source)
    {
        $pattern = '/({{|{%)(.*?)(%}|}})/';

        $callback = function(array $matches) {
            return $matches[1] . ' ' . trim($matches[2]) . ' ' . $matches[3];
        };

        $source = preg_replace_callback($pattern, $callback, $source);
    }

    /**
     * @param string $source
     */
    static public function fixAposWithinTags(&$source)
    {
        $pattern = '/({{\s.*?\s}}|{%\s.*?\s%})/';

        $callback = function(array $matches) {
            return preg_replace('/&apos;/', "'", $matches[1]);
        };

        $source = preg_replace_callback($pattern, $callback, $source);
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
    static public function fixStyledTags(&$source)
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
    static public function fixEmptyParagraphs(&$source)
    {
        $pattern = '/\<text:p[^/>]*?\>({%.*?%})<\/text:p\>/s';

        $callback = function(array $matches) {
            // no replacement if the tag contains Twig output tags
            if (preg_match('/({{|}})/', $matches[1], $newMatches)) {
                return $matches[0];
            }
            return $matches[1];
        };

        $source = preg_replace_callback($pattern, $callback, $source);
    }

    static private function fixForLoopOpeningTags(&$source)
    {
        // opening tag
        $pattern = '#(<text:p[^/>]*?>)(([^/>]*?)({% for .*? in .*? %})(.*?))(</text:p[^/>]*?>)#s';

        $callback = function(array $matches) {
            // no replacement if endfor-tag is within XML-tag
            if (preg_match('/{% endfor %}/', $matches[2])) {
                return $matches[0];
            }

            // remove XML-tag altogether if empty
            if (!$matches[3] && !$matches[5]) {
                return $matches[4];
            }

            return $matches[4] . $matches[1] . $matches[3] . $matches[5] . $matches[6];
        };

        $source = preg_replace_callback($pattern, $callback, $source);
    }

    static private function fixForLoopClosingTags(&$source)
    {
        // closing tag
        $pattern = '#(<text:p[^/>]*?>)(([^/>]*?)({% endfor %})([^/>]*?))(</text:p[^/>]*?>)#s';

        $callback = function(array $matches) {
            // no replacement if for-tag is within XML-tag
            if (preg_match('/{% for .*? in .*? %}/', $matches[2], $x)) {
                return $matches[0];
            }

            // remove XML-tag altogether if empty
            if (!$matches[3] && !$matches[5]) {
                return $matches[4];
            }

            // place {% endfor %} after XML-tag
            return $matches[1] . $matches[3] . $matches[5] . $matches[6] . $matches[4];
        };

        $source = preg_replace_callback($pattern, $callback, $source);
    }

    static private function fixForLoopTableRow(&$source)
    {
        // for-loops for table rows
        $pattern = '#(<table:table-row[^/>]*?>\s*?<table:table-cell[^/>]*?>\s*?<text:p[^/>]*?>)\s*?({% for .*? in .*? %})(.*?)({% endfor %})\s*?(</text:p>\s*?</table:table-cell>\s*?</table:table-row>)#s';

        $callback = function(array $matches) {
            // no replacement if for-loop is closed within text:p-tag
            if (!preg_match('#</text:p>#', $matches[3])) {
                return $matches[0];
            }
            return $matches[2] . $matches[1] . $matches[3] . $matches[5] . $matches[4];
        };

        $source = preg_replace_callback($pattern, $callback, $source);
    }

    static private function fixForLoopTable(&$source)
    {
        // for-loops for tables
        $pattern = '#(<text:p[^/>]*?>\s*?)({% for .*? in .*? %})(.*?)(</text:p>\s*?)(<table:table[^/>]*?>.*?</table:table>)(\s*?<text:p[^/>]*?>)(.*?)({% endfor %})(\s*?</text:p>)#s';

        $callback = function(array $matches) {

            $replacement = $matches[2];

            // keep opening text:p-tag if it has contents
            if (trim($matches[3])) {
                $replacement .= $matches[1] . $matches[3] . $matches[4];
            }

            $replacement .= $matches[5];

            // keep closing text:p-tag if it has contents
            if (trim($matches[7])) {
                $replacement .= $matches[6] . $matches[7] . $matches[9];
            }

            $replacement .= $matches[8];

            return $replacement;
        };

        $source = preg_replace_callback($pattern, $callback, $source);
    }

    /**
     * Fix for loop that is not completed within a XML tag.
     *
     * Changes "<text:p>{% for title in titles %}{{ title }}</text:p>"
     * to "{% for title in titles %}<text:p>{{ title }}</text:p>"
     */
    static public function fixForLoops(&$source)
    {
        self::fixForLoopTable($source);
        self::fixForLoopTableRow($source);
        self::fixForLoopOpeningTags($source);
        self::fixForLoopClosingTags($source);
    }

    /**
     * Fix for-loops in tables.
     *
     * If a for-loop applies to the whole row, the start-tag should be placed
     * right before the table-row tag and the end-tag after it.
     */
    static public function fixTableRowForLoop(&$source)
    {
        $pattern = '#(<table:table-row[^/>]*?>\s*<table:table-cell[^/>]*?>\s*<text:p[^/>]*?>\s*)({% for .*? in .*? %})\s*(.*?)\s*({% endfor %})(\s*<\/text:p>\s*<\/table:table-cell>\s*<\/table:table-row>)#s';

        $callback = function(array $matches) {
            return $matches[2] . $matches[1] . $matches[3] . $matches[5] . $matches[4];
        };

        $source = preg_replace_callback($pattern, $callback, $source);
    }
}
