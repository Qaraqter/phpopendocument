<?php

namespace OpenDocument;

use OpenDocument\Exception\InvalidCacheDirectoryException;
use OpenDocument\Twig\OpenDocumentLoader;

/**
 * Class representing an Open Document file.
 *
 * @author Bart Huttinga <bart@qaraqter.nl>
 */
class File
{
    /**
     * Directory to write cache files to.
     *
     * @var string
     */
    private $cacheDir;

    /**
     * The filename of the Open Document file.
     *
     * @var string
     */
    private $filename;

    /**
     * The Open Document file (which in fact is a zipped archive).
     *
     * @var \ZipArchive
     */
    private $archive;

    /**
     * The Twig environment used for rendering the template.
     *
     * @var \Twig_Environment
     */
    private $twig;

    protected $contentXml;        // To store content of content.xml file
    protected $stylesXml;       // To store content of styles.xml file
    protected $tmpfile;
    protected $images = array();
    protected $vars = array();

    /**
     * Class constructor
     *
     * @param string $filename File name of the ODT file
     * @param string $cacheDir Path to cache directory
     *
     * @throws \RunTimeException
     */
    public function __construct($filename, $cacheDir)
    {
        if (!class_exists('\ZipArchive')) {
            throw new \RunTimeException('Class \ZipArchive is required, but could not be found.');
        }

        $this->archive = new \ZipArchive();
        if (!$this->archive->open($filename)) {
            throw new \RunTimeException('Could not open file ' . $filename);
        }

        $this->contentXml = $this->archive->getFromName('content.xml');
        if (!$this->contentXml) {
            throw new \RunTimeException('An error occured while reading content.xml from the archive.');
        }
        $this->archive->close();

        $this->filename = $filename;
        $this->setCacheDir($cacheDir);
    }

    /**
     * Deletes the temporary file.
     */
    public function __destruct()
    {
        if (file_exists($this->tmpfile)) {
            unlink($this->tmpfile);
        }
    }

    /**
     * Initializes and returns a configured Twig environment.
     *
     * @return \Twig_Environment
     */
    private function getTwigEnvironment()
    {
        if (!$this->twig) {
            $loader = new OpenDocumentLoader($this->cacheDir);
            $twig   = new \Twig_Environment($loader, array('cache' => $this->cacheDir));

            // add OpenDocument extension
            $extension = new Twig\OpenDocumentExtension();
            $twig->addExtension($extension);

            $this->twig = $twig;
        }

        return $this->twig;
    }

    public function render(array $params = array())
    {
        $twig = $this->getTwigEnvironment();

        // put XML template in temp file
        $template = md5($this->contentXml);
        file_put_contents("$this->cacheDir/$template", $this->contentXml);

        // render template with given parameters
        $this->contentXml = $twig->render($template, $params);
    }

    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * Sets the cache directory.
     *
     * @param string $cacheDir
     * @throws InvalidCacheDirectoryException
     */
    public function setCacheDir($cacheDir)
    {
        $cacheDir = realpath($cacheDir);

        if (!file_exists($cacheDir)) {
            throw new InvalidCacheDirectoryException("Cache directory $cacheDir does not exist.");
        }

        if (!is_dir($cacheDir)) {
            throw new InvalidCacheDirectoryException("$cacheDir is not a directory.");
        }

        if (!is_writable($cacheDir)) {
            throw new InvalidCacheDirectoryException("Cache directory $cacheDir is not writable.");
        }

        $this->cacheDir = $cacheDir;
    }

    /**
     * Save the odt file on the disk
     *
     * @param string $file name of the desired file
     * @throws OdfException
     * @return void
     */
    public function saveToDisk($file = null)
    {
        if ($file !== null && is_string($file)) {
            if (file_exists($file) && !(is_file($file) && is_writable($file))) {
                throw new OdfException('Permission denied : can\'t create ' . $file);
            }
            $this->_save();
            copy($this->tmpfile, $file);
        } else {
            $this->_save();
        }
    }

    /**
     * Internal save
     *
     * @throws \RuntimeException
     */
    private function _save()
    {
        // create copy of original file
        $this->tmpfile = tempnam($this->cacheDir, md5($this->filename));
        copy($this->filename, $this->tmpfile);

        // open temporary archive and put rendered in it
        $this->archive->open($this->tmpfile);
        if (!$this->archive->addFromString('content.xml', $this->contentXml)) {
             throw new \RuntimeException('An error occured while writing the rendered XML.');
        }

        // add images to Pictures directory
        $images = $this->getTwigEnvironment()->getExtension('open_document')->getImages();
        foreach ($images as $image) {
            $this->archive->addFile("$this->cacheDir/$image", "Pictures/$image");
        }

        // add images to manifest XML
        $manifest = $this->archive->getFromName('META-INF/manifest.xml');
        $tmpManifestFile = md5($manifest);
        file_put_contents("$this->cacheDir/$tmpManifestFile", $manifest);

        $document = new \DomDocument();
        $document->loadXml(file_get_contents("$this->cacheDir/$tmpManifestFile"));
        $rootElement = $document->getElementsByTagName('manifest')->item(0);
        foreach ($images as $image) {
            $element = $document->createElement('manifest:file-entry');
            $element->setAttribute('manifest:full-path', "Pictures/$image");
            $element->setAttribute('manifest:media-type', '');
            $rootElement->appendChild($element);
        }
        $this->archive->addFromString('META-INF/manifest.xml', $document->saveXML());

        // close archive
        $this->archive->close();
    }

    /**
     * Export the file as attached file by HTTP
     *
     * @param string $name (optionnal)
     * @throws OdfException
     * @return void
     */
    public function exportAsAttachedFile($name="")
    {
        $this->_save();
        if (headers_sent($filename, $linenum)) {
            throw new OdfException("headers already sent ($filename at $linenum)");
        }

        if( $name == "" )
        {
                $name = md5(uniqid()) . ".odt";
        }

        header('Content-type: application/vnd.oasis.opendocument.text');
        header('Content-Disposition: attachment; filename="'.$name.'"');
        readfile($this->tmpfile);
    }
}
