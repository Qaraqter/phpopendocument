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

    protected $tempFiles = array();

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

        $this->filename = tempnam($this->cacheDir, 'working_copy_');
        $this->filesystem->copy($this->template->getFileName(), $this->filename, true);
    }

    /**
     * Deletes the temporary file.
     */
    public function __destruct()
    {
        if (file_exists($this->filename)) {
            unlink($this->filename);
        }

        foreach ($this->tempFiles as $file) {
            $this->filesystem->remove($file);
        }
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
        $fullPath = "$this->cacheDir/$tempFile";

        // put content XML in temporary file
        if (! $this->filesystem->exists($fullPath)) {
            $this->filesystem->dumpFile($fullPath, $this->contentXml);
            $this->tempFiles[] = $fullPath;
        }

        // render content XML
        $twig = $this->generator->getTwig();
        $this->contentXml = $twig->render($tempFile, $data);
    }

    private function renderStyles($data = array())
    {
        $tempFile = md5($this->stylesXml);
        $fullPath = "$this->cacheDir/$tempFile";

        // put styles XML in temporary file
        if (! $this->filesystem->exists($fullPath)) {
            $this->filesystem->dumpFile($fullPath, $this->stylesXml);
            $this->tempFiles[] = $fullPath;
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
        // open archive
        $archive = new \ZipArchive();
        $archive->open($this->filename);

        if (! $archive->addFromString('content.xml', $this->contentXml)) {
            throw new \RuntimeException('An error occured while writing the rendered content XML.');
        }

        if (! $archive->addFromString('styles.xml', $this->stylesXml)) {
            throw new \RuntimeException('An error occured while writing the rendered styles XML.');
        }

        // add images to Pictures directory
        $images = $this->generator->getTwig()->getExtension('open_document')->getImages();
        foreach ($images as $image) {
            $archive->addFile("$this->cacheDir/$image", "Pictures/$image");
        }

        // add images to manifest XML
        $manifest = $archive->getFromName('META-INF/manifest.xml');
        $tempFile = md5($manifest);
        $fullPath = "$this->cacheDir/$tempFile";

        if (! $this->filesystem->exists($fullPath)) {
            $this->filesystem->dumpFile($fullPath, $manifest);
            $this->tempFiles[] = $fullPath;
        }

        $dom = new \DomDocument();
        $dom->loadXml(file_get_contents("$this->cacheDir/$tempFile"));
        $rootElement = $dom->getElementsByTagName('manifest')->item(0);
        foreach ($images as $image) {
            $element = $dom->createElement('manifest:file-entry');
            $element->setAttribute('manifest:full-path', "Pictures/$image");
            $element->setAttribute('manifest:media-type', '');
            $rootElement->appendChild($element);
        }
        $archive->addFromString('META-INF/manifest.xml', $dom->saveXML());

        // close archive
        $archive->close();

        // copy to given filename (if any)
        if ($filename) {
            $this->filesystem->copy($this->filename, $filename, $override);
            $this->filesystem->chmod($filename, 0666);
        }
    }
}
