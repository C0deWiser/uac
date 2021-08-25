<?php


namespace Codewiser\UAC\Model;


class UserOffice
{
    protected $html = [];
    protected $styles = [];
    protected $scripts = [];

    public function __construct($html, $styles, $scripts)
    {
        $this->html = $html;
        $this->styles = $styles;
        $this->scripts = $scripts;
    }

    public function assetHtml()
    {
        return $this->html['webForm'];
    }

    public function assetStyles()
    {
        $styles = [];
        foreach ($this->styles as $style) {
            $styles[] = '<link href="' . $style . '" type="text/css" rel="stylesheet">';
        }
        return implode("\n", $styles);
    }

    public function assetScripts()
    {
        $scripts = [];
        foreach ($this->scripts as $script) {
            $scripts[] = '<script type="text/javascript" src="' . $script . '"></script>';
        }
        return implode("\n", $scripts);
    }

    /**
     * Только для тестовых нужд, если у вас на сайте нет jQuery
     * @return string
     */
    public function assetJQuery()
    {
        return '<script   src="https://code.jquery.com/jquery-3.5.1.min.js"   integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0="   crossorigin="anonymous"></script>'."\n";
    }
}