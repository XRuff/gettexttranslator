<?php

namespace GettextTranslator;

use Nette;

class Panel extends Nette\Object implements Nette\Diagnostics\IBarPanel
{

    /** @var string */
    private $xhrHeader = 'X-Translation-Client';

    /** @var string */
    private $languageKey = 'X-GettextTranslator-Lang';

    /** @var string */
    private $fileKey = 'X-GettextTranslator-File';

    /** @var string */
    private $layout;

    /** @var int */
    private $height;

    /** @var Nette\Application\Application */
    private $application;

    /** @var GettextTranslator\Gettext */
    private $translator;

    /** @var Nette\Http\SessionSection */
    private $sessionStorage;

    /** @var Nette\Http\Request */
    private $httpRequest;

    /**
     * @param Nette\Application\Application
     * @param Gettext\Translator\Gettext
     * @param Nette\Http\Session
     * @param Nette\Http\Request
     * @param string
     * @param int
     */
    public function __construct(Nette\Application\Application $application, Gettext $translator, Nette\Http\Session $session, Nette\Http\Request $httpRequest, $layout, $height)
    {
        $this->application = $application;
        $this->translator = $translator;
        $this->sessionStorage = $session->getSection(Gettext::$namespace);
        $this->httpRequest = $httpRequest;
        $this->height = $height;
        $this->layout = $layout;

        $this->processRequest();
    }

    /**
     * Return's panel ID
     * @return string
     */
    public function getId()
    {
        return __CLASS__;
    }

    /**
     * Returns the code for the panel tab
     * @return string
     */
    public function getTab()
    {
        $template = new Nette\Templating\FileTemplate(__DIR__ . '/tab.latte');
        $template->registerFilter(new \Nette\Latte\Engine);
        $template->registerHelperLoader('Nette\Templating\Helpers::loader');
        return $template;
    }

    /**
     * Returns the code for the panel itself
     * @return string
     */
    public function getPanel()
    {
        $files = array_keys($this->translator->getFiles());

        $strings = $this->translator->getStrings();
        $untranslatedStack = isset($this->sessionStorage['stack']) ? $this->sessionStorage['stack'] : array();
        foreach ($strings AS $string => $data) {
            if (!$data) {
                $untranslatedStack[$string] = FALSE;
            }
        }
        $this->sessionStorage['stack'] = $untranslatedStack;

        foreach ($untranslatedStack AS $string => $value) {
            if (!isset($strings[$string])) {
                $strings[$string] = FALSE;
            }
        }

        $template = new Nette\Templating\FileTemplate(__DIR__ . '/panel.latte');
        $template->registerFilter(new \Nette\Latte\Engine);
        $template->registerHelperLoader('Nette\Templating\Helpers::loader');
        $template->translator = $this->translator;
        $template->ordinalSuffix = function ($count) {
            switch (substr($count, -1)) {
                case '1':
                    return 'st';
                    break;
                case '2':
                    return 'nd';
                    break;
                case '3':
                    return 'rd';
                    break;
                default:
                    return 'th';
                    break;
            }
        };

        $template->application = $this->application;
        $template->strings = $strings;
        $template->height = $this->height;
        $template->layout = $this->layout;
        $template->files = $files;
        $template->xhrHeader = $this->xhrHeader;
        $template->activeFile = $this->getActiveFile($files);
        return $template;
    }


    /**
     * Handles an incomuing request and saves the data if necessary.
     */
    private function processRequest()
    {
        if ($this->httpRequest->isMethod('POST') && $this->httpRequest->isAjax() && $this->httpRequest->getHeader($this->xhrHeader)) {
            $data = json_decode(file_get_contents('php://input'));

            if ($data) {
                if ($this->sessionStorage) {
                    $stack = isset($this->sessionStorage['stack']) ? $this->sessionStorage['stack'] : array();
                }

                $this->translator->lang = $data->{$this->languageKey};
                $file = $data->{$this->fileKey};
                unset($data->{$this->languageKey}, $data->{$this->fileKey});

                foreach ($data AS $string => $value) {
                    $this->translator->setTranslation($string, $value, $file);
                    if ($this->sessionStorage && isset($stack[$string])) {
                        unset($stack[$string]);
                    }
                }
                $this->translator->save($file);

                if ($this->sessionStorage) {
                    $this->sessionStorage['stack'] = $stack;
                }
            }

            exit;
        }
    }

    /**
     * Register this panel
     * @param Nette\Application\Application
     * @param GettextTranslator\Gettext
     * @param Nette\Http\Session
     * @param Nette\Http\Request
     * @param int $layout
     * @param int $height
     */
    public static function register(Nette\Application\Application $application, Gettext $translator, Nette\Http\Session $session, Nette\Http\Request $httpRequest, $layout, $height)
    {
        Nette\Diagnostics\Debugger::getBar()->addPanel(new static($application, $translator, $session, $httpRequest, $layout, $height));
    }

    /**
     * Get active file name
     * @param array
     * @return string
     */
    private function getActiveFile($files)
    {
        $tmp = explode(':', $this->application->presenter->name);

        if (count($tmp) >= 2 && $module = strtolower($tmp[0])) {
            return $module;
        } else {
            return $files[0];
        }
    }

}
