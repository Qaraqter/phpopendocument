<?php
namespace OpenDocument\Twig;

use OpenDocument\Exception\FileNotFoundException;
use OpenDocument\Exception\InvalidImageSizeException;

class OpenDocumentExtension extends \Twig_Extension
{
    const PIXEL_TO_CM = 0.026458333;

    private $images = array();

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'open_document';
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction(
                'image',
                array($this, 'image'),
                array(
                    'needs_environment' => true,
                    'is_safe' => array('html'),
                )
            ),
        );
    }

    public function image(\Twig_Environment $environment, $filename, $config = array())
    {
        // save a local copy of the image if $filename is an URL
        $urlInfo = parse_url($filename);
        if (key_exists('scheme', $urlInfo) && preg_match('/^http/', $urlInfo['scheme'])) {
            if (!$environment->getCache()) {
                throw new \RuntimeException('To use URLs as image filename caching must be enabled for the Twig Environment.');
            }
            $cacheFilename = $environment->getCache() . '/' . basename($urlInfo['path']);
            copy($filename, $cacheFilename);
            $filename = $cacheFilename;
            $this->cacheFiles[] = $filename;
        }

        $filename = realpath($filename);
        if (!$filename) {
            return;
            throw new FileNotFoundException("File $filename not found");
        }

        if (!is_file($filename)) {
            return;
        }

        $pattern = '/^(scale|[0-9.]+(cm|%))$/';
        if (isset($config['width']) && !preg_match($pattern, $config['width'])) {
            throw new InvalidImageSizeException($config['width'] . ' is not a valid image width. Pattern must match ' . $pattern);
        }
        if (isset($config['height']) && !preg_match($pattern, $config['height'])) {
            throw new InvalidImageSizeException($config['height'] . ' is not a valid image height. Pattern must match ' . $pattern);
        }

        // add image to collection
        $pathInfo = pathinfo($filename);
        $imageName = 'image-' . (count($this->images) + 1);
        $this->images[$imageName] = $pathInfo['basename'];

        // create DOM document
        $document = new \DOMDocument();
        $document->loadXML($this->getTemplateSource($environment));

        // create image frame
        $frame = $document->createElement('draw:frame');
        $frame->setAttribute('draw:name', $imageName);
//         $frame->setAttribute('draw:style-name', 'fr1');
        $frame->setAttribute('text:anchor-type', 'paragraph');
//         $frame->setAttribute('draw:z-index', '0');
        $document->appendChild($frame);

        // set original image size
        list ($width, $height) = $this->getOriginalImageSize($filename);
        $frame->setAttribute('svg:width', $width);
        $frame->setAttribute('svg:height', $height);
        $frame->setAttribute('style:rel-width', 'scale');
        $frame->setAttribute('style:rel-height', 'scale');

        // read image width from config
        if (key_exists('width', $config)) {
            if (preg_match('/%$/', $config['width'])) {
                $frame->setAttribute('style:rel-width', $config['width']);
            } else {
                $frame->setAttribute('svg:width', $config['width']);
            }
        }
        // read image height from config
        if (key_exists('height', $config)) {
            if (preg_match('/%$/', $config['height'])) {
                $frame->setAttribute('style:rel-height', $config['height']);
            } else {
                $frame->setAttribute('svg:height', $config['height']);
            }
        }

        // create image
        $image = $document->createElement('draw:image');
        $image->setAttribute('xlink:href', 'Pictures/' . $pathInfo['basename']);
        $image->setAttribute('xlink:type', 'simple');
        $image->setAttribute('xlink:show', 'embed');
        $image->setAttribute('xlink:actuate', 'onLoad');
        $frame->appendChild($image);

        return $document->saveXML($frame);
    }

    public function getImages()
    {
        return $this->images;
    }

    private function getOriginalImageSize($filename)
    {
        $pathInfo = pathinfo($filename);

        if ($pathInfo['extension'] == 'svg') {
            $document = new \DomDocument();
            $document->loadXml(file_get_contents($filename));
            $width  = $document->lastChild->getAttribute('width');
            $height = $document->lastChild->getAttribute('height');
        } else {
            $size = getimagesize($filename);
            list ($width, $height) = $size;
        }

        $width  = number_format($width * self::PIXEL_TO_CM, 2) . 'cm';
        $height = number_format($height * self::PIXEL_TO_CM, 2) . 'cm';

        return array($width, $height);
    }

    private function getTemplateSource(\Twig_Environment $environment)
    {
        $template = $this->getTemplateName($environment);
        $source   = $environment->getLoader()->getSource($template);

        return $source;
    }

    protected function getTemplateName(\Twig_Environment $environment)
    {
        if (!$environment->getCache()) {
            return $environment->getCompiler()->getFilename();
        }

        foreach (debug_backtrace() as $trace) {
            if (isset($trace['object'])
                && $trace['object'] instanceof \Twig_Template
                && get_class($trace['object']) !== $environment->getBaseTemplateClass()
            ) {
                $template = $trace['object'];
            }
        }

        return $template->getTemplateName();
    }
}
