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
        self::fixAposWithinTags($source);
        self::fixStyledTags($source);
        self::fixTagFormatting($source);
        self::fixForLoops($source);
//         self::fixTableRowForLoop($source);
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
        $pattern = '#{{.*?}}|{%.*?%}#s';

        $callback = function(array $matches) {
            return preg_replace('/&apos;/', "'", $matches[0]);
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
     *     <text:p text:style-name="Standard">{{<text:span text:style-name="T1">chart</text:span>}}</text:p>
     * becomes
     *     <text:p text:style-name="Standard">{{chart}}</text:p>
     */
    static public function fixStyledTags(&$source)
    {
        $pattern = '#({{|{%)(.*?)(%}|}})#s';

        $callback = function(array $matches) {
            if (preg_match('#[<>]#', $matches[2])) {
                return $matches[1] . strip_tags(preg_replace('#\s#s', '', $matches[2])) . $matches[3];
            }

            return $matches[0];
        };

        $source = preg_replace_callback($pattern, $callback, $source);
    }

    /**
     * Fix empty paragraphs.
     *
     * Strips paragraph tags from paragraphs that only contain Twig-tags and do
     * not output anything.
     */
    static public function fixEmptyParagraphs(&$source)
    {
        $pattern = '/\<text:p\s?[^/>]*?\>({%.*?%})<\/text:p\>/s';

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
        $pattern = '#(<text:p\s?[^/>]*?>)(([^/>]*?)({% for .*? in .*? %})(.*?))(</text:p>)#s';

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
        $pattern = '#(<text:p\s?[^/>]*?>)(([^/>]*?)({% endfor %})([^/>]*?))(</text:p>)#s';

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
        $pattern = '#(<table:table-row\s?[^/>]*?>\s*?<table:table-cell\s?[^/>]*?>\s*?<text:p\s?[^/>]*?>)\s*?({% for .*? in .*? %})(.*?)({% endfor %})\s*?(</text:p>\s*?</table:table-cell>\s*?</table:table-row>)#s';

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
        $pattern = '#(<text:p\s?[^/>]*?>\s*?)({% for .*? in .*? %})(.*?)(</text:p>)(.*?)(<table:table\s?[^/>]*?>.*?</table:table>)(\s*?<text:p\s?[^/>]*?>)(.*?)({% endfor %})(\s*?</text:p>)#s';

        $callback = function(array $matches) {
            $replacement = $matches[2];

            // keep opening text:p-tag if it has contents
            if (trim($matches[3])) {
                $replacement .= $matches[1] . $matches[3] . $matches[4];
            }

            $replacement .= $matches[5] . $matches[6];

            // keep closing text:p-tag if it has contents
            if (trim($matches[8])) {
                $replacement .= $matches[7] . $matches[8] . $matches[10];
            }

            $replacement .= $matches[9];

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
        self::fixForLoopTableRow($source);
        self::fixForLoopTable($source);
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
        $pattern = '#(<table:table-row\s?[^/>]*?>\s*<table:table-cell\s?[^/>]*?>\s*<text:p\s?[^/>]*?>\s*)({% for .*? in .*? %})\s*(.*?)\s*({% endfor %})(\s*<\/text:p>\s*<\/table:table-cell>\s*<\/table:table-row>)#s';

        $callback = function(array $matches) {
            return $matches[2] . $matches[1] . $matches[3] . $matches[5] . $matches[4];
        };

        $source = preg_replace_callback($pattern, $callback, $source);
    }
}
