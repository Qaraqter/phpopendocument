<?php

namespace OpenDocument;

use OpenDocument\Exception\InvalidCacheDirectoryException;
use OpenDocument\Twig\OpenDocumentLoader;

/**
 * Class representing an Open Document file.
 *
 * @author Bart Huttinga <bart@qaraqter.nl>
 */
class Document
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

    protected $contentXml;        // To store content of content.xml file
    protected $stylesXml;       // To store content of styles.xml file
    protected $tmpFile;
    protected $images = array();
    protected $vars = array();

    /**
     * Deletes the temporary file.
     */
    public function __destruct()
    {
        if (file_exists($this->tmpFile)) {
//             unlink($this->tmpFile);
        }
    }

    /**
     * Class constructor
     *
     * @param string $filename File name of the ODT file
     *
     * @throws \RunTimeException
     */
    public function __construct(Generator $generator)
    {
        $this->generator  = $generator;
        $this->template   = $generator->getTemplate();
        $this->contentXml = $this->template->getContentXml();

        // create copy of template
        $this->cacheDir = $generator->getCacheDir();
        $templateFilename = $this->template->getFileName();
        $this->tmpFile = tempnam($this->cacheDir, md5($templateFilename));
        copy($templateFilename, $this->tmpFile);

        $this->archive = new \ZipArchive();
    }

    public function render($data = array())
    {
        $twig       = $this->generator->getTwig();
        $cacheDir   = $this->generator->getCacheDir();

        // put XML template in temp file
        $template = md5($this->contentXml);
        file_put_contents("$cacheDir/$template", $this->contentXml);

        // render template with given parameters
        $this->contentXml = $twig->render($template, $data);
    }

    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    public function save($filename)
    {
        $this->filename = $this->tmpFile;
        $this->saveToDisk($filename);

        return $this;
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
//             copy($this->tmpFile, $file);
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
        // open temporary archive and put rendered in it
        $this->archive->open($this->tmpFile);
        if (!$this->archive->addFromString('content.xml', $this->contentXml)) {
             throw new \RuntimeException('An error occured while writing the rendered XML.');
        }

        // add images to Pictures directory
        $images = $this->generator->getTwig()->getExtension('open_document')->getImages();
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
        readfile($this->tmpFile);
    }
}
