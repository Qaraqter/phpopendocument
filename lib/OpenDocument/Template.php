<?php

namespace OpenDocument;

use OpenDocument\Exception\InvalidCacheDirectoryException;
use OpenDocument\Twig\OpenDocumentLoader;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class representing an Open Document file.
 *
 * @author Bart Huttinga <bart@qaraqter.nl>
 */
class Template
{
    /**
     * Generator instance.
     *
     * @var Generator
     */
    private $generator;

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

    protected $contentXml;

    protected $stylesXml;

    protected $tmpfile;

    protected $images = array();

    protected $vars = array();

    /**
     * Class constructor
     *
     * @param string $filename File name of the ODT file
     *
     * @throws \RunTimeException
     */
    public function __construct(Generator $generator, $filename)
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
        $this->stylesXml = $this->archive->getFromName('styles.xml');
        if (!$this->stylesXml) {
            throw new \RunTimeException('An error occured while reading styles.xml from the archive.');
        }
        $this->archive->close();

        $this->generator = $generator;
        $this->filename  = $filename;
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

    public function getContentXml()
    {
        return $this->contentXml;
    }

    public function getStylesXml()
    {
        return $this->stylesXml;
    }

    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    public function getFilename()
    {
        return $this->filename;
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
}
