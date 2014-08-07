<?php
namespace OpenDocument;

use OpenDocument\Exception\InvalidCacheDirectoryException;
use OpenDocument\Twig\OpenDocumentLoader;

class Generator
{
    /**
     * The Twig environment used for rendering the template.
     *
     * @var \Twig_Environment
     */
    private $twig;

    /**
     * Directory to write cache files to.
     *
     * @var string
     */
    private $cacheDir;

    /**
     * Document template.
     *
     * @var Template
     */
    private $template;

    /**
     * Class constructor
     *
     * @param string $cacheDir
     */
    public function __construct(\Twig_Environment $twig, $cacheDir)
    {
        $this->twig = $twig;
        $this->setCacheDir($cacheDir);
    }

    public function __destruct()
    {
        $this->twig->clearTemplateCache();
    }

    public function getTwig()
    {
        return $this->twig;
    }

    /**
     * Loads the template file.
     *
     * @param string $filename
     */
    public function loadTemplate($filename)
    {
        $this->template = new Template($this, $filename);
        $this->document = new Document($this);
    }

    /**
     * Returns the loaded template.
     *
     * @return \OpenDocument\Template
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Returns the document to work on.
     *
     * @return \OpenDocument\Document
     */
    public function getDocument()
    {
        return $this->document;
    }

    public function render($data = array())
    {
        $this->document->render($data);

        return $this->document;
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
}
