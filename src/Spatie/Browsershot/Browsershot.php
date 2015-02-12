<?php

namespace Spatie\Browsershot;

use Exception;
use Intervention\Image\ImageManager;

/**
 * Class Browsershot
 * @package Spatie\Browsershot
 */

class Browsershot {

    /**
     * @var int
     */
    private $width;
    /**
     * @var int
     */
    private $height;
    /**
     * @var
     */
    private $URL;
    /**
     * @var string
     */
    private $binPath;


    /**
     * @param string $binPath The path to the phantomjs binary
     * @param int $width
     * @param int $height
     */
    public function __construct($binPath = '', $width = 640, $height = 480)
    {
        if ($binPath == '') {
            $binPath = realpath(dirname(__FILE__) . '/../../../bin/phantomjs');
        }

        $this->binPath = $binPath;
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    /**
     * @param string $binPath The path to the phantomjs binary
     * @return $this
     */
    public function setBinPath($binPath)
    {
        $this->binPath = $binPath;
        return $this;
    }

    /**
     * @param int $width The required with of the screenshot
     * @return $this
     * @throws \Exception
     */
    public function setWidth($width)
    {
        if (! is_numeric($width)) {
            throw new Exception('Width must be numeric');
        }

        $this->width = $width;
        return $this;
    }

    /**
     * @param int $height The required height of the screenshot
     * @return $this
     * @throws \Exception
     */
    public function setHeight($height)
    {
        if (! is_numeric($height)) {
            throw new Exception('Height must be numeric');
        }

        $this->height = $height;
        return $this;
    }

    /**
     * @param string $url The website of which a screenshot should be make
     * @return $this
     * @throws \Exception
     */
    public function setURL($url)
    {
        if (! strlen($url) > 0 ) {
            throw new Exception('No url specified');
        }

        $this->URL = $url;
        return $this;
    }

    /**
     *
     * Convert the webpage to an image
     *
     * @param string $targetFile The path of the file where the screenshot should be saved
     * @return bool
     * @throws \Exception
     */
    public function save($targetFile)
    {
        if ($targetFile == '') {
            throw new Exception('targetfile not set');
        }

        if (! in_array(strtolower(pathinfo($targetFile, PATHINFO_EXTENSION)), ['jpeg', 'jpg', 'png'])) {
            throw new Exception('targetfile extension not valid');
        }

        if ($this->URL == '') {
            throw new Exception('url not set');
        }

        if (filter_var($this->URL, FILTER_VALIDATE_URL) === FALSE) {
            throw new Exception('url is invalid');
        }

        if (! file_exists($this->binPath)) {
            throw new Exception('binary does not exist');
        }


        $tempJsFileHandle = tmpfile();

        $fileContent= "
            var page = require('webpage').create();
            page.settings.javascriptEnabled = true;
            page.viewportSize = { width: " . $this->width . ", height: " . $this->height . " };
            page.open('" . $this->URL . "', function() {
               window.setTimeout(function(){
                page.render('" . $targetFile . "');
                phantom.exit();
            }, 3000); // give phantomjs 5 seconds to process all javascript
        });";

        fwrite($tempJsFileHandle, $fileContent);
        $tempFileName = stream_get_meta_data($tempJsFileHandle)['uri'];
        $cmd = escapeshellcmd("{$this->binPath} " . $tempFileName);

        shell_exec($cmd);

        fclose($tempJsFileHandle);

        if (! file_exists($targetFile) OR filesize($targetFile) < 1024)
        {
            throw new Exception('could not create screenshot');
        }

        $tempJsFileHandle2 = tmpfile();

        $fileContent2= "
            var page = require('webpage').create();
            page.settings.javascriptEnabled = true;
            page.viewportSize = { width: 320, height: 568 };
            page.open('" . $this->URL . "', function() {
               window.setTimeout(function(){
                page.render('" . $targetFile . "-mob');
                phantom.exit();
            }, 3000); // give phantomjs 5 seconds to process all javascript
        });";

        fwrite($tempJsFileHandle2, $fileContent2);
        $tempFileName2 = stream_get_meta_data($tempJsFileHandle2)['uri'];
        $cmd2 = escapeshellcmd("{$this->binPath} " . $tempFileName2);

        shell_exec($cmd2);

        fclose($tempJsFileHandle2);

        if (! file_exists($targetFile.'-mob') OR filesize($targetFile.'-mob') < 1024)
        {
            throw new Exception('could not create screenshot');
        }

        $mob=new ImageManager();
        $mob->make($targetFile.'-mob')->crop(320, 568, 0, 0)->resize(247,437)->save($targetFile.'-mob', 100);

        $ipn = new ImageManager();
        $ipn->make(public_path().'/assets/img/masque-iphone.png')
            ->insert($targetFile.'-mob','top-left',32,98)
            ->save($targetFile.'-mob', 100);

        $sc= new ImageManager();
        $sc->make($targetFile)->crop($this->width, $this->height, 0, 0)->save($targetFile, 100);

        $mask = new ImageManager();
        $mask->make(public_path().'/assets/img/masque-chrome.png')
            ->insert($targetFile,'top-left',56,104)
            ->insert($targetFile.'-mob','bottom-right',134,42)
            ->resize(600, null, function ($constraint) {
                    $constraint->aspectRatio();
                })
            ->save($targetFile, 100);

        return true;
    }


}
