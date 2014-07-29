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
     * Cache directory.
     *
     * @var string
     */
    private $cacheDir;

    /**
     * The Open Document file (which in fact is a zipped archive).
     *
     * @var \ZipArchive
     */
    private $archive;

    /**
     * The content XML.
     *
     * @var string
     */
    protected $contentXml;

    /**
     * The styles XML.
     *
     * @var string
     */
    protected $stylesXml;

    /**
     * Collection of images.
     *
     * @var array
     */
    protected $images = array();

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;

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
        $this->cacheDir   = $generator->getCacheDir();
        $this->contentXml = $this->template->getContentXml();
        $this->stylesXml  = $this->template->getStylesXml();
        $this->filesystem = new Filesystem();

        // create copy of template
        $this->filename = tempnam($this->cacheDir, 'opendocument_');
        $this->filesystem->copy(
            $this->template->getFileName(),
            $this->filename,
            true
        );

        // open archive
        $this->archive = new \ZipArchive();
        $this->archive->open($this->filename);
    }


    /**
     * Deletes the temporary file.
     */
    public function __destruct()
    {
        // close archive
        $this->archive->close();

//         if (file_exists($this->filename)) {
//             unlink($this->tmpFile);
//         }
    }

    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function setContentXml($contentXml)
    {
        $this->contentXml = $contentXml;

        return $this;
    }

    public function setStylesXml($stylesXml)
    {
        $this->stylesXml = $stylesXml;

        return $this;
    }

    public function render($data = array())
    {
        $this->renderContent($data);
        $this->renderStyles($data);
        $this->save();
    }

    private function renderContent($data = array())
    {
        $tempFile = md5($this->contentXml);

        // put content XML in temporary file
        if (! $this->filesystem->exists("$this->cacheDir/$tempFile")) {
            $this->filesystem->dumpFile("$this->cacheDir/$tempFile", $this->contentXml);
        }

        // render content XML
        $twig = $this->generator->getTwig();
        $this->contentXml = $twig->render($tempFile, $data);
    }

    private function renderStyles($data = array())
    {
        $tempFile = md5($this->stylesXml);

        // put styles XML in temporary file
        if (! $this->filesystem->exists("$this->cacheDir/$tempFile")) {
            $this->filesystem->dumpFile("$this->cacheDir/$tempFile", $this->stylesXml);
        }

        // render styles XML
        $twig = $this->generator->getTwig();
        $this->stylesXml = $twig->render($tempFile, $data);
    }

    /**
     * Saves the archive.
     *
     * @throws \RuntimeException
     */
    public function save($filename = null, $override = false)
    {
        if (! $this->archive->addFromString('content.xml', $this->contentXml)) {
            throw new \RuntimeException('An error occured while writing the rendered content XML.');
        }

        if (! $this->archive->addFromString('styles.xml', $this->stylesXml)) {
            throw new \RuntimeException('An error occured while writing the rendered styles XML.');
        }

        // add images to Pictures directory
        $images = $this->generator->getTwig()->getExtension('open_document')->getImages();
        foreach ($images as $image) {
            $this->archive->addFile("$this->cacheDir/$image", "Pictures/$image");
        }

        // add images to manifest XML
        $manifest = $this->archive->getFromName('META-INF/manifest.xml');
        $tempFile = md5($manifest);
        if (! $this->filesystem->exists("$this->cacheDir/$tempFile")) {
            $this->filesystem->dumpFile("$this->cacheDir/$tempFile", $manifest);
        }

        $document = new \DomDocument();
        $document->loadXml(file_get_contents("$this->cacheDir/$tempFile"));
        $rootElement = $document->getElementsByTagName('manifest')->item(0);
        foreach ($images as $image) {
            $element = $document->createElement('manifest:file-entry');
            $element->setAttribute('manifest:full-path', "Pictures/$image");
            $element->setAttribute('manifest:media-type', '');
            $rootElement->appendChild($element);
        }
        $this->archive->addFromString('META-INF/manifest.xml', $document->saveXML());

        if ($filename) {
            $this->archive->close();
            $this->filesystem->copy($this->filename, $filename, $override);
            $this->archive->open($this->filename);
        }
    }
}
